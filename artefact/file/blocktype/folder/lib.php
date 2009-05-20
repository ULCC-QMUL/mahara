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
 * @subpackage blocktype-image
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class PluginBlocktypeFolder extends PluginBlocktype {

    public static function get_title() {
        return get_string('title', 'blocktype.file/folder');
    }

    /**
     * Optional method. If exists, allows this class to decide the title for 
     * all blockinstances of this type
     */
    public static function get_instance_title(BlockInstance $bi) {
        $configdata = $bi->get('configdata');

        if (!empty($configdata['artefactid'])) {
            require_once(get_config('docroot') . 'artefact/lib.php');
            $folder = artefact_instance_from_id($configdata['artefactid']);
            return $folder->get('title');
        }
        return '';
    }

    public static function get_description() {
        return get_string('description', 'blocktype.file/folder');
    }

    public static function get_categories() {
        return array('fileimagevideo');
    }

    public static function get_viewtypes() {
        return array('portfolio', 'profile');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        require_once(get_config('docroot') . 'artefact/lib.php');
        $configdata = $instance->get('configdata');
        $configdata['viewid'] = $instance->get('view');
        $configdata['simpledisplay'] = true;

        // This can be either an image or profileicon. They both implement 
        // render_self
        $result = '';
        if (isset($configdata['artefactid'])) {
            $folder = $instance->get_artefact_instance($configdata['artefactid']);
            $result = $folder->render_self($configdata);
            $result = $result['html'];
        }

        return $result;
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance, $istemplate) {
        $configdata = $instance->get('configdata');
        return array(
            self::artefactchooser_element((isset($configdata['artefactid'])) ? $configdata['artefactid'] : null, $istemplate),
        );
    }

    public static function artefactchooser_element($default=null, $istemplate=false) {
        $element = array(
            'name'  => 'artefactid',
            'type'  => 'artefactchooser',
            'title' => get_string('folder', 'artefact.file'),
            'defaultvalue' => $default,
            'blocktype' => 'folder',
            'limit' => 10,
            'artefacttypes' => array('folder'),
            'template' => 'artefact:file:artefactchooser-element.tpl',
        );
        if (!$istemplate) {
            // You don't have to choose a folder if this view is a template
            $element['rules'] = array(
                'required' => true,
            );
        }
        return $element;
    }

    /**
     * Optional method. If specified, allows the blocktype class to munge the 
     * artefactchooser element data before it's templated
     */
    public static function artefactchooser_get_element_data($artefact) {
        $folderdata = ArtefactTypeFileBase::artefactchooser_folder_data($artefact);

        $artefact->icon = call_static_method(generate_artefact_class_name($artefact->artefacttype), 'get_icon', array('id' => $artefact->id));
        $artefact->hovertitle = $artefact->description;

        $path = $artefact->parent ? ArtefactTypeFileBase::get_full_path($artefact->parent, $folderdata->data) : '';
        $artefact->description = str_shorten_text($folderdata->ownername . $path . $artefact->title, 30);

        return $artefact;
    }

    public static function artefactchooser_get_sort_order() {
        return 'parent, title';
    }

    public static function default_copy_type() {
        return 'full';
    }

}

?>
