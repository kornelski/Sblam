<?php

class SblamTestDeDupe extends SblamTestPost
{
	protected $db;
	function __construct(array $settings)
	{
		$this->db = sblambaseconnect();
	}

	protected $checksum, $length;

	/* insert first to catch several messages sent in parallel */
	function preTestPost(ISblamPost $p)
	{
		$text = preg_replace('/\[:..:\]\s+/',' ',$p->getRawContent());
		if (strlen($text) < 40) $text .= $p->getAuthorURI();
		if (strlen($text) < 40) $text .= $p->getAuthorEmail();
		if (strlen($text) < 40) $text .= $p->getAuthorName();
		if (strlen($text) < 30) $text .= $p->getAuthorIP();
		if (strlen($text) < 35)
		{
			$this->checksum=NULL;
			return;
		}

		$text = preg_replace(array('/\s+/','/([a-f0-9]{1,3}[a-f]{1,6}[0-9]{1,6})+/','/\d\d{1,8}/'),array(' ','H','D'),strtolower($text));
		d($text,'normalized text');

		$this->length = strlen($text);
		$this->checksum = md5($text);

		if (!$this->db->exec(sprintf("/*maxtime5*/INSERT INTO dupes (checksum,count,expires,ip) VALUES(UNHEX('%s'),1,%d,%u)
			ON DUPLICATE KEY UPDATE count = 1 + IF(expires < %d,CEIL(count/10),count), expires = GREATEST(expires + 3600*6, %d)",
			$this->checksum, time()+3600*18, ip2long($p->getAuthorIP()), time(), time()+3600*18))) warn($this->db->errorInfo());
	}

	function testPost(ISblamPost $p)
	{
		if (!$this->checksum) return;

		$res = $this->db->query(sprintf("/*maxtime5*/SELECT count,ip FROM dupes WHERE checksum = UNHEX('%s') LIMIT 1",$this->checksum));
		if ($res) $res = $res->fetchAll(); else return NULL;
		if (count($res))
		{
			$res = $res[0];

			$allowed = 2; // double-posting?
			if (false !== strpos($p->getPath(),'editpost')) $allowed++;

			$score = ($res['count'] - $allowed)/15;

			$cert = self::CERTAINITY_LOW;
			    if ($res['count'] > 100) {$score += 2; $cert = self::CERTAINITY_HIGH;}
			elseif ($res['count'] > 50) {$score += 0.5; $cert = self::CERTAINITY_HIGH;}
			elseif ($res['count'] > 10) {$score += 0.2; $cert = self::CERTAINITY_NORMAL;}

			$ip = long2ip($res['ip']);
			if ($ip != $p->getAuthorIP()) $score = ($score+0.2)*1.2; // different IP? botnet!
			if ($this->length > 250) $score = ($score+0.1)*1.2; // less likely to accidentally dupe

			if ($score > 0.1)
			{
				$score = min($score,2)+min($score/5,4);
				return array($score,$cert,"Duplicate (x".round($res['count'])." = ".round($score,1).")");
			}
		}
	}

	static function info()
	{
		return array(
			'name'=>'DeDupe',
			'desc'=>'Treshold for duplicate (or similar) messages',
			'remote'=>false,
		);
	}
}
