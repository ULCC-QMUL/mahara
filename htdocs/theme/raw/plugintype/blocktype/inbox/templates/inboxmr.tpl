{if !$items}
    <div class="panel-body">
        <p class="lead text-small">{str tag=nomessages section=blocktype.inbox}</p>
    </div>
{else}
    <div id="inboxblock" class="inboxblock list-group">
        {foreach from=$items item=i}
        <div class="has-attachment panel-default collapsible list-group-item">
            {if $i->message}
                <a class="collapsed link-block{if !$i->read} unread{/if}" data-toggle="collapse" href="#message_content_{$i->type}_{$i->id}" aria-expanded="false">
                    {if $i->type == 'usermessage'}
                        <span class="icon icon-envelope text-default left"></span>
                    {elseif $i->type == 'institutionmessage'}
                        <span class="icon icon-university text-default left"></span>
                    {elseif $i->type == 'feedback'}
                        <span class="icon icon-comments text-default left"></span>
                    {elseif $i->type == 'annotationfeedback'}
                        <span class="icon icon-comments-o text-default left"></span>
                    {else}
                        <span class="icon icon-wrench text-default left"></span>
                    {/if}
                    <span class="sr-only">{$item->strtype}</span>
                    {$i->subject|truncate:50}
                    <span class="icon icon-chevron-down collapse-indicator pull-right text-small"></span>
                </a>
            {/if}
            <div class="collapse" id="message_content_{$i->type}_{$i->id}">
                {if $i->message}
                    <p class="content-text">{$i->message|safe}</p>
                    {if $i->url}
                    <a href="{$WWWROOT}{$i->url}" class="text-small">
                        {if $i->urltext}{$i->urltext}{else}{str tag="more..."}{/if} <span class="icon icon-arrow-right mls icon-sm"></span>
                    </a>
                    {/if}
                    {if $i->canreplyall}
                    <a title="{str tag=replyall section=module.multirecipientnotification}" href="{$WWWROOT}module/multirecipientnotification/sendmessage.php?replyto={$i->id}&returnto=outbox" class="text-small">
                        <span class="icon icon-reply-all icon-sm left"></span>
                        {str tag='replyall'  section='module.multirecipientnotification'}
                    </a>
                    {elseif $i->canreply}
                        <a title="{str tag=reply section=module.multirecipientnotification}" href="{$WWWROOT}module/multirecipientnotification/sendmessage.php?id={$i->fromusr}{if !$i->startnewthread}&replyto={$i->id}{/if}&returnto=outbox" class="text-small">
                            <span class="icon icon icon-reply left icon-sm"></span>
                            {str tag='reply' section='module.multirecipientnotification'}
                        </a>
                    {/if}
                {elseif $i->url}
                    <a href="{$WWWROOT}{$i->url}" class="text-small">{$i->subject}</a>
                {else}
                    {$i->subject}
                {/if}
            </div>
        </div>
        {/foreach}
    </div>
    {if $morelink}
    <div class="artefact-detail-link">
        <a class="link-blocktype last" href="{$morelink}">
        <span class="icon icon-arrow-circle-right"></span>
        {str tag=More section=blocktype.inbox}</a>
    </div>
    {/if}
{/if}