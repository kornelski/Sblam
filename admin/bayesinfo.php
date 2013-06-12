<?php

require_once "class/bayesbase.php";

class BayesinfoPage extends AdminPage
{
    protected $prefix = 'bayes';

    protected function getBayesStats()
    {
        return new BayesStats($this->services->getDB(),$this->prefix);
    }

    function index()
    {
        return $this->stats(false);
    }

    function email()
    {
        return $this->stats(true);
    }

    protected function stats($email = false, $limit = 200)
    {
        $b = $this->getBayesStats();

        if ($email) $b->extra_where = ' AND word like \'@%\'';

        $inf = array();
        list($totalspam,$totalham) = $b->getTotalPosts();
        $inf['total'] = array('spam'=>$totalspam, 'ham' => $totalham);
        $inf['totalwords'] = 999999;//$b->getTotalWords();

        $inf['recentham'] = $this->postproc($b->getRecentlyAddedHams($limit), $totalspam,$totalham);
        $inf['recentspam']= $this->postproc(array_values(array_reverse($b->getRecentlyAddedSpams($limit))), $totalspam,$totalham);
        $inf['recentmod'] = $this->postproc($b->getRecentlyModdedWords($limit), $totalspam,$totalham);
        $inf['spammiest'] = $this->postproc($b->getSpammiestWords($limit,$totalspam,$totalham), $totalspam,$totalham);
        $inf['hammiest']    = $this->postproc($b->getHammiestWords($limit,$totalspam,$totalham), $totalspam,$totalham);
        $inf['useless']     = $this->postproc($b->getUselessWords($limit,$totalspam,$totalham), $totalspam,$totalham);
        $inf['strongest'] = $this->postproc($b->getStrongestWords($limit,$totalspam,$totalham), $totalspam,$totalham);
        $inf['oldest']      = $this->postproc($b->getOldestWords($limit), $totalspam,$totalham);

        $inf['pagename'] = $this->prefix == 'bayes' ? 'bayesinfo' : 'bayeslinks';
        $inf['page_template'] = 'bayesinfo';
        $inf['title'] = 'Words for '.$this->prefix.($email?' (emails only)':'');


        $locale = localeconv();
        if (!$locale['thousands_sep']) $locale['thousands_sep']=',';
        $inf['totalwordsf'] = number_format($inf['totalwords'], 0, $locale['decimal_point'], $locale['thousands_sep']);


        $totalf = number_format($totalspam+$totalham, 0, $locale['decimal_point'], $locale['thousands_sep']);
        $inf['totalspamf'] =    number_format($inf['total']['spam'], 0, $locale['decimal_point'], $locale['thousands_sep']);

        return $inf;
    }

    private function postproc($table, $totalspam, $totalham)
    {
            $totalspam = max(1,$totalspam);
            $totalham = max(1,$totalham);

        foreach($table as &$word)
        {
            $word['spammy'] = $word['spam'] / ($totalspam/100);
            $word['hammy'] = $word['ham'] / ($totalham/100);
            $word['spammy'] += 5*sqrt($word['spammy']);
            $word['hammy'] += 5*sqrt($word['hammy']);
            $word['rate'] = ($word['spammy'] / ($word['spammy'] + $word['hammy']) - 0.5) * 2;
        }
        return $table;
    }

    function neuter($wordh)
    {
        $b = $this->getBayesStats();

        $killed = $b->neuterWordHash($wordh);

        list($totalspam, $totalham) = $b->getTotalPosts();

        $totalspam /= 2; $totalham /= 2; // these are too inflated!

        $spam = min(100, $killed['spam'] / ($totalspam/100));
        $ham = min(100, $killed['ham']  / ($totalham/100));

        // and now make it 1-150 with nonlinear skew
        $spam += 5*sqrt($spam);
        $ham += 5*sqrt($ham);

        $killed['spammy'] = round($spam*100/($ham+$spam));
        $killed['normspam'] = round($spam,2);
        $killed['normham'] = round($ham,2);

        $killed['page_template'] = 'bayeskill';
        $killed['title'] = 'Killed „'.(!empty($killed['word'])?$killed['word']:$killed['wordh']).'”';

        return $killed;
    }

    function post_whitelist()
    {
        throw new Exception("unimplemented");
    }
}


class BayesStats extends BayesBase
{
    protected function statsTableJoin()
    {
        return $this->table . 'wordsh_s s left join '.$this->table.'translate t using(wordh) ';
    }

    public $extra_where = '';

    function getRecentlyAddedHams($limit = 30)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where (ham<=2 and spam=0) {$this->extra_where} order by added desc limit $limit");
    }
    function getRecentlyAddedSpams($limit = 30)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where (spam<=2 and ham=0) {$this->extra_where} order by added desc limit $limit");
    }
    function getRecentlyModdedWords($limit = 30)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where spam+ham>2 {$this->extra_where} order by added desc limit $limit");
    }

    function getSpammiestWords($limit = 30, $totalspam, $totalham)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by spam desc limit $limit");
    }

    function getHammiestWords($limit = 30, $totalspam, $totalham)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by ham desc limit $limit");
    }


    function getCommonWords($limit = 30, $totalspam, $totalham)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by (ham/$totalham+spam/$totalspam) desc limit $limit");
    }

    function getUselessWords($limit = 30, $totalspam, $totalham)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by abs(ham/$totalham-spam/$totalspam),spam desc limit $limit");
    }

    function getStrongestWords($limit = 30, $totalspam, $totalham)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by abs(ham/$totalham-spam/$totalspam) desc limit $limit");
    }

    function getOldestWords($limit = 30)
    {
        return $this->getQueryArray("/*maxtime=20*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where 1 {$this->extra_where} order by added limit $limit");
    }

    function neuterWordHash($hash)
    {
        list($totalspam,$totalham) = $this->getTotalPosts();

        $res = $this->changeHashes(array($hash), "ham=10+(ham + ((spam/$totalspam + ham/$totalham)/2)*$totalham)/2, spam=10+(spam + ((spam/$totalspam + ham/$totalham)/2)*$totalspam)/3");

        if (!count($res)) throw new Exception("Unable to find word");
        return reset($res);
    }

    /** no mercy */
    function banWords(array $words)
    {
                    $hashes = $this->hashWords($words,false);

                    return $this->changeHashes($hashes," ham = 0, spam = spam * 2 + 100000");
    }

    private function changeHashes(array $hashes, $algo)
    {

        if (!count($hashes)) throw new Exception("No hashes for $algo");
        $in = "(unhex(?)".str_repeat(',unhex(?)',count($hashes)-1).')';

	    $this->db->prepareExecute("/*maxtime5*/INSERT IGNORE INTO {$this->table}wordsh(wordh,spam,ham) values(unhex(?),0,0)".str_repeat(",(unhex(?),0,0)",count($hashes)-1),$hashes);
        $this->db->prepareExecute("/*maxtime15*/UPDATE {$this->table}wordsh set $algo where wordh in $in",$hashes);

        // if triggers are disabled, this won't work
        $prep = $this->db->prepareExecute("/*maxtime5*/SELECT spam,ham,word,hex(s.wordh) as wordh from ".$this->statsTableJoin()." where s.wordh in $in",$hashes);
        $res = $prep->fetchAll();
        if ($res) return $res;

        $prep = $this->db->prepareExecute("/*maxtime5*/SELECT spam,ham,word,hex(s.wordh) as wordh from {$this->table}wordsh s left join {$this->table}translate t using(wordh) where s.wordh in $in",$hashes);
        return $prep->fetchAll();
    }

    private function getQueryArray($q)
    {
        d($q);
        $res = $this->db->query($q);
        return $res ? $res->fetchAll() : array();
    }

}

