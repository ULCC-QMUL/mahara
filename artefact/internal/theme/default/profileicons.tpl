{include file="header.tpl"}

<div id="column-right">
{include file="quota.tpl"}
{include file="adminmenu.tpl"}
</div>

{include file="columnleftstart.tpl"}

            <div class="fr leftrightlink"><a href="{$WWWROOT}artefact/internal/" id="backtoeditprofile">&laquo; {str tag="editprofile" section="artefact.internal"}</a></div>
			<h2>{str section="artefact.internal" tag="profileicons"}</h2>

            {$settingsformtag}
            <table id="profileicons" class="tablerenderer">
                <thead>
                    <th>{str tag="image"}</th>
                    <th>{str tag="title"}</th>
                    <th>{str tag="default"}</th>
                    <th>{str tag="delete"}</th>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <td></td>
                    <td></td>
                    <td><input id="settings_default" type="submit" class="submit" name="default" value="{str tag="default"}" tabindex="2"></td>
                    <td><input id="settings_delete" type="submit" class="submit" name="delete" value="{str tag="delete"}" tabindex="2"></td>
                </tfoot>
            </table>
            <input type="hidden" name="pieform_settings" value="">
            </form>

            <h3>{str tag="uploadprofileicon" section="artefact.internal"}</h3>
            <p>{str tag="profileiconsiconsizenotice" section="artefact.internal"}</p>

            {$uploadform}

{include file="columnleftend.tpl"}

{include file="footer.tpl"}
