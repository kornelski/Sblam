<?php

class SblamNetworks extends SblamTestPost
{
	protected $whitelist,$blacklist,$isps;
	function __construct(array $settings)
	{
		$whitelist = isset($settings['whitelist'])?$settings['whitelist']:"pl uk";
		$blacklist = isset($settings['blacklist'])?$settings['blacklist']:"cn ru kr jp ca br hk tw th biz my ni vn mx invalid arpa";
		$isps = @file_get_contents( isset($settings['spamisp'])?$settings['spamisp']:"data/blockisp.txt" );

		if (!$isps) throw new Exception("Unable to load file with banned ISPs");

		$this->whitelist = trim(preg_replace('/[^a-z]+/','|',trim($whitelist)),"|");
		$this->blacklist = trim(preg_replace('/[^a-z]+/','|',trim($blacklist)),"|");
		$this->isps = trim(preg_replace('/[^a-z0-9.]+/','|',trim($isps)),"|");
	}

	function preTestPost(ISblamPost $p)
	{
		foreach($p->getAuthorIPs() as $ip)
		{
			SblamURI::gethostbyaddrasync($ip);
		}
	}

	function testPost(ISblamPost $p)
	{
		// whitelist only direct connection (because other can be forged) and only when there aren't any objectionable hosts there
		$out = array();
		$firstIP = true;
		$whitelisted = false;
		foreach($p->getAuthorIPs() as $ip)
		{
			$rev = SblamURI::gethostbyaddr($ip);
			if (!$rev)  continue;
			if (is_array($rev)) {warn($rev,'gethostbyaddr returned array');$rev = reset($rev);} // WTF?

			if (preg_match('!(?:\.|^)(?:'.$this->isps.')$!',$rev)) $out[] = array(0.5, self::CERTAINITY_LOW, "Sent from blacklisted ISP ($rev)");
			else if ($firstIP && preg_match('!\.(?:'.$this->whitelist.')$!',$rev)) $whitelisted = true;
			else if (preg_match('!\.(?:'.$this->blacklist.')$!',$rev)) $out[] = array(0.35, self::CERTAINITY_LOW, "Sent from blacklisted TLD ($rev)");

			$firstIP = false;
		}

		if (!count($out) && $whitelisted) return array(-0.25, self::CERTAINITY_LOW, "Sent from whitelisted TLD ($rev)");
	    if (count($out)) return $out;
	}

	static function info()
	{
		return array(
			'name'=>'Sender\'s network (country/ISP)',
			'desc'=>'Marks posts sent from suspicious networks, suspicious.',
			'remote'=>false,
		);
	}
}
