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
 * @subpackage core
 * @author     Penny Leach <penny@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('PUBLIC', 1);
define('MENUITEM', 'home');

require('init.php');

// Check for whether the user is logged in, before processing the page. After
// this, we can guarantee whether the user is logged in or not for this page.
if (!$USER->is_logged_in()) {
    require_once('pieforms/pieform.php');
    $institutions = get_records_menu('institution', '', '', 'name, displayname');
    $loginform = get_login_form_js(pieform(array(
        'name'       => 'login',
        'renderer'   => 'div',
        'submit'     => false,
        'plugintype' => 'auth',
        'pluginname' => 'internal',
        'elements'   => array(
            'login' => array(
                'type'   => 'fieldset',
                'legend' => get_string('logon'),
                'elements' => array(
                    'login_username' => array(
                        'type'        => 'text',
                        'title'       => get_string('username') . ':',
                        'description' => get_string('usernamedescription'),
                        'rules' => array(
                            'required'    => true
                        )
                    ),
                    'login_password' => array(
                        'type'        => 'password',
                        'title'       => get_string('password') . ':',
                        'description' => get_string('passworddescription'),
                        'value'       => '',
                        'rules' => array(
                            'required'    => true
                        )
                    ),
                    'login_institution' => array(
                        'type' => 'select',
                        'title' => get_string('institution') . ':',
                        'defaultvalue' => get_cookie('institution'),
                        'options' => $institutions,
                        'rules' => array(
                            'required' => true
                        ),
                        'ignore' => count($institutions) == 1
                    )
                )
            ),

            'submit' => array(
                'type'  => 'submit',
                'value' => get_string('login')
            ),
            'register' => array(
                'value' => '<div><a href="' . get_config('wwwroot') . 'register.php">' . get_string('register') . '</a>'
                    . '<br><a href="' . get_config('wwwroot') . 'forgotpass.php">' . get_string('passwordreminder') . '</a></div>'
            )
        )
    )));
    $pagename = 'loggedouthome';
}
else {
    $pagename = 'home';
}

$smarty = smarty();
if (!$USER->is_logged_in()) {
    $smarty->assign('login_form', $loginform);
}
else {
    $smarty->assign('searchform', searchform());
}
$smarty->assign('page_content', get_site_page_content($pagename));
$smarty->assign('site_menu', site_menu());
$smarty->display('index.tpl');

?>
