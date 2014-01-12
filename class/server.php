<?php

require_once "class/sblam.php";
require_once "class/sblamtest.php";
require_once "class/sblampost.php";

class ServerException extends Exception {}

class Account
{
    static function fromApiKeyHash(PDO $db, $keyhash)
	{
		if ($keyhash == md5("^&$@$2\ndefault@@"))
		{
			return new Account(array('id'=>0,'apikey'=>'default'));
		}

		$prep = $db->prepare("/*maxtime=2*/SELECT * from accounts where apikeyhash = unhex(?)");
		if (!$prep || !$prep->execute(array($keyhash)))
		{
			throw new ServerException("Awaria bazy kluczy",500);
		}
		elseif (!($q = $prep->fetchAll(PDO::FETCH_ASSOC)) || !($q = reset($q)))
		{
			throw new ServerException("Niepoprawny klucz API",403);
		}

		return new Account($q);
	}


	public $id,$apikey;

	protected function __construct(array $fields)
	{
		foreach($fields as $k => $v)
		{
			$this->$k = $v;
		}
	}

	function isDefaultAccount()
	{
		return $this->apikey == 'default';
	}
}

class ServerRequest
{
	private $db, $account, $data, $ips, $stored_id = 1;

	function __construct(PDO $db, $datasourcepath = 'php://input')
	{
		$this->db = $db;
		$this->data = $this->convertToArray($this->decodeData(file_get_contents($datasourcepath)));
		$this->normalizeData();
	}

	private function decodeData($data)
	{
		$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : @$_SERVER['HTTP_CONTENT_TYPE'];
		if (!preg_match('!^application/x-sblam\s*;\s*sig\s*=\s*([a-z0-9]{32})([a-z0-9]{32})(\s*;\s*compress\s*=\s*gzip)?\s*$!i', $content_type, $res))
		{
			throw new ServerException("Niepoprawne zapytanie",400);
		}

		$compressed = !empty($res[3]);
		$keyhash = $res[1];
		$sig = $res[2];

		$this->account = Account::fromApiKeyHash($this->db,$keyhash);

		if (md5($this->account->apikey . $data) !== $sig)
		{
			throw new ServerException("Niepoprawny podpis danych lub dane uszkodzone podczas transferu",403);
		}

        if ($compressed) $data = gzuncompress($data,300000);

        return $data;
	}

	private function convertToArray($dat)
	{
		if (!function_exists('mb_convert_encoding') || !($conv = mb_convert_encoding($dat,"UTF-8","UTF-8,ISO-8859-2,Windows-1252")))
		{
			$conv = @iconv("UTF-8","UTF-8",$dat);
			if ($conv === false || strlen($conv) < strlen($dat)*0.9) // iconv can normalize string, which changes length :/
			{
				$conv = @iconv("ISO-8859-2","UTF-8//IGNORE",$dat);
				if ($conv === false || strlen($conv) < strlen($dat))
				{
					warn($conv,"A total failure, not even ISO!");
					$conv = utf8_encode($dat);
				}
				else {d($conv,"got iso converted");}
			}
			else {d($conv,"got UTF-8 converted");}
		}

		$dat = strtr($conv,array(		'ą'=>'ą',		'ę'=>'ę',		'ó'=>'ó',		'ż'=>'ż',		'ś'=>'ś',		'ć'=>'ć',		'ź'=>'ź',		'ń'=>'ń',	'Ą'=>'Ą',		'Ę'=>'Ę',		'Ó'=>'Ó',		'Ż'=>'Ż',		'Ś'=>'Ś',		'Ć'=>'Ć',		'Ź'=>'Ź',		'Ń'=>'Ń' ));

		$dat = explode("\0",$dat);

		$cnt = count($dat);
		$fields = array();
		for($i=1; $i < $cnt; $i+=2)
		{
			$fields[ $dat[$i-1] ] = $dat[$i];
		}
		return $fields;
	}

	private function normalizeData()
	{
		if (!isset($this->data['HTTP_HOST'])) $this->data['HTTP_HOST'] = $this->data['host'];
		$this->data['REMOTE_ADDR'] = $this->data['ip'];

		$this->ips = self::getRequestIPs($this->data, true);
		if (!count($this->ips)) $this->ips = array($this->data['ip']);
	}

	// PHP's filter_var doesn't support all ranges
    private static function isPrivateOrReservedIP($ip_str)
    {
        static $ranges = array(
            array('0.0.0.0', 8),
            array('10.0.0.0', 8 ),
            array('127.0.0.0', 8),
            array('128.0.0.0', 16),
            array('169.254.0.0', 16),
            array('172.16.0.0', 12),
            array('191.255.0.0', 16),
            array('192.0.0.0', 24),
            array('192.168.0.0', 16),
            array('223.255.255.0', 24),
        );

        $ip = ip2long($ip_str);

        foreach($ranges as $range)
        {
            $subnet = ip2long($range[0]);
            $mask = (-1 << (32-$range[1])) & 0xFFFFFFFF;

            if (($ip & $mask) === $subnet) return true;
        }
        return false;
    }


	/** extract all IPs from request headers

		@param headers $_SERVER array
		@return array
	*/
	static function getRequestIPs($headers = NULL, $routable = true)
	{
		if (NULL === $headers) $headers = $_SERVER;
		if (!isset($headers['REMOTE_ADDR'])) $headers['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];

		$out = array($headers['REMOTE_ADDR']=>true);

		// order IS important for security
		$search = array('X_FORWARDED_FOR','FORWARDED_FOR','CLIENT_IP','X_CLIENT_IP',
		'X_CLUSTER_CLIENT_IP','X_FORWARDED','PC_REMOTE_ADDR','FORWARDED','X_WAP_CLIENT_IP','X_COMING_FROM','X_REAL_IP');

		foreach($search as $h)
		{
			$h = 'HTTP_'.$h;
			if (isset($headers[$h]) && preg_match_all('!\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}!',$headers[$h],$t)) // order IS important for security
			{
				foreach($t[0] as $ip) if (!isset($out[$ip])) $out[$ip] = true;
			}
		}

		foreach($out as $ip => $whatever)
		{
			if (self::isPrivateOrReservedIP($ip))
			{
				//d($ip,'Unroutable IP - dropping');
				unset($out[$ip]);
			}
		}

		if (count($out)>1)
		{
			d('checking for proxies');
			$db = sblambaseconnect(Server::getDefaultConfig());
			$prep = $db->prepare("/*maxtime=1*/SELECT 1 FROM trustedproxies p JOIN dnscache d ON p.host = d.host WHERE d.ip = ?");
			if (!$prep) throw new Exception("b0rked".implode(',',$db->errorInfo()));
			foreach($out as $ip => $whatever)
			{
				if (!$prep->execute(array(sprintf("%u",ip2long($ip))))) throw new Exception("bork".implode($prep->errorInfo()));
				if (count($prep->fetchAll()))
				{
					d($ip,'found to be a trusted proxy');
					unset($out[$ip]);
				}
				else
				{
					d($ip,'not a trusted proxy, bye!');
					break;
				}
			}
		}
		return array_keys($out);
	}

	function &getData()
	{
		return $this->data;
	}

	function getIPs()
	{
	    return $this->ips;
    }

	function isDefaultAccount() {return $this->account->isDefaultAccount();}

    function customizeConfig(array $config)
    {
        return $config;
    }

    /**
     * sends response to the browser, flushes and ends HTTP output
     * @param $res -2 to 2, as per public documentatio
     */
	function returnResult($res)
	{
		$n = $res.':'.$this->stored_id.':'.md5($this->account->apikey . $res . $this->data['salt'])."\n";
		header("HTTP/1.0 200 res");
		header("Content-length:".strlen($n));

		echo $n;
		@ob_flush();flush();
	}

	private function insertArray($table,array $out, $maxtime=20)
	{
	    $q = "/*maxtime=$maxtime*/INSERT into $table (".implode(',',array_keys($out)).") values(?".str_repeat(",?",count($out)-1).")";
	    $pre = $this->db->prepare($q);
		if (!$pre) return NULL;
		return $pre->execute(array_values($out));
    }

	function getStoredId()
	{
	    return $this->stored_id;
    }

	function storeData(SblamPost $sblampost)
	{
		$heads = '';
		foreach($this->data as $n => $h)
		{
			if (substr($n,0,5)=='HTTP_') $heads .= $n.': '.strtr($h,"\r\n","	")."\n";
		}
		$post = '';
		foreach($this->data as $n => $h)
		{
			if (substr($n,0,5)=='POST_') $post .= substr($n,5).': '.strtr($h,"\r\n","	 ")."\n";
		}

        $this->db->beginTransaction();
        try
        {
            $out = array(
    			'ip'=>sprintf("%u",ip2long($this->data['ip'])),
    			'timestamp'=>$this->data['time'],
    			'account'=>$this->account->id,
    			'serverid'=>$sblampost->getInstallId(),
    		);
		    $this->insertArray('posts_meta',$out,10);
            $this->stored_id = $this->db->lastInsertId('posts_meta_id_seq');
            assert('$this->stored_id > 0');

		    $out = array(
		        'id'=>$this->stored_id,
    			'content'=> $sblampost->getRawContent(),
    			'name'=>$sblampost->getAuthorName(),
    			'email'=>$sblampost->getAuthorEmail(),
    			'url'=>$sblampost->getAuthorURI(),
    			'headers'=>$heads,
		        'cookies'=>$this->data['cookies'],
    			'chcookie'=>$this->data['sblamcookie'],
    			'session'=>$this->data['session'],
		        'host'=> preg_replace('!^(?:www\.)?([^/:]*).*$!','\1',$this->data['host']),
    			'hostip'=>sprintf("%u",ip2long($_SERVER['REMOTE_ADDR'])),
    			'path'=>$this->data['uri'],
    			'post'=>$post,
    		);

		    $this->insertArray('posts_data',$out,20);
		    $this->db->commit();
		    return true;
	    }
	    catch(Exception $e)
	    {
	        $this->db->rollback();
	        throw $e;
        }
	}

	function storeResult($score, $cert, $reason, $worktime, $added, $profilingres = NULL)
	{
    	$q = "/*maxtime=10*/UPDATE posts_meta set spamscore=?,spamcert=?,worktime=?,added=? where id=?";
		$pre = $this->db->prepare($q);
		if (!$pre || !$pre->execute(array(round($score*100), round($cert*100),  round($worktime*1000), $added, $this->stored_id))) return false;


  		$q = "/*maxtime=10*/UPDATE posts_data set spamreason=?,profiling=? WHERE id=?";
		$pre = $this->db->prepare($q);
		if (!$pre || !$pre->execute(array($reason,$profilingres,$this->stored_id))) return false;

		return true;
	}
}


class Server
{
	private $db, $services, $config;

	function __construct(array $config, ISblamServices $services)
	{
		$this->db = $services->getDB();
		$this->config = $config;
		$this->services = $services;
	}

	static function getDefaultConfig($configfile = 'config.ini')
    {
	$ini = @parse_ini_file($configfile,true);
	if (!$ini) throw new Exception("Unable to read config file $configfile");
	return $ini;
    }

	function process(ServerRequest $req)
	{
	    $starttime = microtime(true);

		$data = $req->getData();

		if ($data['ip'] == '127.0.0.1' && $req->isDefaultAccount())
		{
			throw new ServerException('Brak klucza API',403);
		}

		$fs = isset($data['fields'])?explode("\n",strtolower($data['fields'])):array();

		$postdata = array();
		foreach($data as $key => $val)
		{
			if (substr($key,0,5) === 'POST_')
				$postdata[strtolower(substr($key,5))] = $val;

			if (substr($key,0,6) === 'field_')
				$fs[substr($key,6)] = $val;
		}

		list($content, $author, $email, $url) = $this->findFields($postdata, $fs);


		/* short-circuit filtering for testing */
		if (preg_match('!^[^a-z]*to\s+jest\s+test\s+(sblam|spam)[ua]?[^a-z]*$!i',$content))
		{
			$req->returnResult(1);
			return;
		}

        $p = $this->postFromFields($data, $postdata, $content, $author, $email, $url, $req->getIPs());

		if (!$req->storeData($p))
		{
			dieerr(500,"Awaria bazy danych");
		}

        $config = $req->customizeConfig($this->config);

		$sblam = new Sblam($config, $this->services);

		$rawresult = $sblam->testPost($p);
		list($score,$cert,$reason) = $rawresult;

		$endtime = microtime(true);

		if ($content == '' && $author=='') {$req->returnResult(1);}
		else
		if ($cert < 0.45 || abs($score) < 0.38) // .35 .22 is enough
		{
			$req->returnResult( ($score>0)?1:-1 );
		}
		else
		{
			$req->returnResult( ($score>0)?2:-2 );
		}

		set_time_limit(25);
		$rawresult = $sblam->reportResult($p, $rawresult);

        $req->storeResult($score, $cert, $reason, $endtime - $starttime, empty($p->bayesadded)?0:6, isset($rawresult[3]) ? Sblam::formatProfiling($rawresult[3]) : '');
	}

	private function postFromFields(array $data, array $postdata, $content, $author, $email, $url, $ips)
	{
		$p = new SblamPost($content, $author, $email, $url, $ips);
		$p->setHeaders($data);
		$p->setTime($data['time']);
		$p->setPath($data['uri']);
		$p->setPost($postdata);
		$p->setInstallId($data['uid']);
		return $p;
	}

	/** find which fields contain msg/author/e-mail */
	private function findFields(array &$postdata, array $fs)
	{
		/* @todo if any field name is given, stop guessing! and change this list of prefixes to regexp */
		$prefix = array('','comment_','post_','comment','osly','wp_','d_','shout_','n_','f_');

		$contentlabels = array('content','message','comment','commentcontent','tresc','msg','cont','koment','text','wpis','tekst','mtxMessage','txt','opis','post','trescpostu','rozsztxt','body','komentarz','wiadomosc','description','cfQuestion');
		if (!empty($fs[0])) $contentlabels = array_merge(explode('|',$fs[0]), $contentlabels);
		$content = $this->findField($postdata, $contentlabels, $prefix);

		$authorlabels = array( 'name','nick','nickname','pseudo','author','autor','by','podpis','kto','postedBy','postedby','imie','username','user','login','commentauthor','aut','imie_nazwisko');
		if (!empty($fs[1])) $authorlabels = array_merge(explode('|',$fs[1]), $authorlabels);
		$author = $this->findField($postdata, $authorlabels, $prefix);

		$emaillabels = array('email','mail','emil','eml','e-mail','mejl','emejl','imejl','commentemail','adres_email','txtEmail');
		if (!empty($fs[2])) $emaillabels = array_merge(explode('|',$fs[2]), $emaillabels);
		$email = $this->findField($postdata, $emaillabels, $prefix);

		$urllabels = array('url','uri','strona','www','adres','stronawww','adresstrony','website','site','adres_strony','txtUrl');
		if (!empty($fs[3])) $urllabels = array_merge(explode('|',$fs[3]), $urllabels);
		$url = $this->findField($postdata, $urllabels, $prefix);

		/* decide which additional posted fields are worth checking */
		$extradata = '';
		foreach($postdata as $key => $extra)
		{
			if ((preg_match('![^ ] |<|&lt|http://!i',$extra) || strlen($extra)>12 || preg_match('!^(?:aim|msn|yim|location|occupation|interests|signature)$!',$key))
			&& !preg_match('!submit|password|haslo|preview|key$|id$|^pass|token|bbcode|^sess|dateformat|mode$|helpbox|topic|click|attach|akcja|return|^sc\d+$!',$key))
				$extradata .= "\n[:".substr($key,0,2).":] ".$extra;
		}

		// PHPBB registration will require analyzing all fields
		if (isset($postdata['mode']) && $postdata['mode'] === 'register')
		{
			$content .= $extradata; $extradata = '';
		}

		if ($content === NULL) {$content = $extradata; $extradata = NULL;}

		// some clients submit already-escaped content, flooding service with entities.
		$nonnl2br = str_replace("<br />","\n",$content);
		if (false === strpos($nonnl2br,'<') && preg_match('/&(?:lt|gt|amp|quot|shy|nbsp);/',$nonnl2br))
		{
			$content = html_entity_decode($nonnl2br,ENT_QUOTES,"UTF-8");
		}

		return array($content, $author, $email, $url);
	}

	private function findField(array &$postdata, array $names, array $prefixes)
	{
		foreach($prefixes as $prefix)
		{
			foreach($names as $name)
			{
				if ($name === NULL) continue;
				if (array_key_exists($prefix.$name,$postdata))
				{
				//	d("found data in $prefix.$name");
					$res = $postdata[$prefix.$name];
					unset($postdata[$prefix.$name]);
					return $res;
				}
			}
		}
		return NULL;
	}
}
