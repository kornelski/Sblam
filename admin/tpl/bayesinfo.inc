<h2>Total bayes</h2>

<style type="text/css">
  .wordlist {float:left; margin: 1em 2em 1em 0; border-collapse:collapse; border: 1px solid #ccc; }
  .wordlist td {white-space:nowrap;border-top: 1px solid #ccc; padding: 0.2em;}
  .wordlist .rating {text-align:center;}
  .wordlist .rating span {display:none;}
  .wordlist .the-word {color: #555;font-weight:bold;}
  .wordlist .word-ham .the-word {color: #575;}
  .wordlist .word-spam .the-word {color: #755;}
  .wordlist .word-ham.strong .the-word {color: #080;}
  .wordlist .word-spam.strong .the-word {color: #900;}

  .wordlist .popular {font-size:0.8em;}
  .wordlist input {padding:0; border:0; background:white; cursor:pointer; color: #aaa}
</style>

<p tal:condition="php:total['ham']+total['spam']">Database is built from ${php:total['ham'] + total['spam']} posts (${totalwordsf} words), of which ${totalspamf} (${php:round(100* total['spam']/(total['ham']+total['spam']),1)}%) are spam</p>

<tal:block metal:define-macro="bayestable">
  
  <table class="wordlist"><caption tal:content="caption"/>
  
  <tr tal:condition="not:table" class="wordlist-empty"><td>Empty</td></tr>
  <tr tal:condition="table">
    <th tal:condition="exists:rating">Rating</th>
    <th>Word</th><th>Popular</th>
  </tr>
  <tr tal:repeat="word table" class="${php:(word['rate']<-0.1?'word-ham':(word['rate']>0.1?'word-spam':''))}  ${php:(abs(word['rate'])>0.6?'strong':'')}">
      
      <td tal:condition="exists:rating" class="rating">${php:round(abs(100*word['rate']))}% <span tal:content="php:word['rate']<0?'ham':'spam'"/></td>
      <td class="the-word">
      <a tal:condition="exists:showkill" href="/admin/${pagename}/neuter/${word/wordh}">&darr;</a>
      ${word/word | php:'[' . urlencode(word['wordh']) . ']'}
      </td>
      <td class="popular">${php:round((word['spammy'] + word['hammy']) /3 ,2)}%</td>
  </tr>
</table>
</tal:block>


<tal:block metal:use-macro='bayestable' tal:define="table recentham; caption 'Recently added hams'"/>
<tal:block metal:use-macro='bayestable' tal:define="table recentspam;  caption 'Recently added spams'"/>
<tal:block metal:use-macro='bayestable' tal:define="table recentmod; caption  'Recently learned'; rating '1'; showkill '1'"/>
<tal:block metal:use-macro='bayestable' tal:define="table hammiest; caption  'Popular in hams'; rating '1'; showkill '1'"/>
<tal:block metal:use-macro='bayestable' tal:define="table spammiest; caption  'Popular in spams'; rating '1'; showkill '1'"/>
<tal:block metal:use-macro='bayestable' tal:define="table strongest; caption  'Strongest words'; rating '1'; showkill '1'"/>
<tal:block metal:use-macro='bayestable' tal:define="table oldest;  caption 'Least used words'"/>
<tal:block metal:use-macro='bayestable' tal:define="table useless;  caption 'Useless words'; showkill '1'"/>

