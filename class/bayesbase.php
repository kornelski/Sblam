<?php

require_once "class/interfaces.php";

class BayesBase
{
	protected $db;
	public $ignore = array(); // @todo: public = hack
	protected $table, $translateprob;

	/**
	 * @param tableprefix - common prefix of tables that this instance will use
	 * @param ignorefile - path to file with phrases to ignore, one phrase per line
	 * @param translateprob - probabilty that words will be added to translation table (not needed for filtering, just for your information). 1 = add everything, 0 = add nothing
	 */
	function __construct(PDO $db, $tableprefix = 'bayes', $ignorefile = 'data/bayesignore.txt', $translateprob = 0.3)
	{
		$this->table = $tableprefix;
		$this->translateprob = $translateprob;

		if ( $ignorefile !== NULL)
		{
		  if ($file = @file($ignorefile))
			{
				foreach($file as $line)
				{
					$this->ignore[trim($line)] = true;
				}
			}
			else throw new Exception("Unable to load file with bayes ignored phrases $ignorefile");
		}

		$this->db = $db;
	}

	private function addToTranslate(array $words, array &$hashes)
	{
	    $numwords = count($words);

    	$q = "/*maxtime15*/INSERT ignore into {$this->table}translate (wordh,word) values"
		   . str_repeat('(unhex(?),?),',$numwords-1)."(unhex(?),?)";

		for($i=0; $i<$numwords; $i++)
		{
			$hashes[$i*2 + 1] = $words[$i];
		}

		try{
			$this->db->prepareExecute($q,$hashes);
		}
		catch(Exception $e){}

    }

	function add(array $words, $isspam, $howmuch=1)
	{
		$numwords = count($words);
		if (!$numwords) return true;

		$hashes = $this->hashWords($words, true);

		if (!$this->addHashes($hashes,$isspam,$howmuch)) return false;

		if ((rand()&1023) < 1024*$this->translateprob)
		{
		    $this->addToTranslate($words,$hashes);
		}
		return true;
	}

	private function addHashes(array $hashes, $isspam, $howmuch)
	{
	    $this->db->beginTransaction();
	    try
	    {
		   $colname = $isspam?"spam":"ham";
		   $q = "/*maxtime20*/INSERT into {$this->table}wordsh (wordh,flags,".($isspam?'spam,ham':'ham,spam').") values"
		   . str_repeat("(unhex(?),?,$howmuch,0),",count($hashes)/2-1)."(unhex(?),?,$howmuch,0) on duplicate key update added = now(), $colname = $colname+$howmuch";

			$this->db->prepareExecute($q,$hashes);
		   if (!$this->db->exec("/*maxtime10*/UPDATE {$this->table}total set total$colname = total$colname + $howmuch")) throw new Exception("Can't increase counters");
		  $this->db->commit();
	   }
	   catch(Exception $e)
	   {
	       $this->db->rollBack();
	       throw $e;
       }
	   return true;
	}

	function getTotalWords()
	{
		$total = $this->db->query("/*maxtime20*/SELECT count(*) as `cnt` from {$this->table}wordsh"); if (!$total) return;
		$total = $total->fetchAll(PDO::FETCH_ASSOC);  if (!count($total)) {return;}
		return $total[0]['cnt'];
	}


	function getTotalPosts()
	{
		$total = $this->db->query("/*maxtime=2*/SELECT totalspam,totalham from {$this->table}total"); if (!$total) return;
		$total = $total->fetchAll(PDO::FETCH_ASSOC); if (!count($total)) {return;}
		$totalspam = $total[0]['totalspam'];
		$totalham = $total[0]['totalham'];
		return array($totalspam, $totalham);
	}

	function getWordList(array $hashes)
	{
		$q = "/*maxtime=15*/SELECT hex(wordh) as wordh,spam,ham,flags from {$this->table}wordsh where wordh in (".str_repeat('unhex(?),',count($hashes)-1)."unhex(?))";

		$statement = $this->db->prepare($q);
		if (!$statement || !$statement->execute($hashes)) {return NULL; }

		return $statement;
	}

	static $dictionary = array();

	function hashWords(array $words, $addflags = false)
	{
		$hashes = array();
		foreach($words as $w)
		{
			$h = md5('~$'.$w);
			$hashes[] = $h;
			if ($addflags) $hashes[] = (false === strpos($w,' '))?0:1; // mark which ones are phrases
			else self::$dictionary[strtolower($h)] = $w;
		}
		return $hashes;
	}

	protected function hashToWord($hash)
	{
		if (isset(self::$dictionary[strtolower($hash)])) return 'h:'.self::$dictionary[strtolower($hash)];

		$sta = $this->db->prepare("/*maxtime=2*/SELECT word FROM {$this->table}translate WHERE wordh=unhex(?) LIMIT 1");

		if ($sta && $sta->execute(array($hash))) foreach($sta as $word) return 'H:'.$word['word'];
		return '?'.$hash;
	}

    function getWordListFisherSum(array $hashes, $totalspam, $totalham)
    {
        $q = "SELECT
	        count(*) as numfoundwords,
            -2 * sum(log((0.25 + (ham+spam) * ((spam/$totalspam) / (spam/$totalspam + ham/$totalham))) / (0.5 + ham+spam))) as fisherspam,
            -2 * sum(log(1 - (0.25 + (ham+spam) * ((spam/$totalspam) / (spam/$totalspam + ham/$totalham))) / (0.5 + ham+spam))) as fisherham
	        FROM {$this->table}wordsh WHERE wordh IN (".str_repeat('unhex(?),',count($hashes)-1)."unhex(?))";

		$statement = $this->db->prepare($q);
		if (!$statement || !$statement->execute($hashes)) {return NULL; }

		$res = $statement->fetchAll(PDO::FETCH_ASSOC);

		return array($res[0]['fisherspam'],$res[0]['fisherham'],$res[0]['numfoundwords']);
    }


	function testWordsChiSquare(array $words)
	{
	    $numwords = count($words);
	    if (!$numwords) return;

	    list($totalspam, $totalham) = $this->getTotalPosts(); if (!$totalham || !$totalspam) {return;}

		list($fisherspam ,$fisherham, $numfoundwords ) = $this->getWordListFisherSum($this->hashWords($words), $totalspam, $totalham);

        $probham = self::inverseChiSquareProb($fisherham, 2*$numfoundwords);
        $probspam = self::inverseChiSquareProb($fisherspam, 2*$numfoundwords);

        $cert = abs($probspam - $probham);

        if ($cert < 0.26) return array(0,$cert);

        return array(2*$probspam - 1.7*$probham, $cert);
    }

    static function inverseChiSquareProb($chi,$df)
    {
            $m = $chi / 2.0;
            $sum = $term = exp(-$m);

            for($i=1; $i <= $df/2; $i++)
            {
                $term *= $m / $i;
                $sum += $term;
            }
            return min($sum, 1.0);
    }

	function testWords(array $words)
	{
	    return $this->testWordsPornelsWay($words);//testWordsChiSquare($words);
    }

	function testWordsPornelsWay(array $words)
	{
		$numwords = count($words);
		if (!$numwords) {return;}

		list($totalspam, $totalham) = $this->getTotalPosts(); if (!$totalham || !$totalspam) {return;}
		$totalspam /= 2; $totalham /= 2; // these are too inflated!

		$wordlist = $this->getWordList($this->hashWords($words)); if (!$wordlist) return;
		$words=NULL;

		$judge = 0;

		$spammiestwordh = '';
		$spammiestwordnudge = 0;

		$pos =0;
		$neg =0;
		$realnumwords = 0;
		foreach($wordlist as $r)
		{
			$realnumwords++;

			// make spam/ham in range 0-100
			$spam = min(100, $r['spam'] / ($totalspam/100));
			$ham = min(100, $r['ham']  / ($totalham/100));

			// and now make it 1-150 with nonlinear skew
			$spam += 5*sqrt($spam);
			$ham += 5*sqrt($ham);

			if (($spam+$ham)>1 && abs($spam-$ham)<0.3) continue; // noise?

			$bonus = 2;
			// extra penalty for very spammy words
			if ($spam > 7 && $ham < $spam / 11) { $ham /= 2; $spam = 150-(150-$spam)*0.8; $bonus += 5;}

			if ($r['ham']<1 && $r['spam']>20) $bonus += min(10,2+$spam); // totally spammy words
			if ($r['flags']&1) $bonus += 5 + $bonus/2; // matching phrases is more precise, score higher

			$strong = (abs($spam - $ham)+ $bonus)/(140+$bonus);

			$spamness = $spam/($ham+$spam);

			$nudge = ((1-$strong)/2 + $spamness*$strong) - 0.5; // weak (unpopular or uncertain) words weighted towards 0

			if ($nudge > $spammiestwordnudge) {$spammiestwordnudge = $nudge; $spammiestwordh = $r['wordh'];} // just for fun

			if ($nudge > 0) $pos += $nudge; else $neg -= $nudge;

			$judge += $nudge;

		}
		// numwords is number words in input, realnumwords is number of words in output (sometimes much lower) - have some penalty for unknown words
		$numwords = ($numwords + $realnumwords)/2;

		// boost spam score here to make score uncertain if something is fishy
		$certainity = max($pos,$neg)>0.001?(1-min($pos*1.5,$neg)/max($pos*1.5,$neg)):0;

		$final = ($judge*31 + $judge*48*$certainity)/($numwords+2);

		return array($final,$certainity, $this->hashToWord($spammiestwordh), $spammiestwordnudge);
	}
}
