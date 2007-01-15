{*

  This template displays the 'edit blog post' form

 *}

{include file="header.tpl"}

<div id="column-right">
{include file="adminmenu.tpl"}
</div>
{include file="columnleftstart.tpl"}
        <h2>{str section="artefact.blog" tag=$pagetitle}</h2>
        {$textinputform}
        <div id='insertimage'></div>
        <div id='uploader'></div>
        <table id='filebrowser' style='display: none;' class='tablerenderer'>
          <thead><tr>
            <th></th>
            <th>{str section=artefact.file tag=name}</th>
            <th>{str section=artefact.file tag=description}</th>
            <th>{str section=artefact.file tag=size}</th>
            <th>{str section=mahara tag=date}</th>
            <th></th>
          </tr></thead>
          <tbody><tr><td></td></tr></tbody>
        </table>
        <h3>{str section=artefact.blog tag=attachedfiles}</h3>
        <table id='attachedfiles' class='tablerenderer'>
          <thead><tr>
            <th></th>
            <th>{str section=artefact.file tag=name}</th>
            <th>{str section=artefact.file tag=description}</th>
            <th></th>
          </tr></thead>
          <tbody><tr><td></td></tr></tbody>
        </table>
        <div>
          <input type='button' class='button' value='{str tag=savepost section=artefact.blog}' onclick="saveblogpost()">
          <input type='button' class='button' value='{str tag=cancel}' onclick="canceledit()">
        </div>
{include file="columnleftend.tpl"}
{include file="footer.tpl"}
