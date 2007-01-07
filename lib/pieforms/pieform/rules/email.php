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
 * @subpackage rule
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

/**
 * Returns whether the given field is a valid e-mail address.
 *
 * Currently, the check is [anything]@[anything]. Someone is welcome to write
 * something better, this was made just for testing.
 *
 * @param Pieform $form    The form the rule is being applied to
 * @param string  $value   The e-mail address to check
 * @param array   $element The element to check
 * @return string          The error message, if there is something wrong with
 *                         the address.
 */
function pieform_rule_email(Pieform $form, $value, $element) {
    if (!preg_match('/^[a-z0-9\._%-]+@(?:[a-z0-9-]+\.)+[a-z]{2,4}$/', $value)) {
        return $form->i18n('rule', 'email', 'email', $element);
    }
}

function pieform_rule_email_i18n() {
    return array(
        'en.utf8' => array(
            'email' => 'E-mail address is invalid'
        )
    );
}

?>
