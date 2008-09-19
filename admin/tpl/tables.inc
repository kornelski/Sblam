<tal:block metal:define-macro="table">
	<table class="${class | 'plaintable sortable'}" tal:condition="php:count(table)">
		<thead><tr><th tal:repeat="h php:reset(table)" tal:content="php:strtr(repeat.h.key,'_',' ')" /></tr></thead>
		<tbody><tr tal:repeat="row table"><td tal:repeat="d row" tal:content="d"/></tr></tbody>		
	</table>
</tal:block>
