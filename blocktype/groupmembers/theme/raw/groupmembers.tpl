<div class="friends">
{if $groupmembers}
    <table id="userfriendstable" class="center fullwidth">
      <tbody>
      {$groupmembers.tablerows|safe}
      </tbody>
    </table>
{/if}
<div class="morelinkwrap"><a class="morelink" href="{$show_all.url}">{$show_all.message} &raquo;</a></div>
</div>
