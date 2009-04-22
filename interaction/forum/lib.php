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
 * @subpackage interaction-forum
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

require_once('activity.php');

class PluginInteractionForum extends PluginInteraction {

    public static function instance_config_form($group, $instance=null) {
        if (isset($instance)) {
            $autosubscribe = get_field('interaction_forum_instance_config', 'value', 'field', 'autosubscribe', 'forum', $instance->get('id'));
            $weight = get_field('interaction_forum_instance_config', 'value', 'field', 'weight', 'forum', $instance->get('id'));
            $moderators = get_column_sql(
                'SELECT fm.user FROM {interaction_forum_moderator} fm
                JOIN {usr} u ON (fm.user = u.id AND u.deleted = 0)
                WHERE fm.forum = ?',
                array($instance->get('id'))
            );
        }

        if ($instance === null) {
            $exclude = '';
        }
        else {
            $exclude = 'AND i.id != ' . db_quote($instance->get('id'));
        }

        $existing = get_records_sql_array('
            SELECT i.id, i.title, c.value AS weight
            FROM {interaction_instance} i
            INNER JOIN {interaction_forum_instance_config} c ON (i.id = c.forum AND c.field = \'weight\')
            WHERE i.group = ?
            AND i.deleted != 1
            ' . $exclude . '
            ORDER BY c.value',
            array($group->id));
        if ($existing) {
            foreach ($existing as &$item) {
                $item = (array)$item;
            }
        }
        else {
            $existing = array();
        }

        return array(
            'fieldset' => array(
                'type' => 'fieldset',
                'collapsible' => true,
                'collapsed' => true,
                'legend' => get_string('settings'),
                'elements' => array(
                    'autosubscribe' => array(
                        'type'         => 'select',
                        'title'        => get_string('autosubscribeusers', 'interaction.forum'),
                        'options'      => array(true  => get_string('yes'),
                                                false => get_string('no')),
                        'description'  => get_string('autosubscribeusersdescription', 'interaction.forum'),
                        'defaultvalue' => isset($autosubscribe) ? $autosubscribe : false,
                        'rules' => array(
                            'required' => true,
                        ),
                        'help'         => true,
                    ),
                    'weight' => array(
                        'type' => 'weight',
                        'title' => get_string('Order', 'interaction.forum'),
                        'description' => get_string('orderdescription', 'interaction.forum'),
                        'defaultvalue' => isset($weight) ? $weight : count($existing),
                        'rules' => array(
                            'required' => true,
                        ),
                        'existing' => $existing,
                        'ignore'   => (count($existing) == 0)
                    ),
                    'moderator' => array(
                        'type' => 'userlist',
                        'title' => get_string('Moderators', 'interaction.forum'),
                        'description' => get_string('moderatorsdescription', 'interaction.forum'),
                        'defaultvalue' => isset($moderators) ? $moderators : null,
                        'group' => $group->id,
                        'includeadmins' => false,
                        'filter' => false,
                        'lefttitle' => get_string('potentialmoderators', 'interaction.forum'),
                        'righttitle' => get_string('currentmoderators', 'interaction.forum')
                    )
                )
            )
        );
    }

    public static function instance_config_save($instance, $values){
        db_begin();

        // Autosubscribe
        delete_records_sql(
            "DELETE FROM {interaction_forum_instance_config}
            WHERE field = 'autosubscribe' AND forum = ?",
            array($instance->get('id'))
        );
        insert_record('interaction_forum_instance_config', (object)array(
            'forum' => $instance->get('id'),
            'field' => 'autosubscribe',
            'value' => (bool)$values['autosubscribe'],
        ));

        if ($values['justcreated'] && $values['autosubscribe']) {
            // Subscribe all existing users in the group to the forums
            if ($userids = get_column('group_member', 'member', 'group', $instance->get('group'))) {
                foreach ($userids as $userid) {
                    insert_record(
                        'interaction_forum_subscription_forum',
                        (object)array(
                            'forum' => $instance->get('id'),
                            'user' => $userid,
                        )
                    );
                }
            }
        }

        // Moderators
        delete_records(
            'interaction_forum_moderator',
            'forum', $instance->get('id')
        );
        foreach ($values['moderator'] as $user) {
            insert_record(
                'interaction_forum_moderator',
                (object)array(
                    'user' => $user,
                    'forum' => $instance->get('id')
                )
            );
        }

        // Re-order the forums according to their new ordering
        delete_records_sql(
            'DELETE FROM {interaction_forum_instance_config}
            WHERE field = \'weight\' AND forum IN (
                SELECT id FROM {interaction_instance} WHERE "group" = ?
            )',
            array($instance->get('group'))
        );

        if (isset($values['weight'])) {
            foreach ($values['weight'] as $weight => $id) {
                if ($id === null) {
                    // This is where the current forum is to be placed
                    $id = $instance->get('id');
                }

                insert_record(
                    'interaction_forum_instance_config',
                    (object)array(
                        'forum' => $id,
                        'field' => 'weight',
                        'value' => $weight,
                    )
                );
            }
        }
        else {
            // Element was ignored - because this is the first forum in a group
            insert_record(
                'interaction_forum_instance_config',
                (object)array(
                    'forum' => $instance->get('id'),
                    'field' => 'weight',
                    'value' => 0,
                )
            );
        }
        db_commit();
    }

    public static function get_activity_types() {
        return array(
            (object)array(
                'name' => 'newpost',
                'admin' => 0,
                'delay' => 1
            )
        );
    }

    public static function get_cron() {
        return array(
            (object)array(
                'callfunction' => 'interaction_forum_new_post',
                'minute'       => '*/30',
            ),
        );
    }

    /**
     * Subscribes the forum plugin to events
     *
     * @return array
     */
    public static function get_event_subscriptions() {
        return array(
            (object)array(
                'plugin'       => 'forum',
                'event'        => 'userjoinsgroup',
                'callfunction' => 'user_joined_group',
            ),
        );
    }

    /**
     * When a user joins a group, subscribe them automatically to all forums 
     * that should be subscribable
     *
     * @param array $eventdata
     */
    public static function user_joined_group($event, $gm) {
        if ($forumids = get_column_sql("
            SELECT ii.id
            FROM {group} g
            LEFT JOIN {interaction_instance} ii ON g.id = ii.group
            LEFT JOIN {interaction_forum_instance_config} ific ON ific.forum = ii.id
            WHERE \"group\" = ? AND ific.field = 'autosubscribe' and ific.value = '1'",
            array($gm['group']))) {
            db_begin();
            foreach ($forumids as $forumid) {
                insert_record(
                    'interaction_forum_subscription_forum',
                    (object)array(
                        'forum' => $forumid,
                        'user' => $gm['member'],
                    )
                );
            }
            db_commit();
        }
    }

    /**
     * Optional method. Takes a list of forums and sorts them according to 
     * their weights for the sideblock
     *
     * @param array $forums An array of hashes of forum data
     * @return array        The array, sorted appropriately
     */
    public static function sideblock_sort($forums) {
        if (!$forums) {
            return $forums;
        }

        $weights = get_records_assoc('interaction_forum_instance_config', 'field', 'weight', 'forum', 'forum, value');
        foreach ($forums as &$forum) {
            // Note: forums expect every forum to have a 'weight' record in the 
            // forum instance config table, so we don't need to check that 
            // there is a weight for the forum here - there should be, 
            // otherwise someone has futz'd with the database or there's a bug 
            // elsewhere that allowed this to happen
            $forum->weight = $weights[$forum->id]->value;
        }
        usort($forums, create_function('$a, $b', 'return $a->weight > $b->weight;'));
        return $forums;
    }

/**
 * cronjob for new forum posts
 */
    public static function interaction_forum_new_post() {
        $currenttime = time();
        $minpostdelay = $currenttime - 30 * 60;
        $posts = get_records_sql_array(
            'SELECT s.subscriber, s.type, p.id
            FROM (
                SELECT st."user" AS subscriber, st.topic AS topic, \'topic\' AS type
                FROM {interaction_forum_subscription_topic} st
                UNION SELECT sf."user" AS subscriber, t.id AS topic, \'forum\' AS type
                FROM {interaction_forum_subscription_forum} sf
                INNER JOIN {interaction_forum_topic} t ON t.forum = sf.forum
            ) s
            INNER JOIN {interaction_forum_topic} t ON (t.deleted != 1 AND t.id = s.topic)
            INNER JOIN {interaction_forum_post} p ON (p.sent != 1 AND p.ctime < ? AND p.deleted != 1 AND p.topic = t.id)
            INNER JOIN {interaction_instance} f ON (f.id = t.forum AND f.deleted != 1)
            INNER JOIN {group} g ON (g.id = f.group AND g.deleted = ?)
            INNER JOIN {group_member} gm ON (gm.member = s.subscriber AND gm.group = f.group)
            ORDER BY type, p.id',
            array(db_format_timestamp($minpostdelay), 0)
        );
        // query gets a new object for every subscription
        // this combines all the objects for the same post together with an array for the subscribers
        if ($posts) {
            $count = count($posts);
            for ($i = 0; $i < $count; $i++) {
                $posts[$i]->users = array($posts[$i]->subscriber);
                $temp = $i;
                while (isset($posts[$i+1])
                    && $posts[$i+1]->id == $posts[$temp]->id
                    && $posts[$i+1]->type == $posts[$temp]->type) {
                    $i++;
                    $posts[$temp]->users[] = $posts[$i]->subscriber;
                    unset($posts[$i]);
                }
            }
            foreach ($posts as $post) {
                activity_occurred(
                    'newpost',
                    array(
                        'type' => $post->type,
                        'postid' => $post->id,
                        'users' => $post->users
                    ),
                    'interaction',
                    'forum'
                );
            }
            set_field_select('interaction_forum_post', 'sent', 1,
                'ctime < ? AND deleted = 0 AND sent = 0', array(db_format_timestamp($minpostdelay)));
        }
    }

    public static function can_be_disabled() {
        return false; //TODO until it either becomes an artefact plugin or stops being hardcoded everywhere
    }
}

class InteractionForumInstance extends InteractionInstance {

    public static function get_plugin() {
        return 'forum';
    }

    public function interaction_remove_user($userid) {
        delete_records('interaction_forum_moderator', 'forum', $this->id, 'user', $userid);
        delete_records('interaction_forum_subscription_forum', 'forum', $this->id, 'user', $userid);
        delete_records_select('interaction_forum_subscription_topic',
            'user = ? AND topic IN (SELECT id FROM {interaction_forum_topic} WHERE forum = ?)',
            array($userid, $this->id)
        );
    }

}


class ActivityTypeInteractionForumNewPost extends ActivityTypePlugin {

    protected $postid;
    protected $type; // forum or topic

    public function __construct($data) {
        parent::__construct($data);
        $this->users = activity_get_users($this->get_id(), $this->users);
        $post = get_record_sql(
            'SELECT p.subject, p.body, p.poster, p.parent, t.id AS topicid, p2.subject AS topicsubject, f.title AS forumtitle, g.name AS groupname, f.id AS forumid
            FROM {interaction_forum_post} p
            INNER JOIN {interaction_forum_topic} t ON t.id = p.topic
            INNER JOIN {interaction_forum_post} p2 ON (p2.parent IS NULL AND p2.topic = t.id)
            INNER JOIN {interaction_instance} f ON t.forum = f.id
            INNER JOIN {group} g ON f.group = g.id
            WHERE p.id = ?',
            array($this->postid)
        );
        $this->url = get_config('wwwroot') . 'interaction/forum/topic.php?id=' . $post->topicid . '#post' . $this->postid;
        // When emailing forum posts, create Message-Id headers for threaded display by email clients
        $urlinfo = parse_url(get_config('wwwroot'));
        $hostname = $urlinfo['host'];
        $cleanforumname = str_replace('"', "'", strip_tags($post->forumtitle));
        $this->customheaders = array(
            'Precedence: Bulk',
            'List-Id: "' . $cleanforumname . '" <forum' . $post->forumid . '@' . $hostname . '>',
            'List-Help: ' . get_config('wwwroot') . 'interaction/forum/view.php?id=' . $post->forumid,
            'Message-ID: <forumpost' . $this->postid . '@' . $hostname . '>',
        );
        if ($post->parent) {
            $this->customheaders[] = 'In-Reply-To: <forumpost' . $post->parent . '@' . $hostname . '>';
            $this->customheaders[] = 'References: <forumpost' . $post->parent . '@' . $hostname . '>';
        }
        foreach ($this->users as &$user) {
            if ($post->parent) {
                $user->subject = get_string('replytotopicby', 'interaction.forum', $post->groupname, $post->forumtitle, $post->topicsubject, display_name($post->poster, $user));
            }
            else {
                $user->subject = get_string('newforumpostby', 'interaction.forum', $post->groupname, $post->forumtitle, display_name($post->poster, $user));
            }
            $user->message = ($post->subject ? $post->subject . "\n" . str_repeat('-', strlen($post->subject)) . "\n" : '')
                . trim(html2text($post->body));
        }
        $this->unsubscribename = $post->forumtitle;
        $this->unsubscribeurl = get_config('wwwroot') . 'interaction/forum/view.php?id=' . $post->forumid;
    }

    public function get_subject($user) {
        return $user->subject;
    }

    public function get_message($user) {
        return $user->message;
    }

    public function get_plugintype(){
        return 'interaction';
    }

    public function get_pluginname(){
        return 'forum';
    }

    public function get_required_parameters() {
        return array('postid', 'type');
    }
}

// constants for forum membership types
define('INTERACTION_FORUM_ADMIN', 1);
define('INTERACTION_FORUM_MOD', 2);
define('INTERACTION_FORUM_MEMBER', 4);

/**
 * Can a user access a given forum?
 *
 * @param int $forumid id of forum
 * @param int $userid optional id of user, defaults to logged in user
 *
 * @returns constant access level or false
 */
function user_can_access_forum($forumid, $userid=null) {
    if (empty($userid)) {
        global $USER;
        $userid = $USER->get('id');
    }
    else if (!is_int($userid)) {
        throw new InvalidArgumentException("non integer user id given to user_can_access_forum: $userid");
    }
    if (!is_int($forumid)) {
        throw new InvalidArgumentException("non integer forum id given to user_can_access_forum: $forumid");
    }

    $membership = 0;

    $groupid = get_field('interaction_instance', '"group"', 'id', $forumid);
    $groupmembership = group_user_access((int)$groupid, (int)$userid);

    if (!$groupmembership) {
        return $membership;
    }
    $membership = $membership | INTERACTION_FORUM_MEMBER;
    if ($groupmembership == 'admin') {
        $membership = $membership | INTERACTION_FORUM_ADMIN | INTERACTION_FORUM_MOD;
    }
    if (record_exists('interaction_forum_moderator', 'forum', $forumid, 'user', $userid)) {
        $membership = $membership | INTERACTION_FORUM_MOD;
    }
    return $membership;
}

/**
 * Is a user allowed to edit a post
 *
 * @param boolean $moderator
 * @param int $poster the the id of the user who created the post
 * @param int $posttime the time the post was made
 * @param int $userid optional id of user, defaults to logged in user
 *
 * @returns boolean
 */
function user_can_edit_post($poster, $posttime, $userid=null) {
	if (empty($userid)) {
        global $USER;
        $userid = $USER->get('id');
    }
    return $poster == $userid && $posttime > (time() - 30 * 60);
}

/**
 * Generates a relative date containing yesterday/today when appropriate
 *
 * @param string $relative the format (for strftime) for a relative date (with %v where yesterday/today should be)
 * @param string $absolute the format (for strftime) for an absolute date
 * @param int $time1 the time to display
 * @param int $time2 optional the time $time1 is relative to, defaults to current time
 */
function relative_date($relative, $absolute, $time1, $time2=null) {
    if ($time2==null) {
        $time2 = time();
    }

    $date = getdate($time1);

    $yesterday = getdate(strtotime('-1 day', $time2));
    $tomorrow = getdate(strtotime('+1 day', $time2));
    $today = getdate($time2);

    if ($date['year'] == $yesterday['year'] && $date['yday'] == $yesterday['yday']) {
        return str_replace('%v', get_string('yesterday', 'interaction.forum'), strftime($relative, $time1));
    }
    else if ($date['year'] == $today['year'] && $date['yday'] == $today['yday']) {
        return str_replace('%v', get_string('today', 'interaction.forum'), strftime($relative, $time1));
    }
    return strftime($absolute, $time1);

}

function subscribe_forum_validate(Pieform $form, $values) {
    if (!is_logged_in()) {
        throw new AccessDeniedException();
    }
}

function subscribe_forum_submit(Pieform $form, $values) {
    global $USER, $SESSION;
    if ($values['type'] == 'subscribe') {
        db_begin();
        insert_record(
            'interaction_forum_subscription_forum',
            (object)array(
                'forum' => $values['forum'],
                'user' => $USER->get('id')
            )
        );
        delete_records_sql(
            'DELETE FROM {interaction_forum_subscription_topic}
            WHERE topic IN (
                SELECT id
                FROM {interaction_forum_topic}
                WHERE forum = ?
                AND "user" = ?
            )',
            array($values['forum'], $USER->get('id'))
        );
        db_commit();
        $SESSION->add_ok_msg(get_string('forumsuccessfulsubscribe', 'interaction.forum'));
    }
    else {
        delete_records(
            'interaction_forum_subscription_forum',
            'forum', $values['forum'],
            'user', $USER->get('id')
        );
        $SESSION->add_ok_msg(get_string('forumsuccessfulunsubscribe', 'interaction.forum'));
    }
    if ($values['redirect'] == 'index') {
        redirect('/interaction/forum/index.php?group=' . $values['group']);
    }
    else {
        redirect('/interaction/forum/view.php?id=' . $values['forum'] . '&offset=' . $values['offset']);
    }
}

?>
