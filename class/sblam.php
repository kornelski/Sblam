<?php

require_once "class/interfaces.php";

require_once "class/sblamtest.php";
require_once "class/sblamuri.php";

class Sblam implements ISblam
{

	function __construct(array $config)
	{
		$this->readConfig($config);
	}

	protected function readConfig(array $ini)
	{
		if (!empty($ini['tlds'])) SblamURI::init($ini['tlds'], sblambaseconnect()); else warn('tlds not given!');
		if (!empty($ini['dns'])) AsyncDNS::init(preg_split('![\s,]+!',$ini['dns'],NULL,PREG_SPLIT_NO_EMPTY)); else warn('dns not given!');

		foreach($ini as $name => $settings)
		{
			if (!is_array($settings)) continue;

			if (!empty($settings['disabled']) || (isset($settings['enabled']) && !$settings['enabled'])) {/*d($name,'disabled');*/continue;}

			try {
				include_once "tests/".strtolower($name).".php";
				$classname = "SblamTest".ucfirst($name);
				if (!class_exists($classname)) warn($name,"Problem loading test plugin");

				$info = call_user_func(array($classname,'info'));
				if (!empty($info['remote']) && isset($ini['remote']) && !$ini['remote']) {d($info,'Its a remote service, remote disabled, skipping'); continue;}
				if (!empty($info['unsupported'])) {d($info,'unsupported in this configuration'); continue;}

				//d($settings,'instantiatin '.$classname);
				$test = new $classname($settings);

				if (!$test instanceof ISblamTest) {warn($test,'Not a test');continue;}
				//d($classname,"instantiated");
				$this->addTest($test, isset($settings['phase'])? $settings['phase']:10);
			}
			catch(Exception $e)
			{
				warn($e,"Failed to initialize plugin $name");
			}
		}


		return true;
	}

	protected $testPhases;
	function addTest(ISblamTest $t, $phase)
	{
		$this->testPhases[$phase][] = $t;
		$t->setManager($this);
		d(get_class($t),"added to $phase");
	}

    const EARLY_ESCAPE_LIMIT = 2.5; // maximum score

	function testPost(ISblamPost $p)
	{
		if (!$this->testPhases) return array(0,0,"No tests");

		$profiling = array(); $asyncpolltime = 0;
		$results = array();	$totalspam=0;

		ksort($this->testPhases,SORT_NUMERIC);
		foreach($this->testPhases as $phase => $phaseTests)
		{
		    foreach($phaseTests as $test)
    		{
    			if (!$test instanceof ISblamTestPost) continue;

    			$start = microtime(true);
    			$test->preTestPost($p);
    			$profiling["p$phase:".get_class($test)] = (microtime(true)-$start);
    		}

    		foreach($phaseTests as $test)
    		{
    			if (!$test instanceof ISblamTestPost) continue;

    			$start = microtime(true);
    			AsyncSocket::poll(0); // get those queued DNS queries
    			$asyncpolltime += microtime(true)-$start;

    			$start = microtime(true);
    			$tmpres = $test->testPost($p);
    			$profiling["t$phase:".get_class($test)] = (microtime(true)-$start);

    			$results[] = $tmpres;
    			if ($tmpres && is_numeric($tmpres[0])) $totalspam += $tmpres[0];
    			if ($totalspam > self::EARLY_ESCAPE_LIMIT)
    			{
    				$results[] = array(6,1,"Early escape",$profiling);
    				break 2;
    			}
    		}
        }

        $profiling['tst:AsyncSocket'] = $asyncpolltime;
		$results = $this->sumResults($results);
		$results[2] = implode('; ',$results[2]);
		$results[3] = $profiling;
		return $results;
	}

	function reportResult(ISblamPost $p, array $results, $force=false)
	{
		$profiling = array();
		foreach($this->testPhases as $phaseTests)
		foreach($phaseTests as $test)
		{
			if (!$test instanceof ISblamTestPost) continue;

			$start = microtime(true);
			$test->reportResult($p, $results[0], $results[1], $force);
			$profiling['rep:'.get_class($test)] = microtime(true) - $start;
		}
		if (isset($results[3]) && is_array($results[3])) $results[3] = array_merge($results[3],$profiling); else $results[3] = $profiling;
		return $results;
	}

	function testTrackback(ISblamTrackback $p)
	{
		$results = array();
		foreach($this->testPhases as $phaseTests)
		foreach($phaseTests as $test)
		{
			if (!$test instanceof ISblamTestTrackback) continue;
			$results[] = $test->testTrackback($p);
		}
		return $this->sumResults($results);
	}


	static function sumResults($results)
	{
		$probHam=0;
		$probSpam=0;
		$certHam=0;
		$certSpam=0;

		$names = array();

		foreach($results as $r)
		{
			if (!is_array($r) || !count($r)) continue;
			if (is_array($r[0])) {$r = self::sumResults($r); d($r,'got result from parent');}

		  if (!empty($r[2])) {
				if (is_array($r[2])) $names = array_merge($names,$r[2]);
				else $names[] = $r[2];
			}

			if ($r[0] < 0) {
				$probHam -= $r[0];
				$certHam -= $r[0] * $r[1];
			}
			else
			{
				$probSpam += $r[0];
				$certSpam += $r[0] * $r[1];
			}
		}

		d("sum is: ham $probHam with $certHam cert, spam $probSpam with $certSpam cert - tested ".implode(';',$names));

		$larger = max($certHam,$certSpam);
		$smaller = min($certHam,$certSpam);

		if ($larger)
		{
			// high certainity for ham and spam should cancel each other (smaller/larger).
			// if both were low, don't increase them (min).
			// if certainity was huge, preserve at least a bit of it ($larger/10)
			$endcert = ($larger/10) + min($larger,1-($smaller/$larger));
		}
		else $endcert=0;

		if (abs($certSpam+$certHam) < 0.01) return array(0,0,$names);

		return array(  (-$probHam*$certHam + $probSpam*$certSpam) / ($certSpam+$certHam),$endcert,$names);
	}

	public static function formatProfiling(array $profiling)
	{
	    $profilingres = '';
		arsort($profiling); $limit = 10;
		foreach($profiling as $k => $v)
		{
		    $profilingres .= sprintf("% 5d %s\n",$v*1000,$k);
		    if (!$limit-- || $v < 0.001) break;
	    }
        return $profilingres;
    }

	/**
	 *	return ID that is unique to this server/installation
	*/
	static function getInstallationID()
	{
		return @md5(ini_get('extension_dir') . phpversion() . $_SERVER['HTTP_HOST'] . $_SERVER['SERVER_SOFTWARE'] . __FILE__);
	}
}
