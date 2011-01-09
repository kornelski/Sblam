<?php

class StatsPage extends AdminPage
{
    private function q1($q)
    {
    	$res = $this->services->getDB()->query($q);
    	if (!$res) return NULL;
    	if (!($res = $res->fetchAll(PDO::FETCH_ASSOC))) return NULL;

    	$res = $res[0];
    	if (count($res)==1) return reset($res);
    	return $res;
    }

    function index()
    {
        $stats = $this->stats();
        $stats['graph'] = $this->speedgraph();
        return $stats;
    }

    private function speedgraph()
    {
        $res = $this->services->getDB()->query(
        "SELECT
            count(*) as cnt,
            round(avg(worktime)/ 1000,1) as time,
            count(if(manualspam=0 or (manualspam is null and spamscore<=0),1,NULL)) as hams,
            count(if(manualspam=1 or (manualspam is null and spamscore>0),1,NULL)) as spams,
            ceil(log(worktime)*5 + sqrt(worktime)/100) as grp
        FROM posts_meta
        WHERE id > (SELECT max(id) from posts_meta)-15000
            AND worktime IS NOT NULL
        GROUP BY grp
        HAVING count(*)  > 1
        ORDER BY grp DESC");

        if (!$res) throw new Exception(implode('/',$pdo->errorInfo()));

        $res = $res->fetchAll(PDO::FETCH_ASSOC);

        $totalhams = 0;
        $totalspams=0;
        $linemax = 10;
        foreach($res as $r)
        {
            $totalhams += $r['hams'];
            $totalspams += $r['spams'];
            $linemax = max($linemax,$r['hams']+$r['spams']);
        }

        $spamratio = min(10,max(2,$totalspams / (1+$totalhams/2)));

        foreach($res as $r)
        {
            $r['spams'] /= $spamratio;
        }

        $wfact = 800/$linemax;
        $lines = array();
        $hamssofar=0;
        foreach($res as $r)
        {
            $lines[] = array(
                'height'=>ceil(12+sqrt($r['time'])),
                'time'=>$r['time'],
                'percent'=>round(($totalhams-$hamssofar)/$totalhams*100),
                'spamwidth'=>round($wfact*($r['hams']+$r['spams'])),
                'hamwidth'=>round($wfact*$r['hams']),
            );
        	$hamssofar += $r['hams'];
        }

        return array(
            'lines'=>$lines,
            'totalhams'=>$totalhams,
            );
    }


    private function stats()
    {
        $res = array();

        $db = $this->services->getDB();

        $res['total'] = $this->q1("SELECT count(*) FROM posts_meta");
        $res['tempo'] = intval($this->q1(
        "SELECT
            round(count(*) / {$db->timestampdiff('hour',
                '(SELECT from_unixtime(timestamp) FROM posts_meta ORDER BY id LIMIT 1)',
                'now()')}
                )*24
        FROM posts_meta"));

        $res['unverified'] = $this->q1("SELECT count(*) FROM posts_meta WHERE manualspam is NULL");
        $res['tough'] = $this->q1("SELECT count(*) FROM posts_meta WHERE manualspam is NULL AND (spamcert<110 OR abs(spamscore)<110)");

        $res['unadded'] = $this->q1("SELECT count(*) FROM posts_meta WHERE added is NULL OR added =0");

        $res['hams'] = $this->q1("SELECT count(*) FROM posts_meta WHERE spamscore<0");
        $res['hamsprc'] = $res['total'] ? round($res['hams']*100/$res['total'],1) : 0;

        $res['fhams'] = $this->q1("SELECT count(*) FROM posts_meta WHERE spamscore<0 and manualspam=1");

        $res['phams'] = $res['hams'] ? round($res['fhams']*100/$res['hams'],2) : 0;
        //$res['bphams'] = $res['hams'] ? round($res['bfhams']*100/$res['hams'],2) : 0;
        $res['spams'] = $this->q1("SELECT count(*) FROM posts_meta WHERE spamscore>0");
        $res['spamsprc'] = $res['total'] ? round($res['spams']*100/$res['total'],1) : 0;
        $res['fspams'] = $this->q1("SELECT count(*) FROM posts_meta WHERE spamscore>0 AND manualspam=0");

        $res['pspams'] = $res['spams'] ? round($res['fspams']*100/$res['spams'],2) : 0;

        $res['totalsure'] = max(1,$this->q1("SELECT count(*) FROM posts_meta WHERE abs(spamcert) > 43 AND abs(spamscore) > 36"));

        $res['accuracy'] = $res['total'] ? round(100-($res['fspams']+$res['fhams'])*100/$res['total'],2) : 0;
        $res['unsure'] = $res['total'] ? round(($res['total']-$res['totalsure'])*100/$res['total'],1) : 0;

        return $res;
    }
}

