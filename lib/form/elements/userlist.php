<?php
/**
 * This program is part of Mahara
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301 USA
 *
 * @package    mahara
 * @subpackage form-element
 * @author     Martyn Smith <martyn@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

/**
 * Provides a basic text field input.
 *
 * @todo this is just lies ...
 * @param array $element The element to render
 * @param Form  $form    The form to render the element for
 * @return string        The HTML for the element
 */
function form_render_userlist($element, Form $form) {
    $smarty = smarty();

    $smarty->left_delimiter = '{{';
    $smarty->right_delimiter = '}}';

    $value = $form->get_value($element);

    if (!is_array($value) && isset($element['defaultvalue']) && is_array($element['defaultvalue'])) {
        $value = $element['defaultvalue'];
    }

    if (is_array($value) && count($value)) {
        $members = get_records_select('usr','id IN (' . join(',',$value) . ')', null, '', 'id,firstname,lastname,prefname');

        foreach($members as &$member) {
            $member = display_name($member);
        }

        $smarty->assign('options',$members);
        $smarty->assign('value', join(',',$value));
    }

    $smarty->assign('name', $element['name']);
    if (!empty($element['filter'])) {
        $smarty->assign('filter', true);
    }

    return $smarty->fetch('form/userlist.tpl');
}

function form_get_value_userlist($element, Form $form) {
    $name = $element['name'];

    $global = ($form->get_method() == 'get') ? $_GET : $_POST;

    if (isset($global[$name])) {
        $value = $global[$name];

        if ($value == '') {
            return array();
        }

        if (preg_match('/^(\d+(,\d+)*)$/',$value)) {
            return array_map('intval', explode(',', $value));
        }

        throw new FormException("Invalid value for userlist form element '$name' = '$value'");
    }

    return null;
}

function form_is_empty_userlist($value, $element) {
    if (is_array($value) && count($value)) {
        return false;
    }

    return true;
}

function form_render_userlist_set_attributes($element) {
    // By default, use the filter select box
    if (!isset($element['filter'])) {
        $element['filter'] = true;
    }
    return $element;
}

?>
