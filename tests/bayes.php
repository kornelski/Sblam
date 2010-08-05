<?php

require_once "class/bayesbase.php";

class SblamTestBayes extends SblamTestPost
{
	protected $db;
	protected $add;

	function __construct(array $settings, ISblamServices $services)
	{
        parent::__construct($settings, $services);

		$this->add = !empty($settings['add']);
		$tableprefix = !isset($settings['prefix'])?'bayes':$settings['prefix'];
		$ignorefile = !isset($settings['ignore'])?'data/bayesignore.txt':$settings['ignore'];

		$this->db = new BayesBase($this->services->getDB(), $tableprefix, $ignorefile, $this->add ? 0.2 : 0); // FIXME: hardcoded 0.3
	}

	function reportResult(ISblamPost $p, $score, $cert, $force=false)
	{
		if ($force || ($this->add && abs($score) > 1.2 && $cert > 0.85))
		{
			if (!$force)
			{
				if (function_exists('apc_add') && !apc_add('spambayesaddlock',1,1))
				{
					warn('skipping add due to apc lock');
					return;
				}

				$load = sys_getloadavg();
				if ($load[0]>1)
				{
					warn('skipping add due to load ' . $load[0] . '/'. $load[2]);
					return;
				}
			}

			$p->bayesadded = 1;
			$this->addPost($p, $score > 0);
		}
	}

	function testPost(ISblamPost $p)
	{
		$spammiestword = ''; $spammiestwordnudge = 0;

		// test usual post content
		$postwords = $this->extractWordsFromPost($p);
		list($score,$cert, $newword, $newscore) = $this->db->testWords($postwords);
		if ($newscore > $spammiestwordnudge) {$newscore = $spammiestwordnudge; $spammiestword = $newword;}

		// test post content with signature
		if ($sig = $p->getSignature())
		{
			$words = array_merge($postwords, self::extractWords($sig, $this->db->ignore));
			list($score3, $cert3, $newword, $newscore) = $this->db->testWords($words);
			if ($newscore > $spammiestwordnudge) {$newscore = $spammiestwordnudge; $spammiestword = $newword;}

			// and use signature only if it's spammy
			if ($score3 > $score)
			{
				//d("bayes: signature is spammy");
				$score = ($score3*2 + $score)/3 + 0.1;
				$cert = ($cert3*2 + $cert)/3;
			}
		}

		list($score2,$cert2, $newword, $newscore) = $this->db->testWords($this->extractWordsFromLinks($p));
		if ($newscore > $spammiestwordnudge) {$newscore = $spammiestwordnudge; $spammiestword = $newword;}

		// if link labels are spammier, use that score (protects against stuffing innocent content + spammy link)
		if (count($postwords) > 2 && $cert2 > 0.5 && $score2 > 0.4 && abs($cert2*$score2) > abs($cert*$score))
		{
			//d("bayes: link labels are spammier");
			$score = ($score2*2 + $score)/3 + 0.1;
			$cert = ($cert2*2 + $cert)/3;
		}


		if ($score < -0.8) $score = ($score+0.8)/2-0.8;
		elseif ($score > 0.8) $score = ($score-0.8)/2+0.8;
		if ($score < -1.2) $score = ($score+1.2)/3-1.2;
		elseif ($score > 1.2) $score = ($score-1.2)/3+1.2;

		$scorecert = round((abs($score*$cert) + abs($score))/2,1);

		if ($score < 0) $score *= 0.8;

		if (abs($score) > 0.1 && $cert > 0.2) return array($score, ($cert + self::CERTAINITY_NORMAL)/2 , $score>0?"Bayesian filter spam ($scorecert $spammiestword)":"Bayesian filter ham ($scorecert $spammiestword)");
		return NULL;
	}

	function addPost(ISblamPost $p, $isspam)
	{
		/** @todo add signature as well, but only if its spammy */
		return $this->db->add($this->extractWordsFromPost($p),$isspam);
	}

	function addText($txt, $isspam, $howmuch=1)
	{
		$this->db->add(self::extractWords($txt, $this->db->ignore),$isspam,$howmuch);
	}

	function testText($txt)
	{
		return $this->db->testWords(self::extractWords($txt, $this->db->ignore));
	}

	protected function extractWordsFromLinks(ISblamPost $p)
	{
		// test link labels specifically
		$labels = '';
		foreach($p->getLinks() as $link)
		{
			$labels .= ' '.$link->getLabel();
		}

		return self::extractWords($labels, $this->db->ignore);
	}

	protected function extractWordsFromPost(ISblamPost $p)
	{
		// get both raw and stripped text, to find more phrases (word count doesn't matter)
		$txt = $p->getRawContent().' '.rawurldecode($p->getText()).' '.$p->getAuthorName().' '.$p->getAuthorEmail().' '.$p->getAuthorURI();
		return self::extractWords($txt, $this->db->ignore);
	}

	protected static function splitStringUnicode($words)
	{
		$words = preg_replace(array("![\t\n\r]+!", // all other low ascii characters are removed
		'![\p{Cc}\p{Cf}\p{Cn}\p{Co}\p{Mn}\p{Me}\p{Lm}]!u', // remove all modifiers, private/reserved chars
		'![\p{Lo}\p{So}]{3}!u' // split CJK characters (unfortunately preg_split is used to remove 1- and 2-letter 'words', so they're made groups of 3)
		),array(' ','',' \0 '),$words);


		if (function_exists('mb_strtolower'))
		{
			$words = mb_strtolower($words,'UTF-8');
		}
		else
		{
			$words = strtolower($words);
		}

			return preg_split("![^a-z0-9\pN\pL]+(?:..?[^a-z0-9\pN\pL]+)*!u",$words,NULL,PREG_SPLIT_NO_EMPTY);
	}

	static function extractWords($words, array $ignore = array())
	{
		$words = self::splitStringUnicode($words);

		$c = count($words); if (!$c) return array();

		$tmp = array($words[0] => true);
		for($i=1;$i<$c;$i++)
		{
			$tmp[ $words[$i] ] = true;
			$tmp[ $words[$i-1].' '.$words[$i] ] = true;
		}
		$words = NULL;

		$final = array();
		foreach($tmp as $v => $ignore)
		{
			if (strlen($v) >= 2 && preg_match('![a-z\pL]!u',$v) && !isset($ignore[$v]))
			{
				$final[] = $v;
		    }
		}
		return $final;
	}

	static function info()
	{
		return array(
			'name'=>'Bayesian Filter',
			'desc'=>'Auto-learning filter judges by looking for words and phrases seen in spam/ham',
			'remote'=>false,
		);
	}
}

