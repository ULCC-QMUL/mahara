{if !$hidetitle}<h3>{str tag='membership' section='artefact.resume'}
{if $controls}
    {contextualhelp plugintype='artefact' pluginname='resume' section='addmembership'}
{/if}
</h3>{/if}
<table id="membershiplist{$suffix}" class="tablerenderer resumefive resumecomposite">
    <colgroup width="25%" span="2"></colgroup>
    <thead>
        <tr>
            <th class="resumedate">{str tag='startdate' section='artefact.resume'}</th>
            <th class="resumedate">{str tag='enddate' section='artefact.resume'}</th>
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
            <td>{$row->startdate|escape}</td>
            <td>{$row->enddate|escape}</td>
            <td><div class="jstitle">{$row->title|escape}</div><div class="jsdescription">{$row->description|escape}</div></td>
        </tr>
        {/foreach}
    </tbody>
</table>
{if $controls}
<div>
    <button id="addmembershipbutton" onclick="toggleCompositeForm('membership');">{str tag='add'}</button>
    <div id="membershipform" class="hidden">{$compositeforms.membership}</div>
</div>
{/if}
