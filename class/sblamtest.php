<?php

require_once "class/interfaces.php";

abstract class SblamTest
{
    protected $services;
    function __construct(array $config, ISblamServices $services)
    {
        $this->services = $services;
    }

	static function info() {return array();}
}

abstract class SblamTestPost extends SblamTest implements ISblamTestPost
{
	function preTestPost(ISblamPost $p) {}
	function reportResult(ISblamPost $post,$score,$cert) {}
}
