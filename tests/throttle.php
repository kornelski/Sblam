<?php

class ThrottleAccumulator
{
	private $kill = 0, $waittime=0, $tests = array();

	function increment($label, $key, $min, $max, $mins)
	{
		if (!$key || strlen($key)<4) return;

		if (strlen($key)>30) $key = 'h!'.md5($key);

		$key = $label.':'.$key;

		// race condition, eh
		$val = apc_fetch($key);

		if ($val) {list($time,$val) = explode("\t",$val,2);} else {$time=time();$val=0;}

		if ($time + $mins*60 < time()) {d($key,"expired"); $val = 0; $time = time();}
		elseif ($time + $mins*45 < time()) {d($key,"halflife"); $val /= 2; $time = time();}

		apc_store($key, $time."\t".($val+1), $mins*60);
		// race finishes here!

		$this->tests[] = array($label, $key, $min, $max, $mins);
	}

	function kill($score = 15)
	{
		$this->kill += $score;
	}

	function getResult($maxbasescore = 4)
	{
		$labels = array();
		$score=0;

		foreach($this->tests as $test)
		{
			list($label, $key, $min, $max, $mins) = $test;

			$val = apc_fetch($key); if (!$val) continue;
			list($time,$val) = explode("\t",$val,2);

			if ($val > $min)
			{
				$res = min(($val - $min/2) / $max, 3) + 0.1;

				d(round($res,2)." points for $key which is at $val of $max+$min per $mins mins");

				$score += $res;
				$labels[$label] = $res;
			}
			else d("no points for $key which is at $val of $max+$min per $mins mins");
		}

		$score = min($score,$maxbasescore);

		if ($this->kill) {$score += $this->kill; $labels['kill'] = $this->kill;}

		if (!$score) return array(0,'');

		arsort($labels);
		$res = implode(';',array_keys($labels));
		if ($this->waittime) $res .= ' + w'.(round($this->waittime/1000000,2));
		return array($score, $res);
	}


	function antiConcurrency(ISblamPost $p)
	{
        return;		// slows down server under heavy load :(

	    $ip = $p->getAuthorIP();
		if (!apc_add('ip.lock:'.$ip,1,2) && apc_fetch('ip-ban:'.$ip) < time())
		{
			$wait = 500000 + 100000 * (mt_rand()%10);
			usleep($wait);
			$this->waittime += $wait;
			d("*Throttled*");
		}
	}

}

class SBlamThrottle extends SblamTestPost
{
	protected $accumulator;


	function preTestPost(ISblamPost $p)
	{

		$acc = new ThrottleAccumulator();
		$acc->antiConcurrency($p);

		foreach($p->getAuthorIPs() as $ip)
		{
			if (apc_fetch('ip-ban:'.$ip) > time()) $acc->kill();

			$acc->increment("ip.sec",$ip, 2, 3, 10/60);
			$acc->increment("ip",$ip, 15, 38, 60);
			$acc->increment("ip.day",$ip, 7*15, 7*38, 24*60);
			$acc->increment("ip.range",ip2long($ip)>>8, 20*10, 20*30, 60);
		}
		if ($email = $p->getAuthorEmail())
		{
			$acc->increment("email",$email, 8, 40, 10*60);
			$acc->increment("email.short",$email, 5, 15, 5);
		}
		if ($name = $p->getAuthorName())
		{
			$acc->increment("name",$name, 20, 70, 5*60);
			$acc->increment("name.short",$name, 10, 35, 5);
		}
		$domains = array();
		foreach($p->getLinks() as $link)
		{
			if (($hostname = $link->getHostname()) && ($domain = $link->getDomain()) && $domain != $hostname) // if domain=hostname, it's known neutral domain
			{
				$domains[$domain] = true;
			}
		}
		foreach($domains as $domain => $x)
		{
			$acc->increment("link.domain.short",$domain, 15, 25, 20);
			$acc->increment("link.domain",$domain, 35, 85, 4*60);
		}
		$this->accumulator = $acc;
	}

	function testPost(ISblamPost $p)
	{
		if (!$this->accumulator) return NULL;

		$this->accumulator->antiConcurrency($p);

		list($points,$desc) = $this->accumulator->getResult();

		if ($points > 0) return array($points/5, self::CERTAINITY_NORMAL, "Throttle ".round($points,1)." $desc");
	}

	function reportResult(ISblamPost $p, $score, $cert)
	{
	    if (!function_exists('apc_store')) throw new Exception("NO APC");

		if ($score > 1.2 && $cert > 0.95)
		{
			foreach($p->getAuthorIPs() as $ip)
			{
				apc_store('ip-ban:'.$ip,time()+5,5); // block for 5 sec
			}
		}
	}

	static function info()
	{
		return array(
			'name'=>'Throttle requests',
			'desc'=>'Limit rate of posting',
			'remote'=>false,
			'unsupported'=>!function_exists('apc_store'),
		);
	}
}
