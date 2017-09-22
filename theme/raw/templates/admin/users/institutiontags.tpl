{include file="header.tpl"}

{if !$canedit}<p>{str tag=cantlistinstitutiontags section=tags}</p>{/if}
{if $tags && !$new}
  <p class="lead view-description">{str tag=institutiontagsdescription section=tags}</p>
  {foreach $tags tag}
  <div class="panel panel-default">
    <div id="institutiontags" class="list-group">
        <div id="institutiontags" class="list-group">
          <div class="list-group-item r0 ">
                <div class="row">
                    <div class="col-md-9">
                        <h3 class="title list-group-item-heading" title="{$tag->text}">
                            {$tag->text}
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
  {/foreach}
  {$pagination|safe}
  {if $pagination_js}
    <script type="application/javascript">
    {$pagination_js|safe}
    </script>
  {/if}
{else}
  {if $new}
    {$form|safe}
  {else}
    <p class="lead view-description">{str tag=institutiontagsdescription section=tags}</p>
    <p class="no-results">
        {str tag=notags section=tags}{if $addonelink} <a href={$addonelink}>{str tag=addone}</a>{/if}
    </p>
  {/if}
{/if}

{include file="footer.tpl"}
