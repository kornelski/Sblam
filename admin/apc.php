<?php

class ApcPage extends AdminPage
{
	
	function __construct()
	{
		if (!function_exists('apc_cache_info')) throw new Exception("APC not installed");
	}
	
	function index()
	{
	/*	$usercache = apc_cache_info('user',false);		
		$entrycount = count($usercache['cache_list']);
		$usercache = $usercache['cache_list']; 
		$cnt=0;
		foreach($usercache as $i)
		{
			@list($label,$key) = explode(":",$i['info'],2);
			@list($t,$val) = explode("\t",apc_fetch($i['info']));
			if ($val<=5) continue;
			
			$cnt++; if ($cnt > 1000) break;
			
			if ($key && is_scalar($key)) $tmp[$label][$key] = $val.' '.$i['num_hits'];
			else $tmp['other'][$i['info']] = $val.' '.$i['num_hits'];
		}
		$usercache=NULL;
		foreach($tmp as &$tmp2)
		{
			arsort($tmp2);
		}
*/
		return array(
			'info'=>apc_cache_info(),
			'sma'=>apc_sma_info(true),
			'entrycount'=>0,//$entrycount,
			);
	}

	function post_clear()
	{
		apc_clear_cache('user');
	}
}





