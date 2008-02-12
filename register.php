<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2007 Catalyst IT Ltd (http://www.catalyst.net.nz)
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
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'site');
define('SECTION_PAGE', 'register');
require('init.php');
require_once('pieforms/pieform.php');
define('TITLE', get_string('register'));

/*
 * This page handles three different tasks:
 *   1) Showing a visitor the registration form
 *   2) Telling the visitor to check their e-mail for a message
 *   3) Given a key, display profile information to edit
 *
 * It uses the session to store some state
 */
if (!session_id()) {
    session_start();
}

// Logged in people can't register
if (is_logged_in()) {
    redirect();
}

// Step two of registration (first as it's the easiest): the user has
// registered, show them a screen telling them this.
if (!empty($_SESSION['registered'])) {
    unset($_SESSION['registered']);
    die_info(get_string('registeredok', 'auth.internal'));
}

$key = param_alphanum('key', null);
// Step three of registration - given a key, fill out mandatory profile fields,
// optional profile icon, and register the user
if (isset($key)) {

    // Begin the registration form buliding
    if (!$registration = get_record_select('usr_registration', '"key" = ? AND expiry >= ?', array($key, db_format_timestamp(time())))) {
        die_info(get_string('registrationnosuchkey', 'auth.internal'));
    }

    // In case a new session has started, reset the session language
    // to the one selected during registration
    if (!empty($registration->lang)) {
        $SESSION->set('lang', $registration->lang);
    }

    function create_registered_user($profilefields=array()) {
        global $registration, $SESSION, $USER;

        db_begin();

        // Move the user record to the usr table from the registration table
        $registrationid = $registration->id;
        unset($registration->id);
        unset($registration->expiry);
        if ($expirytime = get_config('defaultaccountlifetime')) {
            $registration->expiry = db_format_timestamp(time() + $expirytime);
        }
        $registration->lastlogin = db_format_timestamp(time());

        $authinstance = get_record('auth_instance', 'institution', $registration->institution, 'authname', 'internal');
        if (false == $authinstance) {
            // TODO: Specify exception
            throw new Exception('No internal auth instance for institution');
        }

        $user = new User();
        $user->username         = $registration->username;
        $user->password         = $registration->password;
        $user->salt             = $registration->salt;
        $user->passwordchange   = 0;
        $user->active           = 1;
        $user->authinstance     = $authinstance->id;
        $user->firstname        = $registration->firstname;
        $user->lastname         = $registration->lastname;
        $user->email            = $registration->email;
        $user->commit();

        $user->add_institution_request($registration->institution);

        $registration->id = $user->id;

        // Insert standard stuff as artefacts
        set_profile_field($user->id, 'email', $registration->email);
        set_profile_field($user->id, 'firstname', $registration->firstname);
        set_profile_field($user->id, 'lastname', $registration->lastname);
        if (!empty($registration->lang) && $registration->lang != 'default') {
            set_account_preference($user->id, 'lang', $registration->lang);
        }

        // Delete the old registration record
        delete_records('usr_registration', 'id', $registrationid);

        // Set mandatory profile fields 
        foreach(ArtefactTypeProfile::get_mandatory_fields() as $field => $type) {
            // @todo here and above, use the method for getting "always mandatory" fields
            if (in_array($field, array('firstname', 'lastname', 'email'))) {
                continue;
            }
            set_profile_field($user->id, $field, $profilefields[$field]);
        }

        $registration->quotaused = 0;
        $registration->quota = get_config_plugin('artefact', 'file', 'defaultquota');

        db_commit();
        handle_event('createuser', $registration);

        // Log the user in and send them to the homepage
        $USER = new LiveUser();
        $USER->reanimate($user->id, $authinstance->id);

        // A special greeting for special people
        if (in_array($user->username, array('waawaamilk', 'Mjollnir`', 'Ned', 'richardm', 'fmarier'))) {
            $SESSION->add_ok_msg('MAMA!!! Maharababy happy to see you :D :D!');
        }
        else if ($user->username == 'htaccess') {
            $SESSION->add_ok_msg('Welcome B-Quack, htaccess!');
        }
        else {
            $SESSION->add_ok_msg(get_string('registrationcomplete', 'mahara', get_config('sitename')));
        }
        redirect();
    }

    function profileform_submit(Pieform $form, $values) {
        create_registered_user($values);
    }

    function profileform_validate(Pieform $form, $values) {
        foreach(ArtefactTypeProfile::get_mandatory_fields() as $field => $type) {
            // @todo here and above, use the method for getting "always mandatory" fields
            if (in_array($field, array('firstname', 'lastname', 'email'))) {
                continue;
            }
            // @todo here, validate the fields using their static validate method
        }
    }

    safe_require('artefact', 'internal');

    $elements = array(
        'mandatoryheader' => array(
            'type'  => 'html',
            'value' => get_string('registerstep3fieldsmandatory')
        )
    );

    foreach(ArtefactTypeProfile::get_mandatory_fields() as $field => $type) {
        if (in_array($field, array('firstname', 'lastname', 'email'))) {
            continue;
        }

        $elements[$field] = array(
            'type'  => $type,
            'title' => get_string($field, 'artefact.internal'),
            'rules' => array('required' => true)
        );

        // @todo ruthlessly stolen from artefact/internal/index.php, could be merged
        if ($type == 'wysiwyg') {
            $elements[$field]['rows'] = 10;
            $elements[$field]['cols'] = 60;
        }
        if ($type == 'textarea') {
            $elements[$field]['rows'] = 4;
            $elements[$field]['cols'] = 60;
        }
        if ($field == 'country') {
            $elements[$field]['options'] = getoptions_country();
            $elements[$field]['defaultvalue'] = 'nz';
        }
    }

    if (count($elements) < 2) { // No mandatory fields, just create the user
        create_registered_user();
    }

    $elements['key'] = array(
        'type' => 'hidden',
        'name' => 'key',
        'value' => $key
    );
    $elements['submit'] = array(
        'type' => 'submit',
        'value' => get_string('completeregistration', 'auth.internal')
    );

    $form = pieform(array(
        'name'     => 'profileform',
        'method'   => 'post',
        'action'   => '',
        'elements' => $elements
    ));

    $smarty = smarty();
    $smarty->assign('register_profile_form', $form);
    $smarty->assign('heading', get_string('register'));
    $smarty->display('register.tpl');
    exit;
}


// Default page - show the registration form

$elements = array(
    'username' => array(
        'type' => 'text',
        'title' => get_string('username'),
        'rules' => array(
            'required' => true
        ),
        'help' => true,
    ),
    'password1' => array(
        'type' => 'password',
        'title' => get_string('password'),
        'description' => get_string('passwordformdescription', 'auth.internal'),
        'rules' => array(
            'required' => true
        ),
        'help' => true,
    ),
    'password2' => array(
        'type' => 'password',
        'title' => get_string('confirmpassword'),
        'rules' => array(
            'required' => true
        )
    ),
    'firstname' => array(
        'type' => 'text',
        'title' => get_string('firstname'),
        'rules' => array(
            'required' => true
        )
    ),
    'lastname' => array(
        'type' => 'text',
        'title' => get_string('lastname'),
        'rules' => array(
            'required' => true
        )
    ),
    'email' => array(
        'type' => 'text',
        'title' => get_string('emailaddress'),
        'rules' => array(
            'required' => true,
            'email' => true
        )
    )
);
$sql = 'SELECT
            i.*
        FROM
            {institution} i,
            {auth_instance} ai
        WHERE
            ai.authname = \'internal\' AND
            ai.institution = i.name AND
            i.registerallowed = 1';
$institutions = get_records_sql_array($sql, array());

if (count($institutions) > 1) {
    $options = array();
    foreach ($institutions as $institution) {
        $options[$institution->name] = $institution->displayname;
    }
    $elements['institution'] = array(
        'type' => 'select',
        'title' => get_string('institution'),
        'options' => $options,
        'rules' => array(
            'required' => true
        )
    );
}
else if ($institutions) {
    $elements['institution'] = array(
        'type' => 'hidden',
        'value' => 'mahara'
    );
}
else {
    die_info(get_string('registeringdisallowed'));
}

$elements['tandc'] = array(
    'type' => 'radio',
    'title' => get_string('iagreetothetermsandconditions', 'auth.internal'),
    'description' => get_string('youmustagreetothetermsandconditions', 'auth.internal'),
    'options' => array(
        'yes' => get_string('yes'),
        'no'  => get_string('no')
    ),
    'defaultvalue' => 'no',
    'rules' => array(
        'required' => true
    ),
    'separator' => ' &nbsp; '
);

$captcharequired = get_config('captcha_on_register_form');
if (is_null($captcharequired) || $captcharequired) {
    $elements['captcha'] = array(
        'type' => 'captcha',
        'title' => get_string('captchatitle'),
        'description' => get_string('captchadescription'),
        'rules' => array('required' => true)
    );
}

$elements['submit'] = array(
    'type' => 'submitcancel',
    'value' => array(get_string('register'), get_string('cancel'))
);

$form = array(
    'name' => 'register',
    'method' => 'post',
    'plugintype' => 'core',
    'pluginname' => 'register',
    'action' => '',
    'showdescriptiononerror' => false,
    'renderer' => 'table',
    'elements' => $elements
);

/**
 * @todo add note: because the form select thing will eventually enforce
 * that the result for $values['institution'] was in the original lot,
 * and because that only allows authmethods that use 'internal' auth, we
 * can guarantee that the auth method is internal
 */
function register_validate(Pieform $form, $values) {
    global $SESSION;
    $institution = $values['institution'];
    safe_require('auth', 'internal');

    if (!$form->get_error('username') && !AuthInternal::is_username_valid($values['username'])) {
        $form->set_error('username', get_string('usernameinvalidform', 'auth.internal'));
    }

    if (!$form->get_error('username') && record_exists('usr', 'username', $values['username'])) {
        $form->set_error('username', get_string('usernamealreadytaken', 'auth.internal'));
    }

    $user =(object) $values;
    $user->authinstance = get_field('auth_instance', 'id', 'authname', 'internal', 'institution', $institution);
    password_validate($form, $values, $user);

    // First name and last name must contain at least one non whitespace
    // character, so that there's something to read
    if (!$form->get_error('firstname') && !preg_match('/\S/', $values['firstname'])) {
        $form->set_error('firstname', $form->i18n('required'));
    }

    if (!$form->get_error('lastname') && !preg_match('/\S/', $values['lastname'])) {
        $form->set_error('lastname', $form->i18n('required'));
    }

    // The e-mail address cannot already be in the system
    if (!$form->get_error('email')
        && (record_exists('usr', 'email', $values['email'])
        || record_exists('artefact_internal_profile_email', 'email', $values['email']))) {
        $form->set_error('email', get_string('emailalreadytaken', 'auth.internal'));
    }
    
    // If the user hasn't agreed to the terms and conditions, don't bother
    if ($values['tandc'] != 'yes') {
        $form->set_error('tandc', get_string('youmaynotregisterwithouttandc', 'auth.internal'));
    }

    // CAPTCHA image
    $captcharequired = get_config('captcha_on_register_form');
    if ((is_null($captcharequired) || $captcharequired) && !$values['captcha']) {
        $form->set_error('captcha', get_string('captchaincorrect'));
    }

    $institution = get_record_sql('
        SELECT 
            i.name, i.maxuseraccounts, i.registerallowed, COUNT(u.id)
        FROM {institution} i
            LEFT OUTER JOIN {usr_institution} ui ON ui.institution = i.name
            LEFT OUTER JOIN {usr} u ON (ui.usr = u.id AND u.deleted = 0)
        WHERE
            i.name = ?
        GROUP BY
            i.name, i.maxuseraccounts, i.registerallowed', array($institution));

    if (!empty($institution->maxuseraccounts) && $institution->count >= $institution->maxuseraccounts) {
        $form->set_error('institution', get_string('institutionfull'));
    }

    if (!$institution->registerallowed) {
        $form->set_error('institution', get_string('registrationnotallowed'));
    }

}

function register_submit(Pieform $form, $values) {
    global $SESSION;

    // store password encrypted
    // don't die_info, since reloading the page shows the login form.
    // instead, redirect to some other page that says this
    safe_require('auth', 'internal');
    $values['salt']     = substr(md5(rand(1000000, 9999999)), 2, 8);
    $values['password'] = AuthInternal::encrypt_password($values['password1'], $values['salt']);
    $values['key']   = get_random_key();
    // @todo the expiry date should be configurable
    $values['expiry'] = db_format_timestamp(time() + 86400);
    $values['lang'] = $SESSION->get('lang');
    try {
        insert_record('usr_registration', $values);

        $f = fopen('/tmp/donal.txt','w');
        fwrite($f, get_string('registeredemailmessagetext', 'auth.internal', $values['firstname'], get_config('sitename'), $values['key'], get_config('sitename')));

        $user =(object) $values;
        $user->admin = 0;
        $user->staff = 0;
        email_user($user, null,
            get_string('registeredemailsubject', 'auth.internal', get_config('sitename')),
            get_string('registeredemailmessagetext', 'auth.internal', $values['firstname'], get_config('sitename'), $values['key'], get_config('sitename')),
            get_string('registeredemailmessagehtml', 'auth.internal', $values['firstname'], get_config('sitename'), $values['key'], $values['key'], get_config('sitename')));
    }
    catch (EmailException $e) {
        log_warn($e);
        die_info(get_string('registrationunsuccessful', 'auth.internal'));
    }
    catch (SQLException $e) {
        log_warn($e);
        die_info(get_string('registrationunsuccessful', 'auth.internal'));
    }

    // Add a marker in the session to say that the user has registered
    $_SESSION['registered'] = true;

    redirect('/register.php');
}

function register_cancel_submit() {
    redirect();
}

$smarty = smarty();
$smarty->assign('register_form', pieform($form));
$smarty->assign('heading', get_string('register'));
$smarty->display('register.tpl');

?>
