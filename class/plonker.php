<?php

class Plonker
{
	private $db,$table,$subnetrelevance, $firstipskew;
	function __construct(PDO $db, $table, $subnetrelevance = 1, $firstipskew = true)
	{
		$this->db = $db; $this->table = $table; $this->subnetrelevance = $subnetrelevance; $this->firstipskew = $firstipskew;
	}

	private function subnet($ip)
	{
		$ipl = ip2long($ip);

		$subnet = 255; // wild guess

		return array(sprintf("%u",$ipl),sprintf("%u",$ipl & (~$subnet)),sprintf("%u",$ipl | $subnet));
	}

	function testIPs(array $ips, $time)
	{
		if (!count($ips)) return;

		$prep = $this->db->prepare("/*maxtime=5*/".
		    "SELECT count(if(ip=?,1,NULL)) as exact,
    		    count(*) as cnt,
    		    sum(if(ip=?,
    		            55000+100*spampoints,
    		            5500+10*spampoints*{$this->subnetrelevance})
    		        /(60000+abs(time_to_sec(timediff(now(),added))))) as total
		     FROM {$this->table}
		     WHERE ip BETWEEN ? AND ?");

		if (!$prep) {warn($this->db->errorInfo(),"prepare failed");return NULL;}

		$total = 0; $count=0;
		foreach($ips as $ip)
		{
			list($ipl, $iplow, $iphigh) = $this->subnet($ip);

			$ips = array($ipl,$ipl,$iplow,$iphigh);

			if (!$prep->execute($ips)) {warn($prep->errorInfo(),"query failed");return NULL;}

			foreach($prep as $res) { // foreach reads one row anyway
				$count += $res['cnt'];
				$total += $res['exact'] ? $res['total'] : $res['total']/3;
			}
		}
		return array($total,$count);
	}


	function addIPs(array $inips, $score, $max=5)
	{
		if (!count($inips)) return;

		if ($score > 4) $score += 2; // get heavy spammers on blacklist quicker

		$first = true;
		$ips = array();
		foreach($inips as $ip)
		{
			if (!$max--) break;

			$ips[] = sprintf("%u",ip2long($ip));
			// unfortunately only the first one is guaraneed to be correct - other could have been maliciously faked, so little weight goes there
			$ips[] = 2+$score * ($first?100:20) * $this->severity($ip);

			$first = !$this->firstipskew;
		}

		$q = "/*maxtime=15*/INSERT INTO {$this->table} (ip,spampoints) values".substr(str_repeat("(?,?),", count($ips)/2),0,-1).
				 " on duplicate key update spampoints = spampoints + 1.5*values(spampoints)";

		if (!($prep = $this->db->prepare($q))) {warn($this->db->errorInfo(),"addprepfail");warn($ips,count($ips)/2);return false;}
		if (!$prep->execute($ips)) {warn($prep->errorInfo(),"addqueryfail: $q");warn($ips,count($ips)/2); return false;}
		return true;
	}

	private function severity($ip)
	{
		$rev = SblamURI::gethostbyaddr($ip);

		if (is_array($rev)) {warn($rev,"gethostbyaddr returned array!?"); $rev = reset($rev);}
		if (!$rev) return 3;

		if (preg_match('/(^|[.-])(vp[sn]|srv)[.\d-]|(^|\.)(colo|dedi?)[-.]|dedic|resell|serv(er|[.\d-])|^ns\d*\.|^mail\d*\.|multicast|invalid|unknown/',$rev)) return 2;
		if (preg_match('/internetdsl\.|static/',$rev) || preg_match('/^[^\d]+$/',$rev) || strlen($rev) < 10) return 1.5;
		if (preg_match('/^nat[\d.-]|cache|proxy|gprs[^a-z]|dynamic|\.dhcp\.|\.sta\.|ppp[\d.-]|\.dyn\.|(^|[.-])adsl[.0-9-]/',$rev)) return 0.8;
		return 1;
	}

	function removeIPs(array $ips, $max=3)
	{
		if (!count($ips)) return;

		$prep = $this->db->prepare("/*maxtime=15*/UPDATE {$this->table} set added = added, spampoints = spampoints * if(ip=?, ?, ?) where ip between ? and ?");
		if (!$prep) return false;

		$first = true;
		foreach($ips as $ip)
		{
			if (!$max--) break;

			list($ipl, $iplow, $iphigh) = $this->subnet($ip);

			$severity = $this->severity($ip);

			$dat = array(
				$ipl,
				($first?0.1:0.7) / $severity,
				(1-(1-($first?0.6:0.8))*$this->subnetrelevance) / $severity,
				$iplow,
				$iphigh
			);

			$prep->execute($dat);

			$first = !$this->firstipskew;
		}
	}
}
