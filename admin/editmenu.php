<?php
/**
 * This program is part of Mahara
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301 USA
 *
 * @package    mahara
 * @subpackage admin
 * @author     Richard Mansfield <richard.mansfield@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL',1);
define('ADMIN', 1);
define('MENUITEM','menueditor');
require(dirname(dirname(__FILE__)).'/init.php');

$strings = array('edit','delete','update','cancel','add','name','unknownerror');
$adminstrings = array('deletefailed','deletingmenuitem','savingmenuitem',
                      'noadminfiles','loggedinmenu','loggedoutmenu','linkedto','externallink','adminfile',
                      'loadingmenuitems','menuitemsloaded','failedloadingmenuitems');
foreach ($strings as $string) {
    $getstring[$string] = "'" . get_string($string) . "'";
}
foreach ($adminstrings as $string) {
    $getstring[$string] = "'" . get_string($string,'admin') . "'";
}

$thead = array(get_string('type','admin'),get_string('name','admin'),get_string('linkedto','admin'),'');
$ijs = "var thead = TR(null,map(partial(TH,null),['" . implode($thead,"','") . "']));\n";
$ijs .= "var externallink = '" . get_string('externallink','admin') . "';\n";
$ijs .= "var adminfile = '" . get_string('adminfile','admin') . "';\n";

$ijs .= <<< EOJS
// Request a list of menu items from the server
function getitems() {
    logDebug({$getstring['loadingmenuitems']});
    processingStart();
    var d = loadJSONDoc('getmenuitems.json.php',{'public':selectedmenu == 'loggedoutmenu'});
    d.addCallback(function(data) {
        if (!data.error) {
            logDebug({$getstring['menuitemsloaded']});
            displaymenuitems(data.menuitems);
            processingStop();
        }
        else {
            displayMessage({$getstring['failedloadingmenuitems']},'error');
            processingStop();
        }
    });
}

// Puts the list of menu items into the empty table.
function displaymenuitems(itemlist) {
    var rows = map(formatrow,itemlist);
    var form = FORM({'id':'form','method':'post','enctype':'multipart/form-data',
                         'encoding':'multipart/form-data'},
                    TABLE(null,TBODY(null,[thead,rows,addform()])));
    replaceChildNodes($('menuitemlist'),form);
}

// Creates one table row
function formatrow (item) {
    // item has id, type, name, link, linkedto
    var type = eval(item.type);
    var linkedto = A({'href':item.linkedto},item.linkedto);
    var del = INPUT({'type':'button','value':{$getstring['delete']}});
    del.onclick = function () { delitem(item.id); };
    var edit = INPUT({'type':'button','value':{$getstring['edit']}});
    edit.onclick = function () { edititem(item); };
    var cells = map(partial(TD,null),[type,item.name,linkedto,[del,edit]]);
    return TR({'id':'menuitem_'+item.id},cells);
}

// Returns the form which adds a new menu item
function addform(type) {
    var item = {'id':'new'};
    item.type = type ? type : 'externallist';
    return editform(item);
}

// Creates the contents of a menu item edit form
// This is formatted as a table within the form (which is within a row of the table).
function editform(item) {
    // item has id, type, name, link, linkedto
    // The form has two radio buttons to select the type, external link or admin file
    var elink = INPUT({'type':'radio','name':'type'+item.id,'value':'externallink'});
    var afile = INPUT({'type':'radio','name':'type'+item.id,'value':'adminfile'});

    // Either a save, a cancel button, or both.
    var savecancel = [];
    var save = INPUT({'type':'button'});
    save.onclick = function () { saveitem(item.id); };

    // The link field will be a text box or a select in the case of an admin file.
    var linkedto = null;

    var rowtype = 'add';
    if (!item) {
        // This is the 'add' form rather than the edit form
        // Set defaults.
        item = {'type':'externallist'};
    }
    if (!item.linkedto) {
        item.linkedto = '';
        item.name = '';
        elink.onclick = function () { changeaddform('externallink'); };
        afile.onclick = function () { changeaddform('adminfile'); };
        // The save button says 'add', and there's no cancel button.
        setNodeAttribute(save,'value',{$getstring['add']});
        savecancel = [save];
    }
    else { // Editing an existing menu item.
        // The save button says 'update' and there's a cancel button.
        var rowtype = 'edit';
        setNodeAttribute(save,'value',{$getstring['update']});
        var cancel = INPUT({'type':'button','value':{$getstring['cancel']}});
        cancel.onclick = closeopenedits;
        savecancel = [save,cancel];
        elink.onclick = function () { changeeditform(item,'externallink'); };
        afile.onclick = function () { changeeditform(item,'adminfile'); };
    }

    // A text field for the name
    var name = INPUT({'type':'text','id':'name'+item.id,'value':item.name});

    if (item.type == 'adminfile') {
        var adminfiles = getadminfiles();
        if (adminfiles == null) {
            // There are no admin files, we don't need the select or save button
            linkedto = {$getstring['noadminfiles']};
            savecancel = [cancel];
        }
        else {
            // Select the currently selected file.
            linkedto = INPUT({'type':'select','id':'linkedto'+item.id});
        }
        setNodeAttribute(afile,'checked',true);
    }
    else { // type = externallist
        linkedto = INPUT({'type':'text','id':'linkedto'+item.id,'value':item.linkedto});
        setNodeAttribute(elink,'checked',true);
    }
    var radios = [DIV(null,elink,{$getstring['externallink']}),
                  DIV(null,afile,{$getstring['adminfile']})];
    var row = TR({'id':'row'+item.id, 'class':rowtype},
                 map(partial(TD,null),[radios,name,linkedto,savecancel]));
    return row;
}

// Close all open edit forms
function closeopenedits() {
    var rows = getElementsByTagAndClassName('tr',null,$('menuitemlist'));
    for (var i=0; i<rows.length; i++) {
        if (hasElementClass(rows[i],'edit')) {
            removeElement(rows[i]);
        }
        else if (hasElementClass(rows[i],'invisible')) {
            removeElementClass(rows[i],'invisible');
        }
    }
}

// Change the type of an edit form
function changeeditform(item, type) {
    item.type = type;
    edititem(item);
}

// Change the type of the add form
function changeaddform(type) {
    var newrow = addform(type);
    swapDOM($('rownew'),newrow);
}

// Open a new edit form
function edititem(item) {
    closeopenedits();
    var menuitem = $('menuitem_'+item.id);
    addElementClass(menuitem,'invisible');
    var newrow = editform(item);
    menuitem.parentNode.insertBefore(newrow,menuitem);
}

// Receive standard json error message
// Request deletion of a menu item from the db
function delitem(itemid) {
    logDebug({$getstring['deletingmenuitem']});
    sendjsonrequest('deletemenuitem.json.php',{'itemid':itemid}, getitems);
}

// Send the menu item in the form to the database.
function saveitem(itemid) {
    var f = $('form');
    var name = $('name'+itemid).value;
    var linkedto = $('linkedto'+itemid).value;
    if (name == '') {
        displayMessage(get_string('namedfieldempty',{$getstring['name']}),'error');
        return false;
    }
    if (linkedto == '') {
        displayMessage(get_string('namedfieldempty',{$getstring['linkedto']}),'error');
        return false;
    }
    logDebug({$getstring['savingmenuitem']});
    var data = {'type':eval('f.type'+itemid+'[0].checked') ? 'externallink' : 'adminfile',
                'name':name,
                'linkedto':linkedto,
                'itemid':itemid,
                'public':selectedmenu == 'loggedoutmenu'};
    sendjsonrequest('updatemenu.json.php', data, getitems);
    return false;
}

// In phase 1 there are no files in the system
function getadminfiles() {
    return null;
}

function changemenu() {
    selectedmenu = $('menuselect').value;
    getitems();
}

var selectedmenu = 'loggedoutmenu';
addLoadEvent(function () {
    $('menuselect').value = selectedmenu;
    $('menuselect').onchange = changemenu;
    changemenu();
});
EOJS;

$menulist = array('loggedinmenu', 'loggedoutmenu');
foreach ($menulist as &$menu) {
    $menu = array('value' => $menu,
                  'name' => get_string($menu,'admin'));
}

$style = '<style type="text/css">.invisible{display:none;}</style>';
$smarty = smarty(array(),array($style));
$smarty->assign('INLINEJAVASCRIPT',$ijs);
$smarty->assign('MENUS',$menulist);
$smarty->display('admin/editmenu.tpl');

?>
