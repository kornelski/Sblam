<?php

class PostFormatter
{
    static function highlight_entity_callback($m)
    {
    	return '<var title="'.$m[0].'">'.htmlspecialchars(html_entity_decode(html_entity_decode($m[0],ENT_QUOTES,'UTF-8'),ENT_QUOTES,'UTF-8')).'</var>';
    }

    private static function highlight_text($frag)
    {
    	return nl2br(str_replace("­","<code>-</code>",preg_replace_callback('/&amp;#?[a-zA-Z0-9]+;/',array('self','highlight_entity_callback'),htmlspecialchars($frag))));
    }

    private static function highlight_inline($frag)
    {
    	$parts = preg_split('!(?:https?://|\bwww\.)([^\]\[\s\(\)<>\"\']+)!i',$frag,NULL,PREG_SPLIT_DELIM_CAPTURE);
    	$parts[] = '';
    	$out = '';
    	for($i=1; $i < count($parts); $i += 2)
    	{
    		$out .= preg_replace('!([^<>&\s/-]{10})([^<>&\s/-]{10})!','\1­\2',self::highlight_text($parts[$i-1]));
    		if (strlen($parts[$i])) $out .= '<a href="http://'.htmlspecialchars($parts[$i]).'">'.preg_replace('!([^<>&\s/-]{10})([^<>&\s/-]{10})!','\1­\2',self::highlight_text(substr(urldecode($parts[$i]),0,100))).'</a> <a class="kill" href="/admin/bayeslinks/kill/'.urlencode($parts[$i]).'">&#x2620;</a>';
    	}
    	return $out;
    }

    static function highlight($post)
    {
    	$post = preg_replace("!(?:\s*\r?\n){3,}!","\n\n",$post);
    	$parts = preg_split('!(<[a-z]+[^>]*>|</[a-z]+\s*>|\[[a-z]+\s*=[^\]<>]*\]|\[/?[a-z]+\s*\])!i',$post,NULL,PREG_SPLIT_DELIM_CAPTURE);
    	$parts[] = '';
    	$out = '';
    	for($i=1; $i < count($parts); $i += 2)
    	{
    		$out .= self::highlight_inline($parts[$i-1]);
    		$out .= '<code>'.self::highlight_inline($parts[$i]).'</code>';
    	}
    	return $out;
    }
    
    static function formatreason($reason)
    {
        $reason = htmlspecialchars($reason);
        return preg_replace('!h:([a-z0-9 ][^\)]*)\)!e','\'<a href="/admin/bayesinfo/neuter/\'.md5(\'~$\1\').\'">\1</a>\'',htmlspecialchars($reason));
    }
}

