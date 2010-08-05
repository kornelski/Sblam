<?php


class SblamTestHttp extends SblamTestPost
{
    function testPost(ISblamPost $p)
    {
        $h = $p->getHeaders(); if (!$h || count($h)<2) return NULL; // HTTP_HOST is hardcoded! :///

        $out = array();

        if (!empty($h['HTTP_MOD_SECURITY_MESSAGE'])) $out[] = array(1,self::CERTAINITY_HIGH,"mod_security warning");

        // Buggy .Net always adds header which is only needed for large forms (and browsers tend not to use it)
        if (!empty($h["HTTP_EXPECT"]) && false!==strpos($h['HTTP_EXPECT'],'100-') && strlen($p->getRawContent()) < 5000)
            $out[] = array(0.3,self::CERTAINITY_NORMAL,"100-expect .Net header");

        // Bots tend to send these
        if (!empty($h["HTTP_PRAGMA"])) $out[] = array(empty($h["HTTP_VIA"])?0.3:0.1,self::CERTAINITY_LOW,"Pragma header");
        if (!empty($h["HTTP_RANGE"])) $out[] = array(0.5,self::CERTAINITY_HIGH,"Range header");
        if (!empty($h["HTTP_PROXY_CONNECTION"])) $out[] = array(0.2,self::CERTAINITY_LOW,"Proxy-Connection header");
        if (!empty($h["HTTP_REFERER"]) && ($cnt=substr_count($h["HTTP_REFERER"],"http://")) > 1)
            $out[] = array(min(1.5,0.5 + $cnt/6),self::CERTAINITY_HIGH,"Multiple links in referrer");

        if (($cnt = count($p->getAuthorIPs())) > 4) $out[] = array(($cnt-2)/10, $cnt>7?self::CERTAINITY_HIGH:self::CERTAINITY_NORMAL, "Insane number of relays ($cnt)");

        // Unpatched IE!?
        if (!empty($h["HTTP_USER_AGENT"]) && in_array($h['HTTP_USER_AGENT'],
            array('Mozilla/4.0 (compatible; MSIE 6.0; Windows 98)',
                        'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)')
            ))
            $out[] = array(0.3,self::CERTAINITY_NORMAL,"Unpatched IE");

        // Browsers almost always send these
        if (empty($h["HTTP_ACCEPT"])) $out[] = array(0.7,self::CERTAINITY_NORMAL,"Missing Accept header");
        if (empty($h["HTTP_USER_AGENT"])) $out[] = array(1,self::CERTAINITY_NORMAL,"Missing UA header");
        if (empty($h["HTTP_ACCEPT_LANGUAGE"])) $out[] = array(0.5,self::CERTAINITY_NORMAL,"Missing Accept-Language header");
        if (empty($h["HTTP_ACCEPT_ENCODING"]) && empty($h["HTTP_VIA"]) && (empty($h["HTTP_USER_AGENT"]) || false===strpos($h["HTTP_USER_AGENT"],'Mozilla/4.0 (compatible; MSIE ')))
            $out[] = array(0.4,self::CERTAINITY_LOW,"Missing Accept-Encoding header");
        if (!empty($h["HTTP_ACCEPT_CHARSET"])) $out[] = array(-0.2,self::CERTAINITY_LOW,"Has Accept-Charset header");

        // Non-transparent proxy must add Via header
        if (empty($h["HTTP_VIA"]) && (!empty($h['HTTP_X_FORWARDED_FOR']) || !empty($h['HTTP_MAX_FORWARDS'])))
            $out[] = array(0.2,self::CERTAINITY_LOW,"Lame proxy");

        // TE: requires Connection:TE
        if (!empty($h["HTTP_TE"]) && (empty($h['HTTP_CONNECTION']) || !preg_match('!\bTE\b!',$h['HTTP_CONNECTION'])))
            $out[] = array(0.2,self::CERTAINITY_NORMAL,"Invalid TE header");

        // Googlebot doesn't post comments!
        if (!empty($h['HTTP_USER_AGENT']) && preg_match('!Googlebot[/ -]|Slurp|Wget/|W3C_Validator|Advertise\.com|nicebot|MMCrawler/|MSIECrawler|ia_archiver|WebaltBot/|nutbot\.com|\+http://search\.!',$h['HTTP_USER_AGENT'])) $out[] = array(1,self::CERTAINITY_NORMAL,"Bots don't post comments");
        if (!empty($h['HTTP_USERAGENT']) ||
				(!empty($h['HTTP_USER_AGENT']) && preg_match('!^User-Agent!i',$h['HTTP_USER_AGENT']))
                ) $out[] = array(1,self::CERTAINITY_NORMAL,"Really badly written bot");

        // I assume multipart forms are too tricky for most bots
        if (!empty($h['HTTP_CONTENT_LENGTH']) && !empty($h['HTTP_CONTENT_TYPE']) && preg_match('!^\s*multipart/form-data\s*;\s*boundary\s*=!i',$h['HTTP_CONTENT_TYPE']))
        {
            $out[] = array(-0.2, self::CERTAINITY_LOW, "Multipart form");
        }

        // browsers nicely decode and normalize paths, remove fragment part
        if (($path = $p->getPath()) && preg_match('!&amp;|^https?://|^//|/%7e|#|\.\./!i',$path))
        {
            $out[] = array(0.3, self::CERTAINITY_NORMAL, "Improperly encoded path");
        }

        if (!empty($h["HTTP_REFERER"]) && preg_match('!&amp;|/%7e|\.\./!i',$h["HTTP_REFERER"]))
        {
            $out[] = array(0.25, self::CERTAINITY_LOW, "Improperly encoded referer");
        }

        if (count($out)) return $out;
    }


    static function info()
    {
        return array(
            'name'=>'Catch buggy HTTP implementations',
            'desc'=>'Invalid HTTP headers, fake Googlebots, etc.',
            'remote'=>false,
        );
    }
}
