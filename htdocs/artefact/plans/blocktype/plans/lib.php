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

        if (!empty($configdata['artefactids']) && is_array($configdata['artefactids'])) {
            if (count($configdata['artefactids']) > 1) {
                return get_string('title', 'blocktype.plans/plans');
            }
            else if (count($configdata['artefactids']) == 1) {
                return $bi->get_artefact_instance($configdata['artefactids'][0])->get('title');
            }
        }
        return '';
    }

    public static function get_instance_javascript(BlockInstance $bi) {
        $blockid = $bi->get('id');
        return array(
            array(
                'file'   => 'js/plansblock.js',
            )
        );
    }

    public static function render_instance(BlockInstance $instance, $editing=false, $versioning=false) {
        global $exporter;

        require_once(get_config('docroot') . 'artefact/lib.php');
        safe_require('artefact','plans');

        $configdata = $instance->get('configdata');
        $limit = (!empty($configdata['count'])) ? $configdata['count'] : 10;
        $smarty = smarty_core();

        // CATALYST CUSTOM - make sure we have institution tagged items for QM dashboard.
        $view = $instance->get_view();
        if ($view->get('type') == 'qmdashboard') {
            $limit = 4;
            $filtertag = param_variable('tag', null);
            $institution = get_config_plugin('module', 'qmframework', 'qminstitution');
            $institutionrow = get_record('institution', 'name', $institution);
            $institutionid = $institutionrow->id;
            $institutionname = $institutionrow->displayname;
            $qmbaseurl = get_config('wwwroot') . 'module/qmframework/dashboard.php?id=' . $view->get('id') . '&tag=' . $filtertag;

            // Reset the list of plans to ones tagged wht institution tags when on QM dashboard.
            if (!$filtertag) {
                $userid = $view->get('owner');

                // First, find all the institution tags.
                $tagsql = "
                    SELECT id
                      FROM {tag} t
                     WHERE resourcetype = ?
                       AND resourceid = ?";
                $tagresults = get_records_sql_array($tagsql, ['institution', $institutionid]);

                // If we found some, let's find the artefacts connected to them.
                if (!empty($tagresults)) {
                    $tagvalues = [];
                    foreach ($tagresults as $tag) {
                        $tagvalues[] = 'tagid_' . $tag->id;
                    }
                    // Get all the artefacts that are plans or tasks, tagged with any tag that is QM institution.
                    $artefactsql = "
                        SELECT DISTINCT a.id, a.artefacttype, a.parent
                          FROM {artefact} a
                          JOIN {tag} t ON (t.resourcetype = ? AND t.resourceid = a.id)
                         WHERE a.owner = ?
                           AND a.artefacttype IN (?, ?)
                           AND t.tag IN (" . join(',', array_fill(0, count($tagvalues), '?')) . ")";
                    $artefactvalues = array_merge(['artefact', $userid, 'plan', 'task'], $tagvalues);

                    $artefacts = get_records_sql_array($artefactsql, $artefactvalues);

                    // Make sure we add a connection from the view to this artefact if for some reason it doesn't have one.
                    $planids = array();
                    if (!empty($artefacts)) {
                        foreach ($artefacts as $artefact) {
                            if ($artefact->artefacttype == 'plan') {
                                $planids[$artefact->id] = $artefact->id;
                                if (!$viewartefact = get_field('view_artefact', 'id', 'view', $view->get('id'), 'artefact', $artefact->id)) {
                                    insert_record('view_artefact', (object) array('view' => $view->get('id'), 'artefact' => $artefact->id, 'block' => $instance->get('id')));
                                }
                            } else if ($artefact->artefacttype == 'task' && !array_key_exists($artefact->parent, $planids)) {
                                $planids[$artefact->parent] = $artefact->parent;
                            }
                        }
                    }
                    ksort($planids); // Sort by ID.

                    // Update the configdata with the identified Artefact IDs;
                    $configdata['artefactids'] = array_values($planids);
                    $newconfigdata = serialize($configdata);
                    $instance->set('configdata', $newconfigdata);
                    update_record('block_instance', array('configdata' => $newconfigdata), array('id' => $instance->get('id')));
                }
            }
        }
        // END CATALYST CUSTOM.

        $plans = array();
        $alltasks = array();
        $template = 'artefact:plans:taskrows.tpl';

        if (($versioning && isset($configdata['existing_artefacts']) && !empty($configdata['existing_artefacts'])) ||
           (isset($configdata['artefactids']) && is_array($configdata['artefactids']) && count($configdata['artefactids']) > 0)) {
            if ($versioning) {
                // recover the configdata from the version
                $blockid = $instance->get('id');
                $blocks = $versioning->blocks;
                foreach ($blocks as $key => $value) {
                  if ($value->originalblockid == $blockid) {
                    $versionblock = $value;
                    break;
                  }
                }
                $configdata = (array)$versionblock->configdata;
                $savedplans = $configdata['existing_artefacts'];
            }
            else {
                $savedplans = array_flip($configdata['artefactids']);
            }
            foreach ($savedplans as $planid => $plancontent) {
                try {
                    $tasks = isset($plancontent->tasks) ? (array)$plancontent->tasks : ArtefactTypeTask::get_tasks($planid, 0, $limit);
                    $blockid = $instance->get('id');

                    // CATALYST CUSTOM - filter tags for the QM dashboard.
                    if ($view->get('type') == 'qmdashboard' && $filtertag && strpos($filtertag, ':') !== false) {
                        if (!in_array($filtertag, $plan->get('tags')) && empty($tasks)) {
                            // If this plan matches the institution tag, OK.
                            // If we didn't match - but there's a matching task with the institution tag, OK.
                            // If neither, move to the next one.
                            continue;
                        }
                    }
                    // END CATALYST CUSTOM.

                    if ($exporter || $versioning) {
                        $pagination = false;
                    }
                    else {
                        $baseurl = $instance->get_view()->get_url();
                        $baseurl = ($view->get('type') == 'qmdashboard') ? $qmbaseurl : $baseurl; // CUSTOM CATALYST - use the QM Dashboard URL.
                        $baseurl .= ((false === strpos($baseurl, '?')) ? '?' : '&') . 'block=' . $blockid . '&planid=' . $planid . '&editing=' . $editing;
                        $pagination = array(
                            'baseurl'   => $baseurl,
                            'id'        => 'block' . $blockid . '_plan' . $planid . '_pagination',
                            'datatable' => 'tasklist_' . $blockid . '_plan' . $planid,
                            'jsonscript' => 'artefact/plans/viewtasks.json.php',
                        );
                    }
                    $configdata['view'] = $instance->get('view');
                    $configdata['block'] = $blockid;
                    $configdata['versioning'] = $versioning;
                    ArtefactTypeTask::render_tasks($tasks, $template, $configdata, $pagination, $editing, $versioning);
                    if (($exporter || $versioning) && $tasks['count'] > $tasks['limit']) {
                        $artefacturl = get_config('wwwroot') . 'artefact/artefact.php?artefact=' . $planid
                            . '&view=' . $instance->get('view');
                        $tasks['pagination'] = '<a href="' . $artefacturl . '">' . get_string('alltasks', 'artefact.plans') . '</a>';
                    }
                    if ($versioning) {
                        $plans[$planid]['title'] = $plancontent->title;
                        $plans[$planid]['description'] = $plancontent->description;
                        $plans[$planid]['owner'] = $plancontent->owner;
                        $plans[$planid]['tags'] = $plancontent->tags;
                    }
                    else {
                        $plan = artefact_instance_from_id($planid);
                        $plans[$planid]['title'] = $plan->get('title');
                        $plans[$planid]['description'] = $plan->get('description');
                        $plans[$planid]['owner'] = $plan->get('owner');
                        $plans[$planid]['tags'] = $plan->get('tags');
                    }
                    $plans[$planid]['id'] = $planid;
                    $plans[$planid]['view'] = $instance->get('view');
                    $plans[$planid]['details'] = get_config('wwwroot') . 'artefact/artefact.php?artefact=' . $planid . '&view=' .
                            $instance->get_view()->get('id') . '&block=' . $blockid;

                    // CATALYST CUSTOM - filter tags for the QM dashboard.
                    if ($view->get('type') == 'qmdashboard') {
                        $plans[$planid]['tags'] = array();
                        foreach ($plan->get('tags') as $tag) {
                            if ($filtertag && $tag === $filtertag) {
                                // The tags are arrays of strings of "Institution: Tag" or similar.
                                // So we're just looking for a simple and direct match.
                                array_push($plans[$planid]['tags'], $tag);
                            } else if (strpos($tag, $institutionname . ':') === 0) {
                                // Otherwise we're looking for a match on "Institution Name:" at the start, matching the QM inst.
                                array_push($plans[$planid]['tags'], $tag);
                            }
                        }
                    }
                    // END CATALYST CUSTOM.

                    $plans[$planid]['numtasks'] = $tasks['count'];
                    $tasks['planid'] = $planid;
                    array_push($alltasks, $tasks);
                }
                catch (ArtefactNotFoundException $e) {
                    // do nothing, the plan doesnt exists anymore
                }
                $smarty->assign('plans', $plans);
                $smarty->assign('alltasks', $alltasks);
            }
        }
        else {
           $smarty->assign('noplans', get_string('noplansselectone', 'blocktype.plans/plans'));
        }

        $smarty->assign('editing', $editing);
        $smarty->assign('blockid', $instance->get('id'));
        $smarty->assign('versioning', $versioning);
        return $smarty->fetch('blocktype:plans:content.tpl');
    }

    // My Plans blocktype only has 'title' option so next two functions return as normal
    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form(BlockInstance $instance) {
        $instance->set('artefactplugin', 'plans');
        $configdata = $instance->get('configdata');
        $owner = $instance->get_view()->get('owner');
        $form = array();
        if ($owner) {
            // Which resume field does the user want
            $form[] = self::artefactchooser_element((isset($configdata['artefactids'])) ? $configdata['artefactids'] : null);
            $form['count'] = array(
                'type' => 'text',
                'title' => get_string('taskstodisplay', 'blocktype.plans/plans'),
                'defaultvalue' => isset($configdata['count']) ? $configdata['count'] : 10,
                'size' => 3,
            );
        }
        else {
            $form['blocktemplatehtml'] = array(
                'type' => 'html',
                'value' => get_string('blockinstanceconfigownerchange', 'mahara'),
            );
            $form['blocktemplate'] = array(
                'type'    => 'hidden',
                'value'   => 1,
            );
        }

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
        return true;
    }

    public static function rewrite_blockinstance_config(View $view, $configdata) {
        safe_require('artefact', 'plans');
        if ($view->get('owner') !== null && !empty($configdata['blocktemplate'])) {
            if ($artefactids = get_column_sql('
                SELECT a.id FROM {artefact} a
                WHERE a.owner = ? AND a.artefacttype = ?', array($view->get('owner'), 'plan'))) {
                $configdata['artefactids'] = $artefactids;
            }
            else {
                $configdata['artefactids'] = array();
            }
            unset($configdata['blocktemplatehtml']);
            unset($configdata['blocktemplate']);
        }
        return $configdata;
    }

    public static function get_current_artefacts(BlockInstance $instance) {
        safe_require('artefact', 'plans');
        $configdata = $instance->get('configdata');
        $artefacts = array();

        foreach ($configdata['artefactids'] as $planid) {
            $plan = artefact_instance_from_id($planid);
            $tasks = ArtefactTypeTask::get_tasks($planid, 0, 0);
            $artefacts[$planid]['tasks'] = $tasks;
            $artefacts[$planid]['title'] = $plan->get('title');
            $artefacts[$planid]['description'] = $plan->get('description');
            $artefacts[$planid]['tags'] = $plan->get('tags');
            $artefacts[$planid]['owner'] = $plan->get('owner');
        }
        return $artefacts;
    }
}
