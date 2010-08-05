<?php

class SBlamOptimist extends SblamTestPost
{
	protected $score;

	function __construct(array $settings)
	{
		$this->score = isset($settings['score']) ? $settings['score'] : -0.35;
	}

	function testPost(ISblamPost $p)
	{
		return array($this->score, self::CERTAINITY_LOW/2, "Optimist");
	}
}
