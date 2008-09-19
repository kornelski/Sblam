
<tal:block tal:condition="exists:bayesresult">
	<h2 tal:condition="php:!is_null(isspam) and isspam">Added spam</h2>
	<h2 tal:condition="php:!is_null(isspam) and !isspam">Added ham</h2>
	<h2 tal:condition="php:is_null(isspam)">Test result</h2>
	<p tal:condition="addtext">Added words</p>
	<p tal:condition="linksadded">Added links: <tal:block tal:repeat="l linksadded">${l} </tal:block></p>

	<p tal:condition="spamvertresult">Links scored ${php:round(spamvertresult[0],2)} with cert ${php:round(spamvertresult[1],2)} for ${spamvertresult/3}</p>	
	<p tal:condition="bayesresult">Words scored ${php:round(bayesresult[0],2)} with cert ${php:round(bayesresult[1],2)} for ${bayesresult/3}</p>	
</tal:block>

<h2>Add words/links</h2>

<form action="" method="post">
	<div><textarea name="stuff" cols="80" rows="25"></textarea></div>
	<div><label><input type="number" value="50" name="howmuch"/> × number of posts</label></div>
	<div><label><input type="checkbox" value="nowords"/> only links</label></div>
	<div>
		<input style='color:#060' type="submit" value="ham" name="ham"/>
		<input style='color:#700' type="submit" value="spam" name="spam"/>
		<input type="submit" value="check" name="test"/>
	</div>
</form>
