{include file="header.tpl"}
{if $cancreate}
<div class="btn-top-right btn-group btn-group-top">
    <a href="{$WWWROOT}group/edit.php" class="btn btn-default creategroup">
        <span class="icon icon-lg icon-plus left"></span>
        {str tag="creategroup" section="group"}
    </a>
</div>
{/if}
{$form|safe}
{if $groups}
<div class="panel panel-default view-container">
    <h2 class="panel-heading">{str tag=Results}</h2>
    <div id="mygroups" class="list-group">
        {foreach from=$groups item=group}
            {include file="group/group.tpl" group=$group returnto='mygroups'}
        {/foreach}
    </div>
</div>
{$pagination|safe}
{else}
<p class="no-results">
    {str tag="trysearchingforgroups" section="group" args=$searchingforgroups}
</p>
{/if}
{include file="footer.tpl"}