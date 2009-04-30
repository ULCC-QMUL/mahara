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
 * @subpackage artefact-blog-export-leap
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class LeapExportElementBlogpost extends LeapExportElement {

    public function add_links() {
       parent::add_links();
        // add on attachments
        if (!$attachments = $this->artefact->attachment_id_list()) {
            return;
        }
        foreach ($attachments as $attachment) {
            $f = artefact_instance_from_id($attachment);
            $this->add_artefact_link($f, 'has_attachment');
        }
    }

    public function replace_content_placeholders($content) {
        $content = parent::replace_content_placeholders($content, 'ARTEFACT(DL|VIEW)LINK');
        return $content;
    }

    public function get_content_type() {
        return 'html';
    }

    public function get_categories() {
        if (!$this->artefact->get('published')) {
            return array(
                array(
                    'scheme' => 'readiness',
                    'term'   => 'Unready',
                )
            );
        }
        return array();
    }
}

class LeapExportElementBlog extends LeapExportElement {

    public function get_leap_type() {
        return 'selection';
    }

    public function get_categories() {
        return array(
            array(
                'scheme' => 'selection_type',
                'term'   => 'Blog',
            )
        );
    }

    public function get_content_type() {
        return 'html';
    }
}

?>
