{include file="header.tpl"}
<div id="column-right">
{include file="adminmenu.tpl"}
</div>

{include file="columnleftstart.tpl"}
                        <h2>{str tag='myviews'}</h2>
    			<div id="views">
        			<div style="text-align:right;">
            		<input type="button" value="{str tag="createnewview"}" onclick="window.location='create1.php';">
        			</div>
					<table id="viewlist">
						<tbody>
						</tbody>
					</table>
    			</div>
{include file="columnleftend.tpl"}
{include file="footer.tpl"}
