<?php
require_once(get_config('docroot') . 'lib/form/elements/autocomplete.php');
/**
 *
 * @package    mahara
 * @subpackage form-element
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

/**
 * Provides a tag input field
 *
 * @param Pieform  $form    The form to render the element for
 * @param array    $element The element to render
 * @return string           The HTML for the element
 */
function pieform_element_tags(Pieform $form, $element) {

    $newelement = array(
        'type' => 'autocomplete',
        'title' => $element['title'],
        'id' => $element['id'],
        'name' => $element['name'],
        'defaultvalue' => isset($element['defaultvalue']) ? $element['defaultvalue'] : null,
        'description' => isset($element['description']) ? $element['description'] : null,
        'help' => isset($element['help']) ? $element['help'] : false,
        'ajaxurl' => get_config('wwwroot') . 'json/taglist.php',
        'multiple' => true,
        'allowclear' => false,
        'initfunction' => 'translate_tags_to_names',
        'ajaxextraparams' => array(),
        'extraparams' => array('tags' => true),
        'width' => '280px',
    );
    return pieform_element_autocomplete($form, $newelement);
}

function translate_tags_to_names(array $tags) {
    global $USER;

    if (empty($tags)) {
        $tags = array();
    } else if (count($tags) == 1 && !is_object($tags[0])) { // Legacy fix.
        $tags = array();
    }

    $results = array();
    $alltags = get_all_tags_for_user();
    foreach ($tags as $tag) {
        $id = $tag->tagid != 0 ? $tag->tagid : $tag->tag;
        if (isset($alltags['tags'][$tag->tag])) {
            $results[] = (object) array('id' => $id, 'text' => display_tag($tag->tag, $alltags['tags']));
        }
        else {
            $results[] = (object) array('id' => $id, 'text' => hsc($tag->tag));
        }
    }

    return $results;
}

/**
 * Display formatted tag
 * Currently is tag name plus the usage count
 *
 * @param string $name    Tag name
 * @param string $alltags  Array of tags to get the information from
 * @return $tag Formatted tag
 */
function display_tag($name, $alltags) {
    if ($alltags[$name]->prefix && !empty($alltags[$name]->prefix)) {
        $prefix = $alltags[$name]->prefix;
        return $prefix . ': '. $name . ' (' . $alltags[$name]->count . ')';
    }

    return $name . ' (' . $alltags[$name]->count . ')';
}

/**
 * Get all tags available for this user
 *
 * @param string $query Search option
 * @param int $limit
 * @param int $offset
 * @retun array $tags  The tags this user has created
 */
function get_all_tags_for_user($query = null, $limit = null, $offset = null) {
    global $USER;
    if ($USER->is_logged_in()) {
        $userid = $USER->get('id');

        $usertagssql = "";
        $usertags = array();
        // If the user is a site admin show all the user tags as well.
        if ($USER->get('admin')) {
          $usertagssql = "
                SELECT tag, 1 AS count, NULL AS prefix, 0 AS tagid
                  FROM {usr_tag} t
                 WHERE t.tagid = 0";
          $usertags = (is_array(get_records_sql_assoc($usertagssql))) ? get_records_sql_assoc($usertagssql) : array();
        }
        // If the user is an institution admin show the user tags of the users belonging to that institution.
        else if ($admininstitutions = $USER->get('admininstitutions')) {
            $insql = "'" . join("','", $admininstitutions) . "'";
            $usertagssql = "
                SELECT tag, COUNT(*) AS count, NULL AS prefix, 0 AS tagid
                  FROM {usr_tag} t
            INNER JOIN {usr} u ON t.usr=u.id
            INNER JOIN {usr_institution} ui ON ui.usr=u.id
                 WHERE ui.institution IN ($insql) AND t.tagid = 0";
          $usertags = (is_array(get_records_sql_assoc($usertagssql))) ? get_records_sql_assoc($usertagssql) : array();
        }
        // Get the user's tags and the institution tags used by the user.
        $values = array($userid, $userid, $userid);
        $sql = "
            SELECT tag, SUM(count) AS count, prefix, tagid
            FROM (
                SELECT t.tag, COUNT(*) AS count, i.displayname AS prefix, t.tagid AS tagid
                  FROM {artefact_tag} t
            INNER JOIN {artefact} a ON t.artefact = a.id
             LEFT JOIN {tag} tag ON t.tag = tag.text
             LEFT JOIN {institution} i ON i.id = tag.owner AND i.tags = 1
                 WHERE a.owner = ?
              GROUP BY 1
             UNION ALL
                SELECT tag, COUNT(*) AS count, i.displayname AS prefix, t.tagid AS tagid
                  FROM {view_tag} t
            INNER JOIN {view} v ON t.view = v.id
             LEFT JOIN {tag} tag ON t.tag = tag.text
             LEFT JOIN {institution} i ON i.id = tag.owner AND i.tags = 1
                 WHERE v.owner = ?
              GROUP BY 1
             UNION ALL
                SELECT tag, COUNT(*) AS count, i.displayname AS prefix, t.tagid AS tagid
                  FROM {collection_tag} t
            INNER JOIN {collection} c ON t.collection = c.id
             LEFT JOIN {tag} tag ON t.tag = tag.text
             LEFT JOIN {institution} i ON i.id = tag.owner AND i.tags = 1
                 WHERE c.owner = ?
              GROUP BY 1) tags
              GROUP BY tag
              ORDER BY LOWER(tag)";
        $usedtag = (is_array(get_records_sql_assoc($sql, $values, $offset, $limit))) ? get_records_sql_assoc($sql, $values, $offset, $limit) : array();

        // Get the institution tags not  yet used by the user.
        $notinsql = "";
        if (!empty($usedtag)) {
          $tagids = array_map(function($tag) {
              return $tag->tagid;
          }, $usedtag);
          $notinsql = "NOT IN (" . implode(", ", $tagids) . ")";
        }
        $unusedsql = "
            SELECT t.text AS tag, 0 AS count, i.displayname AS prefix, t.id AS tagid
              FROM {tag} t
              JOIN {institution} i ON i.id = t.owner AND i.tags = 1
              JOIN {usr_institution} ui ON ui.institution = i.name AND ui.usr = ?
             WHERE t.id $notinsql";
        $unusedtags = (is_array(get_records_sql_assoc($unusedsql, array($userid), $offset))) ? get_records_sql_assoc($unusedsql, array($userid), $offset, $limit) : array();
    }
    $result = array_merge($usedtag, $unusedtags, $usertags);
    $results = !empty($result) ? $result : array();
    $return = array('tags' => $results,
                    'count' => count($results),
    );

    return $return;
}

function pieform_element_tags_get_headdata($element) {
    return pieform_element_autocomplete_get_headdata($element);
}

function pieform_element_tags_get_value(Pieform $form, $element) {
    return pieform_element_autocomplete_get_value($form, $element);
}
