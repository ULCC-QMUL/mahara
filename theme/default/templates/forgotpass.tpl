{include file="header.tpl"}

{include file="columnfullstart.tpl"}

			{if $forgotpass_form}
			<p>{str tag="forgotpasswordtext"}</p>
	
			{$forgotpass_form}
			{/if}
			
			{if $forgotpasschange_form}
			<p>{str tag="forgotpasswordenternew"}</p>
			
			{$forgotpasschange_form}
			{/if}
{include file="columnfullend.tpl"}

{include file="footer.tpl"}
