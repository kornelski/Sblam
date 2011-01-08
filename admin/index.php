<?php

chdir(".."); // data/foo.txt must work, period.

set_include_path(get_include_path() . PATH_SEPARATOR . 'admin/phptal');

require_once "debug.php";
require_once "dbconn.php";
require_once "class/sblam.php";
require_once "class/sblambase.php";
require_once "class/server.php";
require_once "admin/phptal/PHPTAL.php";


set_time_limit(1000);
header("Cache-control: no-cache,must-revalidate");

if ($_SERVER['REQUEST_METHOD']=='POST')
{
    d($_POST,'posted');
}
else d('get method');

class AdminPage
{
	private $sblam;
	protected $services;

	function __construct(ISblamServices $services)
	{
	    $this->services = $services;
    }

	function getSblam()
	{
		if (!$this->sblam)
		{
			$this->sblam = new Sblam(Server::getDefaultConfig(), $this->services);
		}
		return $this->sblam;
	}

	function getSblamBase()
	{
		return new SblamBase($this->services->getDB());
	}

	function execute(array $args)
	{
		$method = count($args) ? array_shift($args) : 'index';

		//if (!ctype_alnum($method)) throw new Exception("Invalid method $method");

		if ($_SERVER['REQUEST_METHOD'] != 'GET') $method = strtolower($_SERVER['REQUEST_METHOD']).'_'.$method;

		if (!method_exists($this, $method)) throw new Exception("There is no method $method");

		$res = call_public_func_array(array($this,$method), $args);
		if (!isset($res['title']))
		{
		  $res['title'] = ucwords(preg_replace('/Page$/','',get_class($this)).': '.strtr($method,'_',' '));
	  }
		return $res;
	}
}

function call_public_func_array($callback, array $args)
{
	return call_user_func_array($callback, $args);
}

class Admin
{
	private static $baseuri = '/admin/';
	private static function parseURI($uri)
	{
		$l = strlen(self::$baseuri);

		if (strlen($uri) < $l || substr($uri, 0, $l) !== self::$baseuri) throw new Exception("Not admin URI");

		$components = explode('/',substr($uri, $l));

		$pagename = array_shift($components); if ($pagename==='') $pagename = 'main';

		foreach($components as &$c) $c = urldecode($c);

		return array('pagename'=>$pagename, 'args'=>$components);
	}

	public static function process(ISblamServices $services)
	{
		$pageinf = self::parseURI($_SERVER['REQUEST_URI']);

		try
		{
			$page = self::loadPage($pageinf['pagename'], $services);

			$res = $page->execute($pageinf['args']);
			d($res,'res');
			assert('is_array($res)');
		}
		catch(Exception $e)
		{
			header('HTTP/1.1 500 argh');
			self::display(array('exception'=>$e,'title'=>'Error: '.get_class($e), 'page_template'=>'exception'),$pageinf);
			return;
		}

		self::display($res, $pageinf);
	}

	private static function display(array $res, array $pageinf)
	{
		if (isset($res['redirect']))
		{
			if ($_SERVER['REQUEST_METHOD'] != 'GET') header('HTTP/1.1 303 see');

			if (preg_match('!^https?://!',$res['redirect'])) $url = $res['redirect'];
			else $url = 'http://'.$_SERVER['HTTP_HOST'].self::$baseuri.$res['redirect'];

			header("Location: $url");
			die($url);
		}

		$phptal = new PHPTAL();
		$phptal->set('POST',$_POST);

		foreach($res as $k => $v)
		{
			$phptal->set($k,$v);
		}

		if (!isset($res['page_template'])) $res['page_template'] = $pageinf['pagename'];
		if (!isset($res['page_content']) && $res['page_template'])
		{
			$phptal->setTemplate('admin/tpl/'.$res['page_template'].'.inc');
			$res['page_content'] = $phptal->execute();
			$phptal->set('page_content', $res['page_content']);
		}

		if (!isset($res['content_type'])) $res['content_type'] = 'text/html;charset=UTF-8';
		header("Content-Type: ".$res['content_type']);

		if (!isset($res['layout_template'])) $res['layout_template'] = 'layout';
		if ($res['layout_template'])
		{
			$phptal->setTemplate('admin/tpl/'.$res['layout_template'].'.inc');
			echo $phptal->execute();
		}
		else
		{
			echo $res['page_content'];
		}
	}

	private static function loadPage($name, ISblamServices $services)
	{
		if (!ctype_alnum($name)) throw new Exception("Invalid page name");

		$basepath = dirname(__FILE__).'/';
		$pagefile = $basepath.$name . '.php';

		if (!file_exists($pagefile))
		{
			throw new Exception("No file $pagefile");
		}

//		ob_start();
		require_once $pagefile;
//		ob_end_clean();

		$class = ucfirst($name).'Page';
		if (!class_exists($class)) throw new Exception("Class $class not found");

		$page = new $class($services);

		if (!$page instanceof AdminPage) throw new Exception("Not an admin page");

		return $page;
	}
}




try
{
	Admin::process(new SblamServices(sblambaseconnect()));
}
catch(Exception $e)
{
	header('HTTP/1.1 500 ERR');
	header("Content-Type: text/plain;charset=UTF-8");
	if (ini_get('display_errors')) echo $e; else echo "Error";
	warn($e,"Died");
}
