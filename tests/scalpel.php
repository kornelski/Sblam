<?php

class SblamTestScalpel extends SblamTestPost
{
	protected $patterns =array();

	function __construct(array $settings, ISblamServices $services)
	{
	    parent::__construct($settings, $services);

		$name = empty($settings['patterns'])?'data/scalpel.txt':$settings['patterns'];

		$this->patterns = array();
		foreach(file($name) as $line)
		{
			$line = trim($line); if (!$line || $line[0]=='#') continue;

			if (!preg_match('!^\s*(-?\d+(?:\.\d+)?)\s*[=:] ?(/.+/i?$|[^/].*)!',$line,$res))
			{
				throw new Exception("Syntax error in patterns: $line");
			}
			if ($res[2][0] !=='/') $res[2] = '/'.preg_quote($res[2],'/').'/';

			$this->patterns[] = array($res[2]."u", (float)$res[1]);
		}
		//d($this->patterns,'scalpel patterns');
	}

	function testPost(ISblamPost $p)
	{
		$score = 0;
		$post = $p->getRawContent()."\n".$p->getAuthorName()."\n".$p->getAuthorEmail()."\n".$p->getAuthorURI();

		foreach($this->patterns as $pattern)
		{
			if (preg_match($pattern[0], $post))
			{
				$score += $pattern[1];
			}
		}

		if ($score) return array($score, self::CERTAINITY_NORMAL, "Exact spam matches");
	}

	static function info()
	{
		return array(
			'name'=>'Scalpel',
			'desc'=>'Checks for exact patterns',
			'remote'=>false,
		);
	}
}
