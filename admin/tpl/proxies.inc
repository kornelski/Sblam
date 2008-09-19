<h2>Trusted proxies</h2>
<p>Proxy to be trusted must pass <code>X-Forwarded-For</code> header (or similar) and must have <strong><em>rev</em>DNS</strong> that is on this list.</p>
<form action="/admin/proxies/cache" method="post">
    <p>For sake of performance, only cached lookups are checked. If any proxies listed below have 0 IPs, you have to fill the cache.</p>
    <div><label><input type="radio" name="type" value="insecure" /> Insecure, incomplete, but quick</label></div>
    <div><label><input type="radio" name="type" value="missing" checked="checked" /> Look up only missing</label></div>
    <div><label><input type="radio" name="type" value="all" /> Look up everything</label></div>
    <div><input type="submit" value="Pre-fill cache" /></div>
</form>
<form action="" method="post">
    <div><label>Hostname <input type="text" name="add" value="${POST/add | nothing}" /></label><input type="submit" value="Add"/></div>
<table class="plaintable sortable">
    <thead><tr><th>Hostname</th><th>IPs</th><th>Action</th></tr></thead>
    <tbody>
    <tr tal:repeat="p proxies"><td tal:content="p/host" /><td tal:content="p/ipcnt"/><td><button name="remove" value="${p/host}" type="submit">Remove</button></td></tr>
    </tbody>
</table>
</form>
