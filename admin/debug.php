<?php

define("DEBUG",strpos(__FILE__,'test') || $_SERVER['HTTP_HOST'] == 'spam'?1:0);

if (file_exists("../t/base/base.php"))
{
	require "../t/base/base.php";
}
else
{
    ini_set('display_errors',1);
    error_reporting(E_ALL);
    function d($x,$y=''){/* print_r($x);echo $y; */}
    function warn($x,$y=''){d($x,$y);}
}

