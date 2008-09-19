
<form action="/admin/apc/clear" method="post">
<input type="submit" value="Clear user cache"/>
</form>
<h2>File cache</h2>
<p>Used ${info/num_entries} of ${info/num_slots} slots, ${info/expunges} expunges, ${info/num_hits} hits / ${info/num_misses} 
misses.</p>
<p>Allocated ${sma/num_seg} * ${sma/seg_size}, unused ${sma/avail_mem}</p>
<tal:block metal:use-macro="tables.inc/table" tal:define="table info/cache_list" />
