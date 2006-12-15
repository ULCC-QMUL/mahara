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

defined('INTERNAL') || die();

/**
 * Class to use for installation exceptions
 */
class InstallationException extends SystemException {}


/**
 * This function checks core and plugins for which need to be upgraded/installed
 *
 * @param string $name The name of the plugin to check. If no name is specified,
 *                     all plugins are checked.
 * @return array of objects
 */
function check_upgrades($name=null) {
 
    $pluginstocheck = plugin_types();

    $toupgrade = array();
    $installing = false;
    $disablelogin = false;

    require('version.php');
    if (isset($config->disablelogin) && !empty($config->disablelogin)) {
        $disablelogin = true;
    }
    // check core first...
    if (empty($name) || $name == 'core') {
        try {
            $coreversion = get_config('version');
        } 
        catch (Exception $e) {
            $coreversion = 0;
        }
        if (empty($coreversion)) {
            $core = new StdClass;
            $core->install = true;
            $core->to = $config->version;
            $core->torelease = $config->release;
            $toupgrade['core'] = $core;
            $installing = true;
        } 
        else if ($config->version > $coreversion) {
            $corerelease = get_config('release');
            if (isset($config->minupgradefrom) && isset($config->minupgraderelease) 
                && $coreversion < $config->minupgradefrom) {
                throw new ConfigSanityException("Must upgrade to $config->minupgradefrom "
                                          . "($config->minupgraderelease) first "
                                          . " (you have $coreversion ($corerelease)");
            }
            $core = new StdClass;
            $core->upgrade = true;
            $core->from = $coreversion;
            $core->fromrelease = $corerelease;
            $core->to = $config->version;
            $core->torelease = $config->release;
            $toupgrade['core'] = $core;
        }
    }

    // If we were just checking if the core needed to be upgraded, we can stop here
    if ($name == 'core') {
        $toupgrade['core']->disablelogin = $disablelogin;
        return $toupgrade['core'];
    }

    $plugins = array();
    if (!empty($name)) {
        $plugins[] = explode('.', $name);
    }
    else {
        foreach ($pluginstocheck as $plugin) {
            $dirhandle = opendir(get_config('docroot') . $plugin);
            while (false !== ($dir = readdir($dirhandle))) {
                if (strpos($dir, '.') === 0) {
                    continue;
                }
                if (!is_dir(get_config('docroot') . $plugin . '/' . $dir)) {
                    continue;
                }
                require_once('artefact.php');
                $funname = $plugin . '_check_plugin_sanity';
                if (function_exists($funname)) {
                    try {
                        $funname($dir);
                    }
                    catch (InstallationException $e) {
                        log_warn("Plugin $plugin $dir is not installable: " . $e->GetMessage());
                        continue;
                    }
                }
                $plugins[] = array($plugin, $dir);
            }
        }
    }

    foreach ($plugins as $plugin) {
        $plugintype = $plugin[0];
        $pluginname = $plugin[1];
        $pluginpath = "$plugin[0]/$plugin[1]";
        $pluginkey  = "$plugin[0].$plugin[1]";

        
        // Don't try to get the plugin info if we are installing - it will
        // definitely fail
        $pluginversion = 0;
        if (!$installing) {
            if ($installed = get_record($plugintype . '_installed', 'name', $pluginname)) {
                $pluginversion = $installed->version;
                $pluginrelease =  $installed->release;
            }
        }
            
        require(get_config('docroot') . $pluginpath . '/version.php');
        if (isset($config->disablelogin) && !empty($config->disablelogin)) {
            $disablelogin = true;
        }

        if (empty($pluginversion)) {
            if (empty($installing) && $pluginkey != $name) {
                continue;
            }
            $plugininfo = new StdClass;
            $plugininfo->install = true;
            $plugininfo->to = $config->version;
            $plugininfo->torelease = $config->release;
            $toupgrade[$pluginkey] = $plugininfo;
        }
        else if ($config->version > $pluginversion) {
            if (isset($config->minupgradefrom) && isset($config->minupgraderelease)
                && $pluginversion < $config->minupgradefrom) {
                throw new SanityException("Must upgrade to $config->minupgradefrom "
                                          . " ($config->minupgraderelease) first "
                                          . " (you have $pluginversion ($pluginrelease))");
            }
            if (isset($config->minupgradefrom) && isset($config->minupgraderelease)
                && $pluginversion < $config->minupgradefrom) {
                throw new ConfigSanityException("Must upgrade to $config->minupgradefrom "
                                          . " ($config->minupgraderelease) first "
                                          . " (you have $pluginversion ($pluginrelease))");
            }
            $plugininfo = new StdClass;
            $plugininfo->upgrade = true;
            $plugininfo->from = $pluginversion;
            $plugininfo->fromrelease = $pluginrelease;
            $plugininfo->to = $config->version;
            $plugininfo->torelease = $config->release;
            $toupgrade[$pluginkey] = $plugininfo;
        }
    }

    // if we've just asked for one, don't return an array...
    if (!empty($name) && count($toupgrade) == 1) {
        $upgrade = new StdClass;
        $upgrade->name = $name;
        foreach ((array)$toupgrade[$name] as $key => $value) {
            $upgrade->{$key} = $value;
        }
        $upgrade->disablelogin = $disablelogin;
        return $upgrade;
    }
    $toupgrade['disablelogin'] = $disablelogin;
    if (count($toupgrade) == 1) {
        $toupgrade = array();
    }
    return $toupgrade;
}

/**
 * Upgrades the core system to given upgrade version.
 *
 * @param object $upgrade   The version to upgrade to
 * @return bool             Whether the upgrade succeeded or not
 * @throws SQLException     If the upgrade failed due to a database error
 */
function upgrade_core($upgrade) {
    global $db;

    $location = get_config('libroot') . '/db/';

    db_begin();

    if (!empty($upgrade->install)) {
        if (!install_from_xmldb_file($location . 'install.xml')) {
            throw new SQLException("Failed to upgrade core (check logs)");
        }
    }
    else {
        require_once($location . 'upgrade.php');
        xmldb_core_upgrade($upgrade->from);
    }

    set_config('version', $upgrade->to);
    set_config('release', $upgrade->torelease);
    
    if (!empty($upgrade->install)) {
        core_postinst();
    }

    db_commit();
    return true;
}

/**
 * Upgrades the plugin to a new version
 *
 * @param object $upgrade   Information about the plugin to upgrade
 * @return bool             Whether the upgrade succeeded or not
 * @throws SQLException     If the upgrade failed due to a database error
 */
function upgrade_plugin($upgrade) {
    global $db;

    $plugintype = '';
    $pluginname = '';

    list($plugintype, $pluginname) = explode('.', $upgrade->name);

    $location = get_config('docroot') . $plugintype . '/' . $pluginname . '/db/';
    $db->StartTrans();

    if (!empty($upgrade->install)) {
        if (is_readable($location . 'install.xml')) {
            $status = install_from_xmldb_file($location . 'install.xml');
        }
        else {
            $status = true;
        }
    }
    else {
        if (is_readable($location .  'upgrade.php')) {
            require_once($location . 'upgrade.php');
            $function = 'xmldb_' . $plugintype . '_' . $pluginname . '_upgrade';
            $status = $function($upgrade->from);
        }
        else {
            $status = true;
        }
    }
    if (!$status || $db->HasFailedTrans()) {
        $db->CompleteTrans();
        throw new SQLException("Failed to upgrade $upgrade->name");
    }

    $installed = new StdClass;
    $installed->name = $pluginname;
    $installed->version = $upgrade->to;
    $installed->release = $upgrade->torelease;
    $installtable = $plugintype . '_installed';

    if (!empty($upgrade->install)) {
        insert_record($installtable,$installed);
    } 
    else {
        update_record($installtable, $installed, 'name');
    }

    // postinst stuff...
    safe_require($plugintype, $pluginname);
    $pcname = generate_class_name($plugintype, $pluginname);

    if ($crons = call_static_method($pcname, 'get_cron')) {
        foreach ($crons as $cron) {
            $cron = (object)$cron;
            if (empty($cron->callfunction)) {
                $db->RollbackTrans();
                throw new InstallationException("cron for $pcname didn't supply function name");
            }
            if (!is_callable(array($pcname, $cron->callfunction))) {
                $db->RollbackTrans();
                throw new InstallationException("cron $cron->callfunction for $pcname supplied but wasn't callable");
            }
            $new = false;
            $table = $plugintype . '_cron';
            if (!empty($upgrade->install)) {
                $new = true;
            }
            else if (!record_exists($table, 'plugin', $pluginname, 'callfunction', $cron->callfunction)) {
                $new = true;
            }
            $cron->plugin = $pluginname;
            if (!empty($new)) {
                insert_record($table, $cron);
            }
            else {
                update_record($table, $cron, array('plugin', 'name'));
            }
        }
    }
    
    if ($events = call_static_method($pcname, 'get_event_subscriptions')) {
        foreach ($events as $event) {
            $event = (object)$event;

            if (!record_exists('event', 'name', $event->event)) {
                $db->RollbackTrans();
                throw new InstallationException("event $event->event for $pcname doesn't exist!");
            }
            if (empty($event->callfunction)) {
                $db->RollbackTrans();
                throw new InstallationException("event $event->event for $pcname didn't supply function name");
            }
            if (!is_callable(array($pcname, $event->callfunction))) {
                $db->RollbackTrans();
                throw new InstallationException("event $event->event with function $event->callfunction for $pcname supplied but wasn't callable");
            }
            $exists = false;
            $table = $plugtype . '_event_subscription';
            if (empty($upgrade->install)) {
                $exists = record_exists($table, 'plugin' , $pluginname, 'event', $event->event());
            }
            $event->plugin = $pluginname;
            if (empty($exists)) {
                insert_record($table, $event);
            }
            else {
                update_record($table, $event, array('id', $exists->id));
            }
        }
    }

     // install artefact types
    if ($plugintype == 'artefact') {
        $types = call_static_method($pcname, 'get_artefact_types');
        $ph = array();
        if (is_array($types)) {
            foreach ($types as $type) {
                $ph[] = '?';
                if (!record_exists('artefact_installed_type', 'plugin', $pluginname, 'name', $type)) {
                    $t = new StdClass;
                    $t->name = $type;
                    $t->plugin = $pluginname;
                    insert_record('artefact_installed_type',$t);
                }
            }
            $select = '(plugin = ? AND name NOT IN (' . implode(',', $ph) . '))';
            delete_records_select('artefact_installed_type', $select,
                                  array_merge(array($pluginname),$types));
        }
    }
    
    $prevversion = (empty($upgrade->install)) ? $upgrade->from : 0;
    call_static_method($pcname, 'postinst', $prevversion);
    
    if ($db->HasFailedTrans()) {
        $status = false;
    }
    $db->CompleteTrans();
    
    return $status;

}

function core_postinst() {
    $status = true;
    $pages = site_content_pages();
    $now = db_format_timestamp(time());
    foreach ($pages as $name) {
        $page->name = $name;
        $page->ctime = $now;
        $page->mtime = $now;
        $page->content = get_string($page->name . 'defaultcontent', 'install');
        if (!insert_record('site_content',$page)) {
            $status = false;
        }
    }
    return $status;
}


function core_install_defaults() {
    // Install the default institution
    db_begin();
    $institution = new StdClass;
    $institution->name = 'mahara';
    $institution->displayname = 'No Institution';
    $institution->authplugin  = 'internal';
    insert_record('institution', $institution);
    
    // Insert the root user
    $user = new StdClass;
    $user->id = 0;
    $user->username = 'root';
    $user->password = '*';
    $user->salt = '*';
    $user->institution = 'mahara';
    $user->firstname = 'System';
    $user->lastname = 'User';
    $user->email = 'root@example.org';
    insert_record('usr', $user);

    // Insert the admin user
    $user = new StdClass;
    $user->username = 'admin';
    $user->password = 'mahara';
    $user->institution = 'mahara';
    $user->passwordchange = 1;
    $user->admin = 1;
    $user->firstname = 'Admin';
    $user->lastname = 'User';
    $user->email = 'admin@example.org';
    $user->id = insert_record('usr', $user, 'id', true);
    set_profile_field($user->id, 'email', $user->email);
    set_profile_field($user->id, 'firstname', $user->firstname);
    set_profile_field($user->id, 'lastname', $user->lastname);
    
    require('template.php');
    $exceptions = upgrade_templates(true);
    set_config('installed', true);
    db_commit();
    return $exceptions;
}

function upgrade_templates($continue=false) {

    $exceptions = array();
    $dbtemplates = array();

    // check dataroot first, they get precedence.
    $templates = get_dir_contents(get_config('dataroot') . 'templates/');
    foreach ($templates as $dir) {
        try {
            $dbtemplates[$dir] = template_parse($dir);
        }
        catch (TemplateParserException $e) {
            if (empty($continue)) {
                throw $e;
            }
            $exceptions[] = $e;
        }
    }

    // and now system templates
    $templates = get_dir_contents(get_config('libroot') . 'templates/');
    foreach ($templates as $dir) {
        if (array_key_exists($dir, $dbtemplates)) { // dataroot gets preference
            continue;
        }
        try {
            $dbtemplates[$dir] = template_parse($dir);
        }
        catch (TemplateParserException $e) {
            if (empty($continue)) {
                throw $e;
            }
            $exceptions[] = $e;
        }
    }

    foreach ($dbtemplates as $name => $data) {
        try {
            $ids = upgrade_template($name, $data);
        }
        catch (TemplateParserException $e) {
            if (empty($continue)) {
                throw $e;
            }
            $exceptions[] = $e;
            unset($dbtemplates[$name]);
            continue;
        }
    }

    if (count($dbtemplates) > 0) {
        set_field_select('template', 'deleted', 1, 
                         'name NOT IN (' . implode(',', db_array_to_ph(array_keys($dbtemplates))). ')', 
                         array_keys($dbtemplates));
    }
    else {
        set_field('template', 'deleted', 1);
    }


    return $exceptions;
}

/**
 * This function upgrades or installs an individual template.
 *
 * @param $name the template name
 * @param $data what you would get from template_parse 
 */
function upgrade_template($name, $data) {
    if (!is_readable($data['location'] . 'config.php')) {
        $e = new TemplateParserException("missing config.php for template $name");
        if (empty($continue)) {
            throw $e;
        }
        $exceptions[] = $e;
        continue;
    }
    require_once($data['location'] . 'config.php');
    $fordb = new StdClass;
    $fordb->name = $name;
    $fordb->mtime = db_format_timestamp(time());
    $fordb->title = $template->title;
    $fordb->description = $template->description;
    $fordb->category = $template->category;
    $fordb->mtime = db_format_timestamp(time());
    $fordb->cacheddata = serialize($data['parseddata']);
    if (isset($data['thumbnail'])) {
        $fordb->thumbnail = 1;
    }
    if (isset($template->owner)) {
        $fordb->owner = $template->owner;
    }
    else {
        $fordb->owner = 0; // root user
    }
    if (record_exists('template', 'name', $name)) {
        update_record('template', $fordb, 'name');
    }
    else {
        $fordb->ctime = $fordb->mtime;
        insert_record('template', $fordb);
    }
}


?>
