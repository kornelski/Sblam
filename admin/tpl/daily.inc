<h2>Spams per hour – daily</h2>
<table cellspacing="1" cellpadding="0"><tr align="center" valign="bottom">
    <td tal:repeat="r hours">
        <div style="background:#a88;width:1.5em;line-height:1;height:${php:round(r['cnt'] * scalefactor)}px" tal:content="structure php:preg_replace('/[ \d]/','<br />\0',round(r['cnt']) . ' ' . round(3600/r['cnt'],1) . 's')" />${r/hour}</td>

</tr></table>

<h2>Spam congestion – per 30s</h2>
<table cellspacing="1" cellpadding="0" style="font-size:0.5em;"><tr align="center" valign="bottom">
    <td tal:repeat="r top"><div style="background:#88a;width:1.2em;line-height:1;height:${php:round(10+r['cnt'] * topscalefactor)}px" tal:content="structure php:preg_replace('/[ \d]/','<br />\0',round(r['cnt']) . ' ' . round(16/r['cnt'],1) . 's')" />${php:date('H i s',r['timestamp'])}</td>
</tr></table>
