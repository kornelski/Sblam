<?php

require_once "class/sblam.php";
require_once "class/sblambase.php";
//require_once "class/sblamphpbb.php";
require_once "class/sblamtest.php";
require_once "tests/bayes.php";
require_once "admin/postformatter.php";


class ScorePage extends AdminPage
{
	private function getUnmoderatedRange($base,$spam,$start,$end)
	{
		$lockkey = 'unmoderated_lock'.$spam;

		if (apc_fetch($lockkey) > time()) sleep(2);
		if (apc_fetch($lockkey) < time())
		{
			apc_store($lockkey,time()+10,10);
			$res= $base->getUnmoderatedRange($spam,$start,$end);
			apc_delete($lockkey);
			return $res;
		}
		return array();
	}

	function index($maxposts = 10)
	{
		$base = $this->getSblamBase();

		$spamqueue = array('spams'=>array(), 'hams'=>array(), 'page_template'=>'score','title'=>"Moderation");

		if ($posts = $this->getUnmoderatedRange($base,false,0,$maxposts))
		{
			$this->queue_prefetch($posts);
			$spamqueue['hams'] = $posts;
		}

		if ($posts = $this->getUnmoderatedRange($base,true,0,$maxposts))
		{
			$this->queue_prefetch($posts);
			$spamqueue['spams'] = $posts;
		}

		return $spamqueue;
	}

	function post_index()
	{
	    if (isset($_POST['bannedhams']))
	    {
	        $this->getPDO()->exec("UPDATE posts_meta SET spamcert = spamcert/10
	            WHERE manualspam is null and spamscore<20
	                AND exists(SELECT 1 FROM plonker f WHERE f.ip = posts_meta.ip AND spampoints > 10)
	            LIMIT 200");

	        return array('redirect'=>'score');
        }

	    if (isset($_POST['bannedspams']))
	    {
	        $this->getPDO()->exec("UPDATE posts_meta SET manualspam=1 WHERE manualspam is null
	            AND (spamscore between 250 and 1200)
	            AND exists(SELECT 1 from plonker f WHERE f.ip = posts_meta.ip and spampoints>100)
	            LIMIT 2000;");
            $this->getPDO()->exec("UPDATE posts_meta SET manualspam=1 WHERE manualspam is null
	            AND (spamscore > 1200)
	            AND exists(SELECT 1 from plonker f WHERE f.ip = posts_meta.ip)
	            LIMIT 4000;");

	        return array('redirect'=>'score');
        }
	    if (isset($_POST['bannedspamslite']))
	    {
	        $this->getPDO()->exec("UPDATE posts_meta SET spamcert = spamcert + 10 + spamcert/10 WHERE manualspam is null
	            AND (spamscore between 1 and 1200)
	            AND exists(SELECT 1 from plonker f WHERE f.ip = posts_meta.ip and spampoints>50)
	            LIMIT 2000;");

	        return array('redirect'=>'score');
		}

	    return $this->score($_POST['id'],isset($_POST['spam']));
	}

	function post_ham($id, $list, $count = 1)
	{
		return $this->score($id,false, $list,$count);
	}

	function post_spam($id, $list, $count = 1)
	{
		return $this->score($id,true, $list,$count);
	}

	protected function score($id, $isspam, $list = NULL, $count = 1)
	{
		$sbl = $this->getSblam();
		$base = $this->getSblamBase();

		$base->moderated($id, $isspam);
		$post = $base->getPostById($id);

		if ($post)
		{
			list($score) = $post->getSpamScore();

			if (!$post->bayesadded || (!$isspam && $score > 0) || ($isspam && $score < 0))
			{
				$sbl->reportResult($post, array($isspam?2:-2, 1, "moderated"), true);
				if ($post->bayesadded)
				{
					$base->saveTestResult($post);
				}
			}
		}

		if ($list !== NULL)
		{
  		$spam = $this->getUnmoderatedRange($base,$list == 'spam', 10, $count);
  		if (!$spam) throw new Exception("No posts");

		  return array('posts'=>$spam, 'page_template'=>'scorexml','content_type'=>'text/xml', 'layout_template'=>'');
	  }
	  return $this->index(2);
	}


    private function queue_prefetch(array $spams)
    {
    	foreach($spams as $spam)
    	{
    		foreach($spam->getAuthorIPs() as $ip)
    		{
    			SblamURI::gethostbyaddrasync($ip);
    		}
    	}
    }


    private function score_to_percent($score)
    {
    	return atan(abs($score*80)/60)*200/M_PI;
    }

}
