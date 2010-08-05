<?php

require_once dirname(__FILE__).'/dnsbl.php';

class SblamTestPHPHTTPBL extends SblamTestDNSBL
{
	protected $key;
	function __construct(array $settings, ISblamServices $services)
	{
        parent::__construct($settings, $services);

		$this->key = $settings['key'];
	}

	protected function reverse($ip)
	{
		return preg_replace('!(\d+)\.(\d+)\.(\d+)\.(\d+)!',$this->key.'.\4.\3.\2.\1.dnsbl.httpbl.org',$ip);
	}


	function score($ip, $resip, $reason, $scorefactor)
	{
		if (preg_match('!127\.(\d+)\.(\d+)\.(\d+)!', $resip, $m))
		{
			list(,$days,$threat,$type) = $m;

			if ($type == 0)  // search engine bots!? http:BL doesn't calculate threat/age for them
			{
				$score = 0.1;
				$cert = self::CERTAINITY_LOW;
			}
			else
			{
				$score = 0.1 + (255+$threat)/350 * 10/($days+9);

				if ($threat > 80) $cert = self::CERTAINITY_HIGH;
				elseif ($threat > 20) $cert = self::CERTAINITY_NORMAL;
				else $cert = self::CERTAINITY_LOW;

				if ($type & 4) $score = $score*1.2 + 0.1; // comment spammer
				if ($type & 1) $score += 0.1;
				if (!($type & 6)) $score /=2; // wtf? no type?
			}
			$score = min(1.5,$score);

			$typename = '';
			if ($type & 4) $typename .= 'C';
			if ($type & 2) $typename .= 'H';
			if ($type & 1) $typename .= '?';

			if ($score < 0.8) return NULL;
			return array($score/2 * $scorefactor, $cert, "HoneypotBL (".round($score,2).'*'.round($scorefactor,2)."; $ip = $typename, ^$threat, {$days}d old)");
		}
	}

	static function info()
	{
		return array(
			'name'=>'Project Honeypot DNS RBL',
			'desc'=>'Checks for banned IPs in Project Honeypot http:BL',
			'remote'=>true,
		);
	}
}

