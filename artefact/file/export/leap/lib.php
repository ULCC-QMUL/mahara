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
 * @subpackage artefact-file-export-leap
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class LeapExportElementFile extends LeapExportElement {

    private $filename;

    public function add_links() {
       parent::add_links();
        // check for blog posts this file may be attached to
        if (!$posts = get_records_array('artefact_attachment',
            'attachment', $this->artefact->get('id'))) {
            return;
        }
        foreach ($posts as $p) {
            $post = artefact_instance_from_id($p->artefact);
            $this->add_artefact_link($post, 'is_attachment_of');
        }
    }

    public function get_leap_type() {
        return 'resource';
    }

    public function get_categories() {
        return array(
            array(
                'scheme' => 'resource_type',
                'term'   => 'Offline',
                'label'  => 'File',
            )
        );
    }

    public function assign_smarty_vars() {
        parent::assign_smarty_vars();
        $this->smarty->assign('summary', $this->artefact->get('description'));
        $this->smarty->assign('contentsrc', $this->exporter->get('filedir') . $this->filename);
    }

    public function add_attachments() {
        $this->filename = $this->exporter->add_attachment($this->artefact->get_path(), $this->artefact->get('title'));
    }

    public function get_content_type() {
        return $this->artefact->get('filetype');
    }

    public function get_content() {
        return '';
    }
}

class LeapExportElementFolder extends LeapExportElement {

    public function get_leap_type() {
        return 'selection';
    }

    public function get_categories() {
        return array(
            array(
                'scheme' => 'selection_type',
                'term'   => 'Folder',
            )
        );
    }

    public function get_content() {
        return hsc($this->artefact->get('description'));
    }
}

class LeapExportElementImage extends LeapExportElementFile { }
class LeapExportElementProfileIcon extends LeapExportElementFile { }
