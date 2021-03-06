{foreach from=$tasks.data item=task}
    {if $task->completed == -1}
        <div class="task-item plan_incomplete list-group-item {if $task->description}list-group-item-default{/if}">

            {if $task->description}<a class="link-block collapsed" href="#expand-task-{$task->id}" data-toggle="collapse" aria-expanded="false" aria-controls="expand-task-{$task->id}">{/if}

                <span class="overdue-task">
                    <span class="icon icon-times text-danger icon-lg left" role="presentation" aria-hidden="true" {if $editing}onclick="changeCheckBox(this, '{$task->id}')"{/if}></span>
                    <span class="text-danger">{$task->title}</span> -
                    <span class="text-small text-midtone">
                        {str tag='completiondate' section='artefact.plans'}: {$task->completiondate}
                    </span>
                    {if $task->description}
                    <span class="icon icon-chevron-down right collapse-indicator pull-right" role="presentation" aria-hidden="true"></span>
                    {/if}

                    {if $editing}
                    <div class="pull-right controls">
                        <a href="{$WWWROOT}artefact/plans/edit/task.php?id={$task->id}" title="{str tag='edit' section='mahara'}"><span class="icon icon-cog text-default"></span></a>
                        <a href="{$WWWROOT}artefact/plans/delete/task.php?id={$task->id}" title="{str tag='delete' section='mahara'}"><span class="icon icon-remove text-danger"></span></a>
                    </div>
                    {/if}
                </span>
            {if $task->description}</a>{/if}

            {if $task->description}
            <div class="collapse" id="expand-task-{$task->id}">
                 <div class="panel-body">
                    {$task->description|clean_html|safe}
                    {if $task->tags}
                    <div class="tags">
                        <strong>{str tag=tags}:</strong> {list_tags owner=$task->owner tags=$task->tags}
                    </div>
                    {/if}
                </div>
            </div>
            {/if}
        </div>
    {else}
        <div class="task-item list-group-item {if $task->description}list-group-item-default{/if}">

            {if $task->description}<a class="link-block collapsed" href="#expand-task-{$task->id}" data-toggle="collapse" aria-expanded="false" aria-controls="expand-task-{$task->id}">{/if}

                <span class="complete-task">
                    {if $task->completed == 1}
                        <span class="icon icon-check-square-o icon-lg text-success left" role="presentation" aria-hidden="true" {if $editing}onclick="changeCheckBox(this, '{$task->id}')"{/if}></span>
                        <span class="sr-only">{str tag=completed section=artefact.plans}</span>
                    {else}
                        <span class="icon-square-o icon icon-lg text-midtone left" role="presentation" aria-hidden="true" {if $editing}onclick="changeCheckBox(this, '{$task->id}')"{/if}></span>
                        <span class="sr-only">{str tag=incomplete section=artefact.plans}</span>
                    {/if}

                    <span class="text-default">{$task->title}</span> -
                    <span class="text-midtone text-small">
                        {str tag='completiondate' section='artefact.plans'}: {$task->completiondate}
                    </span>

                    {if $task->description}
                    <span class="icon icon-chevron-down right collapse-indicator pull-right" role="presentation" aria-hidden="true"></span>
                    {/if}

                    {if $editing}
                    <div class="pull-right controls">
                        <a href="{$WWWROOT}artefact/plans/edit/task.php?id={$task->id}" title="{str tag='edit' section='mahara'}"><span class="icon icon-cog text-default"></span></a>
                        <a href="{$WWWROOT}artefact/plans/delete/task.php?id={$task->id}" title="{str tag='delete' section='mahara'}"><span class="icon icon-remove text-danger"></span></a>
                    </div>
                    {/if}

                </span>

            {if $task->description}</a>{/if}

            {if $task->description}
            <div class="collapse" id="expand-task-{$task->id}">
                <div class="panel-body">

                    {$task->description|clean_html|safe}

                    {if $task->tags}
                    <div class="tags">
                        <strong>{str tag=tags}:</strong> {list_tags owner=$task->owner tags=$task->tags}
                    </div>
                    {/if}

                </div>
            </div>
            {else}
                {if $task->tags}
                    <div class="tags">
                        <strong>{str tag=tags}:</strong> {list_tags tags=$task->tags owner=$task->owner}
                    </div>
                {/if}
            {/if}
        </div>
    {/if}
{/foreach}
