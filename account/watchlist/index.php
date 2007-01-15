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
 * @subpackage core
 * @author     Penny Leach <penny@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('MENUITEM', 'account');
define('SUBMENUITEM', 'watchlist');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');

$viewstring = get_string('views', 'activity');
$communitystring = get_string('communities', 'activity');
$artefactstring = get_string('artefacts', 'activity');
$monitoredstring = get_string('monitored', 'activity');
$allusersstring = get_string('allusers');

$savefailed = get_string('stopmonitoringfailed', 'activity');
$savesuccess = get_string('stopmonitoringsuccess', 'activity');

$recursestr = '[<a href="" onClick="toggleChecked(\'tocheck-r\'); return false;">' 
    . get_string('recurseall', 'activity')
    . '</a>]';
$recursestrjs = str_replace("'", "\'", $recursestr);

$javascript = <<<JAVASCRIPT
var watchlist = new TableRenderer(
    'watchlist',
    'index.json.php', 
    [
        function(r) { 
            if (r.url) { 
                return TD(null,A({'href': r.url}, r.name));
            } 
            return TD(null, r.name);
        },
        function (r, d) {
            return TD(null, INPUT({'type' : 'checkbox', 'class': 'tocheck', 'name': 'stop' + d.type + '-' + r.id}));
        },
        function (r, d) {
            if (d.type != 'communities') {
                return TD(null, INPUT({'type' : 'checkbox', 'class': 'tocheck-r', 'name': 'stop' + d.type + '-' + r.id + '-recurse'}));
            }
            else {
                return '';
            }
        }
    ]
);

watchlist.type = 'views';
watchlist.statevars.push('type');
watchlist.watchlist = 1;
watchlist.statevars.push('watchlist');
watchlist.updateOnLoad();
watchlist.rowfunction = function(r, n) { return TR({'id': r.id, 'class': 'view r' + (n % 2)}); }

function changeTitle(title) {
    var titles = { 'views': '{$viewstring}', 'communities': '{$communitystring}', 'artefacts': '{$artefactstring}' };
    $('typeheader').innerHTML  = '{$monitoredstring} ' + titles[title];
}

function stopmonitoring(form) {
    var e1 = getElementsByTagAndClassName(null,'tocheck',form);
    var e2 = getElementsByTagAndClassName(null,'tocheck-r',form);
    var e = concat(e1, e2);
    var pd = {};
    
    for (cb in e) {
        if (e[cb].checked == true) {
            pd[e[cb].name] = 1;
        }
    }

    pd['stopmonitoring'] = 1;

    var d = loadJSONDoc('index.json.php', pd);
    d.addCallbacks(function (data) {
        if (data.success) {
            if (data.count > 0) {
                $('messagediv').innerHTML = '$savesuccess';
                watchlist.doupdate();
            }
        }
        if (data.error) {
            $('messagediv').innerHTML = '$savefailed (' + data.error + ')';
        }
    },
                   function () {
            $('messagediv').innerHTML = '$savefailed';
            watchlist.doupdate();
        }
    )
}

function statusChange() {
    var typevalue = $('type').options[$('type').selectedIndex].value;
    var uservalue;
    if ($('user').disabled == true) {
        uservalue = undefined;
    } 
    else {
        uservalue = getNodeAttribute($('user').options[$('user').selectedIndex], 'value');
    }

    if (uservalue) {
        watchlist.doupdate({'type': typevalue, 'user': uservalue});
    }
    else {
        watchlist.doupdate({'type': typevalue});
    }
    changeTitle(typevalue); 
    $('messagediv').innerHTML = '';
    if (typevalue == 'communities') {
        $('recurseheader').innerHTML = '';
        $('user').options.length = 0;
        $('user').disabled = true;
    }
    else {
        $('recurseheader').innerHTML = '{$recursestrjs}';
        var pd = {'userlist': typevalue};
        var d = loadJSONDoc('index.json.php', pd);
        d.addCallbacks(function (data) {
            
            var userSelect = $('user');
            var newOptions = new Array()
            var opt = OPTION(null, '{$allusersstring}');
            if (!uservalue) {
                opt.selected = true;
            }
            newOptions.push(opt);
            forEach (data.message.users, function (u) {
                var opt = OPTION({'value': u.id}, u.name);
                if (uservalue == u.id) {
                    opt.selected = true;
                }
                newOptions.push(opt);
            });
            userSelect.disabled = false;
            replaceChildNodes(userSelect, newOptions);
        });
    }
}

JAVASCRIPT;

$prefix = get_config('prefix');
$sql = 'SELECT DISTINCT u.* 
        FROM ' . $prefix . 'usr u
        JOIN ' . $prefix . 'view v ON v.owner = u.id 
        JOIN ' . $prefix . 'usr_watchlist_view w ON w.view = v.id
        WHERE w.usr = ?';

if (!$viewusers = get_records_sql_array($sql, array($USER->get('id')))) {
    $viewusers = array();
}

$smarty = smarty(array('tablerenderer'));
$smarty->assign('viewusers', $viewusers);
$smarty->assign('typestr', get_string('views', 'activity'));
$smarty->assign('selectall', 'toggleChecked(\'tocheck\'); return false;');
$smarty->assign('recursestr', $recursestr);
$smarty->assign('stopmonitoring', 'stopmonitoring(this); return false;');
$smarty->assign('INLINEJAVASCRIPT', $javascript);
$smarty->display('account/watchlist/index.tpl');


?>
