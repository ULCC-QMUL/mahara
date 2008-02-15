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
 * @author     Penny Leach <penny@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

$CFG = new StdClass;
$CFG->docroot = dirname(__FILE__) . '/';

// Figure out our include path
if (!empty($_SERVER['MAHARA_LIBDIR'])) {
    $CFG->libroot = $_SERVER['MAHARA_LIBDIR'];
}
else {
    $CFG->libroot = dirname(__FILE__) . '/lib/';
}
set_include_path($CFG->libroot . PATH_SEPARATOR . $CFG->libroot . 'pear/' . PATH_SEPARATOR . get_include_path());

// Set up error handling
require('errors.php');

if (!is_readable($CFG->docroot . 'config.php')) {
    // @todo Later, this will redirect to the installer script. For now, we
    // just log and exit.
    log_environ('Not installed! Please create config.php from config-dist.php');
    exit;
}

init_performance_info();

require('config.php');
$CFG = (object)array_merge((array)$cfg, (array)$CFG);

// Fix up paths in $CFG
foreach (array('docroot', 'dataroot') as $path) {
    $CFG->{$path} = (substr($CFG->{$path}, -1) != DIRECTORY_SEPARATOR) ? $CFG->{$path} . DIRECTORY_SEPARATOR : $CFG->{$path};
}

// xmldb stuff
$CFG->xmldbdisablenextprevchecking = true;
$CFG->xmldbdisablecommentchecking = true;

// core libraries
require('mahara.php');
ensure_sanity();
require('dml.php');
require('ddl.php');
require('constants.php');
require('web.php');
require('activity.php');
require('user.php');

// Database access functions
require('adodb/adodb-exceptions.inc.php');
require('adodb/adodb.inc.php');

try {
    // ADODB does not provide the raw driver error message if the connection
    // fails for some reason, so we use output buffering to catch whatever
    // the error is instead.
    ob_start();
    
    $db = &ADONewConnection($CFG->dbtype);
    $dbgenerator = null;
    if (empty($CFG->dbhost)) {
        $CFG->dbhost = '';
    }
    else if (!empty($CFG->dbport)) {
        $CFG->dbhost .= ':'.$CFG->dbport;
    }
    if (!empty($CFG->dbpersist)) {    // Use persistent connection (default)
        $dbconnected = $db->PConnect($CFG->dbhost,$CFG->dbuser,$CFG->dbpass,$CFG->dbname);
    } 
    else {                                                     // Use single connection
        $dbconnected = $db->Connect($CFG->dbhost,$CFG->dbuser,$CFG->dbpass,$CFG->dbname);
    }

    $db->SetFetchMode(ADODB_FETCH_ASSOC);
    configure_dbconnection();
    ensure_internal_plugins_exist();

    ob_end_clean();
}
catch (Exception $e) {
    $errormessage = ob_get_contents();
    if (!$errormessage) {
        $errormessage = $e->getMessage();
    }
    ob_end_clean();
    $errormessage = get_string('dbconnfailed', 'error') . $errormessage;
    throw new ConfigSanityException($errormessage);
}
try {
    db_ignore_sql_exceptions(true);
    load_config();
    db_ignore_sql_exceptions(false);
} 
catch (SQLException $e) {
    db_ignore_sql_exceptions(false);
}

// Make sure wwwroot is set and available, either in the database or int the
// config file. Cron requires it for some purposes.
if (!isset($CFG->wwwroot) && isset($_SERVER['HTTP_HOST'])) {
    $proto = (isset($_SERVER['HTTPS'])) ? 'https://' : 'http://';
    $host  =  (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
    if (false !== strpos($host, ',')) {
        list($host) = explode(',', $host);
        $host = trim($host);
    }
    $path  = substr(dirname(__FILE__), strlen($_SERVER['DOCUMENT_ROOT']));
    if ($path) {
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        $path .= '/';
    } else {
        $path = '/';
    }
    $wwwroot = $proto . $host . $path;
    try {
        set_config('wwwroot', $wwwroot);
    }
    catch (Exception $e) {
        // Just set it directly. The system will most likely not be installed, so we don't care
        $CFG->wwwroot = $wwwroot;
    }
}
if (!isset($CFG->noreplyaddress) && isset($_SERVER['HTTP_HOST'])) {
    $noreplyaddress = 'noreply@';
    $host  =  (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
    if (false !== strpos($host, ',')) {
        list($host) = explode(',', $host);
        $host = trim($host);
    }
    $noreplyaddress .= $host;
    try {
        set_config('noreplyaddress', $noreplyaddress);
    }
    catch (Exception $e) {
        // Do nothing again, same reason as above
        $CFG->noreplyaddress = $noreplyaddress;
    }
}

if (!get_config('theme')) { 
    // if it's not set, we're probably not installed, 
    // so set it in $CFG directly rather than the db which doesn't yet exist
    $CFG->theme = 'default'; 
}

$CFG->themeurl = get_config('wwwroot') . 'theme/' . get_config('theme') . '/static/';

// Make sure the search plugin is configured
if (!get_config('searchplugin')) {
    try {
        set_config('searchplugin', 'internal');
    }
    catch (Exception $e) {
        $CFG->searchplugin = 'internal';
    }
}

header('Content-type: text/html; charset=UTF-8');

// Only do authentication once we know the page theme, so that the login form
// can have the correct theming.
require_once('auth/lib.php');
$SESSION = Session::singleton();
$USER    = new LiveUser();
// The installer does its own auth_setup checking, because some upgrades may
// break logging in and so need to allow no logins.
if (!defined('INSTALLER')) {
    auth_setup();
}

// check to see if we're installed...
if (!get_config('installed')
    && false === strpos($_SERVER['SCRIPT_FILENAME'], 'admin/index.php')
    && false === strpos($_SERVER['SCRIPT_FILENAME'], 'admin/upgrade.php')
    && false === strpos($_SERVER['SCRIPT_FILENAME'], 'admin/upgrade.json.php')) {
    redirect('/admin/');
}

if (defined('JSON') && !defined('NOSESSKEY')) {
    $sesskey = param_variable('sesskey', null);
    global $USER;
    if ($sesskey === null || $USER->get('sesskey') != $sesskey) {
        $USER->logout();
        json_reply('global', get_string('invalidsesskey'), 1);
    }
}

/*
 * Initializes our performance info early.
 *
 * Pairs up with get_performance_info() which is actually
 * in lib/mahara.php. This function is here so that we can 
 * call it before all the libs are pulled in. 
 *
 * @uses $PERF
 */
function init_performance_info() {

    global $PERF;
  
    $PERF = new StdClass;
    $PERF->dbqueries = 0;   
    $PERF->logwrites = 0;
    if (function_exists('microtime')) {
        $PERF->starttime = microtime();
        }
    if (function_exists('memory_get_usage')) {
        $PERF->startmemory = memory_get_usage();
    }
    if (function_exists('posix_times')) {
        $PERF->startposixtimes = posix_times();  
    }
}

?>
