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
 * @subpackage blocktype-externalvideo
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

/**
 * todos before this block type can be considered complete
 *  - document this class and methods
 *  - correct category
 *  - more video url sources, and good default behaviour
 *  - block title editable
 *  - i18n
 *  - minvalue/maxvalue rules
 */
class PluginBlocktypeExternalvideo extends SystemBlocktype {

    // Default width and height for video players
    private static $default_width = 250;

    private static $default_height = 250;

    public static function get_title() {
        return get_string('title', 'blocktype.externalvideo');
    }

    public static function get_description() {
        return get_string('description', 'blocktype.externalvideo');
    }

    public static function get_categories() {
        return array('fileimagevideo');
    }

    public static function get_viewtypes() {
        return array('portfolio', 'profile');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        $configdata = $instance->get('configdata');
        $result = '';
        $url    = hsc(self::make_video_url($configdata['videoid']));
        $width  = (!empty($configdata['width'])) ? hsc($configdata['width']) : self::$default_width;
        $height = (!empty($configdata['height'])) ? hsc($configdata['height']) : self::$default_height;

        if (isset($configdata['videoid'])) {
            $result  = '<div class="center">';
            $result .= '<object width="' . $width . '" height="' . $height . '">';
            $result .= '<param name="movie" value="' . $url . '"></param>';
            $result .= '<param name="wmode" value="transparent"></param>';
            $result .= '<param name="allowscriptaccess" value="never"></param>';
            $result .= '<embed src="' . $url . '" ';
            $result .= 'type="application/x-shockwave-flash" wmode="transparent" width="' . $width . '" ';
            $result .= 'height="' . $height . '" allowscriptaccess="never"></embed></object>';
            $result .= '</div>';
        }

        return $result;
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance) {
        $configdata = $instance->get('configdata');
        return array(
            'videoid' => array(
                'type'  => 'text',
                'title' => get_string('videourl','blocktype.externalvideo'),
                'description' => get_string('videourldescription2','blocktype.externalvideo') . self::get_html_of_supported_websites(),
                'width' => '90%',
                'defaultvalue' => $configdata['videoid'],
                'rules' => array(
                    'required' => true
                ),
            ),
            'width' => array(
                'type' => 'text',
                'title' => get_string('width','blocktype.externalvideo'),
                'size' => 3,
                'rules' => array(
                    'required' => true,
                    'integer'  => true,
                    'minvalue' => 100,
                    'maxvalue' => 800,
                ),
                'defaultvalue' => (!empty($configdata['width'])) ? $configdata['width'] : self::$default_width,
            ),
            'height' => array(
                'type' => 'text',
                'title' => get_string('height','blocktype.externalvideo'),
                'size' => 3,
                'rules' => array(
                    'required' => true,
                    'integer'  => true,
                    'minvalue' => 100,
                    'maxvalue' => 800,
                ),
                'defaultvalue' => (!empty($configdata['height'])) ? $configdata['height'] : self::$default_height,
            ),
        );
    }

    private static function make_video_url($url) {
        static $embedsources = array(
            // www.youtube.com
            array(
                'match' => '#.*youtube\.com.*v(=|\/)([a-zA-Z0-9_-]+).*#',
                'url'   => 'http://www.youtube.com/v/$2'
            ),
            // video.google.com
            array(
                'match' => '#.*video.google.com.*docid=(\-?[0-9]+).*#',
                'url'   => 'http://video.google.com/googleplayer.swf?docId=$1',
            ),
            // www.teachertube.com
            array(
                'match' => '#.*teachertube.com/flvideo/([0-9]+)\.flv.*#',
                'url'   => 'http://www.teachertube.com/skin-p/mediaplayer.swf?file=http://www.teachertube.com/flvideo/$1.flv'
            ),
            // www.scivee.tv
            array(
                'match' => '#.*scivee.tv/node/([0-9]+).*#',
                'url'   => 'http://scivee.tv/flash/embedPlayer.swf?id=$1&type=3',
            ),
            array(
                'match' => '#.*scivee.tv.*id=([0-9]+).*#',
                'url'   => 'http://scivee.tv/flash/embedPlayer.swf?id=$1&type=3',
            ),
        );

        foreach ($embedsources as $source) {
            if (preg_match($source['match'], $url)) {
                return preg_replace($source['match'], $source['url'], $url);
            }
        }
        // TODO handle failure case
    }

    /**
     * Returns a block of HTML that the external video block can use to list 
     * which video sites are supported.
     */
    private static function get_html_of_supported_websites() {
        return <<<EOF
<ul style="list-style-type: none;">
    <li><a href="http://www.youtube.com/" target="_blank"><img src="http://www.youtube.com/favicon.ico" alt="YouTube"> youtube.com</a></li>
    <li><a href="http://video.google.com/" target="_blank"><img src="http://video.google.com/favicon.ico" alt="Google Video"> video.google.com</a></li>
    <li><a href="http://www.teachertube.com/" target="_blank"><img src="http://www.teachertube.com/images/favicon.ico" alt="TeacherTube"> teachertube.com</a></li>
    <li><a href="http://www.scivee.tv/" target="_blank"><img src="http://www.scivee.tv/favicon.ico" alt="SciVee"> scivee.tv</a></li>
</ul>
EOF;
    }

    public static function default_copy_type() {
        return 'full';
    }

}

?>
