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
 * @author     Martyn Smith <martyn@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('MENUITEM', 'mycontacts');
define('SUBMENUITEM', 'mygroups');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('mygroups'));

$javascript = <<<JAVASCRIPT
var grouplist = new TableRenderer(
    'grouplist',
    'index.json.php', 
    [
        'name',
        'count',
        function(r) { return TD(null,A({'href':'edit.php?id=' + r.id}, 'edit')); }
    ]
);

grouplist.updateOnLoad();

JAVASCRIPT;

$smarty = smarty(array('tablerenderer'));

$smarty->assign('INLINEJAVASCRIPT', $javascript);
$smarty->display('contacts/groups/index.tpl');

?>
