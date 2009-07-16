<a name="post{$post->id}"></a>


<table class="forumpost fullwidth">
{if $post->subject && !$nosubject}
<tr>
	<td colspan="2" class="forumsubject"><h3>{$post->subject|escape}</h3></td>
</tr>
{/if}
<tr>
	<td class="forumpostleft">
	  <div class="posttime">{$post->ctime}</div>
      <div class="author">
         <img src="{$WWWROOT}thumb.php?type=profileicon&amp;maxsize=40&amp;id={$post->poster}" alt="" class="fl">
         <p><a href="{$WWWROOT}user/view.php?id={$post->poster}"{if in_array($post->poster, $groupadmins)} class="groupadmin"{elseif $post->moderator} class="moderator"{/if}>{$post->poster|display_name|escape}</a></p>
         <p>{$post->postcount}</p>
      </div>
    </td>
	<td class="postedits">{$post->body|clean_html}
{if $post->edit}
        <h5>{str tag="editstothispost" section="interaction.forum}</h5>
        <ul>
            {foreach from=$post->edit item=edit}
            <li>
                <a href="{$WWWROOT}user/view.php?id={$edit.editor}"
                {if $edit.editor == $groupowner} class="groupowner"
                {elseif $edit.moderator} class="moderator"
                {/if}
                >
                <img src="{$WWWROOT}thumb.php?type=profileicon&amp;maxsize=20&amp;id={$edit.editor}" alt="">
                {$edit.editor|display_name|escape}
                </a>
                {$edit.edittime|escape}
            </li>
            {/foreach}
        </ul>
{/if}
    </td>
</tr>
</table>
