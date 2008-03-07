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
define('MENUITEM', 'groups/groupsiown');
require(dirname(dirname(__FILE__)) . '/init.php');
require_once('pieforms/pieform.php');
define('TITLE', get_string('creategroup', 'group'));

$joinoptions = array(
    'invite'     => get_string('membershiptype.invite', 'group'),
    'request'    => get_string('membershiptype.request', 'group'),
    'open'       => get_string('membershiptype.open', 'group'),
);
if ($USER->can_create_controlled_groups()) {
    $joinoptions['controlled'] = get_string('membershiptype.controlled', 'group');
}

$creategroup = pieform(array(
    'name'     => 'creategroup',
    'method'   => 'post',
    'plugintype' => 'core',
    'pluginname' => 'groups',
    'elements' => array(
        'name' => array(
            'type'         => 'text',
            'title'        => get_string('groupname', 'group'),
            'rules'        => array( 'required' => true, 'maxlength' => 128 ),
        ),
        'description' => array(
            'type'         => 'wysiwyg',
            'title'        => get_string('groupdescription', 'group'),
            'rows'         => 10,
            'cols'         => 55,
        ),
        'membershiptype' => array(
            'type'         => 'select',
            'title'        => get_string('membershiptype', 'group'),
            'options'      => $joinoptions,
            'defaultvalue' => 'open',
            'help'         => true,
        ),
        'submit'   => array(
            'type'  => 'submitcancel',
            'value' => array(get_string('savegroup', 'group'), get_string('cancel')),
        ),
    ),
));

function creategroup_validate(Pieform $form, $values) {
    global $USER;
    global $SESSION;

    $cid = get_field('group', 'id', 'name', $values['name']);

    if ($cid) {
        $form->set_error('name', get_string('groupalreadyexists', 'group'));
    }
}

function creategroup_cancel_submit() {
    redirect('/group/mygroups.php');
}

function creategroup_submit(Pieform $form, $values) {
    global $USER;
    global $SESSION;

    db_begin();

    $now = db_format_timestamp(time());

    $id = insert_record(
        'group',
        (object) array(
            'name'           => $values['name'],
            'description'    => $values['description'],
            'jointype'       => $values['membershiptype'],
            'owner'          => $USER->get('id'),
            'ctime'          => $now,
            'mtime'          => $now,
        ),
        'id',
        true
    );

    // If the user is a staff member, they should be added as a tutor automatically
    if ($values['membershiptype'] == 'controlled' && $USER->can_create_controlled_groups()) {
        insert_record(
            'group_member',
            (object) array(
                'group'  => $id,
                'member' => $USER->get('id'),
                'ctime'  => $now,
                'tutor'  => 1
            )
        );
    }
    else {
        insert_record(
            'group_member',
            (object) array(
                'group'  => $id,
                'member' => $USER->get('id'),
                'ctime'  => $now,
                'tutor'  => 0
            )
        );
    }

    $SESSION->add_ok_msg(get_string('groupsaved', 'group'));

    db_commit();

    redirect('/group/mygroups.php');
}

$smarty = smarty();

$smarty->assign('creategroup', $creategroup);

$smarty->display('group/create.tpl');

?>
