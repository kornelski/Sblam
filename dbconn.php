<?php

define("SBLAMDB_PASS", "???");
define("SBLAMDB_USER", "???");
define("SBLAMDB_BASE", "???");
define("SBLAMDB_HOST", "???");

class SblamPDO extends PDO
{
    function prepareExecute($q,array $data)
    {
        $statement = $this->prepare($q);
		if (!$statement) throw new PDOException(implode(',',$this->errorInfo().'// '.$q));
		if (!$statement->execute($data)) throw new PDOException(implode(',',$statement->errorInfo().'// '.$q));
		return $statement;
    }
}

function sblambaseconnect()
{
	static $pdo;
	$max=10;

	while(!$pdo && $max--)
	{
		try {
			$pdo = new SblamPDO("mysql:host=".SBLAMDB_HOST.";dbname=".SBLAMDB_BASE,SBLAMDB_USER,SBLAMDB_PASS);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$pdo->exec("SET NAMES utf8");
		}
		catch(Exception $e)
		{
		    if (!$max) throw $e;
			sleep(1);
		}
	}
	return $pdo;
}
