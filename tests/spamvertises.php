<?php

require_once "class/bayesbase.php";

class SBlamSpamvertises extends SblamTestPost
{
	protected $db;
	
	protected $add;
	
	function __construct(array $settings)
	{
		$this->add = !empty($settings['add']);
		$tableprefix = !isset($settings['prefix'])?'links':$settings['prefix'];
		$ignorefile = !isset($settings['ignore'])?'data/spamvertignore.txt':$settings['ignore'];
				
		$this->db = new BayesBase(sblambaseconnect(), 	$tableprefix, $ignorefile);
	}
	
	function reportResult(ISblamPost $p, $score, $cert)
	{
		if ($this->add && abs($score) > 0.6 && $cert > 0.8)//0.81)
		{
			$this->addPost($p, $score > 0); 
		}
	}


	function testPost(ISblamPost $p)
	{	
		$uris = $this->extractURIsFromPost($p);
		return $this->testURIs($uris);
	}
	
	function testURIs(array $uris)
	{
		if (!count($uris)) {return NULL;}
		
		list($totalspam, $totalham) = $this->db->getTotalPosts(); if (!$totalham || !$totalspam) {return;}
		$totalspam /= 10; $totalham /= 10; // totals are too inflated
		
		$wordlist = $this->db->getWordList($this->db->hashWords($uris)); if (!$wordlist || !count($wordlist)) {return;}
		$wordlist = $wordlist->fetchAll(PDO::FETCH_ASSOC);
		$spamness = 0;
		$maxspamness = 0;
		$spampop = 0; $hampop=0;
		foreach($wordlist as $r)
		{
			// make spam/ham in range 0-100
			$spam = min(100, $r['spam'] / ($totalspam/100));
			$ham = min(100, $r['ham']  / ($totalham/100));
			
			// and now make it 1-150 with nonlinear skew
			$spam += 5*sqrt($spam);
			$ham += 5*sqrt($ham);

			$spampop += max(0,$spam-$ham) * $spam/($ham+$spam) + ($ham<=0.001?min(max(1,$spam/2),3):0);
			$hampop += max(0,$ham-$spam) * $ham/($ham + 3*$spam);
			$spamness += $spam/($ham+$spam) - $ham/2/($ham+$spam);
			$maxspamness = max($maxspamness, $spam/($ham+$spam));
			$maxspamness = max($maxspamness,$spam/($ham+$spam));
		}

		$hampop = max(0, $hampop - $spampop);
		
		if ($hampop > 1 && $spamness < 0 && $maxspamness < 0.3)
		{
			$score = (($spamness - $hampop) / (count($wordlist)+1) * (0.3 - $maxspamness))/3;
			if ($score < -0.3) $score = max(-0.6, ($score+0.3)/2 - 0.3);
			return $score > -0.1 ? NULL : array($score/3, self::CERTAINITY_NORMAL - $maxspamness, "Clean websites (".round($score,2)." = ".round($spamness,2).", max ".round($maxspamness,2).")");
		}

		$maxspamness *= $maxspamness; // if in doubt, don't punish
		$maxspamness *= $maxspamness; // if in doubt, don't punish
		
		$score = (($spampop+3) * $maxspamness)/18;
		if ($score > 0.7) $score = ($score-0.7)/2+0.7;
		if ($score > 1.1) $score = ($score-1.1)/3+1.1;

		return $score < 0.1 ? NULL : array($score, $maxspamness * self::CERTAINITY_NORMAL ,"Spamvertised websites (".round($score,2)." = ".round($spamness,2).", max ".round($maxspamness,2).")");
	}
	
	function addPost(ISblamPost $p, $isspam, $howmuch = 1)
	{
		$this->db->add($this->extractURIsFromPost($p),$isspam, $howmuch);
	}
	
	function addURIs(array $links, $isspam, $howmuch = 1)
	{
		$parsed = array();
		foreach($links as $l)
		{
			try {
				$this->addURI($parsed, new SblamURI($l));
			}
			catch(Exception $e){warn($e);}
		}
		if (count($parsed)) 
		{
			return $this->db->add(array_keys($parsed), $isspam, $howmuch);
	}
		return false;
	}
	
	function addURI(array &$urls, SblamURI $link, $prefix = '')
	{	
	  if ($link->isTLD()) {return;}
	  		  		  
		if ($hostname = $link->getHostname())
		{		  
		  $hostname = preg_replace(array('!^www\.!','!\d\d+!'),array('','D'),$hostname);	// normalise digits! (block bulk registrations)		
			$urls[$prefix.$hostname] = true;

  		if ($domain = $link->getDomain())
  		{
  		  $urls[$prefix.$domain] = true;  		  
		  }

			if ($p = $link->getPath())
			{
				$p = preg_replace('!^(/[^#]{1,7}[^#/\?]{0,5}).*$!','\1',$p); // shorten path. its mainly for getting real tinyurl adresses, not every spammy subpage out there
				if ($p !== '/')	$urls[$prefix.$hostname . $p] = true;
			}
		}

		if (preg_match('!\b(?:site:|https?://)([a-zA-Z0-9.-]+)!',urldecode($link->getPath()),$m))
		{
			$this->addURI($urls, new SblamURI('http://'.$m[1]), $prefix);
		}
	}

	function addEmail(array &$uris, $email)
	{
		// adds e-mail in "@example.com" format.
		if (preg_match('/([^\s:\/#@!;]+)@([a-z0-9.-]+\.[a-z]{2,6})/',strtolower($email),$r))
		{
			$r = array(new SblamURI('http://'.$r[2].'/'), $r[1]); // it's a hack to re-use SblamURI logic
			$this->addURI($uris, $r[0], '@');
			$this->addURI($uris, $r[0], preg_replace('/\d+/','D',$r[1]).'@');
			return true;
		}
		return false;
	}

	protected function extractURIsFromPost(ISBlamPost $p)
	{
		$uris = array();
		if ($uri = $p->getAuthorURI())
		{
			$this->addURI($uris, new SblamURI($uri));
		}
		foreach($p->getLinks() as $link)
		{
			$this->addURI($uris,$link);
		}
		$this->addEmail($uris, $p->getAuthorEmail());
	
		return array_keys($uris);
	}
	
	
	static function info()
	{
		return array(
			'name'=>'Spamvertised links database',
			'desc'=>'Bayesian auto-learning filter for spamvertised domains',
			'remote'=>false,
		);
	}
}

