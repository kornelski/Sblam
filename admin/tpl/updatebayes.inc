
<form method="post" action="/admin/updatebayes">
    <input type="submit" value="Add all" />
</form>

<form method="post" action="/admin/updatebayes/index/10/100">
    <input type="submit" value="Small batch" />
</form>

<form method="post" action="/admin/updatebayes/index/1/10">
    <input type="submit" value="Quick test" />
</form>

<tal:block tal:condition="exists:done">
<h2>Done</h2>
<p>Processed ${done} posts. Failed to process ${failed} posts.</p>
<p>Because of server load pauses between posts took ${waited} seconds, which is ${waitperpost}s per post.</p>
</tal:block>
