<?php

require_once "class/sblampdo.php";

function sblambaseconnect(array $config)
{
	static $pdo;

    if (!isset($config['db'])) throw new Exception("Config does not have db section");
    $dbcfg = $config['db'];

    if (!isset($dbcfg['dsn'])) throw new Exception("Config does not have dsn variable in db section");
    if (!isset($dbcfg['user'], $dbcfg['pass'])) throw new Exception("Config does not have user/pass variables in db section");

    $max=5;
    while(!$pdo && $max--)
    {
    	try {
    		$pdo = SblamPDO::create($dbcfg['dsn'],$dbcfg['user'], $dbcfg['pass']);
    		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    		$pdo->exec("SET NAMES utf8");
    	}
    	catch(PDOException $e)
    	{
    	    if (!$max) throw $e;
    		usleep(250000);
    	}
	}
	return $pdo;
}
