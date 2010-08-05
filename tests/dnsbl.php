<?php

abstract class SblamDNSBL extends SblamTestPost
{
	protected $rblhost;

	protected $addedhosts, $ips;
	function preTestPost(ISblamPost $p)
	{
		$this->addedhosts = array();
		$this->ips = array();

		// Check sender's IPs
		foreach($p->getAuthorIPs() as $ip)
		{
			$this->checkIP($ip,"sender");
		}

		// Check all IPs of all linked websites
		$links = $p->getLinks();
		if ($links) foreach($links as $link)
		{
			$host = $link->getHostname();
			$domain = $link->getDomain();

			if ($host && $domain && $host !== $domain)
			{
				$this->checkHost($host,"link", 0.75);
				$this->checkHost($domain,"link", 0.75);
			}
			else
			{
				if ($host) $this->checkHost($host,"link");
				if ($domain) $this->checkHost($domain,"link");
			}
		}

		$this->checkHostList();
	}

	function testPost(ISblamPost $p)
	{
		return $this->getCheckIPResults();
	}

	protected function checkHost($host, $reason, $scorefactor=1)
	{
		if (isset($this->addedhosts[$host])) return;
		$this->addedhosts[$host] = array($reason, $scorefactor);
		SblamURI::gethostbynameasync($host);
	}

	protected function checkHostList()
	{
		foreach($this->addedhosts as $host => $dat)
		{
			list($reason, $scorefactor) = $dat;

			if (($ips = SblamURI::gethostbynamel($host)))
			{
				foreach($ips as $ip)
				{
					$this->checkIP($ip, $reason, $scorefactor * (0.1 + 0.9/count($ips)));  // a single banned host that resolves to lots of IPs can skew the results
				}
			}
		}
	}

	protected function reverse($ip)
	{
		return preg_replace('!(\d+)\.(\d+)\.(\d+)\.(\d+)!','\4.\3.\2.\1.'.$this->rblhost,$ip);
	}

	protected function checkIP($ip, $reason, $scorefactor=1)
	{
		if (!isset($this->ips[$ip]))
		{
			$this->ips[$ip] = array("$reason", $scorefactor);

			SblamURI::gethostbynameasync($this->reverse($ip));
		}
	}

	abstract function score($ip, $resip, $reason, $scorefactor);

	function getCheckIPResults()
	{
		$out = array();

		foreach($this->ips as $ip => $ipinfo)
		{
			list($reason, $scorefactor) = $ipinfo;

			$res = SblamURI::gethostbynamel($this->reverse($ip));
			if ($res) foreach($res as $resip)
			{
				$tmp = $this->score($ip, $resip, $reason, $scorefactor);
				if ($tmp) $out[] = $tmp;
			}
		}

		if (!count($out)) return NULL;

		$res = Sblam::sumResults($out);

		if ($res[0] > 1) $res[0]=1;
		if ($res[1] > self::CERTAINITY_HIGH) $res[1] = self::CERTAINITY_HIGH;
		return $res;
	}
}

