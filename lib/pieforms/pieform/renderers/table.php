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
 * @subpackage renderer
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

/**
 * Renders form elements inside a <table>.
 *
 * @param Pieform $form         The form the element is being rendered for
 * @param string  $builtelement The element, already built
 * @param array   $rawelement   The element in raw form, for looking up
 *                              information about it.
 * @return string               The element rendered inside an appropriate
 *                              container.
 */
function pieform_renderer_table(Pieform $form, $builtelement, $rawelement) {
    $formname = $form->get_name();
    if ($rawelement['type'] == 'fieldset') {
        // Add table tags to the build element, to preserve HTML compliance
        if (0 === strpos($builtelement, "\n<fieldset>\n<legend>")) {
            $closelegendpos = strpos($builtelement, '</legend>') + 9;
            $builtelement = substr($builtelement, 0, $closelegendpos) . '<table>' . substr($builtelement, $closelegendpos);
        }
        else {
            $builtelement = substr($builtelement, 0, 11) . '<table>' . substr($builtelement, 11);
        }
        $builtelement = substr($builtelement, 0, -12) . '</table></fieldset>';

        $result = "\t<tr>\n\t\t<td colspan=\"2\">";
        $result .= $builtelement;
        $result .= "</td>\n\t</tr>";
        return $result;
    }
    
    $result = "\t<tr";
    $result .= ' id="' . $formname . '_' . $rawelement['name'] . '_container"';
    // Set the class of the enclosing <tr> to match that of the element
    if ($rawelement['class']) {
        $result .= ' class="' . $rawelement['class'] . '"';
    }
    $result .= ">\n\t\t";

    $result .= '<th>';
    if (isset($rawelement['title']) && $rawelement['title'] !== '') {
        if (!empty($rawelement['nolabel'])) {
            // Don't bother with a label for the element
            $result .= Pieform::hsc($rawelement['title']);
        }
        else {
            $result .= '<label for="' . $formname . '_' . $rawelement['id'] . '">' . Pieform::hsc($rawelement['title']) . '</label>';
        }
    }
    $result .= "</th>\n\t\t<td>";
    $result .= $builtelement;

    // Contextual help
    if (!empty($rawelement['help'])) {
        $result .= ' <span class="help"><a href="#" title="' . Pieform::hsc($rawelement['help']) . '">?</a></span>';
    }

    $result .= "</td>\n\t</tr>\n";

    // Description - optional description of the element, or other note that should be visible
    // on the form itself (without the user having to hover over contextual help 
    if (!empty($rawelement['description'])) {
        $result .= "\t<tr>\n\t\t<td colspan=\"2\" class=\"description\">";
        $result .= $rawelement['description'];
        $result .= "</td>\n\t</tr>\n";
    }

    if (!empty($rawelement['error'])) {
        $result .= "\t<tr>\n\t\t<td colspan=\"2\" class=\"errmsg\">";
        $result .= $rawelement['error'];
        $result .= "</td>\n\t</tr>\n";
    }

    return $result;
}

function pieform_renderer_table_header() {
    return "<table cellspacing=\"0\" border=\"0\"><tbody>\n";
}

function pieform_renderer_table_footer() {
    return "</tbody></table>\n";
}

function pieform_renderer_table_messages_js($id, $submitid) {
    $result = <<<EOF
// Given a message and form element name, should set an error on the element
function {$id}_set_error(message, element) {
    {$id}_remove_error(element);
    element += '_container';
    // @todo set error class on input elements...
    $(element).parentNode.insertBefore(TR({'id': '{$id}_error_' + element}, TD({'colspan': 2, 'class': 'errmsg'}, message)), $(element).nextSibling);
}
// Given a form element name, should remove an error associated with it
function {$id}_remove_error(element) {
    element += '_container';
    var elem = $('{$id}_error_' + element);
    if (elem) {
        removeElement(elem);
    }
}
function {$id}_remove_all_errors() {
    forEach(getElementsByTagAndClassName('TD', 'errmsg', $('$id')), function(item) {
        removeElement(item.parentNode);
    });
}
function {$id}_message(message, type) {
    var elem = $('{$id}_pieform_message');
    var msg  = TR({'id': '{$id}_pieform_message'}, TD({'colspan': 2, 'class': type}, message));
    if (elem) {
        swapDOM(elem, msg);
    }
    else {
        appendChildNodes($('{$id}_{$submitid}_container').parentNode, msg);
    }
}
function {$id}_remove_message() {
    var elem = $('{$id}_pieform_message');
    if (elem) {
        removeElement(elem);
    }
}
    
EOF;
    return $result;
}

?>
