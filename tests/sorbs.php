<?php

class SblamSorbs extends SblamDNSBL
{	
	static $dnsrbls;
	
	function __construct()
	{
		$this->rblhost = 'dnsbl.sorbs.net';	
		self::$dnsrbls = array(
			'127.0.0.2'=>array('link'  =>array(0.4,self::CERTAINITY_NORMAL, "Link in SORBS http"), 
					           'sender'=>array(0.1,self::CERTAINITY_LOW, "Sender in SORBS http")),
			'127.0.0.3'=>array('link'  =>array(0.3,self::CERTAINITY_NORMAL, "Link in SORBS socks"), 
			                   'sender'=>array(0.4,self::CERTAINITY_NORMAL, "Sender in SORBS socks")),   
		    '127.0.0.4'=>array('link'  =>array(0.4,self::CERTAINITY_LOW,    "Link in SORBS misc proxy"), 
		                       'sender'=>array(0.5,self::CERTAINITY_NORMAL, "Sender in SORBS misc proxy")), 
			'127.0.0.7'=>array('link'  =>array(0.5,self::CERTAINITY_LOW,    "Link in SORBS web"), 
			                   'sender'=>array(0.5,self::CERTAINITY_LOW,    "Sender in SORBS web")), 
			'127.0.0.9'=>array('link'  =>array(0.7,self::CERTAINITY_NORMAL, "Link in SORBS zombie"), 
			                   'sender'=>array(0.5,self::CERTAINITY_LOW,    "Sender in SORBS zombie")),
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
			'name'=>'SORBS DNS RBL',
			'desc'=>'Checks for banned IPs in Sorbs Realtime Blackhole List',
			'remote'=>true,
		);
	}
}

