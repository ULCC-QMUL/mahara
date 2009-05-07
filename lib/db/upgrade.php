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
 *
 */

defined('INTERNAL') || die();

function xmldb_core_upgrade($oldversion=0) {
    ini_set('max_execution_time', 120); // Let's be safe
    raise_memory_limit('64M');

    $status = true;

    // We discovered that username case insensitivity was not being enforced at 
    // most of the entry points to the system at which users can be created. 
    // This problem manifested itself as users who had the same LOWER(username) 
    // as another not being able to log in. The fix is to implement the checks, 
    // rename the "duplicate" users and add a constraint on the database so it 
    // can't happen again
    if ($oldversion < 2008040202) {
        $renamed = $newusernames = $oldusernames = array();
        $allusers = get_records_array('usr', '', '', 'id', 'id, username');

        $usernamemapping = array();
        foreach ($allusers as $user) {
            $oldusernames[] = $user->username;
            $usernamemapping[strtolower($user->username)][] = array('id' => $user->id, 'username' => $user->username);
        }

        foreach ($usernamemapping as $lcname => $users) {
            if (count($users) == 1) {
                continue;
            }

            // Uhohes. Rename the user(s) who were created last
            $skippedfirst = false;
            foreach ($users as $user) {
                if (!$skippedfirst) {
                    $skippedfirst = true;
                    continue;
                }

                $userobj = new User();
                $userobj->find_by_id($user['id']);

                // Append digits keeping total length <= 30
                $i = 1;
                $newname = substr($user['username'], 0, 29) . $i;
                while (isset($newusernames[$newname]) || isset($oldusernames[$newname])) {
                    $i++;
                    $newname = substr($user['username'], 0, 30 - floor(log10($i)+1)) . $i;
                }
                set_field('usr', 'username', $newname, 'id', $user['id']);
                $newusernames[$newname] = true;

                $renamed[$newname] = $userobj;
                log_debug(" * Renamed {$user['username']} to $newname");
            }
        }

        if (!empty($renamed)) {
            // Notify changed usernames to administrator
            $report = '# Each line in this file is in the form "old_username new_username"'."\n";
            $message = "Mahara now requires usernames to be unique, case insensitively.\n";
            $message .= "Some usernames on your site were changed during the upgrade:\n\n";
            foreach ($renamed as $newname => $olduser) {
                $report .= "$olduser->username $newname\n";
                $message .= "Old username: $olduser->username\n"
                    . "New username: $newname\n\n";
            }
            $sitename = get_config('sitename');
            $file = get_config('dataroot') . 'user_migration_report_2.txt';
            if (file_put_contents($file, $report)) {
                $message .= "\n" . 'A copy of this list has been saved to the file ' . $file;
            }
            global $USER;
            email_user($USER, null, $sitename . ': User migration', $message);
            // Notify changed usernames to users
            $usermessagestart = "Your username at $sitename has been changed:\n\n";
            $usermessageend = "\n\nNext time you visit the site, please login using your new username.";
            foreach ($renamed as $newname => $olduser) {
                if ($olduser->email == '') {
                    continue;
                }
                log_debug("Attempting to notify $newname ($olduser->email) of their new username...");
                email_user($olduser, null, $sitename . ': User name changed', $usermessagestart
                           . "Old username: $olduser->username\nNew username: $newname"
                           . $usermessageend);
            }
        }

        // Now we know all usernames are unique over their lowercase values, we 
        // can put an index in so data doesn't get all inconsistent next time
        if (is_postgres()) {
            execute_sql('DROP INDEX {usr_use_uix}');
            execute_sql('CREATE UNIQUE INDEX {usr_use_uix} ON {usr}(LOWER(username))');
        }
        else {
            // MySQL cannot create indexes over functions of columns. Too bad 
            // for it. We won't drop the existing index because that offers a 
            // large degree of protection, but when MySQL finally supports this 
            // we will be able to add it
        }


        // Install a cron job to delete old session files
        $cron = new StdClass;
        $cron->callfunction = 'auth_remove_old_session_files';
        $cron->minute       = '30';
        $cron->hour         = '20';
        $cron->day          = '*';
        $cron->month        = '*';
        $cron->dayofweek    = '*';
        insert_record('cron', $cron);
    }

    if ($oldversion < 2008040203) {
        // Install a cron job to recalculate user quotas
        $cron = new StdClass;
        $cron->callfunction = 'recalculate_quota';
        $cron->minute       = '15';
        $cron->hour         = '2';
        $cron->day          = '*';
        $cron->month        = '*';
        $cron->dayofweek    = '*';
        insert_record('cron', $cron);
    }

    if ($oldversion < 2008040204) {
        if (field_exists(new XMLDBTable('usr_friend_request'), new XMLDBField('reason'))) {
            if (is_postgres()) {
                execute_sql('ALTER TABLE {usr_friend_request} RENAME COLUMN reason TO message');
            }
            else if (is_mysql()) {
                execute_sql('ALTER TABLE {usr_friend_request} CHANGE reason message TEXT');
            }
        }
    }

    if ($oldversion < 2008080400) {
        // Group type refactor
        log_debug('GROUP TYPE REFACTOR');

        execute_sql('ALTER TABLE {group} ADD grouptype CHARACTER VARYING(20)');
        execute_sql('ALTER TABLE {group_member} ADD role CHARACTER VARYING(255)');

        $groups = get_records_array('group');
        if ($groups) {
            require_once(get_config('docroot') . 'grouptype/lib.php');
            require_once(get_config('docroot') . 'grouptype/standard/lib.php');
            require_once(get_config('docroot') . 'grouptype/course/lib.php');
            foreach ($groups as $group) {
                log_debug("Migrating group {$group->name} ({$group->id})");

                // Establish the new group type
                if ($group->jointype == 'controlled') {
                    $group->grouptype = 'course';
                }
                else {
                    $group->grouptype = 'standard';
                }

                execute_sql('UPDATE {group} SET grouptype = ? WHERE id = ?', array($group->grouptype, $group->id));
                log_debug(' * new group type is ' . $group->grouptype);

                // Convert group membership information to roles
                foreach (call_static_method('GroupType' . $group->grouptype, 'get_roles') as $role) {
                    if ($role == 'admin') {
                        // It would be nice to use ensure_record_exists here, 
                        // but because ctime is not null we have to provide it 
                        // as data, which means the ctime would be updated if 
                        // the record _did_ exist
                        if (get_record('group_member', 'group', $group->id, 'member', $group->owner)) {
                            execute_sql("UPDATE {group_member}
                                SET role = 'admin'
                                WHERE \"group\" = ?
                                AND member = ?", array($group->id, $group->owner));
                        }
                        else {
                            // In old versions of Mahara, there did not need to 
                            // be a record in the group_member table for the 
                            // owner
                            $data = (object) array(
                                'group'  => $group->id,
                                'member' => $group->owner,
                                'ctime'  => db_format_timestamp(time()),
                                'role' => 'admin',
                            );
                            insert_record('group_member', $data);
                        }
                        log_debug(" * marked user {$group->owner} as having the admin role");
                    }
                    else {
                        // Setting role instances for tutors and members
                        $tutorflag = ($role == 'tutor') ? 1 : 0;
                        execute_sql('UPDATE {group_member}
                            SET role = ?
                            WHERE "group" = ?
                            AND member != ?
                            AND tutor = ?', array($role, $group->id, $group->owner, $tutorflag));
                        log_debug(" * marked appropriate users as being {$role}s");
                    }
                }
            }
        }


        if (is_postgres()) {
            execute_sql('ALTER TABLE {group} ALTER grouptype SET NOT NULL');
            execute_sql('ALTER TABLE {group_member} ALTER role SET NOT NULL');
        }
        else if (is_mysql()) {
            execute_sql('ALTER TABLE {group} MODIFY grouptype CHARACTER VARYING(20) NOT NULL');
            execute_sql('ALTER TABLE {group_member} MODIFY role CHARACTER VARYING(255) NOT NULL');
        }

        if (is_mysql()) {
            execute_sql('ALTER TABLE {group} DROP FOREIGN KEY {grou_own_fk}');
        }

        execute_sql('ALTER TABLE {group} DROP owner');
        execute_sql('ALTER TABLE {group_member} DROP tutor');


        // Adminfiles become "institution-owned artefacts"
        execute_sql("ALTER TABLE {artefact} ADD COLUMN institution CHARACTER VARYING(255);");

        if (is_postgres()) {
            execute_sql("ALTER TABLE {artefact} ALTER COLUMN owner DROP NOT NULL;");
        }
        else if (is_mysql()) {
            execute_sql("ALTER TABLE {artefact} MODIFY owner BIGINT(10) NULL;");
        }

        execute_sql("ALTER TABLE {artefact} ADD CONSTRAINT {arte_ins_fk} FOREIGN KEY (institution) REFERENCES {institution}(name);");
        execute_sql("UPDATE {artefact} SET institution = 'mahara', owner = NULL WHERE id IN (SELECT artefact FROM {artefact_file_files} WHERE adminfiles = 1)");
        execute_sql("ALTER TABLE {artefact_file_files} DROP COLUMN adminfiles");
        execute_sql('ALTER TABLE {artefact} ADD COLUMN "group" BIGINT');
        execute_sql('ALTER TABLE {artefact} ADD CONSTRAINT {arte_gro_fk} FOREIGN KEY ("group") REFERENCES {group}(id)');


        // New artefact permissions for use with group-owned artefacts
        execute_sql('CREATE TABLE {artefact_access_role} (
            role VARCHAR(255) NOT NULL,
            artefact INTEGER NOT NULL REFERENCES {artefact}(id),
            can_view SMALLINT NOT NULL,
            can_edit SMALLINT NOT NULL,
            can_republish SMALLINT NOT NULL
        );');
        execute_sql('CREATE TABLE {artefact_access_usr} (
            usr INTEGER NOT NULL REFERENCES {usr}(id),
            artefact INTEGER NOT NULL REFERENCES {artefact}(id),
            can_republish SMALLINT
        );');


        // grouptype tables
        execute_sql("CREATE TABLE {grouptype} (
            name VARCHAR(20) PRIMARY KEY,
            submittableto SMALLINT NOT NULL,
            defaultrole VARCHAR(255) NOT NULL DEFAULT 'member'
        );");
        execute_sql("INSERT INTO {grouptype} (name,submittableto) VALUES ('standard',0)");
        execute_sql("INSERT INTO {grouptype} (name,submittableto) VALUES ('course',1)");

        execute_sql('CREATE TABLE {grouptype_roles} (
            grouptype VARCHAR(20) NOT NULL REFERENCES {grouptype}(name),
            edit_views SMALLINT NOT NULL DEFAULT 1,
            see_submitted_views SMALLINT NOT NULL DEFAULT 0,
            role VARCHAR(255) NOT NULL
        );');
        execute_sql("INSERT INTO {grouptype_roles} (grouptype,edit_views,see_submitted_views,role) VALUES ('standard',1,0,'admin')");
        execute_sql("INSERT INTO {grouptype_roles} (grouptype,edit_views,see_submitted_views,role) VALUES ('standard',1,0,'member')");
        execute_sql("INSERT INTO {grouptype_roles} (grouptype,edit_views,see_submitted_views,role) VALUES ('course',1,0,'admin')");
        execute_sql("INSERT INTO {grouptype_roles} (grouptype,edit_views,see_submitted_views,role) VALUES ('course',1,1,'tutor')");
        execute_sql("INSERT INTO {grouptype_roles} (grouptype,edit_views,see_submitted_views,role) VALUES ('course',0,0,'member')");

        if (is_postgres()) {
            $table = new XMLDBTable('group');
            $key = new XMLDBKey('grouptypefk');
            $key->setAttributes(XMLDB_KEY_FOREIGN, array('grouptype'), 'grouptype', array('name'));
            add_key($table, $key);
        }
        else if (is_mysql()) {
            // Seems to refuse to create foreign key, not sure why yet
            execute_sql("ALTER TABLE {group} ADD INDEX {grou_gro_ix} (grouptype);");
            // execute_sql("ALTER TABLE {group} ADD CONSTRAINT {grou_gro_fk} FOREIGN KEY (grouptype) REFERENCES {grouptype} (name);");
        }


        // Group views
        execute_sql('ALTER TABLE {view} ADD COLUMN "group" BIGINT');
        execute_sql('ALTER TABLE {view} ADD CONSTRAINT {view_gro_fk} FOREIGN KEY ("group") REFERENCES {group}(id)');
        if (is_postgres()) {
            execute_sql('ALTER TABLE {view} ALTER COLUMN owner DROP NOT NULL');
            execute_sql('ALTER TABLE {view} ALTER COLUMN ownerformat DROP NOT NULL');
        }
        else if (is_mysql()) {
            execute_sql('ALTER TABLE {view} MODIFY owner BIGINT(10) NULL');
            execute_sql('ALTER TABLE {view} MODIFY ownerformat TEXT NULL');
        }
        execute_sql('ALTER TABLE {view_access_group} ADD COLUMN role VARCHAR(255)');
        execute_sql("UPDATE {view_access_group} SET role = 'tutor' WHERE tutoronly = 1");
        execute_sql('ALTER TABLE {view_access_group} DROP COLUMN tutoronly');


        // grouptype plugin tables
        $table = new XMLDBTable('grouptype_installed');
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('version', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('release', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL);
        $table->addFieldInfo('active', XMLDB_TYPE_INTEGER,  1, null, XMLDB_NOTNULL, null, null, null, 1);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('name'));
        create_table($table);
       
        $table = new XMLDBTable('grouptype_cron');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('minute', XMLDB_TYPE_CHAR, 25, null, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('hour', XMLDB_TYPE_CHAR, 25, null, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('day', XMLDB_TYPE_CHAR, 25, null, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('dayofweek', XMLDB_TYPE_CHAR, 25, null, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('month', XMLDB_TYPE_CHAR, 25, null, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('nextrun', XMLDB_TYPE_DATETIME, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'callfunction'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'grouptype_installed', array('name'));
        create_table($table); 

        $table = new XMLDBTable('grouptype_config');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 100, null, XMLDB_NOTNULL);
        $table->addFieldInfo('field', XMLDB_TYPE_CHAR, 100, null, XMLDB_NOTNULL);
        $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'field'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'grouptype_installed', array('name'));
        create_table($table);

        $table = new XMLDBTable('grouptype_event_subscription');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, 
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('event', XMLDB_TYPE_CHAR, 50, null, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'grouptype_installed', array('name'));
        $table->addKeyInfo('eventfk', XMLDB_KEY_FOREIGN, array('event'), 'event_type', array('name'));
        $table->addKeyInfo('subscruk', XMLDB_KEY_UNIQUE, array('plugin', 'event', 'callfunction'));
        create_table($table);

        if ($data = check_upgrades('grouptype.standard')) {
            upgrade_plugin($data);
        }
        if ($data = check_upgrades('grouptype.course')) {
            upgrade_plugin($data);
        }


        // Group invitations take a role
        execute_sql('ALTER TABLE {group_member_invite} ADD COLUMN role VARCHAR(255)');


    }

    if ($oldversion < 2008081101) {
        execute_sql("ALTER TABLE {view} ADD COLUMN institution CHARACTER VARYING(255);");
        execute_sql("ALTER TABLE {view} ADD CONSTRAINT {view_ins_fk} FOREIGN KEY (institution) REFERENCES {institution}(name);");
        execute_sql("ALTER TABLE {view} ADD COLUMN template SMALLINT NOT NULL DEFAULT 0;");
    }

    if ($oldversion < 2008081102) {
        execute_sql("ALTER TABLE {view} ADD COLUMN copynewuser SMALLINT NOT NULL DEFAULT 0;");
        execute_sql('CREATE TABLE {view_autocreate_grouptype} (
            view INTEGER NOT NULL REFERENCES {view}(id),
            grouptype VARCHAR(20) NOT NULL REFERENCES {grouptype}(name)
        );');
    }

    if ($oldversion < 2008090100) {
        $table = new XMLDBTable('import_queue');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('host', XMLDB_TYPE_CHAR, 255, null, XMLDB_NOTNULL);
        $table->addFieldInfo('usr', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('queue', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, '1');
        $table->addFieldInfo('ready', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('expirytime', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('format', XMLDB_TYPE_CHAR, 50, null, null);
        $table->addFieldInfo('data', XMLDB_TYPE_TEXT, 'large', null, null);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, 40, null, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('usrfk', XMLDB_KEY_FOREIGN, array('usr'), 'usr', array('id'));
        $table->addKeyInfo('hostfk', XMLDB_KEY_FOREIGN, array('host'), 'host', array('wwwroot'));

        create_table($table);
        // Install a cron job to process the queue
        $cron = new StdClass;

        $cron->callfunction = 'import_process_queue';
        $cron->minute       = '*/5';
        $cron->hour         = '*';
        $cron->day          = '*';
        $cron->month        = '*';
        $cron->dayofweek    = '*';
        insert_record('cron', $cron);
    }

    if ($oldversion < 2008090800) {
        $table = new XMLDBTable('artefact_log');
        $table->addFieldInfo('artefact', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL);
        $table->addFieldInfo('usr', XMLDB_TYPE_INTEGER, 10, null, null);
        $table->addFieldInfo('time', XMLDB_TYPE_DATETIME, null, null, XMLDB_NOTNULL);
        $table->addFieldInfo('title', XMLDB_TYPE_TEXT, null);
        $table->addFieldInfo('description', XMLDB_TYPE_TEXT, null);
        $table->addFieldInfo('parent', XMLDB_TYPE_INTEGER, 10, null, null);
        $table->addFieldInfo('created', XMLDB_TYPE_INTEGER, 1, null, null);
        $table->addFieldInfo('deleted', XMLDB_TYPE_INTEGER, 1, null, null);
        $table->addFieldInfo('edited', XMLDB_TYPE_INTEGER, 1, null, null);
        $table->addIndexInfo('artefactix', XMLDB_INDEX_NOTUNIQUE, array('artefact'));
        $table->addKeyInfo('usrfk', XMLDB_KEY_FOREIGN, array('usr'), 'usr', array('id'));
        create_table($table);
    }

    if ($oldversion < 2008091500) {
        // NOTE: Yes, this number is bigger than the number for the next upgrade
        // The next upgrade got committed first. It deletes all users properly, 
        // but the usr table has a 30 character limit on username, which can be 
        // violated when people with long usernames are deleted
        $table = new XMLDBTable('usr');
        $field = new XMLDBField('username');
        $field->setAttributes(XMLDB_TYPE_CHAR, 100, null, XMLDB_NOTNULL);
        change_field_precision($table, $field);
    }

    if ($oldversion < 2008091200) {
        // Some cleanups for deleted users, based on the new model of handling them
        if ($userids = get_column('usr', 'id', 'deleted', 1)) {
            foreach ($userids as $userid) {
                // We want to append 'deleted.timestamp' to some unique fields in the usr 
                // table, so they can be reused by new accounts
                $fieldstomunge = array('username', 'email');
                $datasuffix = '.deleted.' . time();

                $user = get_record('usr', 'id', $userid, null, null, null, null, implode(', ', $fieldstomunge));

                $deleterec = new StdClass;
                $deleterec->id = $userid;
                $deleterec->deleted = 1;
                foreach ($fieldstomunge as $field) {
                    if (!preg_match('/\.deleted\.\d+$/', $user->$field)) {
                        $deleterec->$field = $user->$field . $datasuffix;
                    }
                }

                // Set authinstance to default internal, otherwise the old authinstance can be blocked from deletion
                // by deleted users.
                $authinst = get_field('auth_instance', 'id', 'institution', 'mahara', 'instancename', 'internal');
                if ($authinst) {
                    $deleterec->authinstance = $deleterec->lastauthinstance = $authinst;
                }

                update_record('usr', $deleterec);

                // Because the user is being deleted, but their email address may be wanted 
                // for a new user, we change their email addresses to add 
                // 'deleted.[timestamp]'
                execute_sql("UPDATE {artefact_internal_profile_email}
                             SET email = email || ?
                             WHERE owner = ? AND NOT email LIKE '%.deleted.%'", array($datasuffix, $userid));

                // Remove remote user records
                delete_records('auth_remote_user', 'localusr', $userid);
            }
        }
    }

    if ($oldversion < 2008091601) {
        $table = new XMLDBTable('event_subscription');
        if (!table_exists($table)) {
            $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED,
                XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
            $table->addFieldInfo('event', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $table->addFieldInfo('callfunction',  XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);

            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->addKeyInfo('eventfk', XMLDB_KEY_FOREIGN, array('event'), 'event_type', array('name'));
            $table->addKeyInfo('subscruk', XMLDB_KEY_UNIQUE, array('event', 'callfunction'));

            create_table($table);
 
            insert_record('event_subscription', (object)array('event' => 'createuser', 'callfunction' => 'activity_set_defaults'));

            $table = new XMLDBTable('view_type');
            $table->addFieldInfo('type', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('type'));

            create_table($table);

            $viewtypes = array('portfolio', 'profile');
            foreach ($viewtypes as $vt) {
                insert_record('view_type', (object)array(
                    'type' => $vt,
                ));
            }

            $table = new XMLDBTable('blocktype_installed_viewtype');
            $table->addFieldInfo('blocktype', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $table->addFieldInfo('viewtype', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('blocktype', 'viewtype'));
            $table->addKeyInfo('blocktypefk', XMLDB_KEY_FOREIGN, array('blocktype'), 'blocktype_installed', array('name'));
            $table->addKeyInfo('viewtypefk', XMLDB_KEY_FOREIGN, array('viewtype'), 'view_type', array('type'));

            create_table($table);

            $table = new XMLDBTable('view');
            $field = new XMLDBField('type');
            $field->setAttributes(XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, null);
            add_field($table, $field);
            $key = new XMLDBKey('typefk');
            $key->setAttributes(XMLDB_KEY_FOREIGN, array('type'), 'view_type', array('type'));
            add_key($table, $key);
            set_field('view', 'type', 'portfolio');
            $field->setAttributes(XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
            change_field_notnull($table, $field);

            if ($blocktypes = plugins_installed('blocktype', true)) {
                foreach ($blocktypes as $bt) {
                    install_blocktype_viewtypes_for_plugin(blocktype_single_to_namespaced($bt->name, $bt->artefactplugin));
                }
            }
        }
    }

    if ($oldversion < 2008091603) {
        foreach(array('myviews', 'mygroups', 'myfriends', 'wall') as $blocktype) {
            $data = check_upgrades("blocktype.$blocktype");
            if ($data) {
                upgrade_plugin($data);
            }
        }
        if (!get_record('view', 'owner', 0, 'type', 'profile')) {
            // First ensure system user has id = 0; In older MySQL installations it may be > 0
            $sysuser = get_record('usr', 'username', 'root');
            if ($sysuser && $sysuser->id > 0 && !count_records('usr', 'id', 0)) {
                set_field('usr', 'id', 0, 'id', $sysuser->id);
            }
            // Install system profile view
            require_once(get_config('libroot') . 'view.php');
            $dbtime = db_format_timestamp(time());
            $viewdata = (object) array(
                'type'        => 'profile',
                'owner'       => 0,
                'numcolumns'  => 2,
                'ownerformat' => FORMAT_NAME_PREFERREDNAME,
                'title'       => get_string('profileviewtitle', 'view'),
                'description' => '',
                'template'    => 1,
                'ctime'       => $dbtime,
                'atime'       => $dbtime,
                'mtime'       => $dbtime,
            );
            $id = insert_record('view', $viewdata, 'id', true);
            $accessdata = (object) array('view' => $id, 'accesstype' => 'loggedin');
            insert_record('view_access', $accessdata);
            $blocktypes = array('myviews' => 1, 'mygroups' => 1, 'myfriends' => 2, 'wall' => 2);  // column ids
            $installed = get_column_sql('SELECT name FROM {blocktype_installed} WHERE name IN (' . join(',', array_map('db_quote', array_keys($blocktypes))) . ')');
            $weights = array(1 => 0, 2 => 0);
            foreach (array_keys($blocktypes) as $blocktype) {
                if (in_array($blocktype, $installed)) {
                    $weights[$blocktypes[$blocktype]]++;
                    insert_record('block_instance', (object) array(
                        'blocktype'  => $blocktype,
                        'title'      => get_string('title', 'blocktype.' . $blocktype),
                        'view'       => $id,
                        'column'     => $blocktypes[$blocktype],
                        'order'      => $weights[$blocktypes[$blocktype]],
                    ));
                }
            }
        }
    }

    if ($oldversion < 2008091604) {
        $table = new XMLDBTable('usr');
        $field = new XMLDBField('lastlastlogin');
        $field->setAttributes(XMLDB_TYPE_DATETIME, null, null);
        add_field($table, $field);
    }

    if ($oldversion < 2008092000) {
        $table = new XMLDBTable('usr');
        $field = new XMLDBField('lastaccess');
        $field->setAttributes(XMLDB_TYPE_DATETIME, null, null);
        add_field($table, $field);
    }

    // The previous upgrade forces the user to be logged out.  The
    // next upgrade should probably set disablelogin = false and
    // minupgradefrom = 2008092000 in version.php.

    if ($oldversion < 2008101500) {
        // Remove event subscription for new user accounts to have a default 
        // profile view created, they're now created on demand
        execute_sql("DELETE FROM {event_subscription} WHERE event = 'createuser' AND callfunction = 'install_default_profile_view';");
    }

    if ($oldversion < 2008101602) {
        // Move artefact/internal/profileicons directory to artefact/file
        set_field('artefact_installed_type', 'plugin', 'file', 'name', 'profileicon');
        set_field('artefact_config', 'plugin', 'file', 'field', 'profileiconwidth');
        set_field('artefact_config', 'plugin', 'file', 'field', 'profileiconheight');

        $artefactdata = get_config('dataroot') . 'artefact/';
        if (is_dir($artefactdata . 'internal/profileicons')) {
            if (!is_dir($artefactdata . 'file')) {
                mkdir($artefactdata . 'file');
            }
            if (!rename($artefactdata . 'internal/profileicons', $artefactdata . 'file/profileicons')) {
                throw new SystemException("Failed moving $artefactdata/internal/profileicons to $artefactdata/file/profileicons");
            }

            // Insert artefact_file_files records for all profileicons
            $profileicons = get_column('artefact', 'id', 'artefacttype', 'profileicon');
            if ($profileicons) {
                foreach ($profileicons as $a) {
                    $filename = $artefactdata . 'file/profileicons/originals/' . ($a % 256) . '/' . $a;
                    if (file_exists($filename)) {
                        $filesize = filesize($filename);
                        $imagesize = getimagesize($artefactdata . 'file/profileicons/originals/' . ($a % 256) . '/' . $a);
                        insert_record('artefact_file_files', (object) array('artefact' => $a, 'fileid' => $a, 'size' => $filesize));
                        insert_record('artefact_file_image', (object) array('artefact' => $a, 'width' => $imagesize[0], 'height' => $imagesize[1]));
                    } else {
                        log_debug("Profile icon artefact $a has no file on disk at $filename");
                    }
                }
            }
        }
    }

    if ($oldversion < 2008102200) {
        $table = new XMLDBTable('view_access_token');
        $table->addFieldInfo('view', XMLDB_TYPE_INTEGER, 10, false, XMLDB_NOTNULL);
        $table->addFieldInfo('token', XMLDB_TYPE_CHAR, 100, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('startdate', XMLDB_TYPE_DATETIME, null, null);
        $table->addFieldInfo('stopdate', XMLDB_TYPE_DATETIME, null, null);
        $table->addKeyInfo('viewfk', XMLDB_KEY_FOREIGN, array('view'), 'view', array('id'));
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('token'));
        create_table($table);
    }

    if ($oldversion < 2008102400) {
        // Feedback can be left by anon users with a view token, so feedback author must be nullable
        $table = new XMLDBTable('view_feedback');
        if (is_mysql()) {
            execute_sql("ALTER TABLE {view_feedback} DROP FOREIGN KEY {viewfeed_aut_fk}");
            execute_sql('ALTER TABLE {view_feedback} MODIFY author BIGINT(10) NULL');
        }
        else {
            $field = new XMLDBField('author');
            $field->setAttributes(XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED);
            change_field_notnull($table, $field);
        }
        $key = new XMLDBKEY('authorfk');
        $key->setAttributes(XMLDB_KEY_FOREIGN, array('author'), 'usr', array('id'));
        add_key($table, $key);

        $table = new XMLDBTable('artefact_feedback');
        if (is_mysql()) {
            execute_sql("ALTER TABLE {artefact_feedback} DROP FOREIGN KEY {artefeed_aut_fk}");
            execute_sql('ALTER TABLE {artefact_feedback} MODIFY author BIGINT(10) NULL');
        }
        else {
            $field = new XMLDBField('author');
            $field->setAttributes(XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED);
            change_field_notnull($table, $field);
        }
        $key = new XMLDBKEY('authorfk');
        $key->setAttributes(XMLDB_KEY_FOREIGN, array('author'), 'usr', array('id'));
        add_key($table, $key);

        table_column('view_feedback', null, 'authorname', 'text', null, null, null, '');
        table_column('artefact_feedback', null, 'authorname', 'text', null, null, null, '');
    }

    if ($oldversion < 2008110700) {
        $table = new XMLDBTable('group');
        $field = new XMLDBField('public');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $field);

        set_config('createpublicgroups', 'admins');
    }

    if ($oldversion < 2008111102) {
        set_field('grouptype_roles', 'see_submitted_views', 1, 'grouptype', 'course', 'role', 'admin');
    }

    if ($oldversion < 2008111200) {
        // Event subscription for auto adding users to groups
        insert_record('event_subscription', (object)array('event' => 'createuser', 'callfunction' => 'add_user_to_autoadd_groups'));

        $table = new XMLDBTable('group');
        $field = new XMLDBField('usersautoadded');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $field);
    }

    if ($oldversion < 2008111201) {
        $event = (object)array(
            'name' => 'userjoinsgroup',
        );
        ensure_record_exists('event_type', $event, $event);
    }

    if ($oldversion < 2008110400) {
        // Correct capitalisation of internal authinstance for 'no institution', only if it hasn't changed previously
        execute_sql("UPDATE {auth_instance} SET instancename = 'Internal' WHERE institution = 'mahara' AND authname = 'internal' AND instancename = 'internal'");
    }

    if ($oldversion < 2008121500) {
        // Make sure the system profile view is marked as a template and is 
        // allowed to be copied by everyone
        require_once('view.php');
        execute_sql("UPDATE {view} SET template = 1 WHERE owner = 0 AND type = 'profile'");
        $view = new View(get_field('view', 'id', 'owner', 0, 'type', 'profile'));
        $view->set_access(array(array(
            'type' => 'loggedin'
        )));
    }

    if ($oldversion < 2008122300) {
        // Delete all activity_queue entries older than 2 weeks. Designed to 
        // prevent total spammage caused by the activity queue processing bug
        delete_records_select('activity_queue', 'ctime < ?', array(db_format_timestamp(time() - (86400 * 14))));
    }

    if ($oldversion < 2009011500) {
        // Make the "port" column larger so it can handle any port number
        $table = new XMLDBTable('host');
        $field = new XMLDBField('portno');
        $field->setAttributes(XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null, null, 80);
        change_field_precision($table, $field);
    }

    if ($oldversion < 2009021600) {
        // Add constraints on view and artefact tables to make sure that of the 
        // owner/group/institution fields, only one is set at any given time

        // First, we make blind assumptions in order to tweak the data into 
        // being valid. In theory, there shouldn't be much danger because most 
        // people will upgrade from 1.0 to 1.1, and thus never have invalid 
        // data in their tables.
        execute_sql('UPDATE {artefact} SET owner = NULL WHERE institution IS NOT NULL');
        execute_sql('UPDATE {artefact} SET "group" = NULL WHERE institution IS NOT NULL');
        execute_sql('UPDATE {artefact} SET owner = NULL WHERE "group" IS NOT NULL');
        execute_sql('UPDATE {view} SET owner = NULL WHERE institution IS NOT NULL');
        execute_sql('UPDATE {view} SET "group" = NULL WHERE institution IS NOT NULL');
        execute_sql('UPDATE {view} SET owner = NULL WHERE "group" IS NOT NULL');

        // Now add the constraints. MySQL parses check constraints but doesn't 
        // actually apply them. So these protections will only apply if you use 
        // Postgres. You did read the installation instruction's 
        // recommendations that you use postgres, didn't you?
        execute_sql('ALTER TABLE {artefact} ADD CHECK (
            (owner IS NOT NULL AND "group" IS NULL     AND institution IS NULL) OR
            (owner IS NULL     AND "group" IS NOT NULL AND institution IS NULL) OR
            (owner IS NULL     AND "group" IS NULL     AND institution IS NOT NULL)
        )');
        execute_sql('ALTER TABLE {view} ADD CHECK (
            (owner IS NOT NULL AND "group" IS NULL     AND institution IS NULL) OR
            (owner IS NULL     AND "group" IS NOT NULL AND institution IS NULL) OR
            (owner IS NULL     AND "group" IS NULL     AND institution IS NOT NULL)
        )');
    }

    if ($oldversion < 2009021700) {
        reload_html_filters();
    }

    if ($oldversion < 2009021701) {
        // Make sure that all views that can be copied have loggedin access
        // This upgrade just fixes potentially corrupt data caused by running a 
        // beta version then upgrading it
        if ($views = get_column('view', 'id', 'copynewuser', '1')) {
            $views[] = 1;
            require_once('view.php');
            foreach ($views as $viewid) {
                $view = new View($viewid);
                $needsadding = true;
                foreach ($view->get_access() as $item) {
                    if ($item['type'] == 'loggedin') {
                        // We're not checking that access dates are null (aka 
                        // it can always be accessed), but the chance of people 
                        // needing this upgrade are slim anyway
                        $needsadding = false;
                        break;
                    }
                }

                if ($needsadding) {
                    log_debug("Adding logged in access for view $viewid");
                    $access = $view->get_access();
                    $access[] = array(
                        'type' => 'loggedin',
                        'startdate' => null,
                        'stopdate'  => null,
                    );
                    $view->set_access($access);
                }
            }
        }
    }

    if ($oldversion < 2009021900) {
        // Generate a unique installation key
        set_config('installation_key', get_random_key());
    }

    if ($oldversion < 2009021901) {
        // Insert a cron job to send registration data to mahara.org
        $cron = new StdClass;
        $cron->callfunction = 'cron_send_registration_data';
        $cron->minute       = rand(0, 59);
        $cron->hour         = rand(0, 23);
        $cron->day          = '*';
        $cron->month        = '*';
        $cron->dayofweek    = rand(0, 6);
        insert_record('cron', $cron);
    }

    if ($oldversion < 2009022700) {
        // Get rid of all blocks with position 0 caused by 'about me' block on profile views
        if (count_records('block_instance', 'order', 0) && !count_records_select('block_instance', '"order" < 0')) {
            if (is_mysql()) {
                $ids = get_column_sql('
                    SELECT i.id FROM {block_instance} i
                    INNER JOIN (SELECT view, "column" FROM {block_instance} WHERE "order" = 0) z
                        ON (z.view = i.view AND z.column = i.column)'
                );
                execute_sql('UPDATE {block_instance} SET "order" =  -1 * "order" WHERE id IN (' . join(',', $ids) . ')');
            } else {
                execute_sql('UPDATE {block_instance} SET "order" =  -1 * "order" WHERE id IN (
                    SELECT i.id FROM {block_instance} i
                    INNER JOIN (SELECT view, "column" FROM {block_instance} WHERE "order" = 0) z
                        ON (z.view = i.view AND z.column = i.column))'
                );
            }
            execute_sql('UPDATE {block_instance} SET "order" = 1 WHERE "order" = 0');
            execute_sql('UPDATE {block_instance} SET "order" = -1 * ("order" - 1) WHERE "order" < 0');
        }
    }

    if ($oldversion < 2009031000) {
        reload_html_filters();
    }

    if ($oldversion < 2009031300) {
        $table = new XMLDBTable('institution');

        $expiry = new XMLDBField('expiry');
        $expiry->setAttributes(XMLDB_TYPE_DATETIME);
        add_field($table, $expiry);

        $expirymailsent = new XMLDBField('expirymailsent');
        $expirymailsent->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $expirymailsent);

        $suspended = new XMLDBField('suspended');
        $suspended->setAttributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, null, null, 0);
        add_field($table, $suspended);

        // Insert a cron job to check for soon expiring and expired institutions
        $cron = new StdClass;
        $cron->callfunction = 'auth_handle_institution_expiries';
        $cron->minute       = '5';
        $cron->hour         = '9';
        $cron->day          = '*';
        $cron->month        = '*';
        $cron->dayofweek    = '*';
        insert_record('cron', $cron);
    }

    if ($oldversion < 2009031800) {

        // Files can only attach blogpost artefacts, but we would like to be able to attach them
        // to other stuff.  Rename the existing attachment table artefact_blog_blogpost_file to
        // artefact_file_attachment so we don't end up with many tables doing the same thing.
        execute_sql("ALTER TABLE {artefact_blog_blogpost_file} RENAME TO {artefact_attachment}");

        if (is_postgres()) {
            // Ensure all of the indexes and constraints are renamed
            execute_sql("
            ALTER TABLE {artefact_attachment} RENAME blogpost TO artefact;
            ALTER TABLE {artefact_attachment} RENAME file TO attachment;

            ALTER INDEX {arteblogblogfile_blofil_pk} RENAME TO {arteatta_artatt_pk};
            ALTER INDEX {arteblogblogfile_blo_ix} RENAME TO {arteatta_art_ix};
            ALTER INDEX {arteblogblogfile_fil_ix} RENAME TO {arteatta_att_ix};

            ALTER TABLE {artefact_attachment} DROP CONSTRAINT {arteblogblogfile_blo_fk};
            ALTER TABLE {artefact_attachment} ADD CONSTRAINT {arteatta_art_fk} FOREIGN KEY (artefact) REFERENCES {artefact}(id);

            ALTER TABLE {artefact_attachment} DROP CONSTRAINT {arteblogblogfile_fil_fk};
            ALTER TABLE {artefact_attachment} ADD CONSTRAINT {arteatta_att_fk} FOREIGN KEY (attachment) REFERENCES {artefact}(id);
            ");
        }
        else if (is_mysql()) {
            execute_sql("ALTER TABLE {artefact_attachment} DROP FOREIGN KEY {arteblogblogfile_blo_fk}");
            execute_sql("ALTER TABLE {artefact_attachment} DROP INDEX {arteblogblogfile_blo_ix}");
            execute_sql("ALTER TABLE {artefact_attachment} CHANGE blogpost artefact BIGINT(10) DEFAULT NULL");
            execute_sql("ALTER TABLE {artefact_attachment} ADD CONSTRAINT {arteatta_art_fk} FOREIGN KEY {arteatta_art_ix} (artefact) REFERENCES {artefact}(id)");

            execute_sql("ALTER TABLE {artefact_attachment} DROP FOREIGN KEY {arteblogblogfile_fil_fk}");
            execute_sql("ALTER TABLE {artefact_attachment} DROP INDEX {arteblogblogfile_fil_ix}");
            execute_sql("ALTER TABLE {artefact_attachment} CHANGE file attachment BIGINT(10) DEFAULT NULL");
            execute_sql("ALTER TABLE {artefact_attachment} ADD CONSTRAINT {arteatta_att_fk} FOREIGN KEY {arteatta_att_ix} (attachment) REFERENCES {artefact}(id)");
        }

        // Drop the _pending table. From now on files uploaded as attachments will become artefacts
        // straight away.  Hopefully changes to the upload/file browser form will make it clear to
        // the user that these attachments sit in his/her files area as soon as they are uploaded.
        $table = new XMLDBTable('artefact_blog_blogpost_file_pending');
        drop_table($table);
    }

    if ($oldversion < 2009040900) {
        // The view access page has been putting the string 'null' in as a group role in IE.
        set_field('view_access_group', 'role', null, 'role', 'null');
    }

    if ($oldversion < 2009040901) {
        $table = new XMLDBTable('import_installed');
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('version', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('release', XMLDB_TYPE_TEXT, 'small', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('active', XMLDB_TYPE_INTEGER,  1, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 1);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('name'));

        create_table($table);

        $table = new XMLDBTable('import_cron');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('minute', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('hour', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('day', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('dayofweek', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('month', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('nextrun', XMLDB_TYPE_DATETIME, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'callfunction'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'import_installed', array('name'));

        create_table($table);

        $table = new XMLDBTable('import_config');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 100, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('field', XMLDB_TYPE_CHAR, 100, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'small', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'field'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'import_installed', array('name'));

        create_table($table);

        $table = new XMLDBTable('import_event_subscription');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('event', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'import_installed', array('name'));
        $table->addKeyInfo('eventfk', XMLDB_KEY_FOREIGN, array('event'), 'event_type', array('name'));
        $table->addKeyInfo('subscruk', XMLDB_KEY_UNIQUE, array('plugin', 'event', 'callfunction'));

        create_table($table);

        $table = new XMLDBTable('export_installed');
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('version', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('release', XMLDB_TYPE_TEXT, 'small', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('active', XMLDB_TYPE_INTEGER,  1, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 1);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('name'));

        create_table($table);

        $table = new XMLDBTable('export_cron');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('minute', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('hour', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('day', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('dayofweek', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('month', XMLDB_TYPE_CHAR, 25, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '*');
        $table->addFieldInfo('nextrun', XMLDB_TYPE_DATETIME, null, null);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'callfunction'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'export_installed', array('name'));

        create_table($table);

        $table = new XMLDBTable('export_config');
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 100, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('field', XMLDB_TYPE_CHAR, 100, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'small', XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('plugin', 'field'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'export_installed', array('name'));

        create_table($table);

        $table = new XMLDBTable('export_event_subscription');
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('plugin', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('event', XMLDB_TYPE_CHAR, 50, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addFieldInfo('callfunction', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL);
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('pluginfk', XMLDB_KEY_FOREIGN, array('plugin'), 'export_installed', array('name'));
        $table->addKeyInfo('eventfk', XMLDB_KEY_FOREIGN, array('event'), 'event_type', array('name'));
        $table->addKeyInfo('subscruk', XMLDB_KEY_UNIQUE, array('plugin', 'event', 'callfunction'));

        create_table($table);
    }

    if ($oldversion < 2009050700) {
        if ($data = check_upgrades('export.html')) {
            upgrade_plugin($data);
        }
        if ($data = check_upgrades('export.leap')) {
            upgrade_plugin($data);
        }
        if ($data = check_upgrades('import.leap')) {
            upgrade_plugin($data);
        }
    }

    return $status;

}

?>
