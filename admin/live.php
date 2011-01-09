<?php

ini_set("zlib.output_handler",0);

class LivePage extends AdminPage
{

	private function fetchdata($lastid)
	{
		$lastid = intval($lastid);

		    // MySQL is crap.
		    // If I make it a dependent query and try to sort it on database side, all gets copied into temporary tables, even when SQL_SMALL_RESULT is added everywhere

		$pdo = $this->services->getDB();
        $sql = "/*maxtime=1*/".
            "SELECT
                posts_meta.id,from_unixtime(\"timestamp\") as \"time\",
                COALESCE(NULLIF(dnsrevcache.host,''),inet_ntoa(posts_meta.ip)) as ip,
                (spamscore || '/' || spamcert) as spamscore,
                (posts_data.host || substring(path,1,50)) as path,
                (round(worktime/1000,1) || 's') as work,
                added,spamreason
            FROM posts_meta INNER JOIN posts_data USING(id)
            LEFT JOIN dnsrevcache USING(ip)
            WHERE posts_meta.id >= $lastid
            ORDER BY posts_meta.id DESC
            LIMIT 30";

		$lastposts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

		usort($lastposts,array('self','sortByTime'));

	 	return array(
			'sysload' => sys_getloadavg(),
			'lastposts' => $lastposts,
			'processlist' => $pdo->getProcesslist(),
		);
	}

	static function sortByTime($a,$b)
	{
	    return strcmp($b['id'],$a['id']); // actually ID makes more sense :)
    }

	function index()
	{
		return array(
			'sysload' => sys_getloadavg(),
			'lastposts' => array(),
			'processlist' => array(),
			'title'=>'Live',
		);
	}

	function json($lastid = 0)
	{
		return array('page_content'=>json_encode($this->fetchdata($lastid)), 'content_type'=>'text/plain;charset=UTF-8', 'layout_template'=>'', 'page_template'=>'');
	}

	function stream($lastid = 0)
	{
		header("Content-Type:application/x-dom-event-stream");

		@ob_end_clean();
		@ob_end_clean();
		@ob_end_clean();

		$boring=0;
		$max = 500;
		$minnullid = 99999999;
		$lastrows = 0;
		ignore_user_abort(false);
		while($max-- && !connection_aborted())
		{
			$data = $this->fetchdata($lastid?min($minnullid,$lastid+1):0);
			$minnullid = $lastid+100000;
			foreach($data['lastposts'] as $d)
			{
				if ($d['work']==NULL) $minnullid = min($minnullid,$d['id']);
				$lastid = max($lastid,$d['id']);
			}
			foreach($data['processlist'] as $k => $d)
			{
				if ($d['Info']=='show processlist') unset($data['processlist'][$k]);
			}
			$data['processlist'] = array_values($data['processlist']);

//			echo "event: message\n";
			echo "id: $lastid\n";
			echo "data: ".json_encode($data)."\n";
			echo "\n";

			@flush();
			if ($lastrows == count($data['lastposts']) + 100*count($data['processlist']))
			{
				$boring++;	if ($boring > 20) $boring=20;
			}
			else $boring=0;

			$lastrows = count($data['lastposts']) + 100*count($data['processlist']);

			if (count($data['lastposts']) > 7 || count($data['processlist']) > 7)
			{
				sleep(1);
			}
			else
			{
				usleep(300000 + 100000 * $boring);
			}
		}
	}
}
