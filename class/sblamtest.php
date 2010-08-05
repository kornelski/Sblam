<?php

require_once "class/interfaces.php";

abstract class SblamTest
{
	protected $manager;
	function setManager(ISblam $m)
	{
		$this->manager = $m;
	}

	static function info() {return array();}
}

abstract class SblamTestPost extends SblamTest implements ISBlamTestPost
{
	function preTestPost(ISblamPost $p) {}
	function reportResult(ISblamPost $post,$score,$cert) {}
}
