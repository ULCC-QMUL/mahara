    <h3>{str tag="networkservers" section="auth.xmlrpc"}{contextualhelp plugintype='auth' pluginname='xmlrpc' section='networkservers'}</h3>
        <div class="sidebar-content">
{if $data}
    <ul id="sitemenu">
{foreach from=$data item=peer}
{if $peer.instance != $userauthinstance}
{if !$MNETUSER}
        <li><a href="{$WWWROOT}auth/xmlrpc/jump.php?wr={$peer.wwwroot|escape}&amp;ins={$peer.instance|escape}">{$peer.name|escape}</a></li>
{/if}
{else}
        <li><a href="{$peer.wwwroot|escape}">{$peer.name|escape}</a></li>
{/if}
{/foreach}
    </ul>
{/if}
</div>