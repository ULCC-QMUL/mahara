{include file="header.tpl"}

{include file="columnfullstart.tpl"}
<div id="edituser">
    <h2><a href="{$WWWROOT}user/view.php?id={$user->id}">{$user->firstname} {$user->lastname} ({$user->username})</a></h2>
    {if !empty($loginas)}
      <div><a href="{$WWWROOT}admin/users/changeuser.php?id={$user->id}">{$loginas}</a></div>
    {/if}
    {if !$suspended}
      <h3>{str tag="suspenduser" section="admin"}</h3>
    {else}
      <h4>{$suspendedby|escape}</h4>
      {if $user->suspendedreason}
      <div><strong>{str tag="suspendedreason" section="admin"}:</strong></div>
      <div>{$user->suspendedreason}</div>
      {/if}
    {/if}
    {$suspendform}
    <h3>{str tag="siteaccountsettings" section="admin"}</h3>
    {$siteform}
    {if ($institutions)}
    <h3>{str tag="institutionsettings" section="admin"}</h3>
    {$institutionform}
    {/if}
</div>
{include file="columnfullend.tpl"}
{include file="footer.tpl"}

