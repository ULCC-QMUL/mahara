{include file="header.tpl"}

{include file="columnfullstart.tpl"}
<div id="view">
	<h3>
        {foreach name=viewnav from=$VIEWNAV item=item}
          {$item}
          {if !$smarty.foreach.viewnav.last}
            :
          {/if}
        {/foreach}
        </h3>
	
	{if $VIEWCONTENT}
	   {$VIEWCONTENT}
	{/if}
	<div id="publicfeedback">
	<table id="feedbacktable">
		<thead>
			<tr><th colspan=4>{str tag=feedback}</th></tr>
		</thead>
	</table>
	</div>
	<div id="viewmenu"></div>
</div>
{include file="columnfullend.tpl"}

{include file="footer.tpl"}
