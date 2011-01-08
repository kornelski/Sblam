<?php
define("DEBUG",0);

function d(){}
function warn($a,$b=NULL){trigger_error(print_r($a,true)." - $b");}

if (!DEBUG)
{
	header("Content-Type:text/plain;charset=UTF-8");
}
else
{
	ini_set('display_errors',1);
	error_reporting(E_ALL);
}

if ($_SERVER['REQUEST_METHOD'] !== "POST")
{
	header("HTTP/1.1 303 See other");
	header("Location: http://sblam.com");
	die("See http://sblam.com");
}

set_time_limit(20);

require_once "dbconn.php";
require_once "class/server.php";



try
{
    $config = Server::getDefaultConfig();
    $services = new SblamServices(sblambaseconnect($config));

	$server = new Server($services);
	$server->process(new ServerRequest($services->getDB()));
}
catch(ServerException $e)
{
	header("HTTP/1.1 ".$e->getCode()." ".$e->getMessage());
	header("Content-Type: text/plain;charset=UTF-8");
	die($e->getMessage());
}
catch(Exception $e)
{
    header("HTTP/1.1 500 err");
	header("Content-Type: text/plain;charset=UTF-8");
	if (ini_get('display_errors')) die($e->getMessage()); else die("Error");
}
