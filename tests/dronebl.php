<?php

class SblamTestDroneBL extends SblamTestDNSBL
{
    static $dnsrbls;

	function __construct()
	{
		$this->rblhost = 'dnsbl.dronebl.org';
		self::$dnsrbls = array(
			'127.0.0.3'=>array('link'  =>array(0.1,self::CERTAINITY_LOW, "Link in drone IRC"),
							   'sender'=>array(0.2,self::CERTAINITY_LOW, "Sender in drone IRC")),
			'127.0.0.5'=>array('link'  =>array(0.2,self::CERTAINITY_LOW, "Link in drone Bottler"),
			                   'sender'=>array(0.2,self::CERTAINITY_LOW, "Sender in drone Bottler")),
			'127.0.0.6'=>array('link'  =>array(0.3,self::CERTAINITY_LOW, "Link in drone worm"),
			                   'sender'=>array(0.3,self::CERTAINITY_LOW, "Sender in drone worm")),
			'127.0.0.7'=>array('link'  =>array(0.2,self::CERTAINITY_LOW, "Link in drone DDoS"),
			                   'sender'=>array(0.3,self::CERTAINITY_LOW, "Sender in drone DDoS")),
			'127.0.0.8'=>array('link'  =>array(0.3,self::CERTAINITY_LOW, "Link in drone SOCKS"),
			                   'sender'=>array(0.4,self::CERTAINITY_LOW, "Sender in drone SOCKS")),
			'127.0.0.9'=>array('link'  =>array(0.2,self::CERTAINITY_LOW, "Link in drone HTTP"),
			                   'sender'=>array(0.4,self::CERTAINITY_LOW, "Sender in drone HTTP")),
			'127.0.0.10'=>array('link' =>array(0.2,self::CERTAINITY_LOW, "Link in drone Proxychain"),
			                   'sender'=>array(0.3,self::CERTAINITY_LOW, "Sender in drone Proxychain")),
			'127.0.0.13'=>array('link' =>array(0.1,self::CERTAINITY_LOW, "Link in drone dictionary"),
			                   'sender'=>array(0.2,self::CERTAINITY_LOW, "Sender in drone dictionary")),
			'127.0.0.14'=>array('link' =>array(0.2,self::CERTAINITY_LOW, "Link in drone WINGATE"),
			                   'sender'=>array(0.3,self::CERTAINITY_LOW, "Sender in drone WINGATE")),
		    '127.0.0.15'=>array('link' =>array(0.3,self::CERTAINITY_LOW, "Link in drone router"),
		                       'sender'=>array(0.3,self::CERTAINITY_LOW, "Sender in drone router")),
		    '127.0.0.255'=>array('link'=>array(0.1,self::CERTAINITY_LOW, "Link in drone Uncategorized"),
		                       'sender'=>array(0.1,self::CERTAINITY_LOW, "Sender in drone Uncategorized")),
		);
	}

	function score($ip, $resip, $reason, $scorefactor)
	{
		if (isset(self::$dnsrbls[$resip]) && isset(self::$dnsrbls[$resip][$reason]))
		{
		    $tempout = self::$dnsrbls[$resip][$reason];
			$tempout[2] .= " ($ip * ".round($scorefactor,2).")";
			$tempout[0] *= $scorefactor;
			return $tempout;
		}
	}

	static function info()
	{
		return array(
			'name'=>'dronebl.org DNS RBL',
			'desc'=>'Checks for banned IPs in dronebl.org abusable IPs list',
			'remote'=>true,
		);
	}
}

