<?php

class AutologinPage extends AdminPage
{
	function apikey($apikey)
	{
		$time = time()+8000;
		return array('redirect'=>'http://sblam.com/key.html?autologin='.md5("^&$@$2\n$apikey@@").":$time:".md5($time.$apikey));
	}

	function account($acc)
	{
		$apikey = $this->services->getDB()->query('SELECT apikey FROM accounts WHERE id='.intval($acc))->fetchAll();
		$apikey = reset($apikey);
		$apikey = reset($apikey);
		return $this->apikey($apikey);
	}
}
