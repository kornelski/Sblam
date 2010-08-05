<?php

class DailyPage extends AdminPage
{
    function index()
    {
        $pdo = $this->services->getDB();

        $hours = $pdo->query("/*maxtime=20*/SELECT count(*)/count(distinct day(from_unixtime(`timestamp`))) as cnt,HOUR(from_unixtime(`timestamp`)) as `hour` FROM posts_meta GROUP BY
            HOUR(from_unixtime(`timestamp`)) ORDER BY `hour`")->fetchAll(PDO::FETCH_ASSOC);

        $max=1;
        foreach($hours as $h)
        {
            $max = max($h['cnt'],$max);
        }
        $scalefactor = 300 / $max;

        $top = $pdo->query("/*maxtime=20*/SELECT count(*) as cnt,`timestamp` >> 5 as `slot` , `timestamp` FROM posts_meta WHERE `timestamp` > unix_timestamp(NOW())-3600*24 GROUP BY from_unixtime(`timestamp`) >> 5 ORDER BY `cnt` desc limit 50")->fetchAll(PDO::FETCH_ASSOC);

        $max=1;
        foreach($top as $h)
        {
            $max = max($h['cnt'],$max);
        }
        $topscalefactor = 200 / $max;

        return array(
            'scalefactor' => $scalefactor,
            'topscalefactor' => $topscalefactor,
            'hours'=>$hours,
            'top'=>$top,
        );
    }
}
