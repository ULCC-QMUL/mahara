<table>
<colgroup width="50%" span="2"></colgroup>
{foreach from=$fields key='field' item='value'}
    <tr>
        <th>{$field|escape}</th>
        <td>{$value|escape}</td>
    </tr>
{/foreach}
</table>
