<?php
/**
 *
 * @package    mahara
 * @subpackage blocktype-plans
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

defined('INTERNAL') || die();

class PluginBlocktypePlans extends MaharaCoreBlocktype {

    public static function get_title() {
        return get_string('title', 'blocktype.plans/plans');
    }

    public static function get_description() {
        return get_string('description1', 'blocktype.plans/plans');
    }

    public static function get_categories() {
        return array('general' => 22000);
    }

     /**
     * Optional method. If exists, allows this class to decide the title for
     * all blockinstances of this type
     */
    public static function get_instance_title(BlockInstance $bi) {
        $configdata = $bi->get('configdata');

        if (!empty($configdata['artefactids'])) {
            if (is_array($configdata['artefactids']) && count($configdata['artefactids']) > 1) {
                return get_string('title', 'blocktype.plans/plans');
            } else if (count($configdata['artefactids']) == 1) {
                return $bi->get_artefact_instance($configdata['artefactids'][0])->get('title');
            } else {
                return $bi->get_artefact_instance($configdata['artefactids'])->get('title');
            }
        }
        return '';
    }

    public static function get_instance_javascript(BlockInstance $bi) {
        $blockid = $bi->get('id');
        return array(
            array(
                'file'   => 'js/plansblock.js',
                'initjs' => "initNewPlansBlock($blockid);",
            )
        );
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        global $exporter;

        require_once(get_config('docroot') . 'artefact/lib.php');
        safe_require('artefact','plans');

        $configdata = $instance->get('configdata');
        $limit = (!empty($configdata['count'])) ? $configdata['count'] : 10;

        // CUSTOM CATALYST - filter tags for the QM Dashboard.
        global $view;
        if ($view->get('type') == 'qmdashboard') {
            $filtertag = param_variable('tag', null);
            $institution = get_config_plugin('module', 'qmframework', 'qminstitution');
            $institutionid = get_field('institution', 'id', 'name', $institution);

            // Add to $configdata['artefactids'] the ids of the plans taged with institution tags.
            if (!$filtertag) {
                $userid = $view->get('owner');
                $artefacts = get_records_sql_array("
                    SELECT DISTINCT a.id, a.artefacttype, a.parent
                    FROM {artefact} a
                    JOIN {artefact_tag} at ON at.artefact = a.id
                    JOIN {tag} t ON at.tagid = t.id AND t.owner = ?
                   WHERE a.owner = ? AND at.tagid != 0 AND a.artefacttype IN ('plan', 'task')", array($institutionid, $userid));

                $planids = array();
                if ($artefacts) {
                    foreach ($artefacts as $artefact) {
                        if ($artefact->artefacttype == 'plan') {
                            array_push($planids, $artefact->id);
                        } else {
                            // If a plan has more than one tagged task or is itself tagged, make sure we're addig it just once.
                            if (!in_array($artefact->parent, $planids)) {
                                array_push($planids, $artefact->parent);
                            }
                        }
                    }
                }
                if ($configdata['artefactids'] == null || array_diff($configdata['artefactids'], $planids) || array_diff($planids, $configdata['artefactids'])) {
                    $configdata['artefactids'] = $planids;
                    $newconfigdata = serialize($configdata);
                    $instance->set('configdata', $newconfigdata);
                    update_record('block_instance', array('configdata' => $newconfigdata), array('id' => $instance->get('id')));
                }
            }
        }
        // END CUSTOM CATALYST.

        $smarty = smarty_core();
        if (isset($configdata['artefactids']) && count($configdata['artefactids']) > 0) {
            $plans = array();
            $alltasks = array();
            foreach ($configdata['artefactids'] as $planid) {
                $plan = artefact_instance_from_id($planid);

                // CUSTOM CATALYST - filter tags for the QM Dashboard.
                if ($view->get('type') == 'qmdashboard') {
                    $alltags = $plan->get('tags');
                    $tagkeys = array_map(function($k) {
                        return $k->tag;
                    }, $alltags);
                    if ($filtertag && !in_array($filtertag, $tagkeys)) {
                        continue;
                    }
                }
                // END CUSTOM CATALYST.

                $tasks = ArtefactTypeTask::get_tasks($planid, 0, $limit);

                $template = 'artefact:plans:taskrows.tpl';
                $blockid = $instance->get('id');
                if ($exporter) {
                    $pagination = false;
                } else {
                    $baseurl = $instance->get_view()->get_url();
                    $baseurl .= ((false === strpos($baseurl, '?')) ? '?' : '&') . 'block=' . $blockid . '&planid=' . $planid;
                    $pagination = array(
                        'baseurl'   => $baseurl,
                        'id'        => "block{$blockid}_plan{$planid}_pagination",
                        'datatable' => "tasklist_{$blockid}_plan{$planid}",
                        'jsonscript' => 'artefact/plans/viewtasks.json.php',
                    );
                }
                ArtefactTypeTask::render_tasks($tasks, $template, $configdata, $pagination, $editing);

                if ($exporter && $tasks['count'] > $tasks['limit']) {
                    $artefacturl = get_config('wwwroot') . 'artefact/artefact.php?artefact=' . $planid
                        . '&view=' . $instance->get('view');
                    $tasks['pagination'] = '<a href="' . $artefacturl . '">' . get_string('alltasks', 'artefact.plans') . '</a>';
                }
                $plans[$planid]['id'] = $planid;
                $plans[$planid]['title'] = $plan->get('title');
                $plans[$planid]['description'] = $plan->get('description');
                $plans[$planid]['owner'] = $plan->get('owner');
                $plans[$planid]['tags'] = $plan->get('tags');
                $plans[$planid]['details'] = '/artefact/artefact.php?artefact=' . $plan->get('id') . '&view=' .
                        $instance->get_view()->get('id') . '&block=' . $blockid;

                // CUSTOM CATALYST - filter tags for the QM dashboard.
                if ($view->get('type') == 'qmdashboard') {
                    $plans[$planid]['tags'] = array();
                    foreach ($plan->get('tags') as $tag) {
                        if ($tag->ownerid && $tag->ownerid == $institutionid) {
                            array_push($plans[$planid]['tags'], $tag);
                        }
                    }
                }
                // END CUSTOM CATALYST.

                $plans[$planid]['numtasks'] = $tasks['count'];

                $tasks['planid'] = $planid;
                array_push($alltasks, $tasks);
            }
            // var_dump($alltasks);die;
            $smarty->assign('editing', $editing);
            $smarty->assign('plans', $plans);
            $smarty->assign('alltasks', $alltasks);
        }
        else {
            $smarty->assign('noplans','blocktype.plans/plans');
        }
        $smarty->assign('blockid', $instance->get('id'));
        return $smarty->fetch('blocktype:plans:content.tpl');
    }

    // My Plans blocktype only has 'title' option so next two functions return as normal
    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form(BlockInstance $instance) {
        $instance->set('artefactplugin', 'plans');
        $configdata = $instance->get('configdata');

        $form = array();

        // Which resume field does the user want
        $form[] = self::artefactchooser_element((isset($configdata['artefactids'])) ? $configdata['artefactids'] : null);
        $form['count'] = array(
            'type' => 'text',
            'title' => get_string('taskstodisplay', 'blocktype.plans/plans'),
            'defaultvalue' => isset($configdata['count']) ? $configdata['count'] : 10,
            'size' => 3,
        );

        return $form;
    }

    public static function artefactchooser_element($default=null) {
        safe_require('artefact', 'plans');
        return array(
            'name'  => 'artefactids',
            'type'  => 'artefactchooser',
            'title' => get_string('planstoshow', 'blocktype.plans/plans'),
            'defaultvalue' => $default,
            'blocktype' => 'plans',
            'selectone' => false,
            'search'    => false,
            'artefacttypes' => array('plan'),
            'template'  => 'artefact:plans:artefactchooser-element.tpl',
        );
    }

    public static function allowed_in_view(View $view) {
        return $view->get('owner') != null;
    }
}
