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
 * @subpackage blocktype-html
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class PluginBlocktypeHtml extends PluginBlocktype {

    public static function get_title() {
        return get_string('title', 'blocktype.file/html');
    }

    public static function get_description() {
        return get_string('description', 'blocktype.file/html');
    }

    public static function get_categories() {
        return array('fileimagevideo');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        $configdata = $instance->get('configdata'); // this will make sure to unserialize it for us
        $configdata['viewid'] = $instance->get('view');

        $result = '';
        if (isset($configdata['artefactid'])) {
            $html = $instance->get_artefact_instance($configdata['artefactid']);

            if (!file_exists($html->get_path())) {
                return;
            }

            $result = clean_html(file_get_contents($html->get_path()));
        }

        return $result;
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance, $istemplate) {
        $configdata = $instance->get('configdata');
        safe_require('artefact', 'file');
        $instance->set('artefactplugin', 'file');
        return array(
            'filebrowser' => self::filebrowser_element($instance, (isset($configdata['artefactid'])) ? array($configdata['artefactid']) : null, $istemplate),
        );
    }

    public static function instance_config_save(&$values) {
        if (isset($values['filebrowser']['selected'])) {
            $values['artefactid'] = $values['filebrowser']['selected'][0];
        }
        unset($values['filebrowser']);
        return $values;
    }

    private static function get_allowed_mimetypes() {
        static $mimetypes = array();
        if (!$mimetypes) {
            $mimetypes = get_column('artefact_file_mime_types', 'mimetype', 'description', 'html');
        }
        return $mimetypes;
    }

    public static function filebrowser_element(&$instance, $default=array(), $istemplate=false) {
        $element = ArtefactTypeFileBase::blockconfig_filebrowser_element($instance, $default, $istemplate);
        $element['title'] = get_string('file', 'artefact.file');
        $element['config']['selectone'] = true;
        $element['filters'] = array(
            'artefacttype'    => array('file'),
            'filetype'        => self::get_allowed_mimetypes(),
        );
        return $element;
    }

    public static function artefactchooser_element($default=null, $istemplate=false) {
        $extraselect = 'filetype IN (' . join(',', array_map('db_quote', self::get_allowed_mimetypes())) . ')';
        $extrajoin   = ' JOIN {artefact_file_files} ON {artefact_file_files}.artefact = a.id ';

        $element = array(
            'name'  => 'artefactid',
            'type'  => 'artefactchooser',
            'title' => get_string('file', 'artefact.file'),
            'defaultvalue' => $default,
            'blocktype' => 'html',
            'limit' => 10,
            'artefacttypes' => array('file'),
            'template' => 'artefact:file:artefactchooser-element.tpl',
            'extraselect' => $extraselect,
        );
        if (!$istemplate) {
            $element['rules'] = array(
                'required' => true,
            );
        }
        return $element;
    }

    public static function get_viewtypes() {
        return array('portfolio', 'profile');
    }

    public static function default_copy_type() {
        return 'full';
    }

}

?>
