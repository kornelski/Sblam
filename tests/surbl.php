<?php

class SblamTestSurbl extends SblamTestPost
{
	function preTestPost(ISblamPost $p)
	{
		$this->addedhosts = array();

		$links = $p->getLinks();
		if ($links) foreach($links as $link)
		{
			if ($host = $link->getHostname()) $this->checkHost($host);
			if ($domain = $link->getDomain()) $this->checkHost($domain);
		}

	}

	function testPost(ISblamPost $p)
	{
		return $this->getCheckHostResults();
	}

	protected $addedhosts;
	function checkHost($host)
	{
		// remove number-only subdomains and www. prefix (these are noise)
		if (preg_match("!(?:^|\.)(?:www\.)?(?:[0-9]+\.)?((?:[a-z0-9-]+\.)?[a-z0-9-]+\.[a-z]{2,4})$!",$host,$m))
		{
			$host = $m[1];
		}

		if (isset($this->addedhosts[$host])) return;
		$this->addedhosts[$host] = true;

 		SblamURI::gethostbynameasync($host . '.multi.surbl.org');
	}

	function getCheckHostResults()
	{
		$score=0;
		foreach($this->addedhosts as $host => $whatever)
		{
			$host .= '.multi.surbl.org';

			$res = SblamURI::gethostbynamel($host);
			if ($res && count($res))
			{
				d($res,"found banned $host");
				$score += 3;
				$mask = 0;
				foreach($res as $ip)
				{
					$mask |= ip2long($ip);
				}
				$mask &= 127 - 1 - 16; // outblaze list has false positives, so lower score
				d($mask,"banned mask");
				while($mask)
				{
					$score++; $mask >>= 1;
				}
				d("total surbl score until now is $score");
			} else d("$host not listed $res");
		}

		$finalscore = min(0.4 + $score/25, 1.5);

		if ($score) return array($finalscore, ($score >= 13)?self::CERTAINITY_HIGH:self::CERTAINITY_NORMAL,"Linked sites in SURBL (".round($finalscore,1)." = $score)");
		return NULL;
	}

	static function info()
	{
		return array(
			'name'=>'SURBL DNS RBL',
			'desc'=>'Checks for banned hostnames in Spam URI Realtime Blocklists',
			'remote'=>false,
		);
	}
}

