<?php


class SblamTestHashes extends SblamTestPost
{

	function testPost(ISblamPost $p)
	{
		if (preg_match('!^\s*([a-f0-9]{32,64}).+?\s.+?([a-f0-9]{32,64})?\s*$!si',$p->getRawContent(),$res))
		{
			if (!preg_match('![a-f][0-9]!',$res[0])) return NULL;
			if (!empty($res[1]) && preg_match('![a-f][0-9]!',$res[1])) return array(0.3,self::CERTAINITY_NORMAL,"Hash marks (2)");
			return array(0.2,self::CERTAINITY_LOW,"Hash marks (1)");
		}
		return NULL;
	}



	static function info()
	{
		return array(
			'name'=>'Hashes marking messages',
			'desc'=>'Stupid spammers mark their messages with unique hashes, probably to find their own successful spammings later',
			'remote'=>false,
		);
	}
}

