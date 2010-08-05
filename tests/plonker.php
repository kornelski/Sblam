<?php

require_once "class/plonker.php";

class SblamTestPlonker extends SblamTestPost
{
	private $plonker;
	private $add;
	function __construct(array $settings)
	{
		$this->add = isset($settings['add'])?$settings['add']:1;
		$table = (isset($settings['table'])) ? $settings['table'] : 'plonker';

		$this->plonker = new Plonker(sblambaseconnect(), $table);
	}

	function testPost(ISblamPost $p)
	{
		$res = $this->plonker->testIPs($p->getAuthorIPs(), sprintf('%u',$p->getPostTime()));

		if (!$res) {return NULL;}
		list($total,$count) = $res;

		if ($total <0.1) return NULL;

		$rawtotal = round($total,1);

		$total = sqrt($total)/2 + $total/800;
		$total = max(0,$total-0.28);

		if ($total > 0.4) {$total = 0.4+($total-0.4)/2;}
		if ($total > 0.7) {$total = 0.7+($total-0.7)/2;}

		$total = min(7.5,$total+0.15);

		return array($total,$total>1.5?self::CERTAINITY_HIGH:self::CERTAINITY_NORMAL,"Automatically banned IPs/range ($count ips, $rawtotal R = ".round($total,1).")");
	}

	function reportResult(ISblamPost $post, $score, $cert)
	{
		if (!$this->add) return;

		if ($score > 0.66 && $cert > 0.75)
		{
			$this->plonker->addIPs($post->getAuthorIPs(), $score);
		}
		else if ($score < -0.6 && $cert > 0.7)
		{
			$this->plonker->removeIPs($post->getAuthorIPs());
		}
	}
}
