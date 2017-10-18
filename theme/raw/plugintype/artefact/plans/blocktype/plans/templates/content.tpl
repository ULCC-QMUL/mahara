{foreach from=$plans item=plan}
<div class="panel-body flush">
    {if $editing}
        <div style="float:right">
            <a href="{$WWWROOT}artefact/plans/edit/index.php?id={$plan.id}">Edit plan</a> |
            <a href="{$WWWROOT}artefact/plans/new.php?id={$plan.id}">Add task</a>
        </div>
    {/if}
    <strong>{$plan.title}</strong>
    <p>{$plan.description}</p>

    {if $plan.tags}
    <div class="tags">
        <strong>{str tag=tags}:</strong> {list_tags owner=$plan.owner tags=$plan.tags}
    </div>
    {/if}

    {if $plan.numtasks != 0}
        {foreach from=$alltasks item=tasks}
            {if $tasks.planid == $plan.id}
                <div id="tasklist_{$blockid}" class="list-group list-unstyled">
                    {$tasks.tablerows|safe}
                </div>
                {if $tasks.pagination}
                    <div id="plans_page_container_{$blockid}" class="hidden">
                        {$tasks.pagination|safe}
                    </div>
                    <script>
                    addLoadEvent(function() {literal}{{/literal}
                        {$tasks.pagination_js|safe}
                        removeElementClass('plans_page_container_{$blockid}', 'hidden');
                    {literal}}{/literal});
                    </script>
                {/if}
            {/if}
        {/foreach}
    {else}
        <div class="lead text-center content-text">{str tag='notasks' section='artefact.plans'}</div>
    {/if}
    <a href="{$plan.details}" class="detail-link link-blocktype"><span class="icon icon-link" role="presentation" aria-hidden="true"></span> {str tag=detailslinkalt section=view}</a>
</div>
{/foreach}
