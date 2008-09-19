<?php

class ClassifyipsPage extends AdminPage
{
	function index()
	{
		$pdo = $this->getPDO();
		$sblam = $this->getSblam(); // inits urls
		
		$table = 'plonker'; // FIXME: read config!
		$accumulate = array();

		$max=10;
		while($max--) foreach($pdo->query("SELECT ip,added from $table where flags=0 order by rand() limit 2000") as $r)
		{	
			try
			{
				$ip = long2ip($r['ip']);
	
				$rev = preg_replace('!(\d+)\.(\d+)\.(\d+)\.(\d+)!','\4.\3.\2.\1.dul.dnsbl.sorbs.net',$ip);
				$r['rev1'] = $rev;
				SblamURI::gethostbynameasync($rev);
	
				$rev = preg_replace('!(\d+)\.(\d+)\.(\d+)\.(\d+)!','\4.\3.\2.\1.korea.services.net',$ip);
				$r['rev2'] = $rev;
				SblamURI::gethostbynameasync($rev);
	
				$accumulate[] = $r;

				usleep(50000);
	
				if (count($accumulate)>=20)
				{
					foreach($accumulate as $r)
					{			
						$res = SblamURI::gethostbyname($r['rev1']) ? 'dul':'nodul';			
						$res .= ',' . (SblamURI::gethostbyname($r['rev2']) ? 'wild':'nowild');

						$q = "update $table set flags = '$res', added = added where ip = {$r['ip']}";
						d($q);
						if (!$pdo->query($q)) warn($pdo->errorInfo());
					}
					$accumulate = array();
				}
			}
			catch(Exception $e){}
		}
		
		return array('redirect'=>'/admin/plonker');
	}
	
	function post_index()
	{
		return $this->index();
	}
}
