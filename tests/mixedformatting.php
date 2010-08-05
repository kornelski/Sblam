<?php


class SBlamMixedformatting extends SblamTestPost
{

	function testPost(ISblamPost $p)
	{
        $txt = $p->getRawContent().' '.$p->getAuthorName().' '.$p->getAuthorEmail();

        $rawlinks = preg_match("!(?:^|\s)https?://!mi",$txt);
        $bbcode = preg_match("!\[url\s*[\]=]\s*http!i",$txt);
        $html = preg_match("!<a\s[^><]*href[^>]!i",$txt);
        $textile = preg_match("!\":https?://!i",$txt);

		if ($bbcode && $html && ($textile || $rawlinks)) return array(1,self::CERTAINITY_NORMAL,"Mixed BBcode, HTML and other links");
		if ($bbcode && $html) return array(0.7,self::CERTAINITY_NORMAL,"Mixed BBcode and HTML");
		return NULL;
	}

	static function info()
	{
		return array(
			'name'=>'Don\'t allow different link formatting styles',
			'desc'=>'Spammers sometimes try all kinds of formatting in case any of them works',
			'remote'=>false,
		);
	}
}

