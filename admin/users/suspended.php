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
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configusers');
define('SUBMENUITEM', 'suspendedusers');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('suspendedusers', 'admin'));
require_once('pieforms/pieform.php');

$smarty = smarty(array('tablerenderer'));

$smarty->assign('INLINEJAVASCRIPT', <<<EOF
var suspendedlist = new TableRenderer(
    'suspendedlist',
    'suspended.json.php',
    [
        'name',
        'studentid',
        'institution',
        'cusrname',
        'reason',
        function (rowdata) { return INPUT({'type': 'checkbox', 'name': 'usr_' + rowdata.id}); }
    ]
);
suspendedlist.updateOnLoad();

EOF
);

$form = new Pieform(array(
    'name' => 'buttons',
    'renderer' => 'oneline',
    'elements' => array(
        'unsuspend' => array(
            'type' => 'submit',
            'name' => 'unsuspend',
            'value' => get_string('unsuspendusers', 'admin')
        ),
        'export' => array(
            'type' => 'submit',
            'name' => 'export',
            'value' => get_string('exportuserprofiles', 'admin')
        ),
        'delete' => array(
            'type' => 'submit',
            'name' => 'delete',
            'value' => get_string('deleteusers', 'admin')
        )
    )
));
$smarty->assign('buttonformopen', $form->get_form_tag());
$smarty->assign('buttonform', $form->build(false));

$smarty->display('admin/users/suspended.tpl');

function buttons_submit_unsuspend(Pieform $form, $values) {
    global $SESSION;

    $ids = get_user_ids_from_post();
    foreach ($ids as $userid) {
        unsuspend_user($userid);
    }

    $SESSION->add_ok_msg(get_string('usersunsuspendedsuccessfully', 'admin'));
    redirect('admin/users/suspended.php');
}

function buttons_submit_export(Pieform $form, $values) {
    global $SESSION;
    $ids = get_user_ids_from_post();
    $SESSION->add_info_msg(get_string('exportingnotsupportedyet', 'admin'));
    redirect('admin/users/suspended.php');
}

function buttons_submit_delete(Pieform $form, $values) {
    global $SESSION;

    $ids = get_user_ids_from_post();
    foreach ($ids as $userid) {
        delete_user($userid);
    }

    $SESSION->add_ok_msg(get_string('usersdeletedsuccessfully', 'admin'));
    redirect('admin/users/suspended.php');
}

function get_user_ids_from_post() {
    $ids = array();
    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 4) == 'usr_') {
            $ids[] = intval(substr($key, 4));
        }
    }

    if (!$ids) {
        global $SESSION;
        $SESSION->add_info_msg(get_string('nousersselected', 'admin'));
        redirect('admin/users/suspended.php');
    }

    return $ids;
}

?>
