<?php

class SblamTestWhiteip extends SblamTestPost
{
	protected $whitelist;
	function __construct(array $settings, ISblamServices $services)
	{
        parent::__construct($settings, $services);

		$this->whitelist = @file( isset($settings['whitelist'])?$settings['whitelist']:"data/whiteip.txt" );
	}

	function testPost(ISblamPost $p)
	{
        $isWhiteIP = false;
        $out = array();
        
		foreach($p->getAuthorIPs() as $ip)
		{
			if( array_search($ip, $this->whitelist) !== false ) {
			    $isWhiteIP = true;   
			} else {
			    $isWhiteIP = false;
			}
		}

		if( $isWhiteIP ) {
		    $out[] = array(-1.0, self::CERTAINITY_HIGH, "Sent from whitelisted IP");
		}
		
		return $out;
	}

	static function info()
	{
		return array(
			'name'=>'Author IP',
			'desc'=>'Mark posts as HAM when sent from whitelisted IP',
			'remote'=>false,
		);
	}
}
