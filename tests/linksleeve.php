<?php
/**
 *  http://www.linksleeve.org/
 *
 *  Looks for way too popular links
 *
 *  Returns accept/reject via XML RPC
 */
class SblamTestLinkSleeve extends SblamTestPost
{
	const API_HOST = 'www.linksleeve.org';
	const API_PATH = '/slv.php';
	protected $timeout;

	function __construct(array $settings, ISblamServices $services)
	{
        parent::__construct($settings, $services);

		$this->timeout = isset($settings['timeout']) ? min(100,max(2,(int)$settings['timeout'])) : 5;

		$ignorefile = isset($settings['ignore'])?$settings['ignore']:'data/spamvertignore.txt';
		$this->ignore = array();
		foreach(file($ignorefile) as $line)
		{
			$line = trim(preg_replace('!^\s*(?:https?://)?([a-z0-9.-]*)(?:\s*\#.*)?!','\1',$line));
			if (!$line) continue;
			$this->ignore[$line] = true;
		}
	}

	function preTestPost(ISblamPost $p)
	{
		$this->startTest($p);
	}

	function testPost(ISblamPost $p)
	{
		return $this->endTest();
	}

	protected $fp;
	function startTest(ISblamPost $p)
	{
		$this->fp = NULL;

		$count = 0;

		$alllinks = "# only links extracted from post;\n";
		foreach($p->getLinks() as $link)
		{
			// linksleeve doesn't support 2-level domains
			$domain = $link->getDomain();
			if ($domain === $link->getHostname() || substr_count($domain,'.') >= 2) {d($link->getURI(),"Skipping because of linksleeve bug");continue;}
			if (isset($this->ignore[$domain])) {d($domain,'skipped linksleeve'); continue;}

			$alllinks .= $link->getURI() . " ; " . substr(preg_replace('/[^a-z0-9.-]+/i','',$link->getLabel()),0,50) . "\n";
			$count++;
	    }
		if (!$count) return NULL;

		$query = '<?xml version="1.0"?><methodCall>
			<methodName>slv</methodName>
			<params>
				<param>
				<value><string>'.htmlspecialchars($alllinks).'</string></value>
				</param>
			</params>
		</methodCall>';

        $this->fp = $this->services->getHTTP()->setHost(self::API_HOST)->setPath(self::API_PATH)->setPost($query,'text/xml')->setTimeout($this->timeout)->requestAsync();

		return $this->fp != NULL;
	}

	function endTest()
	{
		if (!$this->fp) return NULL;

		$res = $this->fp->getResponseBody();

		// I'm way too lazy to parse HTTP and XML/DOM
		if (false===strpos($res,'<fault>') && preg_match('!<param>\s*<value>\s*<int>\s*0\s*</int>!',$res))
		{
			return array(0.25,self::CERTAINITY_LOW, "Listed in LinkSleeve");
		}

		return NULL;//array(-0.1,self::CERTAINITY_LOW,"Not listed in LinkSleeve");
	}

	static function info()
	{
		return array(
			'name'=>'LinkSleeve service',
			'desc'=>'Checks for too-popular links using LinkSleeve.org',
			'remote'=>true,
		);
	}
}

