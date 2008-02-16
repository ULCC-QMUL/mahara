<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2008 Catalyst IT Ltd (http://www.catalyst.net.nz)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('MENUITEM', 'settings/notifications');
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'account');
define('SECTION_PAGE', 'activity');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('notifications'));

$types = get_records_assoc('activity_type', 'admin', 0, 'plugintype,pluginname,name', 'id,name,plugintype,pluginname');
$types = array_map(create_function('$a', '
    if (!empty($a->plugintype)) { 
        $section = "{$a->plugintype}.{$a->pluginname}";
    }
    else {
        $section = "activity";
    }
    return get_string("type" . $a->name, $section);
    '), $types);
if ($USER->get('admin')) {
    $types['adminmessages'] = get_string('typeadminmessages', 'activity');
}

$morestr = get_string('more...');

$star = json_encode(theme_get_url('images/star.png'));
$unread = json_encode(get_string('unread', 'activity'));

$javascript = <<<JAVASCRIPT
var activitylist = new TableRenderer(
    'activitylist',
    'index.json.php', 
    [
        function(r) { 
            if (r.message) {
                var messagemore = DIV({'id' : 'message-' + r.id, 'style': 'display:none'});
                messagemore.innerHTML = r.message;
                if (r.url) {
                    appendChildNodes(messagemore, BR(null), A({'href' : r.url, 'class': 's'}, '{$morestr}'));
                }
                return TD(null, A({'href': '', 'onclick': 'showHideMessage(' + r.id + '); return false;'}, r.subject),
                          messagemore);
            }
            else if (r.url) { 
                return TD(null, A({'href': r.url}, r.subject));
            } 
            else {
                return TD(null, r.subject);
            }
        },
        'type',
        'date',
        function (r, d) {
            if (r.read == 1) {
                return TD({'class': 'center'},IMG({'src' : d.star, 'alt' : d.unread}));
            }
            else {
                return TD({'class': 'center'}, INPUT({'type' : 'checkbox', 'class' : 'tocheckread', 'name' : 'unread-' + r.id}));
            }
        },
        function (r, d) {
            return TD({'class': 'center'}, INPUT({'type' : 'checkbox', 'class' : 'tocheckdel', 'name' : 'delete-' + r.id}));
        }
    ]
);

activitylist.type = 'all';
activitylist.statevars.push('type');
activitylist.updateOnLoad();

function markread(form, action) {

    var e = getElementsByTagAndClassName(null,'tocheck'+action,form);
    var pd = {};
    
    for (cb in e) {
        if (e[cb].checked == true) {
            pd[e[cb].name] = 1;
        }
    }

    if (action == 'read') {
        pd['markasread'] = 1;
    } else if (action == 'del') {
        pd['delete'] = 1;
    }
    
    sendjsonrequest('index.json.php', pd, 'GET', function (data) {
        if (!data.error) {
            if (data.count > 0) {
                activitylist.doupdate();
                forEach(getElementsByTagAndClassName('span', 'unreadmessagescontainer'), function(message) {
                    var countnode = message.firstChild;
                    var oldcount = parseInt(countnode.innerHTML);
                    var newcount = (oldcount - data.count);
                    var messagenode = message.lastChild;
                    if (newcount == 1) { // jump through hoops to change between plural and singular
                        messagenode.innerHTML = get_string('unreadmessage');
                    }
                    else {
                        messagenode.innerHTML = get_string('unreadmessages');
                    }
                    countnode.innerHTML = newcount;
                });
            }
        }
    }, function () {
        activitylist.doupdate();
    });
}

function showHideMessage(id) {
    if (getStyle('message-' + id, 'display') == 'none') {
        var unread = getFirstElementByTagAndClassName('input', 'tocheckread', 
                                                      $('message-' + id).parentNode.parentNode);
        if (unread) {
            var pd = {'markasread':1, 'quiet':1};
            pd['unread-'+id] = 1;
            sendjsonrequest('index.json.php', pd, 'GET', function(data) {
                return !!data.error;
            });
            swapDOM(unread, IMG({'src' : {$star}, 'alt' : {$unread}}));
        }
        showElement('message-' + id);
    }
    else {
        hideElement('message-' + id);
    }
}

JAVASCRIPT;

$smarty = smarty(array('tablerenderer'));
$smarty->assign('selectallread', 'toggleChecked(\'tocheckread\'); return false;');
$smarty->assign('selectalldel', 'toggleChecked(\'tocheckdel\'); return false;');
$smarty->assign('markread', 'markread(this, \'read\'); return false;');
$smarty->assign('markdel', 'markread(document.notificationlist, \'del\'); return false;');
$smarty->assign('typechange', 'activitylist.doupdate({\'type\':this.options[this.selectedIndex].value});');
$smarty->assign('types', $types);
$smarty->assign('INLINEJAVASCRIPT', $javascript);
$smarty->assign('heading', get_string('notifications'));
$smarty->display('account/activity/index.tpl');
?>
