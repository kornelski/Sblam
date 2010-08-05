<?php

class SBlamLateNight extends SblamTestPost
{

	function testPost(ISblamPost $p)
	{
		if ($t = $p->getPostTime())
		{
			$hour = date("G",$t);
			if ($hour >= 2 and $hour <= 5) return array(0.15,self::CERTAINITY_LOW,"Late-night posting ({$hour}h)");
			if ($hour >= 1 and $hour <= 7) return array(0.09,self::CERTAINITY_LOW,"Late-night posting ({$hour}h)");
		}
	}


	static function info()
	{
		return array(
			'name'=>'Late-night posting',
			'desc'=>'Bots spam 24h/day, but humans usually don\'t',
			'remote'=>false,
		);
	}
}
