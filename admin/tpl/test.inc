<tal:block tal:condition="exists:score">

<tal:block metal:use-macro="score.inc/css"/>
<h2>Test</h2>
<h3>Scored ${php:round(score[0],2)} with cert ${php:round(score[1],2)}</h3>
<p tal:content="score/2" />
<tal:block metal:use-macro="score.inc/formatpost"/>

</tal:block>

<tal:block tal:condition="not:exists:score">
    <h2>Test posts</h2>
    <form method="post">
    <p>
    <label>Number of posts <input type="number" name="num" value="100" /></label>
    <input type="submit" value="Test" />
    </p></form>
</tal:block>
