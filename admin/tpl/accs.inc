<tal:block metal:fill-slot="style">
a.msg {font-size:1.35em;line-height:0.8;text-decoration:none}
</tal:block>

<form method="post" action="/admin/accs">
    <p>
        <a tal:condition="exists:brief" href="/admin/accs/detailed">See detailed info</a><a tal:condition="not:exists:brief" href="/admin/accs">Back to brief info</a>.
    </p>
    <p>	<button type="submit">Update script support</button></p>
</form>

<table class="plaintable sortable">
	<thead><tr><th>#</th><th>email</th><th abbr="unread" 
title="unread">u</th><th>Spams</th><th>Cnt</th><th>Ham</th><th>Hosts</th><th>Date</th><th>JS</th></tr></thead>
	<tbody><tr tal:repeat="row accounts">		
		<td><a tal:condition="row/account" href="/admin/autologin/apikey/${row/apikey}" tal:content="row/account" /></td>
		<td><a class="msg" href="/admin/accs/msg/${row/account}">✉</a> <a tal:condition="row/email" href="mailto:${row/email}" 
tal:content="row/email" /></td>
		<td tal:content="row/unread"/>
		<td tal:content="row/spams" />
		<td tal:content="row/cnt" />
		<td tal:content="row/ham" />
		<td><tal:block tal:condition="php:isset(row['hosts'])" tal:repeat="h php:explode(', ',row['hosts'])"><x tal:replace="', '" tal:condition="not:repeat/h/start"/><a href="http://${h}" tal:content="h"/></tal:block></td>
		<td tal:content="row/date" />
		<td tal:content="row/JS" />
	</tr></tbody>		
</table>

