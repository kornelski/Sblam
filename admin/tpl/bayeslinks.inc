<h2>Banned</h2>
<ul><li tal:repeat="l linksadded" tal:content="l"/></ul>

<ul metal:define-macro="rec">
<li tal:repeat="l list">
<tal:block tal:condition="php:is_array(l)"><tal:block tal:define="list l" metal:use-macro="rec"/></tal:block>
<tal:block tal:condition="php:!is_array(l)" tal:content="l" />
</li>
</ul>

<tal:block tal:define="list result" metal:use-macro="rec" />
