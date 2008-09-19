<?php

require_once "class/domainmatch.php";
require_once "class/asyncdns.php";


class SblamURI
{
	protected $uri, $label;
	protected static $useasync;

	static $tlds, $db;
	static function init($tldsfile, PDO $db=NULL)
	{
		//d('initializing tlds');
		self::$tlds = new DomainMatch($tldsfile);
		self::$db = $db;
		if (!$db) warn("No cache");
		
		self::$useasync = AsyncDNS::supported();
	}


	static function filterUnicodeText($text)
	{
		if (!$text) return $text;

		// unicodization (remove &shy;, etc)
		$text = preg_replace('![\p{Cc}\p{Cf}\p{Cn}\p{Co}\p{Mn}\p{Me}\p{Lm}]!u', '',$text);

		if (function_exists('mb_strtolower'))	{$text = mb_strtolower($text,'UTF-8');}
		else {$text = strtolower($text);}

		return $text;
	}

	function __construct($uri, $label = NULL)
	{
		$this->uri = trim($uri);
		$this->label = self::filterUnicodeText($label);
	}

	function isIPHost()
	{
		if (preg_match('!(?:[a-z0-9+-]+:)?/+(\d+\.\d+\.\d+\.\d+)!',$this->uri,$ip)) return $ip[1];
		return false;
	}

	function getIP()
	{
		if ($ip = $this->isIPHost()) return $ip;

		return self::gethostbyname($this->getHostname());
	}

	function getRev()
	{
		$ip = $this->getIP();
		assert('preg_match("!^\d+\.\d+\.\d+\.\d+$!",$ip)');
		$res = self::gethostbyaddr($ip);
		if ($res) return $res;
		return NULL;
	}

	function getLabel() {return $this->label;}

	function __toString() {return $this->uri;}

	function getURI()
	{
		return $this->uri;
	}

	function getPath()
	{
		if (preg_match('!(?:[a-z0-9+-]+:)?/+(?:[^/\s]+@)?[a-z0-9_.-]+(/.*|$)!i',$this->uri,$path))
		{
			return $path[1];
		}
		return '/';
	}

	function getHostname()
	{
		if ($this->isIPHost()) {return $this->getRev();}

		$uri = self::filterUnicodeText($this->uri);

		if (preg_match('!(?:[a-z0-9+-]+:)?/+(?:[^/\s]+@)?([a-z0-9_.-]+)!',strtolower($uri),$domain))
			return trim($domain[1],'.');
		return false;
	}

  function isTLD()
  {
    return NULL === $this->getLevel($this->getHostname());
  }

  private function getLevel($host)
  {
    	if (self::$tlds)
  		{
  			//d(self::$tlds->check($host),'check returns');
  			$level = self::$tlds->check($host); if ($level === NULL) return NULL;
  			$level = max(1,$level)+1;
  			//d("Looked up level $level for $host");
  		}
  		else
  		{
  			warn('tlds not available!');
  			$parts = array_reverse(explode('.',$host));
  			if (!empty($parts[1]) && strlen($parts[1])<=3) $level = 3; else $level = 2;
  		}
		d($level,"$host tld+1");
  		return $level;
  }

	function getDomain()
	{
		$host = $this->getHostname();

	  $level = $this->getLevel($host);	  
	  if ($level === NULL) return NULL;

		$host = implode('.',array_slice(explode('.',$host), -$level, $level));
		return $host;
	}

	static $lookupcache = array();
	static function gethostbyaddr($ip)
	{
		if (!$ip) return false;

		if (array_key_exists($ip,self::$lookupcache))
		{
			if (self::$lookupcache[$ip] instanceof AsyncDNS)
			{
				self::$lookupcache[$ip] = self::$lookupcache[$ip]->getResult();
				self::savebyaddrcache($ip,self::$lookupcache[$ip]);
			}
			return self::$lookupcache[$ip];
		}

		if (($res = self::gethostbyaddrcache($ip)) !== false)
		{
			return self::$lookupcache[$ip] = $res;
		}

		assert('preg_match("!^\d+\.\d+\.\d+\.\d+$!",$ip)');
		warn("Slow lookup of $ip");
		$res = gethostbyaddr($ip);
		if ($res === $ip || false === strpos($res,'.') || strlen($res)<4) $res = false;

		self::savebyaddrcache($ip,$res);
		return self::$lookupcache[$ip] = $res;
	}

	static function gethostbyname($name)
	{
		$res = self::gethostbynamel($name);
		if ($res) return $res[0];
		return $res;
	}

	static function gethostbyaddrasync($ip)
	{
		if (!$ip || isset(self::$lookupcache[$ip])) return;

		if (($res = self::gethostbyaddrcache($ip)) !== false)
		{
			return self::$lookupcache[$ip] = $res;
		}
		elseif (self::$useasync) self::$lookupcache[$ip] = AsyncDNS::query($ip);
	}

	static function gethostbynameasync($name)
	{
		if (!$name || isset(self::$lookupcache[$name])) return;

		if (($res = self::gethostbynamecache($name)) !== false)
		{
			return self::$lookupcache[$name] = $res;
		}
		elseif (self::$useasync) self::$lookupcache[$name] = AsyncDNS::query($name);
	}

	private static function gethostbyaddrcache($ip)
	{
		if (self::$db) foreach(self::$db->query(sprintf("SELECT host FROM dnsrevcache WHERE ip = '%u' LIMIT 1",ip2long($ip)))->fetchAll(PDO::FETCH_ASSOC) as $host)
		{
			d($host['host'],'found cached ip2host');
			if ($host['host'] !== '') return $host['host']; else return NULL;
		}
		d($ip,'ip2host miss');
		return false;
	}

	private static function gethostbynamecache($host)
	{
		if (!self::$db) return false;
		$res = array();
		d($host,'lookup cache');
		foreach(self::$db->query("SELECT ip FROM dnscache WHERE host = '".addslashes(strtolower($host))."'")->fetchAll(PDO::FETCH_ASSOC) as $ip)
		{
			d($ip,'db res');
			$ip = long2ip($ip['ip']);
			if ($ip == 0) {d($host,'cached (ok) empty lookup result');return array();}
			$res[] = $ip;
		}
		if (!count($res))
		{
			d($host,'host2ip miss');
		  return false;
		}
		d($res,'found cached host2ip');
		return $res;
	}

	private static function savebynamecache($ips,$host)
	{
		if (!self::$db) return;
		
		$host = addslashes(strtolower($host));
		
		d($ips,"Saving resolution of $host");

		if (!$ips || !count($ips))
		{
			if (!self::$db->exec(sprintf("/*maxtime10*/REPLACE INTO dnscache (ip,host) VALUES(0,'%s')",$host))) warn(self::$db->errorInfo());
		}
		else foreach($ips as $ip)
		{
			if (!self::$db->exec(sprintf("/*maxtime10*/REPLACE INTO dnscache (ip,host) VALUES('%u','%s')",ip2long($ip),$host))) warn(self::$db->errorInfo());
		}
	}

	private static function savebyaddrcache($ip,$host)
	{
		if (!self::$db) return;
		d("Saving resolution of $ip -> $host");
		
		if (is_array($host)) $host = reset($host);

		if (!self::$db->exec(sprintf("/*maxtime10*/REPLACE INTO dnsrevcache (ip,host) VALUES('%u','%s')",ip2long($ip),addslashes(strtolower($host))))) warn(self::$db->errorInfo());
	}

	static function gethostbynamel($name)
	{
		if (!$name || false===strpos($name,'.')) return false;
		$name = rtrim($name,'.');

		if (array_key_exists($name,self::$lookupcache))
		{
			if (self::$lookupcache[$name] instanceof AsyncDNS)
			{
				$res = self::$lookupcache[$name]->getResult();
				if ($res === false)
				{
					unset(self::$lookupcache[$name]);
					return self::gethostbynamel($name); // asyncdns seems to be unreliable, so allow fallback
				}
				else
				{
					self::$lookupcache[$name] = $res;
					self::savebynamecache($res,$name);
				}
			}
			return self::$lookupcache[$name];
		}

		if (($res = self::gethostbynamecache($name)) !== false)
		{
			return self::$lookupcache[$name] = $res;
		}

		warn("Slow lookup of $name");
		$res = gethostbynamel($name);
		self::savebynamecache($res,$name);

		if (!count($res)) $res = false;
		return self::$lookupcache[$name] = $res;
	}
}
