<?php

require_once "interfaces.php";
require_once "class/sblamuri.php";

class SblamPost implements ISblamPost
{
	public $bayesadded = false;

	function __construct($rawcontent,$name=NULL,$mail=NULL,$uri=NULL,$ip=NULL)
	{
		$this->setRawContent($rawcontent);
		$this->setAuthor($name,$mail,$uri,$ip);
		$this->headers = $_SERVER;
		$this->post = $_POST;
		$this->posttime = time();
	}

	protected $headers;
	function setHeaders(array $h) {$this->headers = $h;}
	function getHeaders() {return $this->headers;}

	protected $post;
	function setPost(array $p) {$this->post = $p;}
	function getPost() {return $this->post;}

	protected $path;
	function setPath($p) {$this->path = $p;}
	function getPath() {return $this->path;}

	private $html,$text,$dom,$links;
	protected function setRawContent($raw)
	{
		$this->raw = $raw; $this->dom = $this->text = $this->links = NULL;
	}
	function getRawContent() {return $this->raw;}
	protected function getDOM()
	{
		if (!$this->dom)
		{
			$this->dom = new DOMDocument();
			if (!@$this->dom->loadHTML(
				'<html><head><meta http-equiv="Content-Type" content="text/html;charset=UTF-8"></head><body>'.
				$this->getRawContent()
			)) {$this->dom = NULL;}
		}
		return $this->dom;
	}

	function _addBBlink($bblink)
	{
		//d($match,'bblink');
		$this->links[] = new SblamURI($bblink[1],$bblink[2]);
	}

	function addLink($uri, $label = '')
	{
		$this->getLinks(); // prefill links array
		$this->links[] =  new SblamURI($uri,$label);
	}

	function getLinks()
	{
		$seenlinks = array();

		if ($this->links === NULL)
		{
			// find all links that are in HTML (DOM should be used to parse according to HTML rules)
			$this->links = array();
			if ($dom = $this->getDOM())
			{
				foreach($dom->getElementsByTagName('a') as $a)
				{
					if ($uri = $a->getAttribute('href'))
					{
						$seenlinks[preg_replace('!\#[^#]*$!','',$uri)] = true;
						$this->links[] = new SblamURI($uri,$a->textContent);
					}
				}
			}

			$nonlinks = $this->getText();
			if (preg_match_all('!\[url\s*=\s*[\'\"]?((?:https?|www|//)[^]<>\s\'\"]+)\s*\]([^]\[]*?)\[/url!is',$nonlinks,$bb,PREG_SET_ORDER))
			{
				foreach($bb as $bblink)
				{
					$this->links[] = new SblamURI($bblink[1],$bblink[2]);
				}
			}

			//d($nonlinks,'new text before debb');
			$this->updateText(preg_replace('!\[url\s*=\s*[\'\"]?((?:https?|www|//)[^]<>\s\'\"]+)\s*\]([^]\[]*?)\[/url\]'.
			'|\[/?(?:url|b|u|i|quote|color|size|list|img|code|bi|pre|s|attach)(?:=[^\]]{1,12})?\]!is','',$nonlinks));

			// find all links outside HTML
			if (preg_match_all('!https?://[^\s)#\'"\!]+|\bwww\.(?:[a-z0-9][a-z0-9-]+\.)+[a-z]{2,6}(?:/[^]\[\s()#\'"\!\*]*)?!',$this->getText(),$matches))
			{
				foreach($matches[0] as $uri)
				{
					if (!isset($seenlinks[$uri]))
						$this->links[] = new SblamURI($uri);//,'label'=>NULL);
				}
			}

			// ignore links pointing to the site itself
			/*$headers = $this->getHeaders();
			if (!empty($headers['HTTP_HOST']))
			{
				$hosturi = new SblamURI('http://'.$headers['HTTP_HOST'].'/');
				$domain = $hosturi->getDomain();

				foreach($this->links as $key => $link)
				{
					if ($domain === $link->getDomain()) unset($this->links[$key]);
				}
			}*/

			if (($headers = $this->getHeaders()) && !empty($headers['HTTP_HOST']) &&
			    !empty($headers['HTTP_REFERER']) && preg_match('!(?:https?:)?//([^/?#]+)[^\s]*!i',$headers['HTTP_REFERER'],$r))
			{
				if (false === strpos($r[1],$headers['HTTP_HOST']))
				{
					$this->links[] = new SblamURI($r[0],$headers['HTTP_REFERER']);
				}
			}
		}
		return $this->links;
	}

	function getText()
	{
		if (!$this->text)
		{
			if ($origdom = $this->getDOM())
			{
				$doc = $origdom->documentElement->cloneNode(true);

				$temp = array();
				foreach($doc->getElementsByTagName('a') as $a) $temp[] = $a; // live collections suck when removing things
				foreach($temp as $a)
				{
					$a->parentNode->removeChild($a);
				}
				$this->text = $doc->textContent;
			}
		}
		return $this->text;
	}

	private function updateText($text)
	{
		//d($text,"new text!");
		$this->text = $text;
	}

	private $authorname,$authormail,$authoruri,$authorips;

	/** @param ip IP either single IP or array of IPs (proxy forwarded hosts). IPs should be in dot notation (11.22.33.44)
	*/
	function setAuthor($name,$mail=NULL,$uri=NULL,$ip=NULL)
	{
		if ($ip === NULL) {warn("No ip given for sblampost, taking from env!");$ip = $_SERVER['REMOTE_ADDR'];}
		else if (is_numeric($ip)) $ip = long2ip($ip);
		if (!is_array($ip)) $ip = array($ip);

		$this->authorname = $name;
		$this->authormail = $mail;
		$this->authoruri = $uri;
		$this->authorips = $ip;
	}

	function getAuthorName() {return $this->authorname;}
	function getAuthorEmail() {return $this->authormail;}
	function getAuthorURI() {return $this->authoruri !== 'http://'?$this->authoruri:NULL;} /** @todo should check if link looks valid. now just excludes one popular default */
	function getAuthorIP() {return count($this->authorips)?$this->authorips[0]:NULL;}
	function getAuthorIPs() {return $this->authorips;}

	protected $signature;
	function setSignature($s) {$this->signature = $s;}
	function getSignature() {return $this->signature;}

	private $dates = array();
	function getDates() {return $this->dates;}

	protected $posttime;
	function getPostTime() {return $this->posttime;}
	function setTime($t) {$this->posttime = $t;}

	protected $serverinstallid;
	function setInstallId($s) {$this->serverinstallid = $s;}
	function getInstallId() {return $this->serverinstallid;}
}

class SblamPostAuto extends SblamPost
{
	function __construct($contentfield=NULL,$namefield=NULL,$mailfield=NULL,$urifield=NULL)
	{
		if ($contentfield && isset($_POST[$contentfield])) $contentfield = $_POST[$contentfield];
		if ($namefield && isset($_POST[$namefield])) $namefield = $_POST[$namefield];
		if ($mailfield && isset($_POST[$mailfield])) $mailfield = $_POST[$mailfield];
		if ($urifield && isset($_POST[$urifield])) $urifield = $_POST[$urifield];

		parent::__construct($contentfield, $namefield, $mailfield, $urifield, ServerRequest::getRequestIPs());
	}
}

