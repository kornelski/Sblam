<?php

require "admin/bayesinfo.php";


class BayeslinksPage extends BayesinfoPage
{
    protected $prefix = 'links';

    function kill($url)
    {
        if (!preg_match('!^(https?:)?//!i',$url)) $url = 'http://'.$url;

        // MESS

        $this->getSblam(); // init tlds
        $spamverts = new SblamTestSpamvertises(array(), $this->services);

        $linkstoadd = array();

    	if (false !== strpos($url,'@'))
    	{
    		d('adding email');
    		$spamverts->addEmail($linkstoadd,$url);
    	}
    	else
    	{
        	$spamverts->addURI($linkstoadd, new SblamURI($url),''); // split subdomains, etc.
    	}

        $linkstoadd = array_keys($linkstoadd);

        $bayesbase = $this->getBayesStats();

        $res = array(
            'title'=>'Banned domains',
            'result'=>$bayesbase->banWords($linkstoadd),
            'linksadded'=>$linkstoadd,
        );
        return $res;
    }
}
