{include file="header.tpl"}
{include file="sidebar.tpl"}

{include file="columnleftstart.tpl"}
                <h2>{$group->name|escape}</h2>
                
                {if $group->description} <p>{$group->description}</p> {/if}
                <p>{str tag='owner' section='group'}: {$group->ownername|escape}</p>
	        {assign var="jointype" value=$group->jointype}
	        {assign var="joinstr" value=groupjointype$jointype}
                {if !$member}<p>{str tag=$joinstr section='group'}</p>{/if}
                {if $canleave} <p><a href="{$WWWROOT}group/leave.php?id={$group->id}">{str tag='leavegroup' section='group'}</a></p>
                {elseif $canrequestjoin} <p id="joinrequest"><a href="{$WWWROOT}group/requestjoin.php?id={$group->id}">{str tag='requestjoingroup' section='group'}</a></p>
                {elseif $canjoin} <p><a href="view.php?id={$group->id}&amp;joincontrol=join"">{str tag='joingroup' section='group'}</a></p>
                {elseif $canacceptinvite} <p>{str tag='grouphaveinvite' section='group'} <a href="view.php?id={$group->id}&amp;joincontrol=acceptinvite">{str tag='acceptinvitegroup' section='group'}</a> | <a href="view.php?id={$group->id}&amp;joincontrol=declineinvite">{str tag='declineinvitegroup' section='group'}</a></p>{/if}
                {if $member}
                    <div class="groupviews">
                        <h5>{str tag='views'}</h5>
                        {if ($tutor || $staff || $admin) && $controlled}
                            <form>
                                <select name="submitted" onChange="viewlist.submitted=this.options[this.selectedIndex].value;viewlist.doupdate();">
                                    <option value="0">{str tag='allviews' section='view'}</option>
                                    <option value="1">{str tag='submittedviews' section='group'}</option>
                                </select>
                            </form>
                        {/if}
                        <table id="group_viewlist">
                            <thead>
                                <tr>
                                    <th>{str tag='name'}</th>
                                    <th>{str tag='owner' section='group'}</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>                   
                    <div class="groupmembers">
                    <a name="members"></a>
                        <h5>{str tag='members' section='group'}</h5>
                        {if $canupdate && $request}
                            <form>
                                <select id="pendingselect" name="pending" onChange="switchPending();">
                                    <option value="0">{str tag='members' section='group'}</option>
                                    <option value="1">{str tag='memberrequests' section='group'}</option>
                                </select>
                            </form>
                         {/if}
                         <table id="memberlist">
                             <thead>
                                 <tr>
                                     <th>{str tag='name'}</th>
                                     <th id="pendingreasonheader">{str tag='reason'}</th>
                                 </tr>
                             </thead>
                             <tbody>
                             </tbody>
                         </table>
	                 {if $canupdate && $hasmembers}
                             <input type="button" class="button" value="{str tag='updatemembership' section='group'}" onclick="return updateMembership();" id="groupmembers_update">
                         {/if}
                     </div>
                {/if}
{include file="columnleftend.tpl"}
{include file="footer.tpl"}
