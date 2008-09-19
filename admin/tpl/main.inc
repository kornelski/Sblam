
<tal:block metal:fill-slot="style">
form p abbr {font-variant:small-caps; text-transform:lowercase}
</tal:block>


<p><strong>Load:</strong> ${load}.
Last update ping: ${php:round((time()-apc_fetch('update_active'))/60,1)}m.</p>

<form action="" method="post">
    <h2>Posts archive</h2>
    <p>~${php:round(stats['posts']/1000)}<abbr>K</abbr> posts available. ${php:round(stats['posts_archive']/1000)}<abbr>K</abbr> posts archivized.</p>
    <p tal:condition="exists:archive">
        Moved ${archive/moved} posts to archive. <strong tal:condition="not:archive/bayes_added">Not all moved posts were added to bayes base.</strong>
    </p>
    <p tal:condition="php:stats['posts'] GT 5000" ><input type="submit" name="archive" value="Move posts to archive" /> <label><input type="checkbox" name="archive_unadded" /> Move posts that weren't added to bayes base.</label></p>
</form>    

<form tal:condition="missing_tables" method="post">

    <h2>Warning! The following tables have to be created:</h2>
    <p>    
        <div tal:repeat="t missing_tables"><label><input type="checkbox" checked="checked" name="tables[]" value="${t}" /> ${t}</label></div>
    </p>
    <p><input type="submit" value="Create" /></p>
</form>

<h2>MySQL Status</h2>
<form method="post" action="">
	<table class="plaintable sortable" tal:condition="php:count(processes)">
		<thead><tr><th tal:repeat="h php:reset(processes)" tal:content="php:strtr(repeat.h.key,'_',' ')" /></tr></thead>
		<tbody><tr tal:repeat="row processes">
		    <tal:block tal:repeat="d row">
		    <td tal:condition="php:repeat.d.key != 'Id'" tal:content="d"/>
		    <td tal:condition="php:repeat.d.key == 'Id'"><button value="${d}" tal:content="d" type="submit" name="kill"/></td>
		    </tal:block>
		</tr></tbody>		
	</table>
</form>

<h2>Tables</h2>
<tal:block metal:use-macro="tables.inc/table" tal:define="table tablestatus" />	

<p><a href="/admin/bayes">Bayes triggers</a>.</p>
<p><a href="/admin/phpinfo">PHP Info</a>.</p>
