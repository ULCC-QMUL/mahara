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
define('MENUITEM', 'myportfolio/views');

define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'view');
define('SECTION_PAGE', 'edit');

require(dirname(dirname(__FILE__)) . '/init.php');
require_once(get_config('docroot') . 'lib/view.php');
require_once(get_config('docroot') . 'lib/group.php');

$id = param_integer('id', 0); // if 0, we're creating a new view
$new = param_boolean('new');

if (empty($id)) {
    $new = true;
    $group = param_integer('group', null);
}
else {
    $view = new View($id);
    if (!$USER->can_edit_view($view)) {
        throw new AccessDeniedException(get_string('canteditdontown', 'view'));
    }

    // If the view has been submitted to a group, disallow editing
    $submittedto = $view->get('submittedto');
    if ($submittedto) {
        throw new AccessDeniedException(get_string('canteditsubmitted', 'view', get_field('group', 'name', 'id', $submittedto)));
    }

    $group = $view->get('group');
}

if ($group && !group_user_access($group)) {
    throw new AccessDeniedException();
}

if ($new || empty($id)) {
    define('TITLE', get_string('createviewstepone', 'view'));
}
else {
    define('TITLE', get_string('editviewdetails', 'view', $view->get('title')));
}

$heading = TITLE; // for the smarty template

require_once('pieforms/pieform.php');

$formatstring = '%s (%s)';
$ownerformatoptions = array(
    FORMAT_NAME_FIRSTNAME => sprintf($formatstring, get_string('firstname'), $USER->get('firstname')),
    FORMAT_NAME_LASTNAME => sprintf($formatstring, get_string('lastname'), $USER->get('lastname')),
    FORMAT_NAME_FIRSTNAMELASTNAME => sprintf($formatstring, get_string('fullname'), full_name())
);

$preferredname = $USER->get('preferredname');
if ($preferredname !== '') {
    $ownerformatoptions[FORMAT_NAME_PREFERREDNAME] = sprintf($formatstring, get_string('preferredname'), $preferredname);
}
$studentid = (string)get_field('artefact', 'title', 'owner', $USER->get('id'), 'artefacttype', 'studentid');
if ($studentid !== '') {
    $ownerformatoptions[FORMAT_NAME_STUDENTID] = sprintf($formatstring, get_string('studentid'), $studentid);
}
$ownerformatoptions[FORMAT_NAME_DISPLAYNAME] = sprintf($formatstring, get_string('displayname'), display_name($USER));

$editview = array(
    'name'     => 'editview',
    'method'   => 'post',
    'autofocus' => 'title',
    'plugintype' => 'core',
    'pluginname' => 'view',
    'elements' => array(
        'id' => array(
            'type'  => 'hidden',
            'value' => $id,
        ),
        'new' => array(
            'type' => 'hidden',
            'value' => $new,
        ),
        'title' => array(
            'type'         => 'text',
            'title'        => get_string('title','view'),
            'defaultvalue' => isset($view) ? $view->get('title') : null,
            'rules'        => array( 'required' => true ),
        ),
        'description' => array(
            'type'         => 'wysiwyg',
            'title'        => get_string('description','view'),
            'rows'         => 10,
            'cols'         => 70,
            'defaultvalue' => isset($view) ? $view->get('description') : null,
        ),
        'tags'        => array(
            'type'         => 'tags',
            'title'        => get_string('tags'),
            'description'  => get_string('tagsdescprofile'),
            'defaultvalue' => isset($view) ? $view->get('tags') : null,
            'help'         => true,
        ),
        'ownerformat' => array(
            'type'         => 'select',
            'title'        => get_string('ownerformat','view'),
            'description'  => get_string('ownerformatdescription','view'),
            'options'      => $ownerformatoptions,
            'defaultvalue' => isset($view) ? $view->get('ownerformat') : FORMAT_NAME_DISPLAYNAME,
            'rules'        => array('required' => true),
        ),
        'submit'   => array(
            'type'  => 'submitcancel',
            'value' => array(empty($new) ? get_string('save') : get_string('next'), get_string('cancel')),
            'confirm' => $new && isset($view) ? array(null, get_string('confirmcancelcreatingview', 'view')) : null,
        )
    ),
);

if ($group) {
    $editview['elements']['group'] = array(
        'type'  => 'hidden',
        'value' => $group
    );
}

$editview = pieform($editview);

function editview_cancel_submit() {
	global $view, $new, $group;
	if (isset($view) && $new) {
	    $view->delete();
	}
        if ($group) {
            redirect('/view/groupviews.php?group='.$group);
        }
    redirect('/view');
}

function editview_submit(Pieform $form, $values) {

    global $USER, $SESSION;

    $editing = !empty($values['id']);
    $view = new View($values['id'], $values);
    $group = isset($values['group']) ? (int)$values['group'] : null;
    if ($group && !group_user_access($group)) {
        $SESSION->add_error_msg(get_string('notamember', 'group'));
        redirect('/view/groupviews.php?group='.$group);
    }

    if (empty($editing)) {
        $view->set('numcolumns', 3); // default
        if ($group) {
            $view->set('group', $group);
        }
        else {
            $view->set('owner', $USER->get('id'));
        }
    }
    else {
        $view->set('dirty', true);
    }

    $view->commit();

    if ($values['new']) {
        $redirecturl = '/view/blocks.php?id=' . $view->get('id') . '&new=1';
    } 
    else {
        $SESSION->add_ok_msg(get_string('viewsavedsuccessfully', 'view'));
        if ($group) {
            $redirecturl = '/view/groupviews.php?group='.$group;
        }
        else {
            $redirecturl = '/view/index.php';
        }
    }

    redirect($redirecturl);

}

$smarty = smarty();
$smarty->assign('heading', $heading);
$smarty->assign('editview', $editview);
$smarty->display('view/edit.tpl');

?>
