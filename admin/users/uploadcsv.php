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
 * @subpackage admin
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configusers');
define('SUBMENUITEM', 'uploadcsv');
require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once('pieforms/pieform.php');

$institutions = get_records_array('institution');
foreach ($institutions as $name => $data) {
    $options[$name] = $data->displayname;
}

$institutions = get_records_select_array('institution', "registerallowed = 1 AND authplugin = 'internal'");
if (count($institutions) > 1) {
    $options = array();
    foreach ($institutions as $institution) {
        $options[$institution->name] = $institution->displayname;
    }
    $institutionelement = array(
        'type' => 'select',
        'title' => get_string('institution'),
        'description' => get_string('institutiondescription'),
        'options' => $options
    );
}
else {
    $institutionelement = array(
        'type' => 'hidden',
        'value' => 'mahara'
    );
}

$form = array(
    'name' => 'uploadcsv',
    'method' => 'post',
    'action' => '',
    'elements' => array(
        'institution' => $institutionelement,
        'file' => array(
            'type' => 'file',
            'title' => get_string('csvfile', 'admin'),
            'description' => get_string('csvfiledescription', 'admin'),
            'rules' => array(
                'required' => true
            )
        ),
        'submit' => array(
            'type' => 'submit',
            'value' => get_string('uploadcsvfile', 'admin')
        )
    )
);

/**
 * The CSV file is parsed here so validation errors can be returned to the
 * user. The data from a successful parsing is stored in the <var>$CVSDATA</var>
 * array so it can be accessed by the submit function
 *
 * @param Pieform  $form   The form to validate
 * @param array    $values The values submitted
 */
function uploadcsv_validate(Pieform $form, $values) {
    global $CSVDATA;

    // Don't even start attempting to parse if there are previous errors
    if ($form->has_errors()) {
        return;
    }

    if ($values['file']['size'] == 0) {
        $form->set_error('file', $form->i18n('required'));
        return;
    }

    require_once('pear/File.php');
    require_once('pear/File/CSV.php');

    $institution = $values['institution'];

    log_debug($values);
    $conf = File_CSV::discoverFormat($values['file']['tmp_name']);
    log_debug($conf);
    $i = 0;
    while ($line = @File_CSV::readQuoted($values['file']['tmp_name'], $conf)) {
        log_debug($line);
        $i++;
        if (count($line) < 3) {
            $form->set_error('file', get_string('uploadcsverrorincorrectfieldcount', 'admin', $i));
            return;
        }
        $username = $line[0];
        $password = $line[1];
        $email    = $line[2];

        safe_require('auth', 'internal', 'lib.php', 'require_once');
        if (!AuthInternal::is_username_valid($username)) {
            $form->set_error('file', get_string('uploadcsverrorinvalidusername', 'admin', $i));
            return;
        }
        if (record_exists('usr', 'username', $username)) {
            $form->set_error('file', get_string('uploadcsverroruseralreadyexists', 'admin', $i, $username));
            return;
        }

        // Note: only checks for valid form are done here, none of the checks
        // like whether the password is too easy. The user is going to have to
        // change their password on first login anyway.
        if (!AuthInternal::is_password_valid($password)) {
            $form->set_error('file', get_string('uploadcsverrorinvalidpassword', 'admin', $i));
            return;
        }

        safe_require('artefact', 'internal');
        $fieldcounter = 2;
        foreach (ArtefactTypeProfile::get_mandatory_fields() as $field => $type) {
            $fieldcounter++;
            if (!isset($line[$fieldcounter])) {
                $form->set_error('file', get_string('uploadcsverrormandatoryfieldnotspecified', 'admin', $i, $field));
                return;
            }

            // @todo validate the mandatory fields somehow. In theory, this should
            // just involve calling a method on a class.
        }

        // All OK!
        $CSVDATA[] = $line;
    }
}

/**
 * Add the users to the system. Make sure that they have to change their
 * password on next login also.
 */
function uploadcsv_submit($values) {
    global $SESSION, $CSVDATA;
    log_info('Inserting users from the CSV file');
    foreach ($CSVDATA as $record) {
        log_debug('adding user ' . $record[0]);
        $user = new StdClass;
        $user->username  = $record[0];
        $user->password  = $record[1];
        $user->institution = $values['institution'];
        $user->passwordchange = 1;
        $id = insert_record('usr', $user, 'id', true);

        $i = 2;
        safe_require('artefact', 'internal');
        foreach (ArtefactTypeProfile::get_mandatory_fields() as $field => $type) {
            set_profile_field($id, $field, $record[$i++]);
        }
    }
    log_info('Inserted ' . count($CSVDATA) . ' records');

    $SESSION->add_ok_msg(get_string('uploadcsvusersaddedsuccessfully', 'admin'));
    // @todo support relative URLs here
    redirect(get_config('wwwroot') . 'admin/users/uploadcsv.php');
}

$smarty = smarty();
$smarty->assign('uploadcsvform', pieform($form));
$smarty->display('admin/users/uploadcsv.tpl');

?>
