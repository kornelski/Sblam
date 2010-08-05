<!DOCTYPE html>
<style type="text/css">/*<![CDATA[*/
#lastposts {border-collapse:collapse;}
#lastposts td {text-align:right;}
#lastposts,#processlist {font-size:0.9em;}
#lastposts .ham {color:#050;}
#lastposts .spam {color:#600;}
 #lastposts td:first-child + td,
 #lastposts td:first-child + td + td ,
 #lastposts td:first-child + td + td + td + td {text-align:left; white-space:nowrap;}
 #lastposts td:first-child + td + td + td + td + td + td + td 
{text-align:left;font-family:"Arial Narrow",sans-serif;font-size:9px;line-height:1;padding:0 0 0 2px;vertical-align:middle}

tr.new {background:#ffe;}

/*]]>*/</style>
<div id="load">
	<tal:block tal:repeat="s sysload">${s},</tal:block> x
</div>

<div style="min-height:7em">
<table id="processlist" border="1" cellpadding="3">
</table>
</div>

<table id="lastposts" border="1" cellpadding="3">
<thead><tr><th>ID</th><th>date</th><th>IP</th><th>score</th><th>URL</th><th>time</th><th>+</th><th>Reason</th></tr></thead><tbody></tbody>
</table>
<script type="text/javascript">/*<![CDATA[*/

var lastposts = document.getElementById('lastposts');
var servers = [{host:''}];
var updno=0;

var processresult = function(serv,result)
{
	var load = document.getElementById('load');

	load.replaceChild(document.createTextNode(result.sysload.toString() + ' ' + updno),load.firstChild);
	
	var minnullid = serv.lastid+9999999;
	var j,lps = result.lastposts;
	for(j=lps.length-1; j >= 0; j--)
	{
		var tbody = lastposts.tBodies[0] || lastposts;
		var row = document.getElementById('post' + lps[j].id);
		if (row)
		{
			while(row.lastChild) row.removeChild(row.lastChild);
		}
		else
		{
			row = document.createElement('tr'); row.setAttribute('id','post'+lps[j].id);
			row.className = 'new'; setTimeout((function(tr){return function(){tr.className=tr.className.replace('new','');}})(row),2000);
			tbody.insertBefore(row,tbody.firstChild);
			if (tbody.childNodes.length > 100) tbody.removeChild(tbody.lastChild);
		}
		var addcell = function(text,url)
		{
			var td = document.createElement('td');
			row.appendChild(td);
			if (url)
			{
				var a = document.createElement('a'); a.setAttribute('href',url); td.appendChild(a); td = a;
			}
			td.appendChild(document.createTextNode(text));
		}
		serv.lastid = Math.max(serv.lastid,parseInt(lps[j].id)+1);
		addcell(lps[j].id,'/admin/test/id/' + lps[j].id);
		addcell(lps[j].time);
		addcell(lps[j].ip);
		addcell(lps[j].spamscore || 'test...');

		if (!/ham|spam/.test(row.className) && lps[j].spamscore)
		{
			if (parseInt(lps[j].spamscore) < 0) row.className +=' ham';
			else if (parseInt(lps[j].spamscore) > 0) row.className +=' spam';
		}
		if (lps[j].spamscore == null) minnullid = Math.min(minnullid,lps[j].id);
		addcell(lps[j].path);
		addcell(lps[j].work || '...');
		addcell(lps[j].added || '-');
		addcell(lps[j].spamreason || '-');
	}
	serv.lastid = Math.min(serv.lastid,minnullid);

	var pl = result.processlist, plt = document.getElementById('processlist');
	if (plt.tBodies && plt.tBodies[0]) plt = plt.tBodies[0];
	var addcell = function(text)
	{
		var td = document.createElement('td');
		row.appendChild(td);
		td.appendChild(document.createTextNode(text));
	}
	while(plt.lastChild) plt.removeChild(plt.lastChild);
	for(i in pl)
	{
//		if (pl[i]['Info'] == 'show processlist') continue;
//		if (pl[i]['Info'] == null) continue;
		row = document.createElement('tr');
		for(j in pl[i])
		{
			addcell(pl[i][j]);
		}
		plt.appendChild(row);
	}

	return (serv.lastid == minnullid || result.lastposts.length);
}

var update = function()
{
	var i,serv;
	for(i=0; i < servers.length; i++)
	{
		serv = servers[i];
		
		var xhr = new XMLHttpRequest();
		
		if (!serv.lastid) serv.lastid=0;
		
		xhr.open('GET',serv.host + '/admin/live/json/' + serv.lastid, true);	
		xhr.onreadystatechange = function()
		{
			if (xhr.readyState == 4 && xhr.status==200)
			{
				updno++;
				eval('var result = ' + xhr.responseText);
				
				var freq = processresult(serv,result);
				
				setTimeout(update, freq? 1500 : 3000);
			}
		}
		xhr.send();	
	}

};

update();

/*]]>*/</script>
