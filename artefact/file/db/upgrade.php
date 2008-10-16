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
 * @subpackage artefact-file
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

function xmldb_artefact_file_upgrade($oldversion=0) {
    
    $status = true;

    if ($oldversion < 2007010900) {
        $table = new XMLDBTable('artefact_file_files');
        $field = new XMLDBField('adminfiles');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 1, false, true, false, null, null, 0);
        add_field($table, $field);
        set_field('artefact_file_files', 'adminfiles', 0);

        // Put all folders into artefact_file_files
        $folders = get_column_sql("
            SELECT a.id
            FROM {artefact} a
            LEFT OUTER JOIN {artefact_file_files} f ON a.id = f.artefact
            WHERE a.artefacttype = 'folder' AND f.artefact IS NULL");
        if ($folders) {
            foreach ($folders as $folderid) {
                $data = (object) array('artefact' => $folderid, 'adminfiles' => 0);
                insert_record('artefact_file_files', $data);
            }
        }
    }

    if ($oldversion < 2007011800) {
        // Make sure the default quota is set
        set_config_plugin('artefact', 'file', 'defaultquota', 10485760);
    }

    if ($oldversion < 2007011801) {
        // Create image table
        $table = new XMLDBTable('artefact_file_image');

        $table->addFieldInfo('artefact', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('width', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('height', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null);

        $table->addKeyInfo('artefactfk', XMLDB_KEY_FOREIGN, array('artefact'), 'artefact', array('id'));

        $status = $status && create_table($table);

        $images = get_column('artefact', 'id', 'artefacttype', 'image');
        log_debug(count($images));
        require_once(get_config('docroot') . 'artefact/lib.php');
        foreach ($images as $imageid) {
            $image = artefact_instance_from_id($imageid);
            $path = $image->get_path();
            $image->set('dirty', false);
            $data = new StdClass;
            $data->artefact = $imageid;
            if (file_exists($path)) {
                list($data->width, $data->height) = getimagesize($path);
            }

            if (empty($data->width) || empty($data->height)) {
                $data->width = 0;
                $data->height = 0;
            }
            insert_record('artefact_file_image', $data);
        }
    }

    if ($oldversion < 2007013100) {
        // Add new tables for file/mime types
        $table = new XMLDBTable('artefact_file_file_types');

        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('enabled', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 1);

        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('description'));

        create_table($table);

        $table = new XMLDBTable('artefact_file_mime_types');

        $table->addFieldInfo('mimetype', XMLDB_TYPE_TEXT, 128, null, XMLDB_NOTNULL);
        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, 128, null, XMLDB_NOTNULL);

        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('mimetype'));
        $table->addKeyInfo('descriptionfk', XMLDB_KEY_FOREIGN, array('description'), 'artefact_file_file_types', array('description'));

        create_table($table);

        safe_require('artefact', 'file');
        PluginArtefactFile::resync_filetype_list();
    }

    if ($oldversion < 2007021400) {
        $table = new XMLDBTable('artefact_file_files');
        $field = new XMLDBField('oldextension');
        $field->setAttributes(XMLDB_TYPE_TEXT);
        add_field($table, $field);
    }

    if ($oldversion < 2007042500) {
        // migrate everything we had to change to  make mysql happy
        execute_sql("ALTER TABLE {artefact_file_file_types} ALTER COLUMN description TYPE varchar(32)");
        execute_sql("ALTER TABLE {artefact_file_mime_types} ALTER COLUMN mimetype TYPE varchar(128)");
        execute_sql("ALTER TABLE {artefact_file_mime_types} ALTER COLUMN description TYPE varchar(32)");

    }

    if ($oldversion < 2008091100) {
        $table = new XMLDBTable('artefact_file_files');
        $field = new XMLDBField('fileid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null);
        add_field($table, $field);
        execute_sql("UPDATE {artefact_file_files} SET fileid = artefact WHERE NOT size IS NULL");
    }

    if ($oldversion < 2008101400) {
        $table = new XMLDBTable('artefact_file_files');
        $field = new XMLDBField('filetype');
        $field->setAttributes(XMLDB_TYPE_TEXT);
        add_field($table, $field);
        // Guess mime type for existing files
        $fileartefacts = get_records_sql_array('
            SELECT
                a.artefacttype, f.artefact, f.oldextension, f.fileid
            FROM
                {artefact} a,
                {artefact_file_files} f
            WHERE
                a.id = f.artefact
        ', array());
        require_once(get_config('libroot') . 'file.php');
        if ($fileartefacts) {
            foreach ($fileartefacts as $a) {
                $type = null;
                if ($a->artefacttype == 'image') {
                    $size = getimagesize(get_config('dataroot') . 'artefact/file/originals/' . ($a->fileid % 256) . '/' . $a->fileid);
                    $type = $size['mime'];
                }
                else if ($a->artefacttype == 'profileicon') {
                    $size = getimagesize(get_config('dataroot') . 'artefact/file/profileicons/originals/' . ($a->fileid % 256) . '/' . $a->fileid);
                    $type = $size['mime'];
                }
                else if ($a->artefacttype == 'file') {
                    $type = get_mime_type(get_config('dataroot') . 'artefact/file/originals/' . ($a->fileid % 256) . '/' . $a->fileid);
                }
                if ($type) {
                    set_field('artefact_file_files', 'filetype', $type, 'artefact', $a->artefact);
                }
            }
        }
        delete_records('config', 'field', 'pathtofile');
    }

    // everything up to here we pre mysql support.
    return $status;
}

?>
