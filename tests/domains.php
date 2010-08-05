<?php

require_once "class/domainmatch.php";

class SblamDomains extends SblamTestPost
{
	protected $blacklist, $blacklistfile;

	function __construct(array $settings)
	{
		$this->blacklistfile = empty($settings['chongqed'])?'blacklist.chongqed.txt':$settings['chongqed'];

		if (!class_exists('DomainMatch')) throw new Exception("DomainMatch class required");
	}

	function importChongqed($filename)
	{
		$lines = @file($filename); if (!$lines) return false;

		$domains = array();
		$regex = '!^(?:'.preg_quote('https?:\/\/([^\/]*\.)?','!').')?(.*?)(?:#.*)?$!';
		foreach($lines as $line)
		{
			$line = stripslashes(trim(preg_replace($regex,'\1',$line))); //remove comments and useless fragment of regexp
			$this->blacklist->add($line);
		}
		return true;
	}

	protected function check(SblamURI $link)
	{
		$domain = $link->getDomain();

		$min = max(2, count(explode('.',$domain))+1);

		$res = $this->blacklist->check($link->getHostname());

		if ($res >= $min) return $res + 1 - $min;
		return 0;
	}

	function testPost(ISblamPost $p)
	{
		if ($this->blacklist === NULL)
		{
			$this->blacklist = new DomainMatch();
			if (!$this->importChongqed($this->blacklistfile)) throw new Exception("Unable to import chongqed.org blacklist from {$this->blacklistfile}");
		}

		$res4=0;
		$domains = array();

		if ($uri = $p->getAuthorURI())
		{
			$uri = new SblamURI($uri);
			if ($tmp = $this->check($uri))
			{
				$domains[$uri->getHostname()] = true;
				$res4 += $tmp;
			}
		}

		foreach($p->getLinks() as $uri)
		{
			if ($tmp = $this->check($uri))
			{
				$domains[$uri->getHostname()] = true;
				$res4 += $tmp;
			}
		}

		if ($res4) return array(0.8, self::CERTAINITY_NORMAL, "Blacklisted domains (".implode(', ',array_keys($domains)).")");
	}

	static function info()
	{
		return array(
			'name'=>'Chongqed.org blacklist',
			'desc'=>'Blacklist used by MediaWiki',
			'remote'=>false,
		);
	}
}
