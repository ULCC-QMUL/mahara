<div class="btn-group btn-toolbar btn-group-top">
    <a class="btn btn-default {if $selected == 'content'}active{/if}" href="{$WWWROOT}view/blocks.php?id={$viewid}{if $new}&new=1{/if}" title="{str tag=editcontent section=view}">
        <span class="icon icon-lg icon-pencil"></span>
        <span class="btn-title">{str tag=editcontent section=view}</span>
    </a>
    <a class="btn btn-default {if $selected == 'layout'}active{/if}" href="{$WWWROOT}view/layout.php?id={$viewid}{if $new}&new=1{/if}" title="{str tag=editlayout section=view}">
        <span class="icon icon-lg icon-columns"></span>
        <span class="btn-title">{str tag=editlayout section=view}</span>
    </a>
    {if !$issitetemplate && can_use_skins(null, false, $issiteview)}
        <a class="btn btn-default {if $selected == 'skin'}active{/if}" href="{$WWWROOT}view/skin.php?id={$viewid}{if $new}&new=1{/if}" title="{str tag=chooseskin section=skin}">
            <span class="icon icon-lg icon-paint-brush"></span>
            <span class="btn-title">{str tag=chooseskin section=skin}</span>
        </a>
    {/if}
    {if $edittitle}
        <a class="btn btn-default {if $selected == 'title'}active{/if}" href="{$WWWROOT}view/edit.php?id={$viewid}{if $new}&new=1{/if}" title="{str tag=edittitleanddescription section=view}">
            <span class="icon icon-lg icon-cogs"></span>
            <span class="btn-title">{str tag=edittitleanddescription section=view}</span>
        </a>
    {/if}
</div>
{if !$issitetemplate}
<div class="with-heading">
    <a href="{$displaylink}">
        {str tag=displayview section=view}
    </a>
    {if $edittitle || $viewtype == 'profile'}
    <a href="{$WWWROOT}view/access.php?id={$viewid}{if $collectionid}&collection={$collectionid}{/if}{if $new}&new=1{/if}">
        <span class="icon icon-unlock-alt"></span>
        {str tag=shareview section=view}
    </a>
    {/if}
</div>
{/if}



