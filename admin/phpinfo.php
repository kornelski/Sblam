<?php

class PhpinfoPage extends AdminPage
{
	function index()
	{
		ob_start();
		phpinfo();
		return array('page_content'=>ob_get_clean());
	}
}
