
<tal:block metal:fill-slot="style">
#preview {float:right; width:45%; margin-right:5%; border:1px solid #ddd; background:#fafafa; padding:1em 1em 3em; min-height:15em;}
#textarea {width:44%;}
.read {color:#aaa;}

td.action,td.sent {width:3em;white-space:nowrap;}
td a {font-size:1.3em; line-height:0.7; text-decoration:none;}
</tal:block>

<p>Sending to account id #${account/id}</p>
<form action="" method="post">
    <p tal:condition="msgid"><input type="hidden" name="msgid" value="${msgid}" />Editing old message. <a href="/admin/accs/msg/${account/id}">Write a new one</a>.</p>
    <p tal:condition="not:msgid">
        New <select name="type">
        <option value="">Regular message</option>
    </select></p>
    <p><div id="preview"></div><textarea id="textarea" name="msg" cols="80" rows="25" tal:content="textarea | nothing"/></p>
    <p><input type="submit" /></p>

<script type="text/javascript">/*<![CDATA[*/
    var t = document.getElementById('textarea');
    var p = document.getElementById('preview');
    var lock = false;
    t.oninput = function()
    {
        if (!lock)
        {
            lock = true;
            setTimeout(function(){
                lock = false;
                p.innerHTML = t.value.replace(/([^> \r])\r?\n/g,'$1<br />');
            },300);
        }
    }
/*]]>*/</script>

<p tal:condition="not:inbox">Inbox is empty.</p>
<table tal:condition="inbox">
    <tr><th>Action</th><th style="min-width:10em">Message</th><th>Sent</th></tr>
    <tr tal:repeat="msg inbox" tal:attributes="class php:msg['read']=='Y'?'read':NULL"><td class="action"><button name="delete" value="${msg/id}">✖</button><button name="read" value="${msg/id}">✔</button> <a href="/admin/accs/msg/${msg/account}/${msg/id}">✐</a></td><td tal:content="structure msg/message_html" /><td class="sent" tal:content="msg/sent"/></tr>
</table>
</form>