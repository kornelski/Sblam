<?php

ini_set("zlib.output_handler",0);

require_once "class/sblam.php";
require_once "class/sblampost.php";
require_once "class/sblamtest.php";
require_once "tests/bayes.php";
require_once "tests/spamvertises.php";

class BayesaddPage extends AdminPage
{
	function index()
	{
		return array('title'=>'Add to bayes base');
	}

	function post_index()
	{
		$sblam = $this->getSblam();
		$bayes = new SblamBayes(array());
		$spamverts = new SBlamSpamvertises(array());

		$isspam = NULL;
		if (!empty($_POST['ham'])) $isspam = false;
		else if (!empty($_POST['spam'])) $isspam = true;

		$addtext = empty($_POST['nowords']);
		$linkstoadd = array();

		$spamvertresult = NULL;
		$bayesresult = NULL;

		if (!empty($_POST['stuff']) && NULL !== $isspam)
		{
			if ($addtext)
			{
				$bayes->addText($_POST['stuff'], $isspam, (int)$_POST['howmuch']);
			}

			if (preg_match_all('@(?:https?://|www\.)([a-z0-9.-]+\.[a-z]{2,4}(?:/[^\s]{1,15})?)@',$_POST['stuff'],$links))
			{

				foreach($links[0] as &$l) if (!preg_match('@^https?://@',$l)) $l = 'http://'.$l;

				$spamverts->addURIs($links[0], $isspam, (int)$_POST['howmuch']);
				$spamvertresult = $spamverts->testURIs($links[0]);
			}
			else d("no links found");
		}

		if (isset($_POST['stuff']) && $addtext)
		{
			$bayesresult = $bayes->testText($_POST['stuff']);
		}
		else $bayesresult = NULL;

		return array(
			'title'=>'Added to bayes base',
			'isspam'=>$isspam,
			'addtext'=>$addtext,
			'linksadded'=>$linkstoadd,
			'spamvertresult'=>$spamvertresult,
			'bayesresult'=>$bayesresult,
		);
	}
}
