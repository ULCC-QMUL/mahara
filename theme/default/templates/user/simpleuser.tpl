<div>
    <div class="fl">
        <img src="{$WWWROOT}thumb.php?type=profileicon&amp;maxwidth=50&amp;maxheight=50&amp;id={$user->id}" alt="">
    </div>
    <h3>
        <a href="{$WWWROOT}user/view.php?id={$user->id}">
            {$user->id|display_name|escape}
        </a>
    </h3>
    {if $user->introduction}
    <p>
        {$user->introduction}
    </p>
    {/if}
</div>
