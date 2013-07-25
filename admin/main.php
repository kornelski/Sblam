<?php

class MainPage extends AdminPage
{
	function index()
	{
		$pdo = $this->services->getDB();

        $tables = $pdo->getTables();

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
			'processes'=>$pdo->getProcessList(),
			'tablestatus'=>$tables,
		);
	}

	private function query1($sql)
	{
	    $res = $this->services->getDB()->query($sql);
	    if (!$res) return NULL;
	    $res = $res->fetchAll(PDO::FETCH_ASSOC);
	    return reset($res);
  }

	private function archivize($move = 600, $leave = 25000)
	{
	    $leave = intval($leave);
	    $move = intval($move);

        $minid = $this->query1("/*maxtime10*/SELECT least((SELECT id-$leave FROM posts_meta ORDER BY id DESC LIMIT 1),(SELECT id+$move FROM posts_meta ORDER BY id LIMIT 1)) AS minid");
        $minid = intval(reset($minid));

        if (!$minid) throw new Exception("Can't find ID of oldest post to archive");

        $info = $this->query1("/*maxtime5*/SELECT \"timestamp\",added as bayes_added FROM posts_meta WHERE id >= $minid LIMIT 1");
        if (!$info) throw new Exception("Can't find info on oldest post id $minid");

        $moved = $this->services->getDB()->exec("/*maxtime710*/INSERT INTO postsarchive(id,spambayes,spamscore,spamcert,spamreason,manualspam,content,name,email,url,ip,\"timestamp\",headers,cookies, session,host,hostip,\"path\",added,post,chcookie,worktime,account,profiling)
            SELECT
posts_meta$suffix.id,spambayes,spamscore,spamcert,spamreason,manualspam,content,name,email,url,ip,\"timestamp\",headers,cookies,session,host,hostip,\"path\",
added,post,chcookie,worktime,account,profiling
                FROM posts_meta$suffix LEFT JOIN posts_data$suffix ON posts_meta$suffix.id = posts_data$suffix.id
                WHERE posts_meta$suffix.id < $minid");

        if ($moved)
        {
            $this->services->getDB()->exec("/*maxtime510*/DELETE FROM posts_meta$suffix WHERE id < $minid");
        }

        $info['moved'] = $moved;
        return $info;
    }

	function post_index()
	{
	    if (isset($_POST['archive']))
	    {
	        $max = 5000;
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
            $this->services->getDB()->exec("KILL ".intval($_POST['kill']));

            return $this->index();
        }

        return $this->index();
    }
}
