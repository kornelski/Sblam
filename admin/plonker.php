<?php

require_once "class/sblam.php";
require_once "class/sblamuri.php";

class PlonkerPage extends AdminPage
{
	protected $table = 'plonker';

	function index()
	{
	    return array(
	        'total' => $this->q1("/*maxtime3*/SELECT count(*) from plonker",true),
	    );
    }

	function stats()
	{
		$pdo = $this->services->getDB();

		$plonkerstats['total'] = $this->q1("/*maxtime=5*/SELECT count(*) from plonker",true);
		$plonkerstats['totalnets'] = $this->q1("/*maxtime=10*/SELECT count(distinct ip>>10) from plonker",true);
		$out = array();
		foreach($pdo->query("/*maxtime20*/SELECT * from plonker order by spampoints desc,added desc,ip limit 100") as $row)
		{
			$out[] = $row;
		}
		$plonkerstats['topips'] = $out;

		$out = array();
		foreach($pdo->query("/*maxtime20*/SELECT  ip,flags ,count(*) as  cnt ,sum( spampoints ) as  spampoints , max( added ) as  added  from plonker group by  ip >>10 order by  cnt  desc, spampoints,ip  limit 100") as $row)
		{
			$out[] = $row;
		}
		$plonkerstats['topranges'] = $out;

		$out = array();
		foreach($pdo->query("/*maxtime20*/SELECT * from plonker order by added desc limit 100") as $row)
		{
			$out[] = $row;
		}
		$plonkerstats['recentips'] = $out;

		$plonkerstats['byflags'] = $pdo->query("/*maxtime20*/SELECT count(*),sum(spampoints) as tot,avg(1-1000/(1000+spampoints)) as avg,flags from plonker where spampoints < 100000 group by flags;")->fetchAll(PDO::FETCH_ASSOC);

		return $plonkerstats;
	}

	function blocklist($min = 6500)
	{
		$out = "# HTTP spam sources identified by http://sblam.com.\n# Generated ".date('Y-m-d H:i:s')."\n# This is list of HTML forms spammers, not suitable for blocking e-mail spam!\n";
		$n=0;
		foreach($this->services->getDB()->query("/*maxtime20*/SELECT ip from plonker where
((added > now() - interval 1 month and spampoints > ".intval($min).") or (added > now() - interval 2 month and spampoints > ".intval(15*$min)."))
 and ip > (11<<24) order by ip

") as $r)
		{
			$ip = long2ip($r['ip']);
			$out .= $ip."\n";
		}
		return array('page_content'=>$out, 'layout_template'=>'', 'content_type'=>'text/plain;charset=UTF-8');
	}

	function post_blocklist($min = 6500)
	{
		return $this->blocklist($min);
	}

	function post_block()
	{
		return $this->block($_POST['block'],empty($_POST['remove']));
	}

	function block($ipstring, $add = NULL)
	{

		$ips = array();
		$block = preg_split("![\s\n,;]+!",$ipstring);
		foreach($block as $l)
		{
			if (!$l || !preg_match('!\s*(?:block:\s*)?(\d+\.\d+\.\d+\.\d+)!',$l,$m)) {continue;}

			$l = sprintf('%u',ip2long($m[1]));
			if ($l) $ips[] = $l;
		}
		$ips = array_values(array_unique($ips));

		if (!$ips) return array('page_content'=>'No ips!');

		if ($add || $add===NULL)
		{
			$add=1;
			$this->increaseCertainityByIP($ips);
			$q = "/*maxtime10*/INSERT into plonker (`ip`,`spampoints`,`added`) values".substr(str_repeat("(?,26000,now()),", count($ips)),0,-1).
					 " on duplicate key update `spampoints` = `spampoints` * 2 + 22000,`added` = now()";
		}
		else
		{
			$q = "/*maxtime10*/DELETE from plonker where ip in(?".str_repeat(",?", count($ips)-1).")";
		}

		$pdo = $this->services->getDB();
		$prep = $pdo->prepare($q);
		if (!$prep) throw new Exception("$q ".implode(',',$pdo->errorInfo()));

		if (!($changed = $prep->execute($ips))) throw new Exception("$q ".implode(',',$prep->errorInfo()));

		return array(
		  'title'=>($add?'Added IPs':'Removed IPs'),
		  'page_template'=>'plonker_blocked',
			'added'=>$add,
			'changed'=>$changed,
			'ips'=>$ips,
			);
	}

	private function increaseCertainityByIP(array $ips)
	{
		$this->services->getDB()->prepareExecute("UPDATE posts_meta SET spamscore = spamscore + abs(spamscore)/10, spamcert = spamcert + 10 + abs(spamcert)/10 WHERE ip IN(?"
.str_repeat(",?",count($ips)-1).")", $ips);
	}

	private function q1($query,$firstcol = false)
	{
		$res = $this->services->getDB()->query($query);
		if ($res) $res = $res->fetchAll(); else throw new Exception("Query $query failed");
		if ($firstcol) return reset($res[0]);
		return $res[0];
	}

}
