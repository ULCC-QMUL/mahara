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

define('INTERNAL', 1);
define('JSON', 1);

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
safe_require('artefact', 'plans');
require_once(get_config('docroot') . 'blocktype/lib.php');
require_once(get_config('docroot') . 'artefact/plans/blocktype/plans/lib.php');

$offset = param_integer('offset', 0);
$limit = param_integer('limit', 10);
$editing = param_variable('editing', false);

if ($blockid = param_integer('block', null)) {
    $bi = new BlockInstance($blockid);
    if (!can_view_view($bi->get('view'))) {
        json_reply(true, get_string('accessdenied', 'error'));
    }
    $options = $configdata = $bi->get('configdata');

    // If block sets limit use that instead
    $limit = !empty($configdata['count']) ? $configdata['count'] : $limit;
    $planid = param_integer('planid');
    $tasks = ArtefactTypeTask::get_tasks($planid, $offset, $limit);

    $template = 'artefact:plans:taskrows.tpl';
    $baseurl = $bi->get_view()->get_url();
    // CUSTOM Catalyst - use the QM Dashboard URL.
    $view = new View($bi->get('view'));
    $baseurl = ($view->get('type') == 'qmdashboard')
        ? get_config('wwwroot') . 'module/qmframework/dashboard.php?id=' . $view->get('id') . '&tag=' . param_variable('tag', '')
        : $baseurl;
    // END CUSTOM Catalyst.
    $baseurl .= ((false === strpos($baseurl, '?')) ? '?' : '&') . 'block=' . $blockid . '&planid=' . $planid . '&editing=' . $editing;
    $pagination = array(
        'baseurl'    => $baseurl,
        'id'         => "block{$blockid}_plan{$planid}_pagination",
        'datatable'  => "tasklist_{$blockid}_plan{$planid}",
        'jsonscript' => 'artefact/plans/viewtasks.json.php',
    );
}
else {
    $planid = param_integer('artefact');
    $viewid = param_integer('view');
    if (!can_view_view($viewid)) {
        json_reply(true, get_string('accessdenied', 'error'));
    }
    $options = array('viewid' => $viewid);
    $tasks = ArtefactTypeTask::get_tasks($planid, $offset, $limit);

    $template = 'artefact:plans:taskrows.tpl';
    $baseurl = get_config('wwwroot') . 'artefact/artefact.php?artefact=' . $planid . '&view=' . $options['viewid'];
    $pagination = array(
        'baseurl' => $baseurl,
        'id' => 'task_pagination',
        'datatable' => 'tasklist',
        'jsonscript' => 'artefact/plans/viewtasks.json.php',
    );

}
ArtefactTypeTask::render_tasks($tasks, $template, $options, $pagination, $editing);

json_reply(false, (object) array('message' => false, 'data' => $tasks));
