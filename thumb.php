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
define('PUBLIC', 1);
require('init.php');
require_once('file.php');

$type = param_alpha('type');

switch ($type) {
    case 'profileiconbyid':
    case 'profileicon':
        $id = param_integer('id');
        $size = get_imagesize_parameters();

        if ($type == 'profileicon') {
            // Convert ID of user to the ID of a profileicon
            $id = get_field('usr', 'profileicon', 'id', $id);
        }

        if ($id) {
            if ($path = get_dataroot_image_path('artefact/file/profileicons', $id, $size)) {
                $mimetype = get_mime_type($path);
                if ($mimetype) {
                    header('Content-type: ' . $mimetype);

                    // We can't cache 'profileicon' for as long, because the 
                    // user can change it at any time. But we can cache 
                    // 'profileiconbyid' for quite a while, because it will 
                    // never change
                    if ($type == 'profileiconbyid') {
                        $maxage = 604800; // 1 week
                    }
                    else {
                        $maxage = 600; // 10 minutes
                    }
                    header('Expires: '. gmdate('D, d M Y H:i:s', time() + $maxage) .' GMT');
                    header('Cache-Control: max-age=' . $maxage);
                    header('Pragma: public');

                    readfile($path);
                    exit;
                }
            }
        }

        // We couldn't find an image for this user. Attempt to use the 'no user 
        // photo' image for the current theme

        // We can cache such images
        $maxage = 604800;
        header('Expires: '. gmdate('D, d M Y H:i:s', time() + $maxage) .' GMT');
        header('Cache-Control: max-age=' . $maxage);
        header('Pragma: public');

        // NOTE: the institutional admin branch allows the theme to be locked 
        // down. This means that $USER->get('theme') should be used here 
        // instead, when that branch is merged. And don't forget to change it 
        // below at the other get_config('theme') call!
        if ($path = get_dataroot_image_path('artefact/file/profileicons/no_userphoto/' . get_config('theme'), 0, $size)) {
            header('Content-type: ' . 'image/png');
            readfile($path);
            exit;
        }

        // If we couldn't find the no user photo picture, we put it into 
        // dataroot if we can
        $nouserphotopic = theme_get_path('images/no_userphoto.png');
        if ($nouserphotopic) {
            // Move the file into the correct place.
            $directory = get_config('dataroot') . 'artefact/file/profileicons/no_userphoto/' . get_config('theme') . '/originals/0/';
            check_dir_exists($directory);
            copy($nouserphotopic, $directory . '0');
            // Now we can try and get the image in the correct size
            if ($path = get_dataroot_image_path('artefact/file/profileicons/no_userphoto/' . get_config('theme'), 0, $size)) {
                header('Content-type: ' . 'image/png');
                readfile($path);
                exit;
            }
        }


        // Emergency fallback
        header('Content-type: ' . 'image/png');
        readfile(theme_get_path('images/no_userphoto.png'));
        exit;
        break;

    case 'blocktype':
        $bt = param_alpha('bt'); // blocktype
        $ap = param_alpha('ap', null); // artefact plugin (optional)
        
        $basepath = 'blocktype/' . $bt;
        if (!empty($ap)) {
            $basepath = 'artefact/' . $ap . '/' . $basepath;
        }
        header('Content-type: image/png');
        $maxage = 604800;
        header('Expires: '. gmdate('D, d M Y H:i:s', time() + $maxage) .' GMT');
        header('Cache-Control: max-age=' . $maxage);
        header('Pragma: public');
        $path = get_config('docroot') . $basepath . '/thumb.png';
        if (is_readable($path)) {
            readfile($path);
            exit;
        }
        readfile(theme_get_path('images/no_thumbnail.png'));
        break;
    case 'viewlayout':
        header('Content-type: image/png');
        $vl = param_integer('vl');
        if ($widths = get_field('view_layout', 'widths', 'id', $vl)) {
            if ($path = theme_get_path('images/vl-' . str_replace(',', '-', $widths) . '.png')) {
                readfile($path);
                exit;
            }
        }
        readfile(theme_get_path('images/no_thumbnail.png'));
        break;
}

?>
