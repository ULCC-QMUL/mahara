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
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'view');
define('SECTION_PAGE', 'blocks');

require(dirname(dirname(__FILE__)) . '/init.php');
require_once(get_config('libroot') . 'view.php');
require_once(get_config('libroot') . 'group.php');

$id = param_integer('id', 0); // if 0, we're editing our profile.
$new = param_boolean('new', false);
$profile = param_boolean('profile');

if (empty($id)) {
    if (!empty($profile)) {
        try {
            $view = View::profile_view($USER->get('id'));
            $id = $view->get('id');
        }
        catch (ViewNotFoundException $_e) {
            throw new ParameterException("Missing parameter id and couldn't find default user profile view");
        }
    }
    else {
        throw new ParameterException("Missing parameter id");
    }
}
if (!empty($id) && empty($view)) {
    $view = new View($id);
}

if (!$USER->can_edit_view($view)) {
    throw new AccessDeniedException();
}

// If the view has been submitted to a group, disallow editing
$submittedto = $view->get('submittedto');
if ($submittedto) {
    throw new AccessDeniedException(get_string('canteditsubmitted', 'view', get_field('group', 'name', 'id', $submittedto)));
}

$group = $view->get('group');
$institution = $view->get('institution');

// check if cancel was selected
if ($new && isset($_POST['cancel'])) {
    $view->delete();
    if ($group) {
        redirect(get_config('wwwroot') . 'view/groupviews.php?group='.$group);
    }
    if ($institution) {
        redirect(get_config('wwwroot') . 'view/institutionviews.php?institution='.$institution);
    }
    redirect(get_config('wwwroot') . 'view/');
}

// If a block was configured & submitted, build the form now so it can
// be processed without having to render the other blocks.
if ($blockid = param_integer('blockconfig', 0)) {
    // However, if removing a newly placed block, let it fall through to process_changes
    if (!isset($_POST['cancel_action_configureblockinstance_id_' . $blockid]) || !param_integer('removeoncancel', 0)) {
        require_once(get_config('docroot') . 'blocktype/lib.php');
        $bi = new BlockInstance($blockid);
        $bi->build_configure_form();
    }
}

View::set_nav($group, $institution, ($view->get('type') == 'profile'));

if ($view->get('type') == 'profile') {
    $profile = true;
    define('TITLE', get_string('editprofileview', 'view'));
}
else if ($new) {
    define('TITLE', get_string('createviewstepone', 'view'));
}
else {
    define('TITLE', get_string('editblocksforview', 'view', $view->get('title')));
}

$category = param_alpha('c', '');
// Make the default category the first tab if none is set
if ($category === '') {
    $category = get_field_sql("
        SELECT bc.name
        FROM {blocktype_category} bc
        JOIN {blocktype_installed_category} bic ON bic.category = bc.name
        JOIN {blocktype_installed_viewtype} biv ON biv.blocktype = bic.blocktype
        WHERE biv.viewtype = ?
        ORDER BY bc.name
        LIMIT 1", array($view->get('type')));
}

$view->process_changes($category, $new);
$columns = $view->build_columns(true);

$extraconfig = array(
    'stylesheets' => array('style/views.css'),
);
$smarty = smarty(array('views', 'tinytinymce', 'paginator', 'tablerenderer'), array('<link rel="stylesheet" type="text/css" href="' . get_config('wwwroot') . 'theme/views.css">'), false, $extraconfig);


// The list of categories for the tabbed interface
$smarty->assign('category_list', View::build_category_list($category, $view, $new));

// The list of blocktypes for the default category
$smarty->assign('blocktype_list', $view->build_blocktype_list($category));

// The HTML for the columns in the view
$smarty->assign('columns', $columns);

// Tell smarty we're editing rather than just rendering
$smarty->assign('editing', true);

// Work out what action is being performed. This is used to put a hidden submit 
// button right at the very start of the form, so that hitting enter in any 
// form fields will cause the correct action to be performed
foreach (array_keys($_POST + $_GET) as $key) {
    if (substr($key, 0, 7) == 'action_') {
        if (param_boolean('s')) {
            // When configuring a blockinstance and the search tab is open, 
            // pressing enter should search
            $key = str_replace('configureblockinstance', 'acsearch', $key);
            if (substr($key, -2) == '_x') {
                $key = substr($key, 0, -2);
            }
        }
        $smarty->assign('action_name', $key);
        break;
    }
}

$smarty->assign('heading', TITLE);
$smarty->assign('formurl', get_config('wwwroot') . 'view/blocks.php');
$smarty->assign('category', $category);
$smarty->assign('new', $new);
$smarty->assign('profile', $profile);
$smarty->assign('view', $view->get('id'));
$smarty->assign('groupid', $group);
$smarty->assign('institution', $institution);
$smarty->assign('can_change_layout', (!$USER->get_account_preference('addremovecolumns') || ($view->get('numcolumns') > 1 && $view->get('numcolumns') < 5)));
$smarty->display('view/blocks.tpl');

?>
