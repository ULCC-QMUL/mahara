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
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

/**#@+
 * @access private
 */
// These are bitmaps - the next one should be 4
/** Display the errors on the screen */
define('LOG_TARGET_SCREEN', 1);
/** Write the errors to the error log, as specified in your php configuration */
define('LOG_TARGET_ERRORLOG', 2);

// Logging levels
/** Environment type errors, such as register_globals being on */
define('LOG_LEVEL_ENVIRON', 1);
/** Debug messages */
define('LOG_LEVEL_DBG', 2);
/** Informational messages */
define('LOG_LEVEL_INFO', 4);
/** Warnings */
define('LOG_LEVEL_WARN', 8);
/**#@-*/

// Tell PHP about our error settings
error_reporting(E_ALL);
set_error_handler('error');
set_exception_handler('exception');


// Logging functions

/**
 * Logs a message at the debug level
 *
 * @param string $message   The message to display
 * @param bool   $escape    Whether to HTML escape the message
 * @param bool   $backtrace Whether to provide a backtrace if the system is
 *                          configured to give backtraces at this level.
 */
function log_debug ($message, $escape=true, $backtrace=true) {
    log_message($message, LOG_LEVEL_DBG, $escape, $backtrace);
}

/**
 * Logs a message at the info level
 *
 * @param string $message   The message to display
 * @param bool   $escape    Whether to HTML escape the message
 * @param bool   $backtrace Whether to provide a backtrace if the system is
 *                          configured to give backtraces at this level.
 */
function log_info ($message, $escape=true, $backtrace=true) {
    log_message($message, LOG_LEVEL_INFO, $escape, $backtrace);
}

/**
 * Logs a message at the warning level
 *
 * @param string $message   The message to display
 * @param bool   $escape    Whether to HTML escape the message
 * @param bool   $backtrace Whether to provide a backtrace if the system is
 *                          configured to give backtraces at this level.
 */
function log_warn ($message, $escape=true, $backtrace=true) {
    log_message($message, LOG_LEVEL_WARN, $escape, $backtrace);
}

/**
 * Logs a message at the environment level
 *
 * @param string $message   The message to display
 * @param bool   $escape    Whether to HTML escape the message
 * @param bool   $backtrace Whether to provide a backtrace if the system is
 *                          configured to give backtraces at this level.
 */
function log_environ ($message, $escape=true, $backtrace=true) {
    log_message($message, LOG_LEVEL_ENVIRON, $escape, $backtrace);
}

/**
 * Logs a message at the given log level. This function should not be called by
 * any code outside of this module.
 *
 * @param string $message   The message to display
 * @param int    $loglevel  The level to log the message at
 * @param bool   $escape    Whether to HTML escape the message
 * @param bool   $backtrace Whether to provide a backtrace if the system is
 *                          configured to give backtraces at this level.
 * @param string $file      The file the error occured in
 * @param int    $line      The line number the error occured on
 * @param array  $trace     The backtrace for the error
 * @access private
 */
function log_message ($message, $loglevel, $escape, $backtrace, $file=null, $line=null, $trace=null) {
    global $SESSION;
    static $loglevelnames = array(
        LOG_LEVEL_ENVIRON => 'environ',
        LOG_LEVEL_DBG     => 'dbg',
        LOG_LEVEL_INFO    => 'info',
        LOG_LEVEL_WARN    => 'warn'
    );

    if (!function_exists('get_config') || null === ($targets = get_config('log_' . $loglevelnames[$loglevel] . '_targets'))) {
        $targets = LOG_TARGET_SCREEN | LOG_TARGET_ERRORLOG;
    }

    // Get nice backtrace information if required
    $trace = ($trace) ? $trace : debug_backtrace();
    // If the last caller was the 'error' function then it came from a PHP warning
    if (!is_null($file)) {
        $filename = $file;
        $linenum  = $line;
    }
    else {
        $filename  = $trace[1]['file'];
        $linenum   = $trace[1]['line'];
    }

    if (!function_exists('get_config') || get_config('log_backtrace_levels') & $loglevel) {
        list($textbacktrace, $htmlbacktrace) = log_build_backtrace($trace);
    }
    else {
        $textbacktrace = $htmlbacktrace = '';
    }

    if (is_bool($message)) {
        $loglines = array(($message ? 'bool(true)' : 'bool(false)'));
    }
    else if (is_null($message)) {
        $loglines = array('NULL');
    }
    else {
        $loglines = explode("\n", print_r($message, true));
    }

    // Make a prefix for each line, if we are logging a normal debug/info/warn message
    if ($loglevel != LOG_LEVEL_ENVIRON && function_exists('get_config')) {
        $docroot = get_config('docroot');
        $prefixfilename = (substr($filename, 0, strlen($docroot)) == $docroot)
            ? substr($filename, strlen($docroot))
            : $filename;
        $prefix = '(' . $prefixfilename . ':' . $linenum . ') ';
    }
    else {
        $prefix = '';
    }
    $prefix = '[' . str_pad(substr(strtoupper($loglevelnames[$loglevel]), 0, 3), 3) . '] ' . $prefix;

    if ($targets & LOG_TARGET_SCREEN) {
        // Work out which method to call for displaying the message
        if ($loglevel == LOG_LEVEL_DBG || $loglevel == LOG_LEVEL_INFO) {
            $method = 'add_info_msg';
        }
        else {
            $method = 'add_error_msg';
        }

        $message = implode("\n", $loglines);
        if ($escape) {
            $message = htmlspecialchars($message, ENT_COMPAT, 'UTF-8');
            $message = str_replace('  ', '&nbsp; ', $message);
        }
        $message = nl2br($message);
        $message = '<div style="font-family: monospace;">' . $prefix . $message . "</div>\n";
        if (is_a($SESSION, 'Session')) {
            $SESSION->$method($message, false);
        }
        else if (!function_exists('get_config') || get_config('installed')) {
            // Don't output when we are not installed, since this will cause the
            // redirect to the install page to fail.
            echo $message;
        }

        if ($backtrace && $htmlbacktrace) {
            if (is_a($SESSION, 'Session')) {
                $SESSION->add_info_msg($htmlbacktrace, false);
            }
            else if (!function_exists('get_config') || get_config('installed')) {
                echo $htmlbacktrace;
            }
        }
    }

    if ($targets & LOG_TARGET_ERRORLOG) {
        foreach ($loglines as $line) {
            error_log($prefix . $line);
        }
        if ($backtrace && $textbacktrace) {
            $lines = explode("\n", $textbacktrace);
            foreach ($lines as $line) {
                error_log($line);
            }
        }
    }
}

/**
 * Given an array that contains a backtrace, builds two versions of it - one in
 * HTML form and one in text form - for logging.
 *
 * @param array $backtrace The backtrace to build
 * @return array           An array containing the backtraces, index 0
 *                         containing the text version and index 1 containing
 *                         the HTML version.
 * @access private
 */
function log_build_backtrace($backtrace) {
    $calls = array();

    // Remove the call to log_message
    //array_shift($backtrace);

    foreach ($backtrace as $bt) {
        $bt['file']  = (isset($bt['file'])) ? $bt['file'] : 'Unknown';
        $bt['line']  = (isset($bt['line'])) ? $bt['line'] : 0;
        $bt['class'] = (isset($bt['class'])) ? $bt['class'] : '';
        $bt['type']  = (isset($bt['type'])) ? $bt['type'] : '';
        $bt['args']  = (isset($bt['args'])) ? $bt['args'] : '';

        $args = '';
        if ($bt['args']) {
            foreach ($bt['args'] as $arg) {
                if (!empty($args)) {
                    $args .= ', ';
                }
                switch (gettype($arg)) {
                    case 'integer':
                    case 'double':
                        $args .= $arg;
                        break;
                    case 'string':
                        $arg = substr($arg, 0, 50) . ((strlen($arg) > 50) ? '...' : '');
                        $args .= '"' . $arg . '"';
                        break;
                    case 'array':
                        $args .= 'array(size ' . count($arg) . ')';
                        break;
                    case 'object':
                        $args .= 'object(' . get_class($arg) . ')';
                        break;
                    case 'resource':
                        $args .= 'resource(' . strstr($arg, '#') . ')';
                        break;
                    case 'boolean':
                        $args .= $arg ? 'true' : 'false';
                        break;
                    case 'NULL':
                        $args .= 'null';
                        break;
                    default:
                        $args .= 'unknown';
                }
            }
        }

        $calls[] = array(
            'file'  => $bt['file'],
            'line'  => $bt['line'],
            'class' => $bt['class'],
            'type'  => $bt['type'],
            'func'  => $bt['function'],
            'args'  => $args
        );
    }

    $textmessage = "Call stack (most recent first):\n";
    $htmlmessage = "<pre><strong>Call stack (most recent first):</strong>\n<ul>";

    foreach ($calls as $call) {
        $textmessage .= "  * {$call['class']}{$call['type']}{$call['func']}({$call['args']}) at {$call['file']}:{$call['line']}\n";
        $htmlmessage .= '<li><span style="color:#933;">' . htmlspecialchars($call['class'], ENT_COMPAT, 'UTF-8') . '</span><span style="color:#060;">'
            . htmlspecialchars($call['type'], ENT_COMPAT, 'UTF-8') . '</span><span style="color:#339;">' . htmlspecialchars($call['func'], ENT_COMPAT, 'UTF-8')
            . '</span><span style="color:#060;">(</span><span style="color:#f00;">' . htmlspecialchars($call['args'], ENT_COMPAT, 'UTF-8') . '</span><span style="color:#060;">)</span> at <strong>' . htmlspecialchars($call['file'], ENT_COMPAT, 'UTF-8') . ':' . $call['line'] . '</strong></li>';
    }
    $htmlmessage .= "</pre>\n";

    return array($textmessage, $htmlmessage);
}

/**
 * Ends the script with an informational message
 *
 * @param string $message The message to display
 * @todo this function should go away
 */
function die_info($message) {
    $smarty = smarty();
    $message .= '<p><a href="#" onclick="history.go(-1)">back</a></p>';
    $smarty->assign('message', $message);
    $smarty->assign('type', 'info');
    $smarty->display('message.tpl');
    exit;
}


// Error/Exception handling

/**
 * Called when any error occurs, due to a PHP error or through a call to
 * {@link trigger_error}.
 *
 * @param int    $code    The code of the error message
 * @param string $message The message reported
 * @param string $file    The file the error was detected in
 * @param string $line    The line number the error was detected on
 * @param array  $vars    The contents of $GLOBALS at the time the error was detected
 * @access private
 */
function error ($code, $message, $file, $line, $vars) {
    static $error_lookup = array(
        E_NOTICE => 'Notice',
        E_WARNING => 'Warning',
        // Not sure if these ever get handled here
        E_ERROR => 'Error',
        // These three are not used by this application but may be used by third parties
        E_USER_NOTICE => 'User Notice',
        E_USER_WARNING => 'User Warning',
        E_USER_ERROR => 'User Error'
    );

    if (!error_reporting()) {
        return;
    }

    if (!isset($error_lookup[$code])) {
        return;
    }

    // Ignore errors from smarty templates, which happen all too often
    if (function_exists('get_config')) {
        $compiledir = get_config('dataroot') . 'smarty/compile';

        if (E_NOTICE == $code && substr($file, 0, strlen($compiledir)) == $compiledir) {
            return;
        }
    }

    // Fix up the message, which is in HTML form
    $message = strip_tags($message);
    $message = htmlspecialchars_decode($message);

    log_message($message, LOG_LEVEL_WARN, true, true, $file, $line);
}

/**
 * Catches all otherwise uncaught exceptions. Will be deliberately used in some
 * situations. After this is called the script will end, so make sure to catch
 * any exceptions that you can deal with.
 *
 * @param Exception $e The exception that was thrown.
 * @access private
 */
function exception (Exception $e) {
    global $USER;
    if ($USER) {
        $logged = false;
        if (!($e instanceof MaharaException) || get_class($e) == 'MaharaException') {
            log_warn("An exception was thrown of class " . get_class($e) . ". \nTHIS IS BAD "
                     . "and should be changed to something extending MaharaException,\n" 
                     . "unless the exception is from a third party library.\n"
                     . "Original trace follows", true, false);
            log_message($e->getMessage(), LOG_LEVEL_WARN, true, true, $e->getFile(), $e->getLine(), $e->getTrace());
            $e = new SystemException($e->getMessage());
            $e->set_log_off();
        }
        
        $e->handle_exception($logged);
    }
    else {
        log_message($e->getMessage(), LOG_LEVEL_WARN, true, true, $e->getFile(), $e->getLine(), $e->getTrace());
    }
}


interface MaharaThrowable {
    
    public function render_exception();
    
}

// Standard exceptions  - top level exception class. 
// all exceptions should extend one of these three.

/**
 * Very top of the tree for exceptions in Mahara.
 * Nothing should extend this directly. 
 * Contains a few helper functions for all exceptions.
 */
class MaharaException extends Exception {

    protected $log = true;

    public function get_string() {
        $args = func_get_args();
        if (function_exists('get_string')) {
            $args[0] = strtolower(get_class($this)) . $args[0];
            $args[1] = 'error';
            $str = call_user_func_array('get_string', $args);
            if (strpos($str, '[[') !== 0) {
                return $str;
            }
        }

        $tag = func_get_arg(0);
        $strings = $this->strings();
        if (array_key_exists($tag, $strings)) {
            return $strings[$tag];
        }
        
        return 'An error occured';
    }

    public function get_sitename() {
        if (!function_exists('get_config') || !$sitename = @get_config('sitename')) {
            $sitename = 'Mahara';
        }
        return $sitename;
    }

    public function strings() {
        return array('title' => $this->get_sitename() . ': Site unavailable');
    }

    public function set_log() {
        $this->log = true;
    }

    public function set_log_off() {
        $this->log = false;
    }

    public function render_exception() {
        return $this->getMessage();
    }

    public function handle_exception() {

        if (!empty($this->log)) {
            log_message($message, LOG_LEVEL_WARN, true, true, $this->getFile(), $this->getLine(), $this->getTrace());
        }

        if (defined('JSON')) { // behave differently
            @header('Content-type: text/plain');
            @header('Pragma: no-cache');
            echo json_encode(array('error' => true, 'message' => $this->render_exception()));
            exit;
        }

        $outputtitle = $this->get_string('title');
        $outputmessage = $this->render_exception();
        $message = strip_tags($outputmessage);
        $message = htmlspecialchars_decode($message);

        if (function_exists('smarty')) {
            $smarty = smarty();
            $smarty->assign('title', $outputtitle);
            $smarty->assign('message', $outputmessage);
            $smarty->display('error.tpl');
        }
        else {
    echo <<<EOF
<html>
<head>
    <title>$outputtitle</title>
    <style type="text/css">
        #reason {
            margin: 0 3em;
        }
    </style>
</head>
<body>
EOF;
    if (function_exists('insert_messages')) {
        echo insert_messages();
    }
    echo <<<EOF
<h1>$outputtitle</h1>
$outputmessage
<hr>
</body>
</html>
EOF;
        }
        // end of printing stuff to the screen...
        die();
    }
}






/**
 * SystemException - this is basically a bug in the system.
 */
class SystemException extends MaharaException implements MaharaThrowable {

    public function render_exception () {
        return $this->get_string('message');
    }

    public function strings() {
        return array_merge(parent::strings(), 
                           array('message' => 'A nonrecoverable error occured. '
                                 . 'This probably means you have encountered a bug in the system'));
    }
    
}

/**
 * ConfigException - something is misconfigured that's causing a problem.
 * Generally these will be the fault of admins
 */
class ConfigException extends MaharaException  implements MaharaThrowable {
     
    public function render_exception () {
        return $this->getMessage();
    }

    public function strings() {
        return array_merge(parent::strings(), 
                           array('message' => $this->get_sitename() 
                           . ' is misconfigured and this is causing problems. '
                           . 'You probably need to contact an administrator to get this fixed.  '
                           . ' Details, if any, follow'));
    }
}

/**
 * UserException - the user has done something they shouldn't (or tried to)
 */
class UserException extends MaharaException implements MaharaThrowable {

    protected $log = false;

    public function render_exception() {
        return $this->get_string('message') . '<br><br>' . $this->getMessage();
    }

    public function strings() {
        return array_merge(parent::strings(),  
                           array('message' => 'Something in the way you\'re interacting with ' 
                                 . $this->get_sitename()
                                 .' is causing an error.<br>Details if any, follow'));
    }
}

    

/** 
 * The configuration that Mahara is trying to be run in is insane
 */
class ConfigSanityException extends ConfigException {
    public function strings() {
        return array_merge(parent::strings(), array('message' => ''));
    }
}

/**
 * An SQL related error occured
 */
class SQLException extends SystemException {
    public function handle_exception() {
        if (!empty($GLOBALS['_TRANSACTION_STARTED'])) {
            db_rollback();
        }
        log_warn($this->getMessage());
        parent::handle_exception();
    }
}

/**
 * An exception generated by invalid GET or POST parameters
 */
class ParameterException extends UserException {
    public function strings() {
        return array_merge(parent::strings(), array('message' => 'A required parameter was missing'));
    }
}

/**
 * An exception generated when e-mail can't be sent
 */
class EmailException extends SystemException {}

/** 
 * Exception - artefact not found 
 */
class ArtefactNotFoundException extends UserException {}

/**
 * Exception - view not found
 */
class ViewNotFoundException extends UserException {}

/**
 * Exception - user not found
 */
class UserNotFoundException extends UserException {}

/**
 * Exception - community not found
 */
class CommunityNotFoundException extends UserException {}

/**
 * Exception - anything to do with template parsing
 */
class TemplateParserException extends ConfigException {}

/**
 * Exception - Access denied. Throw this if a user is trying to view something they can't
 */
class AccessDeniedException extends UserException {
    public function strings() {
        return array_merge(parent::strings(), 
                           array('message' => 'You do not have access to view this page',
                                 'title' => 'Access denied'));
    }

    public function render_exception() {
        header("HTTP/1.0 403 Forbidden", true);
        return parent::render_exception();
    }
}


?>
