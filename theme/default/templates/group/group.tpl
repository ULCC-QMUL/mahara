<h3><a href="{$WWWROOT}group/view.php?id={$group->id|escape}">{$group->name|escape}</a></h3>
<h6>{foreach name=admins from=$group->admins item=id}<a href="{$WWWROOT}user/view.php?id={$id|escape}">{$id|display_name|escape}</a>{if !$smarty.foreach.admins.last}, {/if}{/foreach}</h6>
{$group->description}
<ul>
<li id="groupmembers">{str tag="memberslist" section="group"}
{foreach name=members from=$group->members item=member}
   <a href="{$WWWROOT}user/view.php?id={$member->id|escape}" class="links-members">{$member->name|escape}</a>{if !$smarty.foreach.members.last}, {/if}
{/foreach}
{if $group->membercount > 3}<a href="{$WWWROOT}group/members.php?id={$group->id|escape}" class="links-members">...</a>{/if}
</li>

{include file="group/groupuserstatus.tpl" group=$group returnto='find'}

</ul>
