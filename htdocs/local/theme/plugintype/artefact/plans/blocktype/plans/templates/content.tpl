{foreach from=$plans item=plan name='plans'}
<div class="panel-body flush">
    {if $editing}
        <div style="float:right">
            <a href="{$WWWROOT}artefact/plans/edit/index.php?id={$plan.id}">{str tag='editplan' section='artefact.plans'}</a> |
            <a href="{$WWWROOT}artefact/plans/delete/index.php?id={$plan.id}">{str tag='deleteplan' section='artefact.plans'}</a> |
            <a href="{$WWWROOT}artefact/plans/new.php?id={$plan.id}">{str tag='addtask' section='artefact.plans'}</a>
        </div>
    {/if}
    {if $.foreach.plans.total > 1}
    <a class="link-block collapsed" href="#plan{$plan.id}-expand" data-toggle="collapse" aria-expanded="false" aria-controls="plan{$plan.id}-expand"><strong>{$plan.title}</strong><span class="icon icon-chevron-down right collapse-indicator pull-right" role="presentation" aria-hidden="true"></span></a>
    {else}
    <strong>{$plan.title}</strong>
    {/if}

    <div id="plan{$plan.id}-expand"{if $.foreach.plans.total > 1} class="collapse"{/if}>
        <p>{$plan.description}</p>
        {if $plan.tags}
        <div class="tags">
            <strong>{str tag=tags}:</strong> {list_tags owner=$plan.owner tags=$plan.tags}
        </div>
        {/if}
        {if $plan.numtasks != 0}
            {foreach from=$alltasks item=tasks}
                {if $tasks.planid == $plan.id}
                    <div id="tasklist_{$blockid}_plan{$tasks.planid}" class="list-group list-unstyled">
                        {$tasks.tablerows|safe}
                    </div>
                    {if $tasks.pagination}
                        <div id="plans_page_container_{$blockid}_plan{$tasks.planid}">
                            {$tasks.pagination|safe}
                        </div>
                        <script>
                        addLoadEvent(function() {literal}{{/literal}
                            {$tasks.pagination_js|safe}
                            removeElementClass('plans_page_container_{$blockid}_plan{$tasks.planid}');
                        {literal}}{/literal});
                        </script>
                    {/if}
                    <a href="{$plan.details}" class="detail-link link-blocktype"><span class="icon icon-link" role="presentation" aria-hidden="true"></span> {str tag=detailslinkalt section=view}</a>
                {/if}
            {/foreach}
        {else}
            <div class="lead text-center content-text">{str tag='notasks' section='artefact.plans'}</div>
            <a href="{$plan.details}" class="detail-link link-blocktype"><span class="icon icon-link" role="presentation" aria-hidden="true"></span> {str tag=detailslinkalt section=view}</a>
        {/if}
    </div>
</div>
{/foreach}
