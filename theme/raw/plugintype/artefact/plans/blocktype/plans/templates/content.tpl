{foreach from=$plans item=plan}
<div class="panel-body flush">
    {if $editing}
        <div style="float:right">
            <a href="{$WWWROOT}artefact/plans/edit/index.php?id={$plan.id}">{str tag='editplan' section='artefact.plans'}</a> |
            <a href="{$WWWROOT}artefact/plans/delete/index.php?id={$plan.id}">{str tag='deleteplan' section='artefact.plans'}</a> |
            <a href="{$WWWROOT}artefact/plans/new.php?id={$plan.id}">{str tag='addtask' section='artefact.plans'}</a>
        </div>
    {/if}
    {if count($plans) > 1}
    <h4>{$plan.title}</h4>
    {/if}
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
                    <script type="application/javascript">
                    jQuery(function($) {literal}{{/literal}
                        {$tasks.pagination_js|safe}
                        $('#plans_page_container_{$blockid}_plan{$tasks.planid}').removeClass('hidden');
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
