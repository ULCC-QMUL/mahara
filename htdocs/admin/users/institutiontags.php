<?php
/**
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', 1);

define('INSTITUTIONALADMIN', 1);
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'admin');
define('MENUITEM', 'manageinstitutions/institutiontags');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once(get_config('libroot') . 'institution.php');

$institution = param_alphanum('institution', false);
$new = param_boolean('new', 0);

// Get all the institutions that the current user has access to.
$institutionelement = get_institution_selector(true, false, false, false, false, true);
if (!$institutionelement || empty($institutionelement['options'])) {
    throw new AccessDeniedException(get_string('cantlistinstitutioncollections', 'collection'));
}

if (!$institution || !$USER->can_edit_institution($institution, true)) {
    $institution = empty($institutionelement['value']) ? $institutionelement['defaultvalue'] : $institutionelement['value'];
}
else if (!empty($institution)) {
    $institutionelement['defaultvalue'] = $institution;
}

define('TITLE', get_string('institutiontags', 'tags'));

$institutionselector = pieform(array(
    'name' => 'usertypeselect',
    'class' => 'form-inline',
    'elements' => array(
        'institution' => $institutionelement,
    )
));

// The institution drop-down selector if applicable.
$wwwroot = get_config('wwwroot');
$js = <<< EOF
function reloadTags() {
    window.location.href = '{$wwwroot}admin/users/institutiontags.php?institution='+$('#usertypeselect_institution').val();
}
$(document).ready(function() {
    $('#usertypeselect_institution').on('change', reloadTags);
});
EOF;

// Check if user is a institution admin
$canedit = $USER->get('admin') || $USER->is_institutional_admin();
if (!$canedit) {
    throw new AccessDeniedException(get_string('cantlistinstitutiontags', 'tags'));
}

// Building the new tag form.
$elements = array(
    'tag' => array(
        'type' => 'text',
        'defaultvalue' => null,
        'title' => get_string('institutiontags', 'tags'),
        'size' => 30,
        'rules' => array(
            'required' => true,
        ),
    ),
    'submit' => array(
        'type'    => 'submitcancel',
        'class'   => 'btn-primary',
        'value'   => array(get_string('save'), get_string('cancel')),
        'confirm' => null,
    )
);
$form = pieform(array(
    'name'       => 'institutiontag',
    'plugintype' => 'core',
    'pluginname' => 'tags',
    'elements'   => $elements,
));


/**
 * Submit the new institution tag form
 *
 * @param Pieform  $form   The form to submit
 * @param array    $values The values submitted
 */
function institutiontag_submit(Pieform $form, $values) {
    global $SESSION, $institution, $USER;

    $institutiontag = new stdClass;
    $institutiontag->text     = $values['tag'];
    $institutiontag->owner    = get_field('institution', 'id', 'name', $institution);
    $institutiontag->editedby = $USER->id;
    $institutiontag->ctime    = date("Y-m-d", time());
    $institutiontag->mtime    = date("Y-m-d", time());

    $id = insert_record('tag', $institutiontag, 'id', true);
    if ($id) {
        $SESSION->add_ok_msg(get_string('institutiontagsaved', 'tags'));
    }
    else {
        $SESSION->add_error_msg(get_string('institutiontagcantbesaved', 'tags'));
    }
    redirect("/admin/users/institutiontags.php?institution={$institution}");
}

/**
 * Cancel the submission of the new institution tag form.
 */
function institutiontag_cancel_submit() {
    global $institution;
    redirect("/admin/users/institutiontags.php?institution={$institution}");
}

/**
 * Validate the submitted data from the new institution tag form. New tags must not:
 *  - be empty strings
 *  - match an existing tag within the institution
 *
 * @param Pieform  $form   The form to validate
 * @param array    $values The values submitted
 */
function institutiontag_validate(Pieform $form, $values) {
    global $institution;

    // Don't even start attempting to parse if there are previous errors
    if ($form->has_errors()) {
        return;
    }
    if (empty(trim($values['tag'])) || trim($values['tag']) === '') {
        $form->set_error('tag', get_string('error:emptytag', 'tags'));
        return;
    }
    $id = get_field('institution', 'id', 'name', $institution);
    if (record_exists('tag', 'owner', $id, 'text', $values['tag'])) {
        $form->set_error('tag', get_string('error:duplicatetag', 'tags'));
        return;
    }
}

// Delete tag.
$delete = param_variable('delete', null);
if ($delete) {
    $institutionid = get_field('institution', 'id', 'name', $institution);
    $deleterecords = get_records_sql_array("
        SELECT
            t.tag
        FROM (
           (SELECT at.tag FROM {artefact_tag} at WHERE at.tagid = ?)
           UNION
           (SELECT vt.tag FROM {view_tag} vt WHERE vt.tagid = ?)
           UNION
           (SELECT ct.tag FROM {collection_tag} ct WHERE ct.tagid = ?)
    ) t",
        array($delete, $delete, $delete)
    );
    if (!$deleterecords || empty($deleterecords)) {
        db_begin();
        if (delete_records_select('tag', "owner = {$institutionid} AND id = {$delete}")) {
            $SESSION->add_ok_msg(get_string('institutiontagdeleted', 'tags'));
        } else {
            $SESSION->add_error_msg(get_string('cantdeleteinstitutiontag', 'tags'));
        }
        db_commit();
    }

    redirect("/admin/users/institutiontags.php?institution={$institution}");
}

// Get the institution tags and their used.
$id  = get_field('institution', 'id', 'name', $institution);
$sql = "
    SELECT id, text, SUM(count) AS count
    FROM (
        SELECT tag.id, tag.text, 0 AS count
          FROM {tag} tag
         WHERE tag.owner = ?
      GROUP BY 1
     UNION ALL
        SELECT tag.id, tag.text, COUNT(*) AS count
          FROM {artefact_tag} t
     LEFT JOIN {tag} tag ON t.tag = tag.text
         WHERE tag.owner = ?
      GROUP BY 1
     UNION ALL
        SELECT tag.id, tag.text, COUNT(*) AS count
          FROM {view_tag} t
     LEFT JOIN {tag} tag ON t.tag = tag.text
         WHERE tag.owner = ?
      GROUP BY 1
     UNION ALL
        SELECT tag.id, tag.text, COUNT(*) AS count
          FROM {collection_tag} t
     LEFT JOIN {tag} tag ON t.tag = tag.text
         WHERE tag.owner = ?
      GROUP BY 1) tags
      GROUP BY text
      ORDER BY LOWER(text)";
$tags = get_records_sql_assoc($sql, array($id, $id, $id, $id));
if (!$tags) {
    $tags = array();
}

$smarty = smarty(array('paginator'));
setpageicon($smarty, 'icon-university');
$smarty->assign('institutionselector', $institutionselector);
$smarty->assign('INLINEJAVASCRIPT', $js);
$smarty->assign('canedit', $canedit);
$smarty->assign('institution', $institution);
$smarty->assign('new', $new);
$smarty->assign('form', $form);
$smarty->assign('tags', $tags);
$smarty->assign('SUBPAGETOP', 'admin/users/institutiontagsactions.tpl');
$smarty->assign('addonelink', get_config('wwwroot') . "admin/users/institutiontags.php?new=1&institution={$institution}");
$smarty->display('admin/users/institutiontags.tpl');
