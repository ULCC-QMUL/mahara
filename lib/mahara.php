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
 * @subpackage core
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 * @copyright  (C) portions from Moodle, (C) Martin Dougiamas http://dougiamas.com
 */

defined('INTERNAL') || die();

/** 
 * work around silly php settings
 * and broken setup stuff about the install
 * and raise a warning/fail depending on severity
 */
function ensure_sanity() {

    // PHP version
    if (version_compare(phpversion(), '5.1.3') < 0) {
        throw new ConfigSanityException(get_string('phpversion', 'error'));
    }

    // Various required extensions
    if (!extension_loaded('json')) {
        throw new ConfigSanityException(get_string('jsonextensionnotloaded', 'error'));
    }
    switch (get_config('dbtype')) {
    case 'postgres8':
        if (!extension_loaded('pgsql')) {
            throw new ConfigSanityException(get_string('pgsqldbextensionnotloaded', 'error'));
        }
        break;
    case 'mysql5':
        if (!extension_loaded('mysql')) {
            throw new ConfigSanityException(get_string('mysqldbextensionnotloaded', 'error'));
        }
        break;
    default:
        throw new ConfigSanityException(get_string('unknowndbtype', 'error'));
    }
    if (!extension_loaded('xml')) {
        throw new ConfigSanityException(get_string('xmlextensionnotloaded', 'error', 'xml'));
    }
    if (!extension_loaded('libxml')) {
        throw new ConfigSanityException(get_string('xmlextensionnotloaded', 'error', 'libxml'));
    }
    if (!extension_loaded('gd')) {
        throw new ConfigSanityException(get_string('gdextensionnotloaded', 'error'));
    }
    if (!extension_loaded('session')) {
        throw new ConfigSanityException(get_string('sessionextensionnotloaded', 'error'));
    }

    // register globals workaround
    if (ini_get_bool('register_globals')) {
        log_environ(get_string('registerglobals', 'error'));
        $massivearray = array_keys(array_merge($_POST, $_GET, $_COOKIE, $_SERVER, $_REQUEST, $_FILES));
        foreach ($massivearray as $tounset) {
            unset($GLOBALS[$tounset]);
        }
    }

    // magic_quotes_gpc workaround
    if (!defined('CRON') && ini_get_bool('magic_quotes_gpc')) {
        log_environ(get_string('magicquotesgpc', 'error'));
        function stripslashes_deep($value) {
            $value = is_array($value) ?
                array_map('stripslashes_deep', $value) :
                stripslashes($value);
            return $value;
        }
        $_POST = array_map('stripslashes_deep', $_POST);
        $_GET = array_map('stripslashes_deep', $_GET);
        $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
        $_REQUEST = array_map('stripslashes_deep', $_REQUEST);

        $servervars = array('REQUEST_URI','QUERY_STRING','HTTP_REFERER','PATH_INFO','PHP_SELF','PATH_TRANSLATED');
        foreach ($servervars as $tocheck) {
            if (array_key_exists($tocheck,$_SERVER) && !empty($_SERVER[$tocheck])) {
                $_SERVER[$tocheck] = stripslashes($_SERVER[$tocheck]);
            }
        }
    }

    if (ini_get_bool('magic_quotes_runtime')) {
        // Turn of magic_quotes_runtime. Anyone with this on deserves a slap in the face
        set_magic_quotes_runtime(0);
        log_environ(get_string('magicquotesruntime', 'error'));
    }

    if (ini_get_bool('magic_quotes_sybase')) {
        // See above comment re. magic_quotes_runtime
        @ini_set('magic_quotes_sybase', 0);
        log_environ(get_string('magicquotessybase', 'error'));
    }

    if (ini_get_bool('safe_mode')) {
        // We don't run with safe mode
        throw new ConfigSanityException(get_string('safemodeon', 'error'));
    }

    // Other things that might be worth checking:
    //    memory limit
    //    file_uploads (off|on)
    //    upload_max_filesize
    //    allow_url_fopen (only if we use this)
    //

    // dataroot inside document root.
    if (strpos(get_config('dataroot'), get_config('docroot')) !== false) {
        throw new ConfigSanityException(get_string('datarootinsidedocroot', 'error'));
    }

    // dataroot not writable..
    if (!check_dir_exists(get_config('dataroot')) || !is_writable(get_config('dataroot'))) {
        throw new ConfigSanityException(get_string('datarootnotwritable', 'error', get_config('dataroot')));
    }

    if (
        !check_dir_exists(get_config('dataroot') . 'smarty/compile') ||
        !check_dir_exists(get_config('dataroot') . 'smarty/cache') ||
        !check_dir_exists(get_config('dataroot') . 'sessions') ||
        !check_dir_exists(get_config('dataroot') . 'htmlpurifier')) {
        throw new ConfigSanityException(get_string('couldnotmakedatadirectories', 'error'));
    }
}

/**
 * Check to see if the internal plugins are installed. Die if they are not.
 */
function ensure_internal_plugins_exist() {
    // Internal things installed
    if (get_config('installed')) {
        foreach (plugin_types() as $type) {
            if (!record_exists($type . '_installed', 'name', 'internal')) {
                throw new ConfigSanityException(get_string($type . 'notinstalled'));
            }
        }
    }
}

function get_string($identifier, $section='mahara') {

    $variables = func_get_args();
    if (count($variables) > 2) { // we have some stuff we need to sprintf
        array_shift($variables);
        array_shift($variables); //shift off the first two.
    }
    else {
        $variables = array();
    }
    
    return get_string_location($identifier, $section, $variables);
}

function get_string_from_language($lang, $identifier, $section='mahara') {

    $variables = func_get_args();
    if (count($variables) > 3) { // we have some stuff we need to sprintf
        array_shift($variables);
        array_shift($variables);
        array_shift($variables); //shift off the first three.
    }
    else {
        $variables = array();
    }
    
    return get_string_location($identifier, $section, $variables, 'format_langstring', $lang);
}

function get_helpfile($plugintype, $pluginname, $form, $element, $page=null, $section=null) {
    if ($langfile = get_helpfile_location($plugintype, $pluginname, $form, $element, $page, $section)) {
        return file_get_contents($langfile);
    }
    return false;
}

function get_helpfile_location($plugintype, $pluginname, $form, $element, $page=null, $section=null) {

    $location = get_config('docroot') ;
    $file = 'help/';

    if ($plugintype != 'core') {
        $location .= $plugintype . '/' . $pluginname . '/lang/';
    }
    else {
        $location .= 'lang/';
    }
    if ($page) {
        $page = str_replace('-', '/', $page);
        if ($plugintype != 'core') {
            $file .=  'pages/' . $page . '.html';
        }
        else {
            $file .= 'pages/' . $pluginname . '/' . $page . '.html';
        } 
    }
    else if ($section) {
        $file .= 'sections/' . $section . '.html';
    }
    else if (!empty($form) && !empty($element)) {
        $file .= 'forms/' . $form . '.' . $element . '.html';
    }
    else if (!empty($form) && empty($element)) {
        $file .= 'forms/' . $form . '.html';
    }
    else {
        return false;
    }

    // now we have to try and locate the help file
    $lang = current_language();
    if ($lang == 'en.utf8') {
        $trieden = true;
    }
    else {
        $trieden = false;
    }

    // try the current language
    $langfile = $location . $lang . '/' . $file;
    if (is_readable($langfile)) {
        return $langfile;
    }

    // if it's not found, try the parent language if there is one...
    if (empty($data) && empty($trieden)) {
        $langfile = get_config('docroot') . 'lang/' . $lang . '/langconfig.php';
        if ($parentlang = get_string_from_file('parentlanguage', $langfile)) {
            if ($parentlang == 'en.utf8') {
                $trieden = true;
            }
            $langfile = get_config('docroot') . 'lang/' . $parentlang . '/' . $file;
            if (is_readable($langfile)) {
                return $langfile;
            }
        }
    }

    // if it's STILL not found, and we haven't already tried english ...
    if (empty($data) && empty($trieden)) {
        $langfile = $location .  'en.utf8/' . $file;
        if (is_readable($langfile)) {
            return $langfile;
        }
    }
    return false;
}

// get a string without sprintfing it.
function get_raw_string($identifier, $section='mahara') {
    // For a raw string we don't want to format any arguments using
    // sprintf, so the replace function passed to get_string_location
    // should just return the first argument and ignore the second.
    return get_string_location($identifier, $section, array(), 
                               create_function('$string, $args','return $string;'));
}


/**
 * This function gets a language string identified by $identifier from
 * an appropriate location, and formats the string and any arguments
 * in $variables using the function $replacefunc.
 *
 * @param string   $identifier
 * @param string   $section
 * @param array    $variables
 * @param function $replacefunc
 * @return string
 */
function get_string_location($identifier, $section, $variables, $replacefunc='format_langstring', $lang='') {

    $langconfigstrs = array('parentlanguage', 'strftimedate', 'strftimedateshort', 'strftimedatetime',
                            'strftimedaydate', 'strftimedaydatetime', 'strftimedayshort', 'strftimedaytime',
                            'strftimemonthyear', 'strftimerecent', 'strftimerecentfull', 'strftimetime',
                            'strfdaymonthyearshort', 'thislanguage');

    if (in_array($identifier, $langconfigstrs)) {
        $section = 'langconfig';
    }

    if (empty($lang)) {
        $lang = current_language();
    }

    // Define the locations of language strings for this section
    $langstringroot = get_language_root();
    $langdirectory  = ''; // The directory in which the language file for this string should ideally reside, if the language has implemented it
    
    if (false === strpos($section, '.')) {
        $langdirectory = 'lang/';
    }
    else {
        $extras = plugin_types(); // more later..
        foreach ($extras as $tocheck) {
            if (strpos($section, $tocheck . '.') === 0) {
                $pluginname = substr($section ,strlen($tocheck) + 1);
                if ($tocheck == 'blocktype' && 
                    strpos($pluginname, '/') !== false) { // it belongs to an artefact plugin
                    $bits = explode('/', $pluginname);
                    $langdirectory = 'artefact/' . $bits[0] . '/blocktype/' . $bits[1] . '/lang/';
                    $section = 'blocktype.' . $bits[1];
                }
                else {
                    $langdirectory = $tocheck . '/' . $pluginname . '/lang/';
                }
            }
        }
    }

    // First check all the normal locations for the string in the current language
    $langfile = $langstringroot . $langdirectory . $lang . '/' . $section . '.php';
    if (is_readable($langfile)) {
        if ($result = get_string_from_file($identifier, $langfile)) {
            return $replacefunc($result, $variables);
        }
    }

    // If the preferred language was English (utf8) we can abort now
    // saving some checks beacuse it's the only "root" lang
    if ($lang == 'en.utf8') {
        return '[[' . $identifier . '/' . $section . ']]';
    }

    // Is a parent language defined?  If so, try to find this string in a parent language file
    $langfile = $langstringroot . 'lang/' . $lang . '/langconfig.php';
    if (is_readable($langfile)) {
        if ($parentlang = get_string_from_file('parentlanguage', $langfile)) {
            $langfile = get_language_root($parentlang) . 'lang/' . $parentlang . '/' . $section . '.php';
            if (is_readable($langfile)) {
                if ($result = get_string_from_file($identifier, $langfile)) {
                    return $replacefunc($result, $variables);
                }
            }
        }
    }

    /// Our only remaining option is to try English
    $langfile = get_config('docroot') . $langdirectory . 'en.utf8/' . $section . '.php';
    if (is_readable($langfile)) {
        if ($result = get_string_from_file($identifier, $langfile)) {
            return $replacefunc($result, $variables);
        }
    }

    return '[[' . $identifier . '/' . $section . ']]';  // Last resort
}


/**
 * Return a list of available languages
 *
 */
function get_languages() {
    $langs = array();

    foreach (language_get_searchpaths() as $searchpath) {
        $langbase = $searchpath . '/lang/';
        if (!$langdir = opendir($langbase)) {
            throw new SystemException('Unable to read language directory '.$langbase);
        }
        while (false !== ($subdir = readdir($langdir))) {
            $langfile = $langbase . $subdir . '/langconfig.php';
            if ($subdir != "." && $subdir != ".." && is_readable($langfile)) {
                if ($langname = get_string_from_file('thislanguage',$langfile)) {
                    $langs[$subdir] = $langname;
                }
            }
        }
        closedir($langdir);
    }

    return $langs;
}

function language_installed($lang) {
    return is_readable(get_config('docroot') . 'lang/' . $lang . '/langconfig.php');
}

/**
 * Returns a list of directories in which to search for language packs.
 *
 * This is influenced by the configuration variable 'langpacksearchpaths'
 */
function language_get_searchpaths() {
    static $searchpaths = array();

    if (!$searchpaths) {
        $searchpaths = array(get_config('docroot'));
        if ($langpacksearchpaths = get_config('langpacksearchpaths')) {
            foreach ($langpacksearchpaths as $path) {
                $searchpaths[] = (substr($path, -1) == '/') ? $path : "$path/";
            }
        }
    }

    return $searchpaths;
}

/**
 * Get the directory in which the specified language pack resides.
 *
 * Defaults to getting the directory for the current_language() - i.e. the 
 * language the user is using
 *
 * Returns null if the language can't be found
 *
 * @param string $language The language to look for
 */
function get_language_root($language=null) {
    static $language_root_cache = array();

    if (!isset($language_root_cache[$language])) {
        if ($language == null) {
            $language = current_language();
        }

        foreach (language_get_searchpaths() as $path) {
            if (is_dir("$path/lang/$language")) {
                return $language_root_cache[$language] = $path;
            }
        }

        // Oh noes, can't be found
        $language_root_cache[$language] = null;

    }

    return $language_root_cache[$language];
}

/**
 * Return a list of available themes.
 *
 * Themes _must_ have a config.php, and it should specify a theme name.
 */
function get_themes() {
    $themes = array();
    $themebase = get_config('docroot') . 'theme/';
    if (!$themedir = opendir($themebase)) {
        throw new SystemException('Unable to read theme directory '.$themebase);
    }
    while (false !== ($subdir = readdir($themedir))) {
        if ($subdir != "." && $subdir != ".." && is_dir($themebase . $subdir)) {
            $config_path = $themebase . $subdir . '/config.php';
            if (is_readable($config_path)) {
                require($config_path);
                if (isset($theme->name)) {
                    $themes[$subdir] = $theme->name;
                }
            }
        }
    }
    closedir($themedir);
    return $themes;
}


/**
 * This function is only used from {@link get_string()}.
 *
 * @internal Only used from get_string, not meant to be public API
 * @param string $identifier ?
 * @param string $langfile ?
 * @param string $destination ?
 * @return string|false ?
 * @staticvar array $strings Localized strings
 * @access private
 * @todo Finish documenting this function.
 */
function get_string_from_file($identifier, $langfile) {

    static $strings;    // Keep the strings cached in memory.

    if (empty($strings[$langfile])) {
        $string = array();
        include ($langfile);
        $strings[$langfile] = $string;
    } else {
        $string = &$strings[$langfile];
    }

    if (!isset ($string[$identifier])) {
        return false;
    }

    return $string[$identifier];
}

/**
 * This function makes the return value of ini_get consistent if you are
 * setting server directives through the .htaccess file in apache.
 * Current behavior for value set from php.ini On = 1, Off = [blank]
 * Current behavior for value set from .htaccess On = On, Off = Off
 * Contributed by jdell @ unr.edu
 *
 * @param string $ini_get_arg setting to look for
 * @return bool
 */
function ini_get_bool($ini_get_arg) {
    $temp = ini_get($ini_get_arg);

    if ($temp == '1' or strtolower($temp) == 'on') {
        return true;
    }
    return false;
}

/**
 * This function loads up the basic $CFG
 * from the database table
 * note that it doesn't load plugin config
 * as not every page needs them
 * @return boolean false if the assignment fails (generally if the databse is not installed)
 */
function load_config() {
    global $CFG;
    
    $dbconfig = get_records_array('config', '', '', '', 'field, value');
    
    foreach ($dbconfig as $cfg) {
        if (isset($CFG->{$cfg->field}) && $CFG->{$cfg->field} != $cfg->value) {
            // @todo warn that we're overriding db config with $CFG
            continue;
        }
        $CFG->{$cfg->field} = $cfg->value;
    }

    return true;
}

function load_default_config() {
    global $CFG;

    require_once(get_config('docroot') . 'config-defaults.php');
    $CFG = (object)array_merge((array)$cfg, (array)$CFG);

    return true;
}

/**
 * This function returns a value from $CFG
 * or null if it is not found
 * 
 * @param string $key config setting to look for 
 * @return mixed
 */
function get_config($key) {
    global $CFG;
    if (isset($CFG->$key)) {
        return $CFG->$key;
    }
    return null;
}


/**
 * This function sets a config variable
 * both in $CFG and in the database
 *
 * @param string $key config field to set
 * @param string $value config value
 */
function set_config($key, $value) {
    global $CFG;

    db_ignore_sql_exceptions(true);
    if (get_record('config', 'field', $key)) {
        if (set_field('config', 'value', $value, 'field', $key)) {
            $status = true;
        }
    } 
    else {
        $config = new StdClass;
        $config->field = $key;
        $config->value = $value;
        $status = insert_record('config', $config);
    }
    db_ignore_sql_exceptions(false);

    if (!empty($status)) {
        $CFG->{$key} = $value;
        return true;
    }

    return false;
}

/**
 * This function returns a value for $CFG for a plugin
 * or null if it is not found
 * note that it may go and look in the database
 *
 * @param string $plugintype eg artefact
 * @param string $pluginname eg blog
 * @param string $key the config setting to look for
 */
function get_config_plugin($plugintype, $pluginname, $key) {
    global $CFG;

    // Suppress NOTICE with @ in case $key is not yet cached
    @$value = $CFG->plugin->{$plugintype}->{$pluginname}->{$key};
    if (isset($value)) {
        return $value;
    }

    $records = get_records_array($plugintype . '_config', 'plugin', $pluginname, 'field', 'field, value');
    if (!empty($records)) {
        foreach($records as $record) {
            $CFG->plugin->{$plugintype}->{$pluginname}->{$record->field} = $record->value;
            if ($record->field == $key) {
                $value = $record->value;
            }
        }
    }

    return $value;
}

function set_config_plugin($plugintype, $pluginname, $key, $value) {
    global $CFG;
    $table = $plugintype . '_config';

    if (false !== get_field($table, 'value', 'plugin', $pluginname, 'field', $key)) {
        //if (set_field($table, 'value', $key, 'plugin', $pluginname, 'field', $value)) {
        if (set_field($table, 'value', $value, 'plugin', $pluginname, 'field', $key)) {
            $status = true;
        }
    }
    else {
        $pconfig = new StdClass;
        $pconfig->plugin = $pluginname;
        $pconfig->field  = $key;
        $pconfig->value  = $value;
        $status = insert_record($table, $pconfig);
    }
    if ($status) {
        $CFG->plugin->{$plugintype}->{$pluginname}->{$key} = $value;
        return true;
    }
    return false;
}

/**
 * This function returns a value for $CFG for a plugin instance
 * or null if it is not found. Initially this is interesting only 
 * for multiauth. Note that it may go and look in the database
 *
 * @param string $plugintype   E.g. auth
 * @param string $pluginname   E.g. internal
 * @param string $pluginid     Instance id
 * @param string $key          The config setting to look for
 */
function get_config_plugin_instance($plugintype, $pluginid, $key) {
    global $CFG;

    // Must be unlikely to exist as a config option for any plugin
    $instance = '_i_n_s_t'.$pluginid;

    // Suppress NOTICE with @ in case $key is not yet cached
    @$value = $CFG->plugin->{$plugintype}->{$instance}->{$key};
    if (isset($value)) {
        return $value;
    }

    $records = get_records_array($plugintype . '_instance_config', 'instance', $pluginid, 'field', 'field, value');
    if (!empty($records)) {
        foreach($records as $record) {
            $CFG->plugin->{$plugintype}->{$instance}->{$record->field} = $record->value;
            if ($record->field == $key) {
                $value = $record->value;
            }
        }
    }

    return $value;
}

/**
 * This function returns a value for $CFG for a plugin instance
 * or null if it is not found. Initially this is interesting only 
 * for multiauth. Note that it may go and look in the database
 *
 * @param string $plugintype   E.g. auth
 * @param string $pluginname   E.g. internal
 * @param string $pluginid     Instance id
 * @param string $key          The config setting to look for
 */
function set_config_plugin_instance($plugintype, $pluginname, $pluginid, $key, $value) {
    global $CFG;
    $table = $plugintype . '_instance_config';

    if (false !== get_field($table, 'value', 'instance', $pluginid, 'field', $key)) {
        if (set_field($table, 'value', $value, 'instance', $pluginid, 'field', $key)) {
            $status = true;
        }
    }
    else {
        $pconfig = new StdClass;
        $pconfig->instance = $pluginid;
        $pconfig->field  = $key;
        $pconfig->value  = $value;
        $status = insert_record($table, $pconfig);
    }
    if ($status) {
        // Must be unlikely to exist as a config option for any plugin
        $instance = '_i_n_s_t'.$pluginid;
        $CFG->plugin->{$plugintype}->{$pluginname}->{$instance}->{$key} = $value;
        return true;
    }
    return false;
}

/**
 * This function prints an array or object
 * wrapped inside <pre></pre>
 * 
 * @param $mixed value to print
 */
function print_object($mixed) {
    echo '<pre>';
    print_r($mixed);
    echo '</pre>';
}

/**
 * This function returns the current 
 * language to use, either for a given user
 * or sitewide, or the default
 * 
 * @return string
 */
function current_language() {
    global $USER, $CFG, $SESSION;
    if ($USER instanceof User) {
        $lang = $USER->get_account_preference('lang');
        if ($lang !== null && $lang != 'default') {
            if (language_installed($lang)) {
                return $lang;
            }
            $USER->set_account_preference('lang', 'default');
        }
    }

    if (is_a($SESSION, 'Session')) {
        $sesslang = $SESSION->get('lang');
        if (!empty($sesslang) && $sesslang != 'default') {
            return $sesslang;
        }
    }

    if (!empty($CFG->lang)) {
        return $CFG->lang;
    }
    return 'en.utf8';
}

/**
 * Helper function to sprintf language strings
 * with a variable number of arguments
 * 
 * @param string $string raw string to use
 * @param array $args arguments to sprintf
 */
function format_langstring($string,$args) {
    return call_user_func_array('sprintf',array_merge(array($string),$args));
}

/**
 * Helper function to figure out whether an array is an array or a hash
 * @param array $array array to check
 * @return bool true if the array is a hash
 */
function is_hash($array) {
    if (!is_array($array)) {
        return false;
    }
    $diff = array_diff_assoc($array,array_values($array));
    return !empty($diff);
}

/**
 * Function to check if a directory exists and optionally create it.
 *
 * @param string absolute directory path
 * @param boolean create directory if does not exist
 * @param boolean create directory recursively
 *
 * @return boolean true if directory exists or created
 */
function check_dir_exists($dir, $create=true, $recursive=true) {

    $status = true;

    if(!is_dir($dir)) {
        if (!$create) {
            $status = false;
        } else {
            $mask = umask(0000);
            $status = @mkdir($dir, get_config('directorypermissions'), true);
            umask($mask);
        }
    }
    return $status;
}

/**
 * Function to require a plugin file. This is to avoid doing 
 * require and include directly with variables.
 *
 * This function is the one safe point to require plugin files.
 * so USE it :)
 *
 * @param string $plugintype the type of plugin (eg artefact)
 * @param string $pluginname the name of the plugin (eg blog)
 * @param string $filename the name of the file to include within the plugin structure
 * @param string $function (optional, defaults to require) the require/include function to use
 * @param string $nonfatal (optional, defaults to false) just returns false if the file doesn't exist
 */
function safe_require($plugintype, $pluginname, $filename='lib.php', $function='require_once', $nonfatal=false) {
    $plugintypes = plugin_types();
    if (!in_array($plugintype, $plugintypes)) {
        throw new Exception("\"$plugintype\" is not a valid plugin type");
    }
    require_once(get_config('docroot') . $plugintype . '/lib.php');

    if (!in_array($function,array('require', 'include', 'require_once', 'include_once'))) {
        if (!empty($nonfatal)) {
            return false;
        }
        throw new Exception ('invalid require type');
    }

    if ($plugintype == 'blocktype') { // these are a bit of a special case
        $bits = explode('/', $pluginname);
        if (count($bits) == 2) {
           $fullpath = get_config('docroot') . 'artefact/' . $bits[0] . '/blocktype/' . $bits[1] . '/' . $filename;
        }
        else {
            if (table_exists(new XMLDBTable('blocktype_installed'))) {
                if ($artefactplugin = get_field('blocktype_installed', 'artefactplugin', 'name', $pluginname)) {
                    $fullpath = get_config('docroot') . 'artefact/' . $artefactplugin . '/blocktype/' . $pluginname . '/'. $filename;
                }
            }
        }
    } 
    if (empty($fullpath)) {
        $fullpath = get_config('docroot') . $plugintype . '/' . $pluginname . '/' . $filename;
    }

    if (!$realpath = realpath($fullpath)) {
        if (!empty($nonfatal)) {
            return false;
        }
        throw new Exception ("File $fullpath did not exist");
    }

    if (strpos($realpath, get_config('docroot') !== 0)) {
        if (!empty($nonfatal)) {
            return false;
        }
        throw new Exception ("File $fullpath was outside document root!");
    }

    if ($function == 'require') { return require($realpath); }
    if ($function == 'include') { return include($realpath); }
    if ($function == 'require_once') { return require_once($realpath); }
    if ($function == 'include_once') { return include_once($realpath); }
    
}

/**
 * This function returns the list of plugintypes we currently care about.
 *
 * NOTE: use plugin_types_installed if you just want the installed ones.
 *
 * @return array of names
 */
function plugin_types() {
    static $pluginstocheck;
    if (empty($pluginstocheck)) {
        // ORDER MATTERS! artefact has to be first!
        $pluginstocheck = array('artefact', 'auth', 'notification', 'search', 'blocktype', 'interaction', 'grouptype');
    }
    return $pluginstocheck;
}

/**
 * Returns plugin types that are actually installed
 */
function plugin_types_installed() {
    static $plugins = array();
    if (empty($plugins)) {
        require_once('ddl.php');
        foreach (plugin_types() as $plugin) {
            if (table_exists(new XMLDBTable("{$plugin}_installed"))) {
                $plugins[] = $plugin;
            }
        }
    }
    return $plugins;
}

/** 
 * This return returns the names of plugins installed 
 * for the given plugin type.
 * 
 * @param string $plugintype type of plugin
 */
function plugins_installed($plugintype) {
    return get_records_array($plugintype . '_installed');
}

/**
 * Helper to call a static method when you do not know the name of the class
 * you want to call the method on. PHP5 does not support $class::method().
 */
function call_static_method($class, $method) {
    $args = func_get_args();
    array_shift($args);
    array_shift($args);
    return call_user_func_array(array($class, $method), $args);
}

function generate_class_name() {
    $args = func_get_args();
    if (count($args) == 2 && $args[0] == 'blocktype') {
        return 'PluginBlocktype' . ucfirst(blocktype_namespaced_to_single($args[1]));
    }
    return 'Plugin' . implode('', array_map('ucfirst', $args));
}

function generate_artefact_class_name($type) {
    return 'ArtefactType' . ucfirst($type);
}

function generate_interaction_instance_class_name($type) {
    return 'Interaction' . ucfirst($type) . 'Instance';
}

function blocktype_namespaced_to_single($blocktype) {
    if (strpos($blocktype, '/') === false) { // system blocktype
        return $blocktype;
    }
    return substr($blocktype, strpos($blocktype, '/') + 1 );
}

function blocktype_single_to_namespaced($blocktype, $artefact='') {
    if (empty($artefact)) {
        return $blocktype;
    }
    return $artefact . '/' . $blocktype;
}

/**
 * Given a blocktype name, convert it to the namespaced version.
 *
 * This will be $artefacttype/$blocktype, or just plain $blocktype for system 
 * blocktypes.
 *
 * This is useful for language strings
 */
function blocktype_name_to_namespaced($blocktype) {
    static $resultcache = array();

    if (!isset($resultcache[$blocktype])) {
        $artefactplugin = get_field('blocktype_installed', 'artefactplugin', 'name', $blocktype);
        if ($artefactplugin) {
            $resultcache[$blocktype] = "$artefactplugin/$blocktype";
        }
        else {
            $resultcache[$blocktype] = $blocktype;
        }
    }

    return $resultcache[$blocktype];
}


/**
 * Fires an event which can be handled by different parts of the system
 */
function handle_event($event, $data) {
    if (!$e = get_record('event_type', 'name', $event)) {
        throw new Exception("Invalid event");
    }

    if ($data instanceof ArtefactType || $data instanceof BlockInstance) {
        // leave it alone
    }
    else if (is_object($data)) {
        $data = (array)$data;
    }
    else if (is_numeric($data)) {
        $data = array('id' => $data);
    }

    if ($coreevents = get_records_array('event_subscription', 'event', $event)) {
        foreach ($coreevents as $ce) {
            if (function_exists($ce->callfunction)) {
                call_user_func($ce->callfunction, $data);
            }
            else {
                log_warn("Event $event caused a problem with a core subscription "
                . " $ce->callfunction, which wasn't callable.  Continuing with event handlers");
            }
        }
    }

    $plugintypes = plugin_types_installed();
    foreach ($plugintypes as $name) {
        if ($subs = get_records_array($name . '_event_subscription', 'event', $event)) {
            foreach ($subs as $sub) {
                safe_require($name, $sub->plugin);
                $classname = 'Plugin' . ucfirst($name) . ucfirst($sub->plugin);
                try {
                    call_static_method($classname, $sub->callfunction, $event, $data);
                }
                catch (Exception $e) {
                    log_warn("Event $event caused an exception from plugin $classname "
                             . "with function $sub->callfunction. Continuing with event handlers");
                }
            }
        }
    }
}

/**
 * function to convert an array of objects to 
 * an array containing one field per place
 * 
 * @param array $array input array
 * @param mixed $field field to look for in each object
 */
function mixed_array_to_field_array($array, $field) {
    $repl_fun = create_function('$n, $field', '$n = (object)$n; return $n->{$field};');
    $fields = array_pad(array(), count($array), $field);
    return array_map($repl_fun, $array, $fields);
}


/** 
 * Adds stuff to the log
 * @todo write this function
 *
 * @param string $plugintype plugin type or core
 * @param string $pluginname plugin name or core component (eg 'view')
 * @param string $action action string (like 'add')
 * @param int $user id of user making the action
 * @param int $id relevant id (ie, profile view would have id of profile owner)
 * 
 */
function add_to_log($plugintype, $pluginname, $action, $user, $id=0) {

}

/**
 * Used by XMLDB
 */
function debugging ($message, $level) {
    log_debug($message);
}
function xmldb_dbg($message) {
    log_warn($message);
}
define('DEBUG_DEVELOPER', 'whocares');

/** 
 * Base class for all plugintypes.
 */
class Plugin {
    
    /**
     * This function returns an array of crons it wants to have run
     * Each item should be a StdClass object containing - 
     * - callfunction (static function on the Plugin Class)
     * - any or all of minute, hour, day, month, dayofweek 
     * (will default to * if not supplied)
     */
    public static function get_cron() {
        return array();
    }

    /** 
     * This function returns an array of events to subscribe to
     * by unique name. 
     * If an event the plugin is trying to subscribe to is unknown by the
     * core, an exception will be thrown.
     * @return array
     */
    public static function get_event_subscriptions() {
        return array();
    }


    /**
     * This function will be run after every upgrade to the plugin.
     * 
     * @param int $fromversion version upgrading from (or 0 if installing)
     */
    public static function postinst($fromversion) {
        return true;
    }

    /**
     * Whether this plugin has admin plugin config options.
     * If you return true here, you must supply a valid pieform
     * in {@link get_config}
     */
    public static function has_config() {
        return false;
    }

    /**
    * Does this plugin offer any activity types
    * If it does, you must subclass ActivityTypePlugin like 
    * ActivityType{$PluginType}{$Pluginname}
    */
    public static function get_activity_types() {
        return array();
    }
}

/**
 * formats a unix timestamp to a nice date format.
 * 
 * @param int $date unix timestamp to format
 * @param string $formatkey language key to fetch the format from
 * @param string $notspecifiedkey (optional) language key to fetch 'not specified string' from
 * @param string $notspecifiedsection (optional) language section to fetch 'not specified string' from
 * (see langconfig.php or the top of {@link get_string_location}
 * for supported keys
 */

function format_date($date, $formatkey='strftimedatetime', $notspecifiedkey='strftimenotspecified', $notspecifiedsection='mahara') {
    if (empty($date)) {
        return get_string($notspecifiedkey, $notspecifiedsection);
    }
    return strftime(get_string($formatkey), $date);
}

/**
 * Returns a random string suitable for registration/change password requests
 *
 * @param int $length The length of the key to return
 * @return string
 */
function get_random_key($length=16) {
    if ($length < 1) {
        throw new IllegalArgumentException('Length must be a positive number');
    }
    $pool = array_merge(
        range('A', 'Z'),
        range('a', 'z'),
        range(0, 9)
    );
    shuffle($pool);
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $pool[$i];
    }
    return $result;
}


//
// Pieform related functions
//

/**
 * Configures a default form
 */
function pieform_configure() {
    global $USER;
    $theme = ($USER->get('theme')) ? $USER->get('theme') : 'default';
    require(get_config('docroot') . 'theme/' . $theme . '/config.php');
    return array(
        'method'    => 'post',
        'action'    => '',
        'language'  => current_language(),
        'autofocus' => true,
        'renderer'  => (isset($theme->formrenderer)) ? $theme->formrenderer : 'table',
        'requiredmarker' => true,
        'elementclasses' => true,
        'descriptionintwocells' => true,
        'jsdirectory'    => get_config('wwwroot') . 'lib/pieforms/static/core/',
        'replycallback'  => 'pieform_reply',
        'jserrorcallback'       => 'formError',
        'globaljserrorcallback' => 'formGlobalError',
        'jssuccesscallback'     => 'formSuccess',
        'presubmitcallback'     => 'formStartProcessing',
        'postsubmitcallback'    => 'formStopProcessing',
        'jserrormessage' => get_string('errorprocessingform'),
        'configdirs' => get_config('libroot') . 'form/',
        'helpcallback' => 'pieform_get_help',
        'elements'   => array(
            'sesskey' => array(
                'type'  => 'hidden',
                'value' => $USER->get('sesskey')
            )
        )
    );
}

function pieform_validate(Pieform $form, $values) {
    global $USER;
    if (!isset($values['sesskey'])) {
        throw new UserException('No session key');
    }
    if ($USER && $USER->is_logged_in() && $USER->get('sesskey') != $values['sesskey']) {
        throw new UserException('Invalid session key');
    }

    // Check to make sure the user has not been suspended, so that they cannot
    // perform any action
    if ($USER) {
        $record = get_record_sql('SELECT suspendedctime, suspendedreason
            FROM {usr}
            WHERE id = ?', array($USER->get('id')));
        if ($record && $record->suspendedctime) {
            throw new UserException(get_string('accountsuspended', 'mahara', $record->suspendedctime, $record->suspendedreason));
        }
    }
}

function pieform_reply($code, $data) {
    global $SESSION;
    if (isset($data['message'])) {
        if ($code == PIEFORM_ERR) {
            $SESSION->add_error_msg($data['message']);
        }
        else {
            $SESSION->add_ok_msg($data['message']);
        }
    }
    if (isset($data['goto'])) {
        redirect($data['goto']);
    }
    // NOT explicitly exiting here. Pieforms will throw an exception which will 
    // force the user to fix their form
}

function pieform_element_calendar_configure($element) {
    $element['jsroot'] = get_config('wwwroot') . 'js/jscalendar/';
    $element['themefile'] = theme_get_url('style/calendar.css');
    $element['imagefile'] = theme_get_url('images/calendar.gif');
    $language = substr(current_language(), 0, 2);
    $element['language'] = $language;
    return $element;
}

function pieform_element_textarea_configure($element) {
    if (!array_key_exists('resizable', $element)) {
        $element['resizable'] = true;
    }
    return $element;
}

/**
 * Given a view id, and a user id (defaults to currently logged in user if not
 * specified) will return wether this user is allowed to look at this view.
 *
 * @param integer $view_id      View ID to check
 * @param integer $user_id      User trying to look at the view (defaults to
 * currently logged in user, or null if user isn't logged in)
 *
 * @returns boolean Wether the specified user can look at the specified view.
 */
function can_view_view($view_id, $user_id=null) {
    /*
    TODO PENNY PROFILEVIEW MERGE
        I couldn't figure out this patch and needed to continue:
        http://paste.dollyfish.net.nz/0e6800
    */
    global $USER;
    $now = time();
    $dbnow = db_format_timestamp($now);

    if ($user_id === null) {
        $user_id = $USER->get('id');
    }

    if (!$USER->is_logged_in()) {
        // check public
        $publicviews = get_config('allowpublicviews');
        $publicprofiles = get_config('allowpublicprofiles');
        if ($publicviews || $publicprofiles) {
            $public = get_record_sql("
                SELECT
                    v.id, v.type, a.*
                FROM
                    {view} v
                    LEFT OUTER JOIN {view_access} a ON v.id = a.view
                WHERE
                    v.id = ? AND a.accesstype = 'public'
            ", array($view_id));
            return $public && 
                ( ( $publicviews
                    && ( $public->startdate == null || $public->startdate < $now )
                    && ( $public->stopdate == null || $public->stopdate > $now )
                    )
                  ||
                  ( $publicprofiles && $public->type == 'profile' )
                );
        }
        return false;
    }

    // The user is logged in; they can see the view if
    // - they can edit it, or
    // - it has been submitted to them for assessment, or
    // - they have been granted access via the edit view access page.

    require_once(get_config('docroot') . 'lib/view.php');
    $view = new View($view_id);

    if ($USER->can_edit_view($view)) {
        return true;
    }

    if ($submitgroup = $view->get('submittedto')) {
        require_once(get_config('docroot') . 'lib/group.php');
        if (group_user_can_assess_submitted_views($submitgroup, $user_id)) {
            return true;
        }
    }

    // Check access for loggedin, friends, user, group
    $access = get_records_sql_array('
            SELECT accesstype AS type,
                CASE WHEN accesstype = \'friends\' THEN 4 ELSE 1 END AS typeorder,
                ' . db_format_tsfield('startdate') . ', ' . db_format_tsfield('stopdate') . '
            FROM {view_access}
            WHERE view = ?
        UNION
            SELECT \'user\' AS type, 2 AS typeorder, ' . db_format_tsfield('startdate') . ', ' . db_format_tsfield('stopdate') . '
            FROM {view_access_usr}
            WHERE view = ? AND usr = ?
        UNION
            SELECT \'group\' AS type, 3 AS typeorder, ' . db_format_tsfield('startdate') . ', ' . db_format_tsfield('stopdate') . '
            FROM
                {view_access_group} vg
                INNER JOIN {group} g ON (vg.group = g.id AND g.deleted = 0)
                INNER JOIN {group_member} m ON (g.id = m.group AND (vg.role IS NULL OR vg.role = m.role))
            WHERE vg.view = ? AND m.member = ?
        ORDER BY typeorder ', array($view_id, $view_id, $user_id, $view_id, $user_id));

    if (empty($access)) {
        return false;
    }

    foreach ($access as $a) {
        if ($a->type == 'friends') {
            $owner = $view->get('owner');
            if (!get_field_sql('SELECT COUNT(*) FROM {usr_friend} f WHERE (usr1=? AND usr2=?) OR (usr1=? AND usr2=?)',
                               array( $owner, $user_id, $user_id, $owner ))) {
                continue;
            }
        }
        if (($a->startdate == null || $a->startdate < $now) && ($a->stopdate == null || $a->stopdate > $now)) {
            return true;
        }
    }

    return false;
}

/**
 * get the views that a user can see belonging
 * to the given users
 *
 * @param array $users users to fetch views owned by
 * @param int $userlooking (optional, defaults to logged in user)
 * @param int $limit grab this many views. (setting this null means get all)
 *
 * @return array Associative array keyed by userid, of arrays of view ids
 */
function get_views($users, $userlooking=null, $limit=5) {
    $userlooking = optional_userid($userlooking);
    if (is_int($users)) {
        $users = array($users);
    }

    $list = array();

    if(count($users) == 0) {
        return $list;
    }

    $users = array_flip($users);

    $dbnow  = db_format_timestamp(time());

    if ($friends = get_records_sql_array(
        'SELECT
            CASE WHEN usr1=? THEN usr2 ELSE usr1 END AS id
        FROM
            {usr_friend} f
        WHERE
            ( usr1=? AND usr2 IN (' . join(',',array_map('db_quote', array_keys($users))) . ') )
            OR
            ( usr2=? AND usr1 IN (' . join(',',array_map('db_quote', array_keys($users))) . ') )
        ',
        array($userlooking,$userlooking,$userlooking)
    )) {
        foreach ( $friends as $user_id ) {
            $users[$user_id->id] = 'friend';
        }
    }

    // public, logged in, or friends' views
    if ($results = get_records_sql_array(
        'SELECT
            v.*,
            ' . db_format_tsfield('atime') . ',
            ' . db_format_tsfield('mtime') . ',
            ' . db_format_tsfield('ctime') . '
        FROM 
            {view} v
            INNER JOIN {view_access} a ON
                v.id=a.view
                AND (
                    accesstype IN (\'public\',\'loggedin\')
            ' . (
                    count(preg_grep('/^friend$/', $users)) > 0
                    ?  'OR (
                            accesstype = \'friends\'
                            AND v.owner IN (' . join(',',array_map('db_quote', array_keys(preg_grep('/^friend$/', $users)))) . ')
                        )'
                    : ''
                )
            . '
                )
        WHERE
            v.owner IN (' . join(',',array_map('db_quote', array_keys($users))) . ')
            AND ( v.startdate IS NULL OR v.startdate < ? )
            AND ( v.stopdate IS NULL OR v.stopdate > ? )
        ',
        array( $dbnow, $dbnow )
        )
    ) {
        foreach ($results as &$row) {
            $list[$row->owner][$row->id] = $row;
        }
    }

    // bail if we've filled all users to the limit
    if (_get_views_trim_list($list, $users, $limit)) {
        return $list;
    }

    // check individual user access
    if ($results = get_records_sql_array(
        'SELECT
            v.*,
            ' . db_format_tsfield('atime') . ',
            ' . db_format_tsfield('mtime') . ',
            ' . db_format_tsfield('ctime') . '
        FROM 
            {view} v
            INNER JOIN {view_access_usr} a ON v.id=a.view AND a.usr=?
        WHERE
            v.owner IN (' . join(',',array_map('db_quote', array_keys($users))) . ')
            AND ( v.startdate IS NULL OR v.startdate < ? )
            AND ( v.stopdate IS NULL OR v.stopdate > ? )
        ',
        array($userlooking, $dbnow, $dbnow)
        )
    ) {
        foreach ($results as &$row) {
            $list[$row->owner][$row->id] = $row;
        }
    }

    // bail if we've filled all users to the limit
    if (_get_views_trim_list($list, $users, $limit)) {
        return $list;
    }

    // check group access
    if ($results = get_records_sql_array(
        'SELECT
            v.*,
            ' . db_format_tsfield('v.atime','atime') . ',
            ' . db_format_tsfield('v.mtime','mtime') . ',
            ' . db_format_tsfield('v.ctime','ctime') . '
        FROM 
            {view} v
            INNER JOIN {view_access_group} a ON v.id=a.view
            INNER JOIN {group_member} m ON m.group=a.group AND m.member=?
            INNER JOIN {group} g ON (g.id = a.group AND g.deleted = ?)
        WHERE
            v.owner IN (' . join(',',array_map('db_quote', array_keys($users))) . ')
            AND ( v.startdate IS NULL OR v.startdate < ? )
            AND ( v.stopdate IS NULL OR v.stopdate > ? )
        ',
        array($userlooking, 0, $dbnow, $dbnow)
        )
    ) {
        foreach ($results as &$row) {
            $list[$row->owner][$row->id] = $row;
        }
    }

    // bail if we've filled all users to the limit
    if (_get_views_trim_list($list, $users, $limit)) {
        return $list;
    }

    return $list;
}

function _get_views_trim_list(&$list, &$users, $limit) {
    if ($limit === null) {
        return;
    }
    foreach ($list as $user_id => &$views) {
        if($limit and count($views) > $limit) {
            $views = array_slice($views, 0, $limit);
        }
        if($limit and count($views) == $limit) {
            unset($users[$user_id]);
        }
    }
    if (count($users) == 0) {
        return true;
    }
    return false;
}

function artefact_in_view($artefact, $view) {
    $sql = 'SELECT a.id 
            FROM {view_artefact} a WHERE view = ? AND artefact = ?
            UNION
            SELECT c.parent 
            FROM {view_artefact} top JOIN {artefact_parent_cache} c
              ON c.parent = top.artefact 
            WHERE top.view = ? AND c.artefact = ?';

    return record_exists_sql($sql, array($view, $artefact, $view, $artefact));
}

function get_dir_contents($directory) {
    $contents = array();
    $dirhandle = opendir($directory);
    while (false !== ($dir = readdir($dirhandle))) {
        if (strpos($dir, '.') === 0) {
            continue;
        }
        $contents[] = $dir;
    }
    return $contents;
}

/**
 * Returns the subdirectory where mahara is installed, normally / but could
 * be something different on a shared host. Useful for setting cookie paths.
 *
 * @return string
 */
function get_mahara_install_subdirectory() {
    $wwwroot = get_config('wwwroot');
    $wwwroot = preg_replace('#^https?://#', '', $wwwroot);
    return substr($wwwroot, strpos($wwwroot, '/'));
}

/**
 *** get_performance_info() pairs up with init_performance_info()
 *** loaded in init.php. Returns an array with 'html' and 'txt'
 *** values ready for use, and each of the individual stats provided
 *** separately as well.
 ***
 **/
function get_performance_info() {

    if (!get_config('perftofoot') && !get_config('perftolog')) {
        return array();
    }

    global $PERF;

    $info = array();

    $info['realtime'] = microtime_diff($PERF->starttime, microtime());


    if (function_exists('memory_get_usage')) {
        $info['memory_total'] = memory_get_usage();
        $info['memory_growth'] = memory_get_usage() - $PERF->startmemory;
    }

    $inc = get_included_files();
    $info['includecount'] = count($inc);


    if (!empty($PERF->dbreads)) {
        $info['dbreads'] = $PERF->dbreads;
    }

    if (!empty($PERF->dbwrites)) {
        $info['dbwrites'] = $PERF->dbwrites;
    }

    if (function_exists('posix_times')) {
        $ptimes = posix_times();
        if (is_array($ptimes)) {
            foreach ($ptimes as $key => $val) {
                $info[$key] = $ptimes[$key] -  $PERF->startposixtimes[$key];
            }
        }
    }

    // Grab the load average for the last minute
    // /proc will only work under some linux configurations
    // while uptime is there under MacOSX/Darwin and other unices
    if (is_readable('/proc/loadavg') && $loadavg = @file('/proc/loadavg')) {
        list($server_load) = explode(' ', $loadavg[0]);
        unset($loadavg);
    } else if ( function_exists('is_executable') && is_executable('/usr/bin/uptime') && $loadavg = `/usr/bin/uptime` ) {
        if (preg_match('/load averages?: (\d+[\.,:]\d+)/', $loadavg, $matches)) {
            $server_load = $matches[1];
        } else {
            log_debug('PERF: Could not parse uptime output!');
        }
    }
    if (!empty($server_load)) {
        $info['serverload'] = $server_load;
    }
    else {
        $info['serverload'] = 'unknown';
    }

    return $info;
}

function perf_to_log($info=null) {
    if (!get_config('perftolog')) {
        return true;
    }

    if (empty($info)) {
        $info = get_performance_info();
    }

    $logstring = 'PERF: ' .  strip_querystring(get_script_path()). ': ';
    $logstring .= ' memory_total: '.$info['memory_total'].'B (' . display_size($info['memory_total']).') memory_growth: '.$info['memory_growth'].'B ('.display_size($info['memory_growth']).')';
    $logstring .= ' time: '.$info['realtime'].'s';
    $logstring .= ' includecount: '.$info['includecount'];
    $logstring .= ' dbqueries: '.$info['dbreads'] . ' reads, ' . $info['dbwrites'] . ' writes';
    $logstring .= ' ticks: ' . $info['ticks']  . ' user: ' . $info['utime'] . ' sys: ' . $info['stime'] .' cuser: ' . $info['cutime'] . ' csys: ' . $info['cstime'];
    $logstring .= ' serverload: ' . $info['serverload'];
    log_debug($logstring);
}

/**
 * microtime_diff
 *
 * @param string $a ?
 * @param string $b ?
 * @return string
 * @todo Finish documenting this function
 */
function microtime_diff($a, $b) {
    list($a_dec, $a_sec) = explode(' ', $a);
    list($b_dec, $b_sec) = explode(' ', $b);
    return $b_sec - $a_sec + $b_dec - $a_dec;
}

/**
 * Converts bytes into display form
 *
 * @param string $size  ?
 * @return string
 * @staticvar string $gb Localized string for size in gigabytes
 * @staticvar string $mb Localized string for size in megabytes
 * @staticvar string $kb Localized string for size in kilobytes
 * @staticvar string $b Localized string for size in bytes
 * @todo Finish documenting this function. Verify return type.
 */
function display_size($size) {

    static $gb, $mb, $kb, $b;

    if (empty($gb)) {
        $gb = get_string('sizegb');
        $mb = get_string('sizemb');
        $kb = get_string('sizekb');
        $b  = get_string('sizeb');
    }

    if ($size >= 1073741824) {
        $size = round($size / 1073741824 * 10) / 10 . $gb;
    } else if ($size >= 1048576) {
        $size = round($size / 1048576 * 10) / 10 . $mb;
    } else if ($size >= 1024) {
        $size = round($size / 1024 * 10) / 10 . $kb;
    } else {
        $size = $size .' '. $b;
    }
    return $size;
}

/**
 * creates the profile sideblock
 */
function profile_sideblock() {
    global $USER, $SESSION;
    safe_require('notification', 'internal');
    require_once('group.php');
    $data = array(
        'id'          => $USER->get('id'),
        'profileicon' => $USER->get('profileicon') ? $USER->get('profileicon') : 0,
    );

    if ($SESSION->get('mnetuser')) {
        $authinstance = $SESSION->get('authinstance');
        if ($authinstance) {
            $authobj = AuthFactory::create($authinstance);
            $peer = get_peer($authobj->wwwroot);
            $data['mnetloggedinfrom'] = get_string('youhaveloggedinfrom', 'auth.xmlrpc',
                $authobj->wwwroot, $peer->name);
        }
    }
    $data['unreadnotifications'] = call_static_method(generate_class_name('notification', 'internal'), 'unread_count', $USER->get('id'));
    $data['unreadnotificationsmessage'] = $data['unreadnotifications'] == 1 ? get_string('unreadmessage') : get_string('unreadmessages');
    $invitedgroups = get_records_sql_array('SELECT g.*, gmi.ctime, gmi.reason
             FROM {group} g
             JOIN {group_member_invite} gmi ON gmi.group = g.id
             WHERE gmi.member = ? AND g.deleted = ?', array($USER->get('id'), 0));
    $data['invitedgroups'] = $invitedgroups ? count($invitedgroups) : 0;
    $data['invitedgroupsmessage'] = $data['invitedgroups'] == 1 ? get_string('invitedgroup') : get_string('invitedgroups');
    $data['pendingfriends'] = count_records('usr_friend_request', 'owner', $USER->get('id'));
    $data['pendingfriendsmessage'] = $data['pendingfriends'] == 1 ? get_string('pendingfriend') : get_string('pendingfriends');
    $data['groups'] = get_records_sql_array(
        "SELECT g.id, g.name, gm.role
        FROM {group} g
        JOIN {group_member} gm ON (gm.group = g.id)
        WHERE gm.member = ?
        AND g.deleted = 0
        ORDER BY gm.role = 'admin' DESC, gm.role, g.id", array($USER->get('id')));
    $data['views'] = get_records_sql_array(
        'SELECT v.id, v.title
        FROM {view} v
        INNER JOIN {view_tag} vt ON (vt.tag = ? AND vt.view = v.id)
        WHERE v.owner = ?
        ORDER BY v.title',
        array(get_string('profile'), $USER->get('id'))
    );
    $data['artefacts'] = get_records_sql_array(
         'SELECT a.id, a.artefacttype, a.title
         FROM {artefact} a
         INNER JOIN {artefact_tag} at ON (a.id = at.artefact AND tag = ?)
         WHERE a.owner = ?
         ORDER BY a.title',
         array(get_string('profile'), $USER->get('id'))
    );
    return $data;
}

/**
 * Gets data about users who have been online in the last while.
 *
 * The time is configured by setting the 'accessidletimeout' configuration 
 * option.
 *
 * NOTE: currently returns all online users, this might not be desirable on a 
 * really busy site.
 */
function onlineusers_sideblock() {
    global $USER;

    $onlineusers = get_records_select_array('usr', 'lastaccess > ?',
        array(db_format_timestamp(time() - get_config('accessidletimeout'))), 'lastaccess DESC');

    foreach ($onlineusers as &$user) {
        // Use 'profileiconbyid' for the current user, just in case they change their profile icon
        if ($user->id == $USER->get('id')) {
            $user->profileiconurl = get_config('wwwroot') . 'thumb.php?type=profileiconbyid&id=' . (int)$user->profileicon . '&size=20x20';
        }
        else {
            $user->profileiconurl = get_config('wwwroot') . 'thumb.php?type=profileicon&id=' . $user->id . '&size=20x20';
        }

        // If the user is an MNET user, show where they've come from
        $authobj = AuthFactory::create($user->authinstance);
        if ($authobj->authname == 'xmlrpc') {
            $peer = get_peer($authobj->wwwroot);
            $user->loggedinfrom = $peer->name;
        }
    }
    return array(
        'users' => $onlineusers,
        'count' => count($onlineusers),
        'lastminutes' => floor(get_config('accessidletimeout') / 60),
    );
}

/**
 * Cronjob to recalculate how much quota each user is using and update it as 
 * appropriate.
 *
 * This gives a backstop for the possibility that there is a bug elsewhere that 
 * has caused the quota count to get out of sync
 */
function recalculate_quota() {
    if (!$artefacts = get_records_array('artefact', '', '', '', 'id, artefacttype, owner')) {
        // Nothing to do
        return;
    }

    $userquotas = array();

    foreach ($artefacts as $artefact) {
        safe_require('artefact', get_field('artefact_installed_type', 'plugin', 'name', $artefact->artefacttype));
        if (!isset($userquotas[$artefact->owner])) {
            $userquotas[$artefact->owner] = 0;
        }
        $userquotas[$artefact->owner] += call_static_method(generate_artefact_class_name($artefact->artefacttype), 'get_quota_usage', $artefact->id);
    }

    foreach ($userquotas as $user => $quota) {
        $data = (object) array(
            'quotaused' => $quota
        );
        $where = (object) array(
            'id' => $user
        );
        update_record('usr', $data, $where);
    }
}

?>
