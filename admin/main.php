<?php

class MainPage extends AdminPage
{
    // {{{
    static $needs_tables = array(
        'posts_meta'=>'CREATE TABLE `posts_meta` (
            `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `account` mediumint unsigned DEFAULT NULL,
            `ip` int unsigned not null default 0,
            `timestamp` int DEFAULT NULL,
            `spambayes` smallint DEFAULT NULL,
            `spamscore` mediumint DEFAULT NULL,
            `spamcert` mediumint DEFAULT NULL,
            `worktime` int unsigned DEFAULT NULL,
            `added` tinyint unsigned DEFAULT NULL,
            `manualspam` tinyint unsigned DEFAULT NULL,
            `serverid` varchar(64) NOT NULL,
            KEY `manualspam` (`manualspam`,`spamscore`),
            KEY `account` (`account`),
            KEY `spamscore` (`spamscore`,`spamcert`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',

        'posts_data'=>'CREATE TABLE `posts_data` (
            `id` int unsigned NOT NULL UNIQUE KEY,
            `content` text NOT NULL,
            `name` varchar(255) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `url` varchar(255) DEFAULT NULL,
            `headers` text,
            `cookies` tinyint(4) DEFAULT NULL,
            `session` tinyint(4) DEFAULT NULL,
            `host` varchar(255) DEFAULT NULL,
            `hostip` int unsigned not null default 0,
            `path` varchar(255) DEFAULT NULL,
            `post` text,
            `chcookie` varchar(255) DEFAULT NULL,
            `spamreason` mediumtext,
            `profiling` mediumtext,
            FOREIGN KEY(id) REFERENCES posts_meta(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8',

        'postsarchive'=>'CREATE TABLE `postsarchive` (
          `id` mediumint(9) NOT NULL,
          `spambayes` smallint(6) DEFAULT NULL,
          `spamscore` mediumint(9) DEFAULT NULL,
          `spamcert` mediumint(9) DEFAULT NULL,
          `spamreason` mediumtext,
          `manualspam` tinyint(4) DEFAULT NULL,
          `content` text NOT NULL,
          `name` varchar(255) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `url` varchar(255) DEFAULT NULL,
          `ip` int(10) unsigned NOT NULL,
          `timestamp` int(11) DEFAULT NULL,
          `headers` text,
          `cookies` tinyint(4) DEFAULT NULL,
          `session` tinyint(4) DEFAULT NULL,
          `host` varchar(255) DEFAULT NULL,
          `hostip` int(10) unsigned NOT NULL,
          `path` varchar(255) DEFAULT NULL,
          `submitname` varchar(255) DEFAULT NULL,
          `added` tinyint(1) unsigned DEFAULT NULL,
          `checksum` varchar(56) DEFAULT NULL,
          `surbl` tinyint(3) unsigned DEFAULT NULL,
          `post` text,
          `chcookie` varchar(255) DEFAULT NULL,
          `worktime` int(10) unsigned DEFAULT NULL,
          `account` mediumint(8) unsigned DEFAULT NULL,
          `profiling` mediumtext
        ) ENGINE=ARCHIVE DEFAULT CHARSET=utf8',

        'account_messages'=>'CREATE TABLE `account_messages` (
          `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
          `account` mediumint(8) unsigned NOT NULL,
          `type` varchar(16) DEFAULT NULL,
          `message_html` mediumtext NOT NULL,
          `read` enum(\'N\',\'Y\') NOT NULL DEFAULT \'N\',
          `sent` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `account` (`account`,`type`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8',
    );
    // }}}
    private function getTables()
    {
        return $this->getPDO()->query("/*maxtime10*/SHOW table status")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMissingTables(array $has_tables)
    {
        $missing_tables = self::$needs_tables;
        foreach($has_tables as $tab)
        {
            unset($missing_tables[$tab['Name']]);
        }
        return $missing_tables;
    }

	function index()
	{
		$pdo = $this->getPDO();

        $tables = $this->getTables();
        $missing = $this->getMissingTables($tables);

        $tables_index = array();
        foreach($tables as $tabinfo)
        {
            $tables_index[$tabinfo['Name']] = $tabinfo;
        }


        $stats = NULL;
        if (isset($tables_index['posts_meta'],$tables_index['posts_data'],$tables_index['postsarchive']))
        {
            $stats = array(
		        'posts'=> round(($tables_index['posts_meta']['Rows'] * 10 + $tables_index['posts_data']['Rows'])/11), // posts_data seems to be overestimating, posts_meta underestimating.
		        'posts_archive'=>$tables_index['postsarchive']['Rows'],
		    );
        }

		return array(
		    'stats' => $stats,
			'load'=>implode(", ",sys_getloadavg()),
			'processes'=>$pdo->query("/*maxtime2*/SHOW processlist")->fetchAll(PDO::FETCH_ASSOC),
			'tablestatus'=>$tables,
			'missing_tables'=>array_keys($missing),
		);
	}

	private function query1($sql)
	{
	    $res = $this->getPDO()->query($sql);
	    if (!$res) return NULL;
	    $res = $res->fetchAll(PDO::FETCH_ASSOC);
	    return reset($res);
  }

	private function archivize($move = 500, $leave = 5000)
	{
	    $leave = intval($leave);
	    $move = intval($move);

        $minid = $this->query1("/*maxtime10*/SELECT least((SELECT id-$leave FROM posts_meta ORDER BY id DESC LIMIT 1),(SELECT id+$move FROM posts_meta ORDER BY id LIMIT 1)) AS minid");
        $minid = intval(reset($minid));

        if (!$minid) throw new Exception("Can't find ID of oldest post to archive");

        $info = $this->query1("/*maxtime5*/SELECT `timestamp`,added as bayes_added FROM posts_meta WHERE id >= $minid LIMIT 1");
        if (!$info) throw new Exception("Can't find info on oldest post id $minid");

        $moved = $this->getPDO()->exec("/*maxtime710*/INSERT INTO postsarchive(id,spambayes,spamscore,spamcert,spamreason,manualspam,content,name,email,url,ip,`timestamp`,headers,cookies, session,host,hostip,`path`,added,post,chcookie,worktime,account,profiling)
            SELECT posts_meta.id,spambayes,spamscore,spamcert,spamreason,manualspam,content,name,email,url,ip,`timestamp`,headers,cookies,session,host,hostip,`path`, added,post,chcookie,worktime,account,profiling
                FROM posts_meta LEFT JOIN posts_data ON posts_meta.id = posts_data.id
                WHERE posts_meta.id < $minid");

        if ($moved)
        {
            $this->getPDO()->exec("/*maxtime510*/DELETE FROM posts_meta WHERE id < $minid");
        }

        $info['moved'] = $moved;
        return $info;
    }

	function post_index()
	{
	    if (isset($_POST['archive']))
	    {
	        $max = 15;
	        $moved_total = 0;
	        while($max--)
	        {
	            $info = $this->archivize();
	            $moved_total += $info['moved'];
	            if (!$info['moved']) break;
	            if (!$info['bayes_added'] && empty($_POST['archive_unadded'])) break;
            }

            $res = $this->index();
            $res['archive'] = array(
                'moved' => $moved_total,
                'bayes_added' => $info['bayes_added'],
            );
            return $res;
        }

        //////////////

        elseif (!empty($_POST['kill']))
        {
            $this->getPDO()->exec("KILL ".intval($_POST['kill']));

            return $this->index();
        }

	    ///////////////

	    if (!isset($_POST['tables']) || !is_array($_POST['tables'])) throw new Exception("No tables selected");

	    $tables = $this->getTables();
	    $missing = $this->getMissingTables($tables);
	    $pdo = $this->getPDO();

	    foreach($_POST['tables'] as $tab)
	    {
	        if (!isset($missing[$tab])) throw new Exception("Table $tab has already been created");
	        $pdo->exec($missing[$tab]);
        }
        return $this->index();
    }
}
