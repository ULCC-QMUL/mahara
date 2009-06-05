{if empty($filelist)}
<p>{str tag=nofilesfound section=artefact.file}</p>
{else}
<table id="filelist" class="tablerenderer filelist">
 <thead>
  <tr>
   <th></th>
   <th>{str tag=Name section=artefact.file}</th>
   <th>{str tag=Description section=artefact.file}</th>
   <th>{str tag=Size section=artefact.file}</th>
   <th>{str tag=Date section=artefact.file}</th>
   <th></th>
  </tr>
 </thead>
 <tbody>
  {foreach from=$filelist item=file}
    {if !$publishing || !$file->permissions || $file->can_republish}{assign var=publishable value=1}{else}{assign var=publishable value=0}{/if}
  <tr id="file:{$file->id}" class="r{cycle values=0,1} directory-item{if $file->isparent} parentfolder{/if}{if $file->artefacttype == 'folder'} folder{/if}{if !empty($highlight) && $highlight == $file->id} highlight-file{/if}{if $edit == $file->id} hidden{/if}{if !$publishable && $file->artefacttype != 'folder'} disabled{/if}" {if !$publishable && $file->artefacttype != 'folder'} title="{str tag=notpublishable section=artefact.file}"{/if}>
    <td>
      {if $editable}
      <div{if !$file->isparent} class="icon-drag" id="drag:{$file->id}"{/if}>
        <img src="{if $file->artefacttype == 'image'}{$WWWROOT}artefact/file/download.php?file={$file->id}&size=20x20{else}{$THEMEURL}images/{$file->artefacttype}.gif{/if}"{if !$file->isparent} title="{str tag=clickanddragtomovefile section=artefact.file arg1=$file->title}"{/if}>
      </div>
      {else}
        <img src="{if $file->artefacttype == 'image'}{$WWWROOT}artefact/file/download.php?file={$file->id}&size=20x20{else}{$THEMEURL}images/{$file->artefacttype}.gif{/if}">
      {/if}
    </td>
    <td class="filename">
    {assign var=displaytitle value=$file->title|str_shorten_text:34|escape}
    {if $file->artefacttype == 'folder'}
      <a href="{$querybase}folder={$file->id}{if $owner}&owner={$owner}{if $ownerid}&ownerid={$ownerid}{/if}{/if}" class="changefolder" title="{str tag=gotofolder section=artefact.file arg1=$displaytitle}">{$displaytitle}</a>
    {elseif !$publishable}
      {$displaytitle}
    {else}
      <a href="{$WWWROOT}artefact/file/download.php?file={$file->id}" target="_blank" title="{str tag=downloadfile section=artefact.file arg1=$displaytitle}">{$displaytitle}</a>
    {/if}
    </td>
    <td>{$file->description|escape}</td>
    <td>{$file->size}</td>
    <td>{$file->mtime}</td>
    <td>
    {if $editable && !$file->isparent}
      {if !isset($file->can_edit) || $file->can_edit !== 0}<button type="submit" name="{$prefix}_edit[{$file->id}]" value="{$file->id}">{str tag=edit}</button>{/if}
      {if $file->childcount == 0}<button type="submit" name="{$prefix}_delete[{$file->id}]" value="{$file->id}">{str tag=delete}</button>{/if}
    {/if}
    {if $selectable && $file->artefacttype != 'folder' && $publishable}
      <button type="submit" class="select small" name="{$prefix}_select[{$file->id}]" id="{$prefix}_select_{$file->id}" value="{$file->id}">{str tag=select}</button>
    {/if}
    </td>
  </tr>
  {if $edit == $file->id}
    {include file="artefact:file:form/editfile.tpl" prefix=$prefix fileinfo=$file groupinfo=$groupinfo}
  {/if}
  {/foreach}
 </tbody>
</table>
{/if}
