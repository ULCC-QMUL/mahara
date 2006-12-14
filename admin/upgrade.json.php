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
 * @author     Penny Leach <penny@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('INSTALLER', 1);
require(dirname(dirname(__FILE__)) . '/init.php');
require(get_config('libroot') . 'upgrade.php');

$install = param_boolean('install');
if (!$install) {
    $name    = param_variable('name');
}

if ($install) {
    $message = '';
    if (!get_config('installed')) {
        try {
            $exceptions = core_install_defaults();
        }
        catch (SQLException $e) {
            json_reply(true, $e->getMessage());
        }
        catch (TemplateParserException $e) {
            $message = '<a href="' . get_config('wwwroot') .'admin/extensions/templates.php">' 
                . get_string('fixtemplatescontinue', 'admin') . '</a>';
        }
        if (is_array($exceptions) && count($exceptions) > 0) {
            // these ones are non fatal... 
            $message = '<a href="' . get_config('wwwroot') .'admin/extensions/templates.php">' 
                . get_string('fixtemplatescontinue', 'admin') . '</a>';
        }
    }
    json_reply(false, $message);
}

$upgrade = check_upgrades($name);
$data = array(
    'key'        => $name
);             

if (!empty($upgrade)) {
    $data['newversion'] = $upgrade->torelease . ' (' . $upgrade->to . ')' ;
    if ($name == 'core') {
        $funname = 'upgrade_core';
    } 
    else {
        $funname = 'upgrade_plugin';
    }
    try {
        $funname($upgrade);
        if (isset($upgrade->install)) {
            $data['install'] = $upgrade->install;
        }
        json_reply(false, $data);
        exit;
    } 
    catch (Exception $e) {
        $data['errormessage'] = $e->getMessage();
        json_reply(true, $data);
        exit;
    }
}
else {
    json_reply(false, array('message' => string('nothingtoupgrade','admin')));
    exit;
}
?>
