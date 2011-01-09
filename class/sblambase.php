<?php

require_once "class/sblambasepost.php";
require_once "class/server.php";

class SblamBaseIterator implements Iterator
{
	protected $pdo, $base;
	function __construct(SblamBase $base, PDOStatement $pdo)
	{
		$this->base = $base;
		$this->pdo = $pdo;
		$pdo->setFetchMode(PDO::FETCH_ASSOC);

		$this->next();
	}

	protected $current, $key=0;
	function next()
	{
		$this->current = $this->pdo->fetch();
	}

	function key()
	{
		return $this->key++;
	}

	function valid()
	{
		return false !== $this->current;
	}

	function rewind()
	{
	}

	function current()
	{
		return $this->base->nextRow($this);
	}

	function nextRow() {return $this->current;}
}

class SblamBase
{
	protected $db;
	function __construct(PDO $db)
	{
		$this->db = $db;
	}

	function getUnmoderatedRange($isspam, $start = 0, $count = 5)
	{
		$sign = $isspam ? '>' : '<=';
		$q = "SELECT * from posts_meta
		        LEFT JOIN posts_data on posts_meta.id = posts_data.id
		        WHERE spamscore $sign 0 AND manualspam IS NULL
		        ORDER BY spamcert
		        LIMIT $count OFFSET $start";
		return $this->query($q);
	}

	function moderated($id,$isspam)
	{
		$sta = $this->db->prepare("UPDATE posts_meta set manualspam = ? where id = ?");
		$sta->execute(array($isspam?1:0,$id));
	}

	function query($q, $args = NULL)
	{
		$prep = $this->db->prepare($q);

		if (!$prep || !$prep->execute($args)) {d($this->db->errorInfo());return false;}

		$prep->setFetchMode(PDO::FETCH_ASSOC);

		$out = array();
		foreach($prep as $row)
		{
			$out[] = $this->postFromRow($row);
		}
		return $out;
	}

	function queryIterator($q, $args = NULL)
	{
		$prep = $this->db->prepare($q);

		if (!$prep || !$prep->execute($args)) {warn($this->db->errorInfo());return false;}

		return new SblamBaseIterator($this,$prep);
	}

	function nextRow(SblamBaseIterator $i)
	{
		return $this->postFromRow($i->nextRow());
	}

	protected function postFromRow(array $r)
	{
		d($r,'creating new post from row');

		assert('!empty($r["id"])');
		assert('!empty($r["headers"])');
		assert('!empty($r["path"])');
		assert('!empty($r["timestamp"])');
		assert('array_key_exists("name",$r)');
		assert('isset($r["content"])');
		assert('isset($r["ip"])');

		$heads = array();
		foreach(explode("\n",$r['headers']) as $h)
		{
			if (preg_match("!(.*?):\s*(.*)!",$h,$o))
			{
				$heads[$o[1]] = $o[2];
			}
		}
		if (!isset($heads['HTTP_HOST'])) $heads['HTTP_HOST'] = $r['host'];

		if ($r['ip']) $heads['REMOTE_ADDR'] = long2ip($r['ip']);

		$post = new SblamBasePost($r['content'],$r['name'],$r['email'],$r['url'], ServerRequest::getRequestIPs($heads,true));

		$post->setSpamReason($r['spamreason']);
		$post->setSpamScore(array($r['spamscore']/100,$r['spamcert']/100));
		$post->setPostId($r['id']);

		$post->setHeaders($heads);
		$post->setTime($r['timestamp']);
		$post->setPath($r['path']);

        $post->setInstallId($r['serverid']);
		$post->bayesadded = $r['added'];
		$post->manualspam = $r['manualspam'];
		$post->worktime = $r['worktime'];
		$post->account = $r['account'];
		$post->profiling = $r['profiling'];

		$postarr = array();
		if ($r['post'])
		{
			foreach(explode("\n",$r['post']) as $pline)
			{
				if (preg_match("!^([^:]+): ?(.*)$!",$pline,$out))
				{
					$postarr[$out[1]] = $out[2];
				}
			}
			$post->setPost($postarr);
		}

		return $post;
	}

	function getPostById($id)
	{
		$q = "SELECT * FROM posts_meta LEFT JOIN posts_data ON posts_meta.id = posts_data.id WHERE posts_meta.id = ? LIMIT 1";
		foreach($this->query($q,array($id)) as $p)
		{
			return $p;
		}
		throw new Exception("Post not found: $id");
	}

	function saveTestResult(SblamBasePost $p)
	{
		$prep = $this->db->prepare("UPDATE posts_meta SET spamscore = ?, spamcert = ?, added = ? WHERE id = ?");
		if (!$prep) {warn($this->db->errorInfo());throw new Exception("pdo failed");}

		list($score,$cert) = $p->getSpamScore();
		if (!$score && !$cert) trigger_error("getspamscore failure");
		return $prep->execute(array($score*100,$cert*100, $p->bayesadded, $p->getPostId()));
	}
}
