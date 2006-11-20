<?php
/**
 * This program is part of Pieforms
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
 * @package    pieform
 * @subpackage element
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

/**
 * Renders a "cancel" button. Custom buttons are rendered nearly the same as
 * normal submit buttons, only their name is changed (for use by the Pieform
 * class internally).
 *
 * @param array $element    The element to render
 * @param Pieform  $form    The form to render the element for
 * @return string           The HTML for the element
 */
function pieform_render_cancel($element, Pieform $form) {
    if (!isset($element['value'])) {
        throw new PieformException('Cancel elements must have a value');
    }

    $attributes = Pieform::element_attributes($element);
    $attributes = preg_replace('/name="(.*)"/', 'name="cancel_$1"', $attributes);
    $attributes = preg_replace('/id="(.*)"/', 'id="cancel_$1"', $attributes);
    return '<input type="submit"'
        . $attributes
        . ' value="' . Pieform::hsc($element['value']) . '">';
}

// @todo how to support cancel buttons for ajax post? Possibly do a full post regardless...
// or allow the user to specify a javascript function to run... it could do document.location=
// @todo also, cancel buttons don't need to be sent around via js... maybe make this return empty string
function pieform_get_value_js_cancel($element, Pieform $form) {
    //$formname = $form->get_name();
    //$name = $element['name'];
    //return "    data['{$name}_cancel'] = document.forms['$formname'].elements['{$name}_cancel'].value;\n";
    return '';
}

?>
