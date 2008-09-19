<h2>${php:added?'Added':'Removed'} ${php:count(ips)} IP${php:count(ips) gt 1?'s':''}</h2>
<ul>
  <li tal:repeat="ip ips"><img width="14" height="12" src="http://api.hostip.info/flag.php?ip=${php:long2ip(ip)}"/> ${php:long2ip(ip)}</li>
</ul>
