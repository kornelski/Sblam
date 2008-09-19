<h2>Triggers</h2>

<form action="" method="post">
<table>
    <tr tal:repeat="t tables">
        <th tal:content="t"/>
        <td><label><input type="checkbox" value="${t}" name="drop" /> drop </label></td>
        <td><label><input type="checkbox" value="${t}" name="create" /> create </label></td>
    </tr>
</table>
<p><input type="submit" value="Execute"/></p>
</form>
