{include file="header.tpl"}
<h2>{$subheading|escape}</h2>

<div class="message">{$deleteform}</div>

{include file="interaction:forum:simplepost.tpl" post=$post groupadmins=$groupadmins}

{include file="footer.tpl"}
