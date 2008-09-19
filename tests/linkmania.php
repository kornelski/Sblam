<?php


class SBlamLinkmania extends SblamTestPost
{
	function testPost(ISblamPost $p)
	{
		$links = $p->getLinks(); if ($links === NULL) return NULL;
		
		$linkcount = count($links); $authorlink = ($p->getAuthorURI())?1:0; // count separately, because this link may be unrelated to post's contents, so shouldn't skew link/words ratio
		
		if (($linkcount+$authorlink) == 0)
		{
			if (strlen($p->getText()) > 20) return array(-0.5,self::CERTAINITY_NORMAL, "No links");
			return NULL; // don't give nolinks bonus to posts with no content (no content is abnormal and it may be another way to spam)
		}
		if (($linkcount+$authorlink) == 1) return array(0.1,self::CERTAINITY_LOW, "Single link");
		if (($linkcount+$authorlink) == 2) return array(0.2,self::CERTAINITY_LOW, "Two links");
		
		$numwords = count(preg_split('![^a-z0-9\x7F-\xFF-]+|https?://[^\]\[\s\'"<>]+!i',$p->getText(),500,PREG_SPLIT_NO_EMPTY));
		
		// long posts may legitimately have more links. can't set any limits, because wiki pages may contain lots of links.
		$ratio = round($linkcount*100 / (10+$numwords));
			
		if ($ratio > 22) return array(0.45, self::CERTAINITY_NORMAL, "Flooded with links (A$ratio: $linkcount per {$numwords} words)");
		if ($ratio > 17) return array(0.35, self::CERTAINITY_NORMAL, "Flooded with links (B$ratio: $linkcount per {$numwords} words)");
		if ($ratio > 12) return array(0.25, self::CERTAINITY_NORMAL, "Flooded with links (C$ratio: $linkcount per {$numwords} words)");
		if ($ratio > 6) return array(0.25, self::CERTAINITY_NORMAL, "Lots of links (D$ratio: $linkcount per {$numwords} words)");
		return array(0.25, self::CERTAINITY_LOW,"Some links (E$ratio: $linkcount per {$numwords} words)");
	}
	
	static function info()
	{
		return array(
			'name'=>'LinkMania',
			'desc'=>'Assumes that posts flooded with links are spam',
			'remote'=>false,
		);
	}
}
