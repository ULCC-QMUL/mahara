{if !$hidetitle}<h3>{str tag='book' section='artefact.resume'}
{if $controls}
    {contextualhelp plugintype='artefact' pluginname='resume' section='addbook'}
{/if}
</h3>{/if}
<table id="booklist{$suffix}" class="tablerenderer resumefour resumecomposite">
    <colgroup width="25%" span="1"></colgroup>
    <thead>
        <tr>
            <th class="resumedate">{str tag='date' section='artefact.resume'}</th>
            <th>{str tag='title' section='artefact.resume'}</th>
            {if $controls}
            <th class="resumecontrols"></th>
            <th class="resumecontrols"></th>
            <th class="resumecontrols"></th>
            {/if}
        </tr>
    </thead>
    <tbody>
        {foreach from=$rows item=row}
        <tr class="r{cycle values=0,1}">
            <td>{$row->date|escape}</td>
            <td><div class="jstitle">{$row->title|escape}</div><div class="jsdescription">{$row->description|escape}</div></td>
        </tr>
        {/foreach}
    </tbody>
</table>
{if $controls}
<div>
    <button id="addbookbutton" onclick="toggleCompositeForm('book');">{str tag='add'}</button>
    <div id="bookform" class="hidden">{$compositeforms.book}</div>
</div>
{/if}
