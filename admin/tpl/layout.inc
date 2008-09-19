<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title tal:content="title | 'Admin'"/>
	<style type="text/css">/*<![CDATA[*/
	body {font: 0.86em/1.2 Arial,sans-serif; background:white; color:black; margin:0;padding:0;}
	h1 {font-size: 3em; padding:0.2em; margin:0; background: #777; color:white; border-bottom:1px solid black;text-shadow:0 2px 5px #444}
	#menu {margin:0 0 1em;padding:0;list-style:none; border-bottom:1px solid black; background: #999}
	#menu li, #menu li form {display:inline; margin:0; padding:0;}
	#menu li a, #menu li button {
		padding: 0 1em;
		font:inherit; cursor:pointer;
		text-decoration:none;
		color: white; background: #999;
		border:0; display:inline;
		border-right:1px solid black;
		text-shadow: 0 1px 0 black;
	}
	h1 a {color:white;text-decoration:none;}

#menu li a:hover, #menu li button:hover { background: #989898;text-shadow: 0 0 2px black;}

	.plaintable {border-collapse:collapse; margin:1em 0;max-width:100%}
	.plaintable td {border:0;padding:1px 2px 2px}
	.plaintable th {border-bottom:1px solid #aaa;padding:1px 0 1px;font-size:0.9em;font-family:"arial narrow",arial,sans-serif;font-weight:normal;}

	.plaintable tr:nth-child(even) td {background:#f0f0ff;}	
	.plaintable td:nth-child(even) {background:#f8f8f8;}
	.plaintable tr:nth-child(even) td:nth-child(even) {background:#e8e8fa;}

	#content {padding: 0 5px;}
    hr {clear:both;height:0;border:0;border-top:1px solid #ddd;}

	${style | nothing} /*:} ]]>*/
	<tal:block metal:define-slot="style"/>
	</style>
	</head><body>
<h1><a href="/admin/">Sblam</a></h1>
<ul id="menu">
<li><a href="/admin/stats">Stats</a></li>
<li><a href="/admin/accs">Accounts</a></li>
<li><a href="/admin/bayesinfo">Bayes words</a></li>
<li><a href="/admin/bayeslinks">Bayes links</a></li>
<li><a href="/admin/plonker">IP bans</a></li>
<li><a href="/admin/score">Moderate</a></li>
<li><a href="/admin/bayesadd">Train filter</a></li>
<li><a href="/admin/atom">Autotrain</a></li>
<li><a href="/admin/updatebayes">Update bayes</a></li>
<li><a href="/admin/live">Live</a></li>
<li><a href="/admin/apc">APC</a></li>
<li><a href="/admin/daily">daily</a></li>
<li><a href="/admin/proxies">proxies</a></li>
</ul>
<div id="content" tal:content="structure page_content" />
<hr/>
<script src="/sorttable.js"></script>
</body></html>
