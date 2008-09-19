<?php


class SBlamCorrectFields extends SblamTestPost
{
	protected function hasURI($text)
	{
		return preg_match('!https?://!',$text);
	}

	function testPost(ISblamPost $p)
	{
		$out = array();
		
		if ($this->hasURI($p->getAuthorEmail()))
		{
			$score = 0.2;
			if ($this->hasURI($p->getAuthorURI())) $score = 0.4;
			
			$p->addLink($p->getAuthorEmail()); // expose it!
			
			$out[] = array($score,self::CERTAINITY_LOW,"Link stuffed in e-mail field");
		}
	
		if ($this->hasURI($p->getAuthorName()))
		{
			$score = 0.1;
			if ($this->hasURI($p->getAuthorURI())) $score = 0.3;
			
			$p->addLink($p->getAuthorName()); // expose it!
			
			$out[] = array($score,self::CERTAINITY_LOW,"Link stuffed in name field");
		}
		else if ("" === $p->getAuthorName())
		{
			$out[] = array(0.1,self::CERTAINITY_LOW,"Anonymous");
		}
	
		if ($cnt = substr_count($p->getAuthorURI(),"http://") > 1)
		{
			$out[] = array($cnt/10+0.2,self::CERTAINITY_LOW, "Multiple links in author URI field");
		}
		if ($cnt = substr_count($p->getAuthorURI(),"<a ") > 1)
		{
			$out[] = array($cnt/5+0.2,self::CERTAINITY_LOW, "HTML in author URI field");
		}
	
		$longs = 0;
		if (strlen($p->getAuthorName()) > 50) $longs++;
		if (strlen($p->getAuthorEmail()) > 50) $longs++;
		if (strlen($p->getAuthorURI()) > 150) $longs++;
		
		if ($longs) $out[] = array($longs/10+0.1,self::CERTAINITY_LOW, "Looong text in name/e-mail/URI fields");
	
	
		if ("" === trim($p->getRawContent()))
		{
			$out[] = array(0.6,self::CERTAINITY_LOW,"Empty content");
		}
		
		if (preg_match('!\b(google\.com|msn\.com)\b!',$p->getAuthorURI()))
		{
			$out[] = array(0.2,self::CERTAINITY_LOW,"Not your website");
		}
		
		return $out;
	}
	
	
	static function info()
	{
		return array(
			'name'=>'Check if fields are correctly filled-in',
			'desc'=>'Ensure that post doesn\'t have mistakes that bots would easily make',
			'remote'=>false,
		);
	}
}
