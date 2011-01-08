<h2>Spam score</h2>

<tal:block metal:define-macro="css">
<style type="text/css">
#spam-score-lists {overflow:hidden;}
#spam-list, #ham-list {float:left; width:50%; list-style:none}
#spam-score-lists .list-empty {font-weight:bold; font-size: 3em; color: #eee; text-align:center;}

#spam-list ul, #ham-list ul {margin:0 1em;padding:0;}

.spam-entry {border: 1px solid #ccc; background: #f5f5f5; margin:0.5em 0; padding:0.4em; list-style:none; overflow:hidden;}
.spam-entry p {margin:0 0 1em;}
.spam-content {margin-top:1em; max-height: 15em; overflow:auto}
.worktime {float:right;}
.spam-entry a {color: #88d;}
.spam-entry a:visited {color: #f8f;}
.spam-entry a:hover {color: #00f;}

.spam-entry code {font: inherit; color: #999}
.spam-entry var {border-bottom:1px solid #f80; font-style:normal;}

  .spam-buttons {float:left; padding-right:0.5em; margin-bottom:0.2em; white-space:nowrap;}
  .spam-buttons input {height:2.3em}
  .spam-content {clear:left;}
  .spam-reason {margin-bottom:0.6em; font-size: 0.9em; color: #500}
  .spam-score {color:black;font-weight:bold;}
.spam-ips {float:right;}
.spam-ips img {background:gray;border:1px solid #888;}

.spam-ips span + span::before {content: " ← ";}

.spam-error {border-color:red;}

table.heads {font-size: 80%;}
table.heads td,table.heads th  {vertical-align:top;}
table.heads th {text-align:right; font-weight:normal; white-space:nowrap;}
table.heads th:after {content:":";}
input[name=ham] {color: #060}
input[name=spam] {color: #600}

a.kill {text-decoration:none;}
a.kill:visited {color:red;}

h3 {margin-top:0;}
h3 input {margin-right:1.5em; font-size:0.6em; float:right}

.id-acc {font-size:0.9em;}
a[title=msg] {text-decoration:none; font-size:1.33em;line-height:0.8;}
</style>
</tal:block>

<tal:block metal:use-macro="css"/>

<script type="text/javascript">/*<![CDATA[*/

function async_request(url,okcallback,failcallback)
{
  var timer = setTimeout(failcallback,10000);

  var xhr = new XMLHttpRequest();

  xhr.onreadystatechange = function()
  {
    if (xhr.readyState == 4) {clearTimeout(timer); okcallback(xhr);}
  }
  xhr.open("POST", url, true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhr.setRequestHeader("Content-Length",0);
  xhr.send('');

}

var recently_used_ids = [];

function spam_init_one(item, isspam){

  var list = item.parentNode;
  if (!list) return; // zonk

  var ajaxify = function() {
    count = 1;
    if (list.childNodes.length < 10) {count = 3;}
    else if (list.childNodes.length < 6) {count = 5;}
    else if (list.childNodes.length < 4) {count = 10;}

	item.parentNode.appendChild(item); // move to the end
    item.style.display='none';
    async_request('/admin/score/' + escape(this.name) + '/' + escape(this.form.elements.id.value) + '/' + (isspam?'spam':'ham') + '/' + count,
      function(xhr)
      {

        var newElements = [];
        if (xhr.responseXML && xhr.responseXML.documentElement)
        {
          var temp = xhr.responseXML.documentElement.childNodes;
          for(var i=0; i < temp.length; i++)
          {
            if (temp[i].nodeType != 1) continue;
            newElements[newElements.length] = temp[i];
          }
        }
        else
        {
          var temp = document.createElement('div');
          temp.innerHTML = xhr.responseText;
          newElements[0] = temp.firstChild;
        }

        list && list.removeChild(item); item=null;

        for(var i=0; i < newElements.length; i++)
        {
          var lid = newElements[i].getAttribute('id');
          if (!document.getElementById( lid ) && !recently_used_ids[ lid ])
          {
              recently_used_ids[ lid ] = true;
              var newElementImported = document.importNode(newElements[i],true);
            list.appendChild(newElementImported);
            spam_init_one(newElementImported, isspam);
          }
        }
      },
      function()
      {
        item.parentNode.appendChild(item);item.style.display='block'; item.className += ' spam-error'
      });
    return false;
  }

  var form = item.getElementsByTagName('form')[0];

  if (!form)return;

  form.elements.ham.onclick = ajaxify;
  form.elements.spam.onclick = ajaxify;
}


function spam_init_list(lis, isspam)
{
  for(var i=0; i < lis.length; i++)
  {
    spam_init_one(lis[i], isspam);
  }
}

/*]]>*/</script>


<tal:block metal:define-macro="formatpost"><li xmlns="http://www.w3.org/1999/xhtml" class="spam-entry" tal:attributes="id php:post instanceof SblamBasePost ? post.getPostId() : NULL">

    <span class="worktime" tal:condition="post/worktime" tal:content="php:round(post.worktime/1000,1) . '\'s'"/>
    <form action="" method="post">
      <tal:block tal:condition="php:post instanceof SblamBasePost">
        <p class="spam-buttons"><input type="hidden" name="id" value="${post/getPostId}"/><input type="submit" name="spam" value="Spam"/> <input type="submit" name="ham" value="Ham"/></p>
        <span class="spam-score" tal:define="scorecert post/getSpamScore" title="${scorecert/0}*${scorecert/1}">${php:round(abs(scorecert[0]*100))} ${php:scorecert[0] lt 0?'ham':'spam'}${php:post.bayesadded?' added':''}</span>
      </tal:block>

      <p class="spam-content" tal:content="structure php:PostFormatter::highlight(post.getRawContent())"/>

      <tal:block tal:condition="post/getAuthorName" tal:content="structure php:PostFormatter::highlight('by ' . post.getAuthorName())"/>
      <i tal:condition="not:post/getAuthorName">anonymous</i>

      <tal:block tal:condition="php:post.getAuthorURI() or post.getAuthorEmail()">
        (
        <tal:block tal:condition="post/getAuthorURI" tal:content="structure php:PostFormatter::highlight(post.getAuthorURI())"/>
        <tal:block tal:condition="post/getAuthorEmail"><x tal:replace="'; '" tal:condition="post/getAuthorURI"/>
${structure php:PostFormatter::highlight(post.getAuthorEmail())} <a
href="/admin/bayeslinks/kill/${php:urlencode(post.getAuthorEmail())}">&#x2620;</a></tal:block>
        )
      </tal:block>

      <table class="heads" tal:define="headers post/getHeaders"><?php ksort($ctx->headers); ?>
        <tr tal:repeat="dat headers"><th tal:content="php:ucwords(strtr(strtolower(preg_replace('!^HTTP_!','', repeat.dat.key )),'_','-'))"/><td tal:content="dat"/></tr>
      </table>

      <span class="spam-ips">
        <tal:block tal:repeat="ip post/getAuthorIPs">
          <tal:block tal:define="rev1 php:SblamURI::gethostbyaddr(ip); rev php: rev1 ? rev1 : ip">
            <span tal:omit-tag="not:repeat/ip/start" title="${ip}"><em tal:omit-tag="repeat/ip/start" title="${ip} forwarded-for">
              <a href="/admin/plonker/block/${php:urlencode(ip)}"> <img width="12" height="10" src="http://api.hostip.info/flag.php?ip=${php:urlencode(ip)}" /></a> ${rev}
            </em></span>
          </tal:block>
        </tal:block>
      </span>
      <tal:block tal:condition="php:post instanceof SblamBasePost">
        <span class="id-acc"><a href="/admin/test/id/${post/getPostId}" title="test" tal:content="post/getPostId"/> <a tal:condition="post/account" title='list' href='/admin/autologin/account/${post/account}' tal:content="post/account"/> <a tal:condition="post/account" title='msg' href="/admin/accs/msg/${post/account}">✉</a></span>
        <p class="spam-reason" tal:content="structure php:PostFormatter::formatreason(post.getSpamReason())"/>
        <pre tal:condition="post/profiling" tal:content="post/profiling" />
      </tal:block>
    </form></li></tal:block>

<tal:block metal:define-macro="queue">
  <p tal:condition="not:queue" class="list-empty">Nothing to see here</p>
  <ul tal:condition="queue"><tal:block tal:repeat="post queue"><tal:block metal:use-macro="formatpost" /></tal:block></ul>
</tal:block>

<div id="spam-score-lists">
  <div id="spam-list">
    <form method="post" action=""><h3>Spams <input type="submit" name="bannedspams" value="Remove banned" /> <input type="submit" name="bannedspamslite" value="Sort banned"
/></h3></form>
    <tal:block metal:use-macro="queue" tal:define="queue spams" />
  </div>
  <script type="text/javascript">
    spam_init_list(document.getElementById('spam-list').getElementsByTagName('li'), true);
  </script>
  <div id="ham-list">
    <form method="post" action=""><h3>Hams <input type="submit" name="bannedhams" value="Move banned by IP to top" /></h3></form>
    <tal:block metal:use-macro="queue" tal:define="queue hams" />
  </div>
  <script type="text/javascript">/*<![CDATA[*/
    spam_init_list(document.getElementById('ham-list').getElementsByTagName('li'), false);

	document.addEventListener('keypress',function(e){
		if (e.keyCode == 104 || e.keyCode == 115)
		{
			var isspam = e.keyCode==115;
			var el,form,list = isspam ? 'spam-list' : 'ham-list';
			if ((el = document.getElementById(list)) && (form = el.getElementsByTagName('form')[1]))
			{

				form.elements[isspam?'spam':'ham'].onclick();
				e.stopPropagation(); e.preventDefault();
			}
		}
	},true);
/*]]>*/</script>
</div>
