{include file="header.tpl"}
{include file="sidebar.tpl"}

{include file="columnleftstart.tpl"}

<h2>{$heading}</h2>
<div id="friendslistcontainer">
    {$form}
{if $users}
    <table id="friendslist">
    {foreach from=$users item=user}
        <tr class="r{cycle values=1,0}">
        {include file="user/user.tpl" user=$user page='find'}
        </tr>
    {/foreach}
    </table>
</div>
<div class="center">
    {$pagination}
{else}
</div>
{if $message}
<div class="message">
    {$message}
</div>
{/if}
{/if}
</div>

{include file="columnleftend.tpl"}
{include file="footer.tpl"}
