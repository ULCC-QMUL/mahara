{auto_escape off}
    <tr>
        <td style="width: 20px;" rowspan="2">
            {$formcontrols}
        </td>
        <th><label for="{$elementname}_{$artefact->id}" title="{$artefact->title|strip_tags|substr:0:60|escape}">{str tag=$artefact->artefacttype section=artefact.internal}</label></th>
    </tr>
    <tr>
        <td>{if $artefact->description}{$artefact->description}{/if}</td>
    </tr>
{/auto_escape}
