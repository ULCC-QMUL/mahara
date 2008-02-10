{* 

  This template displays a list of the user's blog posts for a particular blog.

 *}

{include file="header.tpl"}

{include file="sidebar.tpl"}

{include file="columnleftstart.tpl"}
			<div id="myblogs">
                <div class="blogsettingscontainer">
                    <div class="addiconcontainer">
                        <span class="addicon">
                            <a href="{$WWWROOT}artefact/blog/post.php?blog={$blog->get('id')}">{str section="artefact.blog" tag="addpost"}</a>
                        </span>
                    </div>
                    <span class="settingsicon">  
                        <a href="{$WWWROOT}artefact/blog/settings/?id={$blog->get('id')}">{str section="artefact.blog" tag="settings"}</a>
                    </span>
                </div>

		
				<div><table id="postlist" class="hidden tablerenderer">
					<tbody>
									  <tr><td></td><td></td><td></td></tr>
					</tbody>
				</table></div>
					
				</div>
				
{include file="columnleftend.tpl"}
{include file="footer.tpl"}
