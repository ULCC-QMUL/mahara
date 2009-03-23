{include file="header.tpl"}
{include file="sidebar.tpl"}

{include file="columnleftstart.tpl"}

{include file="user/simpleuser.tpl" user=$user}

{if $replyto}
<h4>{$replyto->subject|escape}:</h4>
<br>
{foreach from=$replyto->lines item=line}
{$line|escape}<br>
{/foreach}
{/if}

{$form}

{include file="columnleftend.tpl"}
{include file="footer.tpl"}
