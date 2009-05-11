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
require(dirname(dirname(__FILE__)) . '/init.php');
require_once('pieforms/pieform.php');
require_once('view.php');
$viewid = param_integer('id');

$view = new View($viewid, null);

if (!$view || !$USER->can_edit_view($view)) {
    throw new AccessDeniedException(get_string('cantdeleteview', 'view'));
}
$groupid = $view->get('group');
$institution = $view->get('institution');
View::set_nav($groupid, $institution);

if ($groupid) {
    $goto = 'groupviews.php?group=' . $groupid;
}
else if ($institution) {
    $goto = 'institutionviews.php?institution=' . $institution;
}
else {
    $goto = 'index.php';
}

define('TITLE', get_string('deletespecifiedview', 'view', $view->get('title')));

$form = pieform(array(
    'name' => 'deleteview',
    'autofocus' => false,
    'method' => 'post',
    'elements' => array(
        'submit' => array(
            'type' => 'submitcancel',
            'title' => get_string('deleteviewconfirm', 'view'),
            'value' => array(get_string('yes'), get_string('no')),
            'goto' => get_config('wwwroot') . 'view/' . $goto,
        )
    ),
));

$smarty = smarty();
$smarty->assign('heading', TITLE);
$smarty->assign('form', $form);
$smarty->display('view/delete.tpl');

function deleteview_submit(Pieform $form, $values) {
    global $SESSION, $viewid, $groupid, $institution;
    $view = new View($viewid, null);
    $view->delete();
    $SESSION->add_ok_msg(get_string('viewdeleted', 'view'));
    if ($groupid) {
        redirect('/view/groupviews.php?group='.$groupid);
    }
    if ($institution) {
        redirect('/view/institutionviews.php?institution='.$institution);
    }
    redirect('/view/');
}
?>
