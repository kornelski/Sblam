<h2>Fool me once</h2>

<style type="text/css">
    .iptable {float:left; margin: 1em 2em 1em 0; border-collapse:collapse; border: 1px solid #ccc; }
    .iptable th {text-align:left;}
    .iptable td {white-space:nowrap;border-top: 1px solid #ccc; padding: 0.2em;}
    .iptable td {text-align:right;}
    .iptable td.hostname {font-weight:bold;text-align:left;}
    .iptable .odd {background: #f8f8ff;}
    .iptable td.long {font-family: Arial Narrow,Arial,sans-serif;}

    #plonker {overflow:auto}
</style>

<p tal:condition="not:exists:totalnets"><a href="/admin/plonker/stats">Show statistics of ${total} ips</a>.</p>
<p tal:condition="exists:totalnets">There are ${total} IPs from about ${totalnets} subnets in the database.</p>


<form action="/admin/classifyips" method="post" tal:condition="exists:byflags">
    <tal:block metal:use-macro="tables.inc/table" tal:define="table byflags" />
    <input type="submit" value="Update" />
</form>



<form method="post" action="/admin/plonker/block"><textarea name="block" rows="15" cols="40"></textarea>
<input type="submit" value="Ban"/><input type="submit" value="Unban" name="remove"/>
</form>

<form method="post" action="/admin/plonker/blocklist">
<hr/>
<input type="submit" name="blocklist" value="Blocklist"/>
</form>

<?php

function displayIPtable($tab, $caption, $cnt=false, $mask=false)
{
    if (!$tab || !count($tab)) return;

    echo '<table class="iptable"><caption>'.htmlspecialchars($caption).'</caption>';
    echo '<tr>';
    if ($cnt) echo '<th class="count">IPs</th>';
    echo '<th>IP</th><th>Score</th><th>Age</th></tr>';

    $odd = 0;

    $now = time();
    foreach($tab as $r)
    {
        $odd = 1-$odd;
        if (empty($r['ip'])) continue;

        $ip = long2ip($r['ip']);

        echo '<tr'.($odd?' class="odd"':'').'>';

        if ($cnt) echo '<td class="count">'.$r['cnt'].'</td>';

        $rev = SblamURI::gethostbyaddr($ip);

        echo '<td class="hostname'.(strlen($rev.($cnt?$r['cnt']:''))>40?' long':'').'"';
/*      if ($mask)
        {
            $rev = SblamURI::gethostbyaddr(long2ip(($r['ip'] & (~255)) | 1));
            if (!$rev)  $rev = SblamURI::gethostbyaddr($ip);
        }
        else*/

        if (!$rev)
        {
            if ($mask) $ip = long2ip(ip2long($ip) & (~255));
            $rev = $ip;
        }
        else echo ' title="'.$ip.'"';

        echo '>'.$rev;

        echo '</td><td>'.'<img width="12" height="10" src="http://api.hostip.info/flag.php?ip='.urlencode($ip).'" /> '.
round(sqrt($r['spampoints'])/3 + $r['spampoints']/300).'</td><td>';

        $age = $now - strtotime($r['added']);
        if ($age < 0) {echo '-'; $age = -$age;}
                 if ($age > 3*60*60*24) echo round($age/(60*60*24))."d";
        else if ($age > 60*60*24) echo round($age/(60*60*24),1)."d";
        else if ($age > 3*60*60) echo round($age/(60*60))."h";
        else if ($age > 60*60) echo round($age/(60*60),1)."h";
        else if ($age > 3*60) echo round($age/60,1)."m";
        else if ($age > 60) echo round($age/60)."m";
        else echo round($age)."s";

        echo '</td>';

        if (isset($r['flags']))
        {
            echo '<td>';
            if (preg_match('/(^|,)dul/',$r['flags'])) echo '&#x260E;';
            if (preg_match('/(^|,)wild/',$r['flags'])) echo '&#x2622;';
            if ($r['flags']=='') echo '?';
            echo '</td>';
        }

        echo '</tr>';
    }
    echo '</table>';
}

?>
<div id="plonker">

<tal:block tal:condition="exists:recentips" tal:content='php:displayIPtable(recentips,"Recently spotted IPs")' />
<tal:block tal:condition="exists:topips" tal:content='php:displayIPtable(topips,"Spammiest IPs")' />
<tal:block tal:condition="exists:topranges" tal:content='php:displayIPtable(topranges,"Most crowded subnets",true,true)' />
<script type="text/javascript">/*<![CDATA[*/
var plonker = document.getElementById('plonker');
if (plonker)
{
    plonker.onclick = function(e){
        var target = e.target;
        while(target)
        {
            if (target.tagName && target.tagName.toLowerCase() == 'td')
            {
                var t = target.getAttribute('title'); if (!t) return;
                target.setAttribute('title', target.innerHTML);
                target.innerHTML = t;
                break;
            }
            target = target.parentNode;
        }
    }
}
/*]]>*/</script>
</div>
