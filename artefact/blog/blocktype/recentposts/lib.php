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
 * @subpackage blocktype-recentposts
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class PluginBlocktypeRecentposts extends PluginBlocktype {

    public static function get_title() {
        return get_string('title', 'blocktype.blog/recentposts');
    }


    public static function get_description() {
        return get_string('description', 'blocktype.blog/recentposts');
    }

    public static function get_categories() {
        return array('blog');
    }

    public static function get_viewtypes() {
        return array('portfolio', 'profile');
    }

    public static function render_instance(BlockInstance $instance) {
        $configdata = $instance->get('configdata');

        $result = '';
        if (!empty($configdata['artefactids'])) {
            $artefactids = implode(', ', array_map('db_quote', $configdata['artefactids']));
            if (!$mostrecent = get_records_sql_array(
            'SELECT a.title, ' . db_format_tsfield('a.ctime', 'ctime') . ', p.title AS parenttitle, a.id, a.parent
                FROM {artefact} a
                JOIN {artefact} p ON a.parent = p.id
                WHERE a.artefacttype = \'blogpost\'
                AND a.parent IN ( ' . $artefactids . ' ) 
                AND a.owner = (SELECT owner from {view} WHERE id = ?)
                ORDER BY a.ctime DESC
                LIMIT 10', array($instance->get('view')))) {
                $mostrecent = array();
            }
            // format the dates
            foreach ($mostrecent as &$data) {
                $data->displaydate = format_date($data->ctime);
            }
            $smarty = smarty_core();
            $smarty->assign('mostrecent', $mostrecent);
            $smarty->assign('view', $instance->get('view'));
            $result = $smarty->fetch('blocktype:recentposts:recentposts.tpl');
        }

        return $result;
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance) {
        $configdata = $instance->get('configdata');
        return array(
            self::artefactchooser_element((isset($configdata['artefactids'])) ? $configdata['artefactids'] : null),
        );
    }

    public static function artefactchooser_element($default=null) {
        return array(
            'name'  => 'artefactids',
            'type'  => 'artefactchooser',
            'title' => get_string('blogs', 'artefact.blog'),
            'defaultvalue' => $default,
            'rules' => array(
                'required' => true,
            ),
            'blocktype' => 'recentposts',
            'limit'     => 10,
            'selectone' => false,
            'artefacttypes' => array('blog'),
            'template'  => 'artefact:blog:artefactchooser-element.tpl',
        );
    }

    /**
     * Optional method. If specified, changes the order in which the artefacts are sorted in the artefact chooser.
     *
     * This is a valid SQL string for the ORDER BY clause. Fields you can sort on are as per the artefact table
     */
    public static function artefactchooser_get_sort_order() {
        return 'title';
    }

    public static function copy_allowed($newowner=null) {
        return false;
    }

    public static function copy_artefacts_allowed($newowner=null) {
        return false;
    }

}

?>
