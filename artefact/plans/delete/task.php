<?php
/**
 *
 * @package    mahara
 * @subpackage artefact-plans
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', true);
define('MENUITEM', 'content/plans');

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/init.php');
safe_require('artefact','plans');

define('TITLE', get_string('deletetask','artefact.plans'));

$id = param_integer('id');
$todelete = new ArtefactTypeTask($id);
if (!$USER->can_edit_artefact($todelete)) {
    throw new AccessDeniedException(get_string('accessdenied', 'error'));
}

// This SESSION variable is necessary for the return to the previous page after task edit.
if (!strpos($_SERVER['HTTP_REFERER'], 'artefact/plans') && !$SESSION->get('artefact-plan_returnurl')) {
    $SESSION->set('artefact-plan_returnurl', $_SERVER['HTTP_REFERER']);
}

$deleteform = array(
    'name' => 'deletetaskform',
    'class' => 'form-delete',
    'plugintype' => 'artefact',
    'pluginname' => 'plans',
    'renderer' => 'div',
    'elements' => array(
        'submit' => array(
            'type' => 'submitcancel',
            'class' => 'btn-default',
            'value' => array(get_string('deletetask','artefact.plans'), get_string('cancel')),
            'goto' => get_config('wwwroot') . '/artefact/plans/plan.php?id='.$todelete->get('parent'),
        ),
    )
);
$form = pieform($deleteform);

$smarty = smarty();
$smarty->assign('form', $form);
$smarty->assign('PAGEHEADING', $todelete->get('title'));
$smarty->assign('subheading', get_string('deletethistask','artefact.plans',$todelete->get('title')));
$smarty->assign('message', get_string('deletetaskconfirm','artefact.plans'));
$smarty->display('artefact:plans:delete.tpl');

// calls this function first so that we can get the artefact and call delete on it
function deletetaskform_submit(Pieform $form, $values) {
    global $SESSION, $todelete;

    $todelete->delete();
    $SESSION->add_ok_msg(get_string('taskdeletedsuccessfully', 'artefact.plans'));

    // Redirect to blocktype view if task was deleted from a blocktype.
    if ($returnurl = $SESSION->get('artefact-plan_returnurl')) {
        $SESSION->clear('artefact-plan_returnurl');
    } else {
        $returnurl = '/artefact/plans/plan.php?id='.$todelete->get('parent');
    }
    redirect($returnurl);
}
