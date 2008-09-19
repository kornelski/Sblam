<style type="text/css">
th {text-align:right; padding-right:0.3em}
td strong {color:red}
</style>
	
<table tal:condition="graph" style="border-collapse:collapse; font-size:0.8em; line-height:1; margin:1em auto"><caption>${graph/totalhams} hams</caption>
    
	<tr tal:repeat="l graph/lines"><th>${l/time}s</th><td>${l/percent}%</td>	    
	    <td style="padding:0;white-space:nowrap;"><div style="background:#faa;height: ${l/height}px;width: ${l/spamwidth}px"><div style="background:#292;height:${l/height}px;width: ${l/hamwidth}px"></div></div></td>
	</tr>
</table>

	
  <table><tr><th>Number of posts: </th><td>${total} (${tempo}/day)</td></tr>
  <tr><th>Unverified posts: </th><td>${unverified} (${tough} tough ones)</td></tr>
  <tr><th>Unadded posts: </th><td>${unadded} unadded</td></tr>
  <tr><th>Hams:</th><td> ${hams} (${hamsprc}%)</td></tr>
  <tr><th>false negatives</th><td>${fhams} (${phams}%)</td></tr>
  <tr><th>Spams: </th><td>${spams} (${spamsprc}%)</td></tr>
  <tr><th>false positives </th><td> <strong>${fspams} (${pspams}%)</strong></td></tr>
  <tr><th>Accuracy:</th><td>${accuracy}% (${unsure}% unsure)</td></tr>  
  </table>
