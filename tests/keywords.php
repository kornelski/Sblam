<?php

class SblamTestKeywords extends SblamTestPost
{
	protected $keywords, $blocklist;

	function __construct(array $settings, ISblamServices $services)
	{
        parent::__construct($settings, $services);

		$this->blocklist = !empty($settings['blocklist2']) ? $settings['blocklist2'] : 'data/blocklist.txt';
	}

	function importBlocklist2($blocklist2file)
	{
		$file = @file_get_contents($blocklist2file); if (!$file) return false;
		foreach(explode("\n",$file) as $line)
		{
			if ('block:'===substr($line,0,6))
			{
				$this->keywords[] = preg_replace('![^a-z0-9._-]+!',' ',strtolower(trim(substr($line,6))));
			}
		}
		return true;
	}

	private function testText($text)
	{
		return count(array_intersect($this->getKeywordsFromText($text),$this->keywords));
	}

	// crappy, us-ascii only
	private function getKeywordsFromText($x)
	{
		return str_word_count(preg_replace('![^a-z0-9._-]+!',' ',strtolower($x)),1);
	}

	function testPost(ISblamPost $p)
	{
		if ($this->keywords === NULL)
		{
			$this->importBlocklist2($this->blocklist);
		}
		if (!count($this->keywords)) return NULL;

		$res1 = $this->testText($p->getText().' '.$p->getAuthorName());
		$res2=0;
		$res3=0;

		$alluris = '';
		if ($uri = $p->getAuthorURI()) $alluris .= strtolower($uri);
		if ($uri = $p->getAuthorEmail()) $alluris .= ' '.strtolower($uri);

		foreach($p->getLinks() as $link)
		{
			if ($label = $link->getLabel()) $res2 += count(array_intersect($this->getKeywordsFromText($label),$this->keywords));
			if ($uri = $link->getURI()) $alluris .= ' '.strtolower($uri);
		}

		$cnt=0;
		str_replace($this->keywords,$this->keywords,$alluris,$res3);

		$sum = $res1+$res2+$res3;
		if (!$sum) return NULL;//array(-0.1,self::CERTAINITY_LOW, "No banned keywords");

		$out = array();
		if ($res1) $out[] = array(1.2-1/($res1), $sum > 2 ? self::CERTAINITY_HIGH : self::CERTAINITY_NORMAL, "Banned keywords in text ($res1)");
		if ($res2) $out[] = array(1.2-1/($res2+1), self::CERTAINITY_HIGH, "Banned keywords in link labels ($res2)");
		if ($res3) $out[] = array(1.2-1/($res3), $sum > 2 ? self::CERTAINITY_HIGH : self::CERTAINITY_NORMAL, "Banned keywords in URLs ($res3)");
		if (count($out)) return $out;
	}

}
