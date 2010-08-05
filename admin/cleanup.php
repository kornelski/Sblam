<?php

class CleanupPage extends AdminPage
{
	function index()
	{
		$pdo = $this->services->getDB();
		$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		$sblam = $this->getSblam();

		$pdo->exec("/*maxtime15*/DELETE from dupes where expires < unix_timestamp(now())");

		$pdo->exec("TRUNCATE bayeswordsh_s");
		$pdo->exec("TRUNCATE linkswordsh_s");

		$pdo->exec("/*maxtime15*/UPDATE plonker f join dnscache d on f.ip=d.ip left join trustedproxies t on t.host=d.host
		set f.added=f.added,f.spampoints = f.spampoints/2 where
		d.host like '%.adsl.tpnet.pl' or
		d.host like '%.dialog.net.pl' or
		d.host like '%.chello.pl' or
		d.host like '%.unregistered.net.telenergo.pl' or
		t.host is not null
		");
		$pdo->exec("/*maxtime10*/DELETE from plonker where (spampoints<100 and added < now() - interval 3 month) or spampoints<5");

		$pdo->exec("/*maxtime10*/DELETE from dnscache where host is NULL or rand()<0.4");
		$pdo->exec("/*maxtime10*/DELETE from dnsrevcache where ip = 0 or rand()<0.4");
		$pdo->exec("/*maxtime10*/delete from plonker where ip = inet_aton('127.0.0.1') or
ip between inet_aton('172.16.0.0') and inet_aton('172.31.255.255') or
ip between inet_aton('192.168.0.0') and inet_aton('192.168.255.255') or
ip between inet_aton('10.0.0.0') and inet_aton('10.255.255.255') or
ip between inet_aton('224.0.0.0') and inet_aton('255.255.255.255');");

		$n=0;
		$q = $pdo->query("/*maxtime15*/SELECT t.host FROM trustedproxies t left join dnscache d ON d.host = t.host WHERE d.host is NULL");
		if ($q) foreach($q->fetchAll(PDO::FETCH_ASSOC) as $res)
		{
			SblamURI::gethostbyname($res['host']);
		}

		$pdo->exec("/*maxtime10*/INSERT into dnscache (host,ip) select t.host,r.ip FROM trustedproxies t left join dnscache d ON d.host = t.host join dnsrevcache r on t.host = r.host WHERE d.host is NULL;");

		if (date("d")%1)
		{
			$pdo->exec("/*maxtime60*/DELETE from bayeswordsh where spam<3 and ham<2 and added < now() - interval 1 month limit 200000");
		}
		else
		{
			$pdo->exec("/*maxtime60*/DELETE from linkswordsh where spam<3 and ham<2 and added < now() - interval 1 month limit 100000");
		}

		$pdo->exec("DELETE from plonker WHERE ip BETWEEN  INET_ATON('192.168.0.0') AND INET_ATON('192.168.255.255')");

		$pdo->exec("DELETE from plonker WHERE ip BETWEEN  INET_ATON('172.16.0.0' ) AND INET_ATON('172.31.255.255')");

		$pdo->exec("DELETE from plonker WHERE ip BETWEEN  INET_ATON('127.0.0.0' ) AND INET_ATON('127.0.0.255')");

/*
		set @minid = least((select id-40000 from posts_meta order by id desc limit 1),(select id+5000 from posts_meta order by id limit 1)); insert into posts_archive
		select * from posts_meta left join posts_data on posts_meta.id = posts_data.id where posts_meta.id < @minid; delete from posts_meta where id < @minid;
*/

		return array('page_content'=>'ok');
	}
}
