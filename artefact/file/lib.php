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
 * @subpackage artefact-internal
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

class PluginArtefactFile extends PluginArtefact {

    public static function get_artefact_types() {
        return array(
            'file',
            'folder',
            'image',
            'profileicon',
        );
    }
    
    public static function get_block_types() {
        return array('image');
    }

    public static function get_plugin_name() {
        return 'file';
    }

    public static function menu_items() {
        return array(
            array(
                'path' => 'myportfolio/files',
                'url' => 'artefact/file/',
                'title' => get_string('myfiles', 'artefact.file'),
                'weight' => 20,
            ),
            array(
                'path' => 'profile/icons',
                'url' => 'artefact/file/profileicons.php',
                'title' => get_string('profileicons', 'artefact.file'),
                'weight' => 11,
            ),
        );
    }

    public static function group_tabs($groupid) {
        return array(
            'files' => array(
                'path' => 'groups/files',
                'url' => 'artefact/file/groupfiles.php?group='.$groupid,
                'title' => get_string('Files', 'artefact.file'),
                'weight' => 60,
            ),
        );
    }

    public static function get_event_subscriptions() {
        $subscriptions = array(
            (object)array(
                'plugin'       => 'file',
                'event'        => 'createuser',
                'callfunction' => 'newuser',
            ),
        );

        return $subscriptions;
    }

    public static function postinst($prevversion) {
        if ($prevversion == 0) {
            set_config_plugin('artefact', 'file', 'defaultquota', 52428800);
            set_config_plugin('artefact', 'file', 'uploadagreement', 1);
        }
        self::resync_filetype_list();
    }

    public static function newuser($event, $user) {
        if (empty($user->quota)) {
            update_record('usr', array('quota' => get_config_plugin('artefact', 'file', 'defaultquota')), array('id' => $user['id']));
        }
    }
    

    public static function sort_child_data($a, $b) {
        if ($a->container && !$b->container) {
            return -1;
        }
        else if (!$a->container && $b->container) {
            return 1;
        }
        return strnatcasecmp($a->text, $b->text);
    }

    public static function themepaths($type) {
        static $themepaths = array(
            'filebrowser' => array(
                'images/file.gif',
                'images/folder.gif',
                'images/image.gif',
            ),
        );
        return $themepaths[$type];
    }

    public static function jsstrings($type) {
        static $jsstrings = array(
            'file' => array(
                'mahara' => array(
                    'cancel',
                    'delete',
                    'edit',
                    'Permissions',
                    'republish',
                    'tags',
                    'view',
                ),
                'artefact.file' => array(
                    'copyrightnotice',
                    'create',
                    'createfolder',
                    'deletefile?',
                    'deletefolder?',
                    'Description',
                    'destination',
                    'editfile',
                    'editfolder',
                    'File',
                    'fileexistsoverwritecancel',
                    'filenamefieldisrequired',
                    'home',
                    'Name',
                    'namefieldisrequired',
                    'nofilesfound',
                    'overwrite',
                    'savechanges',
                    'timeouterror',
                    'title',
                    'titlefieldisrequired',
                    'unlinkthisfilefromblogposts?',
                    'upload',
                    'uploadfile',
                    'uploadfileexistsoverwritecancel',
                    'uploadingfiletofolder',
                    'youmustagreetothecopyrightnotice',
                ),
                'group' => array(
                    'Role',
                ),
            ),
            'filebrowser' => array(
                'mahara' => array(
                    'remove',
                ),
                'artefact.file' => array(
                    'detachfilewarning',
                    'editfile',
                    'editfolder',
                    'filewithnameexists',
                    'foldernamerequired',
                    'nametoolong',
                    'namefieldisrequired',
                    'uploadingfiletofolder',
                    'youmustagreetothecopyrightnotice',
                ),
            ),
        );
        return $jsstrings[$type];
    }

    public static function jshelp($type) {
        static $jshelp = array(
            'filebrowser' => array(
                'artefact.file' => array(
                    'notice',
                    'quota_message',
                    'uploadfile',
                    'tags',
                ),
            ),
        );
        return $jshelp[$type];
    }


    /**
     * Resyncs the allowed filetypes list with the XML configuration file.
     *
     * This can be called on install (and is, in the postinst method above),
     * and every time an upgrade is made that changes the file.
     */
    function resync_filetype_list() {
        require_once('xmlize.php');
        db_begin();
        log_info('Beginning resync of filetype list');

        $currentlist = get_records_assoc('artefact_file_mime_types');
        $newlist     = xmlize(file_get_contents(get_config('docroot') . 'artefact/file/filetypes.xml'));
        $filetypes   = $newlist['filetypes']['#']['filetype'];
        $newtypes    = array();

        // Step one: if a mimetype is in the new list that is not in the current
        // list, add it to the current list.
        foreach ($filetypes as $filetype) {
            $description = $filetype['#']['description'][0]['#'];
            foreach ($filetype['#']['mimetypes'][0]['#']['mimetype'] as $type) {
                $mimetype = $type['#'];
                if (!isset($currentlist[$mimetype])) {
                    log_debug('Adding mimetype: ' . $mimetype . ' (' . $description . ')');
                    execute_sql("INSERT INTO {artefact_file_mime_types} (mimetype, description) VALUES (?,?)", array($mimetype, $description));
                }
                else if ($currentlist[$mimetype]->description != $description) {
                    log_debug('Updating mimetype: ' . $mimetype . ' (' . $description . ')');
                    execute_sql("UPDATE {artefact_file_mime_types} SET description = ? WHERE mimetype = ?", array($description, $mimetype));
                }
                $newtypes[$mimetype] = true;
                $currentlist[$mimetype] = (object) array(
                    'mimetype'    => $mimetype,
                    'description' => $description,
                );
            }
        }

        // Step two: If a mimetype is in the current list that is not in the
        // new list, remove it from the current list.
        foreach ($currentlist as $mimetype => $type) {
            if (!isset($newtypes[$mimetype])) {
                log_debug('Removing mimetype: ' . $mimetype);
                delete_records('artefact_file_mime_types', 'mimetype', $mimetype);
            }
        }
       
        db_commit();
    }

    public static function can_be_disabled() {
        return false;
    }
}

abstract class ArtefactTypeFileBase extends ArtefactType {

    protected $size;

    // The original filename extension (when the file is first
    // uploaded) is saved here.  This is used as a workaround for IE's
    // detecting filetypes by extension: when the file is downloaded,
    // the extension can be appended to the name if it's not there
    // already.
    protected $oldextension;

    // The id used for the filename on the filesystem.  Usually this
    // is the same as the artefact id, but it can be different if the
    // file is a copy of another file artefact.
    protected $fileid;

    protected $filetype; // Mime type

    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);
        
        if (empty($this->id)) {
            $this->locked = 0;
        }

        if ($this->id && ($filedata = get_record('artefact_file_files', 'artefact', $this->id))) {
            foreach($filedata as $name => $value) {
                if (property_exists($this, $name)) {
                    $this->{$name} = $value;
                }
            }
        }

    }

    public function render_self($options) {
        $options['id'] = $this->get('id');

        $downloadpath = get_config('wwwroot') . 'artefact/file/download.php?file=' . $this->get('id');
        if (isset($options['viewid'])) {
            $downloadpath .= '&view=' . $options['viewid'];
        }
        $filetype = get_string($this->get('oldextension'), 'artefact.file');
        if (substr($filetype, 0, 2) == '[[') {
            $filetype = $this->get('oldextension') . ' ' . get_string('file', 'artefact.file');
        }

        $smarty = smarty_core();
        $smarty->assign('iconpath', $this->get_icon($options));
        $smarty->assign('downloadpath', $downloadpath);
        $smarty->assign('filetype', $filetype);
        $smarty->assign('owner', $this->display_owner());
        $smarty->assign('created', strftime(get_string('strftimedaydatetime'), $this->get('ctime')));
        $smarty->assign('modified', strftime(get_string('strftimedaydatetime'), $this->get('mtime')));
        $smarty->assign('size', $this->describe_size() . ' (' . $this->get('size') . ' ' . get_string('bytes', 'artefact.file') . ')');

        foreach (array('title', 'description', 'artefacttype') as $field) {
            $smarty->assign($field, $this->get($field));
        }

        return array('html' => $smarty->fetch('artefact:file:file_render_self.tpl'), 'javascript' => '');
    }

    /**
     * This function updates or inserts the artefact.  This involves putting
     * some data in the artefact table (handled by parent::commit()), and then
     * some data in the artefact_file_files table.
     */
    public function commit() {
        // Just forget the whole thing when we're clean.
        if (empty($this->dirty)) {
            return;
        }
      
        // We need to keep track of newness before and after.
        $new = empty($this->id);

        // Commit to the artefact table.
        parent::commit();

        // Reset dirtyness for the time being.
        $this->dirty = true;

        $data = (object)array(
            'artefact'      => $this->get('id'),
            'size'          => $this->get('size'),
            'oldextension'  => $this->get('oldextension'),
            'fileid'        => $this->get('fileid'),
            'filetype'      => $this->get('filetype'),
        );

        if ($new) {
            if ($this->get('artefacttype') != 'folder' && empty($data->fileid)) {
                $data->fileid = $data->artefact;
            }
            insert_record('artefact_file_files', $data);
        }
        else {
            update_record('artefact_file_files', $data, 'artefact');
        }

        $this->dirty = false;
    }

    public static function is_singular() {
        return false;
    }

    public static function get_icon($options=null) {

    }

    public static function collapse_config() {
        return 'file';
    }

    public function move($newparentid) {
        $this->set('parent', $newparentid);
        $this->commit();
        return true;
    }

    public function delete() {
        if (empty($this->id)) {
            return; 
        }
        try {
            delete_records('artefact_attachment', 'attachment', $this->id);
        } 
        catch ( Exception $e ) {}
        delete_records('artefact_file_files', 'artefact', $this->id);
        parent::delete();
    }

    // Check if something exists in the db with a given title and parent,
    // either in adminfiles or with a specific owner.
    public static function file_exists($title, $owner, $folder, $institution=null, $group=null) {
        $filetypesql = "('" . join("','", array_diff(PluginArtefactFile::get_artefact_types(), array('profileicon'))) . "')";
        $ownersql = artefact_owner_sql($owner, $group, $institution);
        return get_field_sql('SELECT a.id FROM {artefact} a
            LEFT OUTER JOIN {artefact_file_files} f ON f.artefact = a.id
            WHERE a.title = ?
            AND a.' . $ownersql . '
            AND a.parent ' . (empty($folder) ? ' IS NULL' : ' = ' . $folder) . '
            AND a.artefacttype IN ' . $filetypesql, array($title));
    }


    // Sort folders before files; then use nat sort order.
    public static function my_files_cmp($a, $b) {
        return strnatcasecmp((-2 * isset($a->isparent) + ($a->artefacttype != 'folder')) . $a->title,
                             (-2 * isset($b->isparent) + ($b->artefacttype != 'folder')) . $b->title);
    }


    public static function get_my_files_data($parentfolderid, $userid, $group=null, $institution=null) {
        $select = '
            SELECT
                a.id, a.artefacttype, a.mtime, f.size, a.title, a.description,
                COUNT(c.id) AS childcount, COUNT (aa.artefact) AS attachcount';
        $from = '
            FROM {artefact} a
                LEFT OUTER JOIN {artefact_file_files} f ON f.artefact = a.id
                LEFT OUTER JOIN {artefact} c ON c.parent = a.id 
                LEFT OUTER JOIN {artefact_attachment} aa ON aa.attachment = a.id';
        $where = "
            WHERE a.artefacttype IN ('" . join("','", array_diff(PluginArtefactFile::get_artefact_types(), array('profileicon'))) . "')";
        $groupby = '
            GROUP BY
                a.id, a.artefacttype, a.mtime, f.size, a.title, a.description';

        $phvals = array();

        if ($institution) {
            $where .= '
            AND a.institution = ? AND a.owner IS NULL';
            $phvals[] = $institution;
        }
        else if ($group) {
            global $USER;
            $select .= ',
                r.can_edit, r.can_view';
            $from .= '
                LEFT OUTER JOIN (
                    SELECT ar.artefact, ar.can_edit, ar.can_view
                    FROM {artefact_access_role} ar
                    INNER JOIN {group_member} gm ON ar.role = gm.role
                    WHERE gm.group = ? AND gm.member = ? 
                ) r ON r.artefact = a.id';
            $phvals[] = $group;
            $phvals[] = $USER->get('id');
            $where .= '
            AND a.group = ? AND a.owner IS NULL AND r.can_view = 1';
            $phvals[] = $group;
            $groupby .= ', r.can_edit, r.can_view';
        }
        else {
            $where .= '
            AND a.institution IS NULL AND a.owner = ?';
            $phvals[] = $userid;
        }

        if ($parentfolderid) {
            $where .= '
            AND a.parent = ? ';
            $phvals[] = $parentfolderid;
        }
        else {
            $where .= '
            AND a.parent IS NULL';
        }

        $filedata = get_records_sql_assoc($select . $from . $where . $groupby, $phvals);
        if (!$filedata) {
            $filedata = array();
        }
        else {
            foreach ($filedata as $item) {
                $item->mtime = format_date(strtotime($item->mtime), 'strfdaymonthyearshort');
                $item->tags = array();
                if ($item->size) { // Doing this here now for non-js users
                    $item->size = ArtefactTypeFile::short_size($item->size, true);
                }
            }
            $where = 'artefact IN (' . join(',', array_keys($filedata)) . ')';
            $tags = get_records_select_array('artefact_tag', $where);
            if ($tags) {
                foreach ($tags as $t) {
                    $filedata[$t->artefact]->tags[] = $t->tag;
                }
            }
            if ($group) {  // Fetch permissions for each artefact
                $perms = get_records_select_array('artefact_access_role', $where);
                if ($perms) {
                    foreach ($perms as $perm) {
                        $filedata[$perm->artefact]->permissions[$perm->role] = array(
                            'view' => $perm->can_view,
                            'edit' => $perm->can_edit,
                            'republish' => $perm->can_republish
                        );
                    }
                }
            }
        }

        // Add parent folder to the list
        if (!empty($parentfolderid)) {
            $grandparentid = (int) get_field('artefact', 'parent', 'id', $parentfolderid);
            $filedata[$grandparentid] = (object) array(
                'title'        => get_string('parentfolder', 'artefact.file'),
                'artefacttype' => 'folder',
                'description'  => get_string('parentfolder', 'artefact.file'),
                'isparent'     => true,
                'id'           => $grandparentid
            );
        }

        uasort($filedata, array("ArtefactTypeFileBase", "my_files_cmp"));
        return $filedata;
    }

    public static function files_form($page='', $group=null, $institution=null, $folder=null, $highlight=null, $edit=null) {
        $folder = param_integer('folder', 0);
        $edit = param_variable('edit', 0);
        if (is_array($edit)) {
            $edit = array_keys($edit);
            $edit = $edit[0];
        }
        $edit = (int) $edit;
        $highlight = null;
        if ($file = param_integer('file', 0)) {
            $highlight = array($file); // todo convert to file1=1&file2=2 etc
        }

        $form = array(
            'name'               => 'files',
            'jsform'             => true,
            'newiframeonsubmit'  => true,
            'jssuccesscallback'  => 'files_success',
            'renderer'           => 'oneline',
            'plugintype'         => 'artefact',
            'pluginname'         => 'file',
            'configdirs'         => array(get_config('libroot') . 'form/', get_config('docroot') . 'artefact/file/form/'),
            'group'              => $group,
            'institution'        => $institution,
            'elements'           => array(
                'filebrowser' => array(
                    'type'         => 'filebrowser',
                    'folder'       => $folder,
                    'highlight'    => $highlight,
                    'edit'         => $edit,
                    'page'         => $page,
                    'config'       => array(
                        'upload'          => true,
                        'uploadagreement' => get_config_plugin('artefact', 'file', 'uploadagreement'),
                        'createfolder'    => true,
                        'edit'            => true,
                        'select'          => false,
                    ),
                ),
            ),
        );
        return $form;
    }

    public static function files_js() {
        return "function files_success(form, data) { files_filebrowser.success(form, data); }";
    }

    public static function count_user_files($owner=null, $group=null, $institution=null) {
        $filetypes = array_diff(PluginArtefactFile::get_artefact_types(), array('profileicon'));
        foreach ($filetypes as $k => $v) {
            if ($v == 'folder') {
                unset($filetypes[$k]);
            }
        }
        $filetypesql = "('" . join("','", $filetypes) . "')";

        $ownersql = artefact_owner_sql($owner, $group, $institution);
        return (object) array(
            'files'   => count_records_select('artefact', "artefacttype IN $filetypesql AND $ownersql", array()),
            'folders' => count_records_select('artefact', "artefacttype = 'folder' AND $ownersql", array())
        );
    }

    public static function artefactchooser_get_file_data($artefact) {
        $artefact->icon = call_static_method(generate_artefact_class_name($artefact->artefacttype), 'get_icon', array('id' => $artefact->id));
        if ($artefact->artefacttype == 'profileicon') {
            $artefact->hovertitle  =  $artefact->note;
            if ($artefact->title) {
                $artefact->hovertitle .= ': ' . $artefact->title;
            }
        }
        else {
            $artefact->hovertitle  =  $artefact->title;
            if ($artefact->description) {
                $artefact->hovertitle .= ': ' . $artefact->description;
            }
        }

        $folderdata = self::artefactchooser_folder_data($artefact);

        if ($artefact->artefacttype == 'profileicon') {
            $artefact->description = str_shorten_text($artefact->title, 30);
        }
        else {
            $path = $artefact->parent ? self::get_full_path($artefact->parent, $folderdata->data) : '';
            $artefact->description = str_shorten_text($folderdata->ownername . $path . $artefact->title, 30);
        }

        return $artefact;
    }

    public static function artefactchooser_folder_data(&$artefact) {
        // Grab data about all folders the artefact owner has, so we
        // can make full paths to them, and show the artefact owner if
        // it's a group or institution.
        static $folderdata = array();

        $ownerkey = $artefact->owner . '::' . $artefact->group . '::' . $artefact->institution;
        if (!isset($folderdata[$ownerkey])) {
            $ownersql = artefact_owner_sql($artefact->owner, $artefact->group, $artefact->institution);
            $folderdata[$ownerkey]->data = get_records_select_assoc('artefact', "artefacttype='folder' AND $ownersql", array(), '', 'id, title, parent');
            if ($artefact->group) {
                $folderdata[$ownerkey]->ownername = get_field('group', 'name', 'id', $artefact->group) . ':';
            }
            else if ($artefact->institution) {
                if ($artefact->institution == 'mahara') {
                    $folderdata[$ownerkey]->ownername = get_config('sitename') . ':';
                }
                else {
                    $folderdata[$ownerkey]->ownername = get_field('institution', 'displayname', 'name', $artefact->institution) . ':';
                }
            }
            else {
                $folderdata[$ownerkey]->ownername = '';
            }
        }

        return $folderdata[$ownerkey];
    }

    /**
     * Works out a full path to a folder, given an ID. Implemented this way so 
     * only one query is made.
     */
    public static function get_full_path($id, &$folderdata) {
        $path = '';
        while (!empty($id)) {
            $path = $folderdata[$id]->title . '/' . $path;
            $id = $folderdata[$id]->parent;
        }
        return $path;
    }

    public function default_parent_for_copy(&$view, &$template, $artefactstoignore) {
        static $folderid;

        if (!empty($folderid)) {
            return $folderid;
        }

        $viewfilesfolder = ArtefactTypeFolder::get_folder_id(get_string('viewfilesdirname', 'view'), get_string('viewfilesdirdesc', 'view'),
                                                             null, true, $view->get('owner'), $view->get('group'), $view->get('institution'), $artefactstoignore);
        $foldername = $view->get('id');
        $existing = get_column_sql("
            SELECT title
            FROM {artefact}
            WHERE parent = ? AND title LIKE ? || '%'", array($viewfilesfolder, $foldername));
        $sep = '';
        $ext = '';
        if ($existing) {
            while (in_array($foldername . $sep . $ext, $existing)) {
                $sep = '-';
                $ext++;
            }
        }
        $data = (object) array(
            'title'       => $foldername . $sep . $ext,
            'description' => get_string('filescopiedfromviewtemplate', 'view', $template->get('title')),
            'owner'       => $view->get('owner'),
            'group'       => $view->get('group'),
            'institution' => $view->get('institution'),
            'parent'      => $viewfilesfolder,
        );
        $folder = new ArtefactTypeFolder(0, $data);
        $folder->commit();

        $folderid = $folder->get('id');

        return $folderid;
    }

    /**
     * Return a unique artefact title for a given owner & parent.
     *
     * Try to add digits before the filename extension: If the desired
     * title contains a ".", add "." plus digits before the final ".",
     * otherwise append "." and digits.
     * 
     * @param string $desired
     * @param integer $parent
     * @param integer $owner
     * @param integer $group
     * @param string $institution
     */
    public static function get_new_file_title($desired, $parent, $owner, $group, $institution) {
        $bits = split('\.', $desired);
        if (count($bits) > 1 && preg_match('/[^0-9]/', end($bits))) {
            $start = join('.', array_slice($bits, 0, count($bits)-1));
            $end = '.' . end($bits);
        }
        else {
            $start = $desired;
            $end = '';
        }

        $where = $parent ? "parent = $parent" : 'parent IS NULL';
        $where .=  ' AND ' . artefact_owner_sql($owner, $group, $institution);

        $taken = get_column_sql("
            SELECT title FROM {artefact}
            WHERE artefacttype IN ('" . join("','", array_diff(PluginArtefactFile::get_artefact_types(), array('profileicon'))) . "')
            AND title LIKE ? || '%' || ? AND " . $where, array($start, $end));
        $taken = array_flip($taken);

        $i = 0;
        $newname = $start . $end;
        while (isset($taken[$newname])) {
            $i++;
            $newname = $start . '.' . $i . $end;
        }
        return $newname;
    }
}

/**
 * Submit function for My/Group/Institution/Site files
 *
 * This function will only be called when javascript is disabled.
 *
 * Outside the File class because Pieforms doesn't appear to like
 * being given static class method as a submit callback.
 */
function files_submit(Pieform $form, $values) {
    // @todo: move group/inst stuff to form defn.
    $group       = $form->get_property('group');
    $institution = $form->get_property('institution');
    if ($group) {
        $redirect = get_config('wwwroot') . 'artefact/file/groupfiles.php';
        $params = array('group' => $group);
    } else if ($institution) {
        if ($institution == 'mahara') {
            $redirect = get_config('wwwroot') . 'admin/site/files.php';
            $params = array();
        }
        else {
            $redirect = get_config('wwwroot') . 'artefact/file/institutionfiles.php';
            $params = array('institution' => $institution);
        }
    } else {
        $redirect = get_config('wwwroot') . 'artefact/file/index.php';
        $params = array();
    }

    // Some updates on the filebrowser element need to set params and
    // redirect back to this page.
    if (isset($values['filebrowser']['folder'])) {
        $params['folder'] = $values['filebrowser']['folder'];
    }
    if (isset($values['filebrowser']['edit'])) {
        $params['edit'] = $values['filebrowser']['edit'];
    }
    if (isset($values['filebrowser']['highlight'])) {
        $params['file'] = $values['filebrowser']['highlight'];
    }

    if ($params) {
        foreach ($params as $k => $v) {
            $params[$k] = $k . '=' . $v;
        }
        $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . join('&', $params);
    }

    $result = $values['filebrowser'];
    $result['goto'] = $redirect;
    $form->reply(empty($result['error']) ? PIEFORM_OK : PIEFORM_ERR, $result);
}

class ArtefactTypeFile extends ArtefactTypeFileBase {

    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);
        
        if (empty($this->id)) {
            $this->container = 0;
        }

    }

    public static function get_file_directory($id) {
        return "artefact/file/originals/" . ($id % 256);
    }

    public function get_path() {
        return get_config('dataroot') . self::get_file_directory($this->fileid) . '/' .  $this->fileid;
    }


    /**
     * Test file type and return a new Image or File.
     */
    public static function new_file($path, $data) {
        require_once('file.php');
        if (is_image_file($path)) {
            // If it's detected as an image, overwrite the browser mime type
            $imageinfo      = getimagesize($path);
            $data->filetype = $imageinfo['mime'];
            $data->width    = $imageinfo[0];
            $data->height   = $imageinfo[1];
            return new ArtefactTypeImage(0, $data);
        }
        return new ArtefactTypeFile(0, $data);
    }

    /**
     * Moves a file into the myfiles area.
     * Takes the name of a file outside the myfiles area.
     * Returns a boolean indicating success or failure.
     */
    public static function save_file($pathname, $data, User &$user=null, $outsidedataroot=false) {
        // This is only used when blog posts are saved: Files which
        // have been uploaded to the post are moved to a permanent
        // location in the files area using this function. 
        $dataroot = get_config('dataroot');
        if (!$outsidedataroot) {
            $pathname = $dataroot . $pathname;
        }
        if (!$size = filesize($pathname)) {
            return false;
        }
        $f = self::new_file($pathname, $data);
        $f->set('size', $size);
        // @todo: Set mime type! (and old extension)
        $f->commit();
        $id = $f->get('id');

        $newdir = $dataroot . self::get_file_directory($id);
        check_dir_exists($newdir);
        $newname = $newdir . '/' . $id;
        if (!rename($pathname, $newname)) {
            $f->delete();
            return false;
        }
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }
        try {
            $user->quota_add($size);
            $user->commit();
            return $id;
        }
        catch (QuotaExceededException $e) {
            $f->delete();
            return false;
        }
    }


    /**
     * Processes a newly uploaded file, copies it to disk, and creates
     * a new artefact object.
     * Takes the name of a file input.
     * Returns false for no errors, or a string describing the error.
     */
    public static function save_uploaded_file($inputname, $data) {
        require_once('uploadmanager.php');
        $um = new upload_manager($inputname);
        if ($error = $um->preprocess_file()) {
            throw new UploadException($error);
        }
        $size = $um->file['size'];
        global $USER;
        if (!isset($data->institution) && !isset($data->group) && !$USER->quota_allowed($size)) {
            throw new QuotaExceededException(get_string('uploadexceedsquota', 'artefact.file'));
        }
        $data->size         = $size;
        $data->filetype     = $um->file['type'];
        $data->oldextension = $um->original_filename_extension();
        $f = self::new_file($um->file['tmp_name'], $data);
        $f->commit();
        $id = $f->get('id');
        // Save the file using its id as the filename, and use its id modulo
        // the number of subdirectories as the directory name.
        if ($error = $um->save_file(self::get_file_directory($id) , $id)) {
            $f->delete();
            throw new UploadException($error);
        }
        else {
            if (!isset($data->institution) && !isset($data->group)) {
                $USER->quota_add($size);
                $USER->commit();
            }
        }
        return $id;
    }


    // Return the title with the original extension appended to it if
    // it's not already there.
    public function download_title() {
        $extn = $this->get('oldextension');
        $name = $this->get('title');
        if (substr($name, -1-strlen($extn)) == '.' . $extn) {
            return $name;
        }
        return $name . (substr($name, -1) == '.' ? '' : '.') . $extn;
    }


    public static function get_admin_files($public) {
        $pubfolder = ArtefactTypeFolder::admin_public_folder_id();
        $artefacts = get_records_sql_assoc("
            SELECT
                a.id, a.title, a.parent, a.artefacttype
            FROM {artefact} a
                INNER JOIN {artefact_file_files} f ON f.artefact = a.id
            WHERE a.institution = 'mahara'", array());

        $files = array();
        if (!empty($artefacts)) {
            foreach ($artefacts as $a) {
                if ($a->artefacttype != 'folder') {
                    $title = $a->title;
                    $parent = $a->parent;
                    while (!empty($parent)) {
                        if ($public && $parent == $pubfolder) {
                            $files[] = array('name' => $title, 'id' => $a->id);
                            continue 2;
                        }
                        $title = $artefacts[$parent]->title . '/' . $title;
                        $parent = $artefacts[$parent]->parent;
                    }
                    if (!$public) {
                        $files[] = array('name' => $title, 'id' => $a->id);
                    }
                }
            }
        }
        return $files;
    }

    public function delete() {
        if (empty($this->id)) {
            return; 
        }
        $file = $this->get_path();
        // Detach this file from any view feedback
        set_field('view_feedback', 'attachment', null, 'attachment', $this->id);
        if (is_file($file)) {
            $size = filesize($file);
            // Only delete the file on disk if no other artefacts point to it
            if (count_records('artefact_file_files', 'fileid', $this->get('id')) == 1) {
                unlink($file);
            }
            global $USER;
            // Deleting other users' files won't lower their quotas yet...
            if (!$this->institution && $USER->id == $this->get('owner')) {
                $USER->quota_remove($size);
                $USER->commit();
            }
        }
        parent::delete();
    }

    public static function has_config() {
        return true;
    }

    public static function get_icon($options=null) {
        return theme_get_url('images/file.gif');
    }

    public static function get_config_options() {
        $elements = array();
        $defaultquota = get_config_plugin('artefact', 'file', 'defaultquota');
        if (empty($defaultquota)) {
            $defaultquota = 1024 * 1024 * 10;
        }
        $elements['quotafieldset'] = array(
            'type' => 'fieldset',
            'legend' => get_string('defaultquota', 'artefact.file'),
            'elements' => array(
                'defaultquotadescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('defaultquotadescription', 'artefact.file') . '</td></tr>'
                ),
                'defaultquota' => array(
                    'title'        => get_string('defaultquota', 'artefact.file'), 
                    'type'         => 'bytes',
                    'defaultvalue' => $defaultquota,
                )
            ),
            'collapsible' => true
        );

        // Require user agreement before uploading files
        // Rework this when/if we provide translatable agreements
        $uploadagreement = get_config_plugin('artefact', 'file', 'uploadagreement');
        $usecustomagreement = get_config_plugin('artefact', 'file', 'usecustomagreement');
        $elements['uploadagreementfieldset'] = array(
            'type' => 'fieldset',
            'legend' => get_string('uploadagreement', 'artefact.file'),
            'elements' => array(
                'uploadagreementdescription' => array(
                    'value' => '<tr><td colspan="2">' . get_string('uploadagreementdescription', 'artefact.file') . '</td></tr>'
                ),
                'uploadagreement' => array(
                    'title'        => get_string('requireagreement', 'artefact.file'), 
                    'type'         => 'checkbox',
                    'defaultvalue' => $uploadagreement,
                ),
                'defaultagreement' => array(
                    'type'         => 'html',
                    'title'        => get_string('defaultagreement', 'artefact.file'), 
                    'value'        => get_string('uploadcopyrightdefaultcontent', 'install'),
                ),
                'usecustomagreement' => array(
                    'title'        => get_string('usecustomagreement', 'artefact.file'), 
                    'type'         => 'checkbox',
                    'defaultvalue' => $usecustomagreement,
                ),
                'customagreement' => array(
                    'name'         => 'customagreement',
                    'title'        => get_string('customagreement', 'artefact.file'), 
                    'type'         => 'wysiwyg',
                    'rows'         => 10,
                    'cols'         => 80,
                    'defaultvalue' => get_field('site_content', 'content', 'name', 'uploadcopyright'),
                ),
            ),
            'collapsible' => true
        );

        // Profile icon size
        $currentwidth = get_config_plugin('artefact', 'file', 'profileiconwidth');
        $currentheight = get_config_plugin('artefact', 'file', 'profileiconheight');
        $elements['profileiconsize'] = array(
            'type' => 'fieldset',
            'legend' => get_string('profileiconsize', 'artefact.file'),
            'elements' => array(
                'profileiconwidth' => array(
                    'type' => 'text',
                    'size' => 4,
                    'suffix' => get_string('widthshort'),
                    'title' => get_string('width'),
                    'defaultvalue' => ((!empty($currentwidth)) ? $currentwidth : 100),
                    'rules' => array(
                        'required' => true,
                        'integer'  => true,
                    )
                ),
                'profileiconheight' => array(
                    'type' => 'text',
                    'suffix' => get_string('heightshort'),
                    'size' => 4,
                    'title' => get_string('height'),
                    'defaultvalue' => ((!empty($currentheight)) ? $currentheight : 100),
                    'rules' => array(
                        'required' => true,
                        'integer'  => true,
                    ),
                    'help' => true,
                ),
            ),
            'collapsible' => true
        );

        return array(
            'elements' => $elements,
            'renderer' => 'table'
        );
    }

    public static function save_config_options($values) {
        global $USER;
        set_config_plugin('artefact', 'file', 'defaultquota', $values['defaultquota']);
        set_config_plugin('artefact', 'file', 'profileiconwidth', $values['profileiconwidth']);
        set_config_plugin('artefact', 'file', 'profileiconheight', $values['profileiconheight']);
        set_config_plugin('artefact', 'file', 'uploadagreement', $values['uploadagreement']);
        set_config_plugin('artefact', 'file', 'usecustomagreement', $values['usecustomagreement']);
        $data = new StdClass;
        $data->name    = 'uploadcopyright';
        $data->content = $values['customagreement'];
        $data->mtime   = db_format_timestamp(time());
        $data->mauthor = $USER->get('id');
        update_record('site_content', $data, 'name');
    }

    public static function short_size($bytes, $abbr=false) {
        if ($bytes < 1024) {
            return $bytes <= 0 ? '0' : ($bytes . ($abbr ? 'b' : (' ' . get_string('bytes', 'artefact.file'))));
        }
        if ($bytes < 1048576) {
            return floor(($bytes / 1024) * 10 + 0.5) / 10 . 'K';
        }
        return floor(($bytes / 1048576) * 10 + 0.5) / 10 . 'M';
    }

    public function describe_size() {
        return ArtefactTypeFile::short_size($this->get('size'));
    }

    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default' => $wwwroot . 'artefact/file/download.php?file=' . $id,
            get_string('folder', 'artefact.file') => $wwwroot . 'artefact/file/?folder=' . $id,
        );
    }

    public function override_content_type() {
        static $extensions;
        if (empty($extensions)) {
            $extensions = array(
                'wmv' => 'video/x-ms-wmv',
                'flv' => 'video/x-flv',
            );
        }
        if (array_key_exists($this->get('oldextension'), $extensions)) {
            return $extensions[$this->get('oldextension')];
        }
        return false;
    }

    public static function get_quota_usage($artefact) {
        return get_field('artefact_file_files', 'size', 'artefact', $artefact);
    }

    public function copy_extra($new) {
        global $USER;
        if ($new->get('owner') && $new->get('owner') == $USER->get('id')) {
            // TODO test what happens when quota is exceeded!
            $USER->quota_add($new->get('size'));
            $USER->commit();
        }
    }

    // Only changes to group files get put in the artefact_log table
    public function can_be_logged() {
        return (bool) $this->get('group');
    }

}

class ArtefactTypeFolder extends ArtefactTypeFileBase {

    public function __construct($id = 0, $data = null) {

        parent::__construct($id, $data);

        if (empty($this->id)) {
            $this->container = 1;
            $this->size = null;
        }

    }

    public function folder_contents() {
        return get_records_array('artefact', 'parent', $this->get('id'));
    }

    public function render_self($options) {
        $smarty = smarty_core();
        $smarty->assign('title', $this->get('title'));
        $smarty->assign('description', $this->get('description'));
        $smarty->assign('viewid', isset($options['viewid']) ? $options['viewid'] : 0);
        $smarty->assign('simpledisplay', isset($options['simpledisplay']) ? $options['simpledisplay'] : false);

        if ($childrecords = $this->folder_contents()) {
            $this->add_to_render_path($options);
            usort($childrecords, array('ArtefactTypeFileBase', 'my_files_cmp'));
            $children = array();
            foreach ($childrecords as &$child) {
                $c = artefact_instance_from_id($child->id);
                $child->title = $child->hovertitle = $c->get('title');
                if (!empty($options['simpledisplay'])) {
                    $child->title = str_shorten_text($child->title, 20);
                }
                $child->date = format_date(strtotime($child->mtime), 'strfdaymonthyearshort');
                $child->iconsrc = call_static_method(generate_artefact_class_name($child->artefacttype), 'get_icon', array('id' => $child->id, 'viewid' => isset($options['viewid']) ? $options['viewid'] : 0));
            }
            $smarty->assign('children', $childrecords);
        }
        return array('html' => $smarty->fetch('artefact:file:folder_render_self.tpl'),
                     'javascript' => null);
    }

    public function describe_size() {
        return $this->count_children() . ' ' . get_string('files', 'artefact.file');
    }

    public static function get_icon($options=null) {
        return theme_get_url('images/folder.gif');
    }

    public static function collapse_config() {
        return 'file';
    }
    
    public static function admin_public_folder_id() {
        // There is one public files directory and many admins, so the
        // name of the directory uses the site language rather than
        // the language of the admin who first creates it.
        $name = get_string_from_language(get_config('lang'), 'adminpublicdirname', 'admin');
        $folderid = get_field_sql("
           SELECT
             a.id
           FROM {artefact} a
             INNER JOIN {artefact_file_files} f ON a.id = f.artefact
           WHERE a.title = ?
             AND a.artefacttype = ?
             AND a.institution = 'mahara'
             AND a.parent IS NULL", array($name, 'folder'));
        if (!$folderid) {
            $description = get_string_from_language(get_config('lang'), 'adminpublicdirdescription', 'admin');
            $data = (object) array('title' => $name,
                                   'description' => $description,
                                   'institution' => 'mahara');
            $f = new ArtefactTypeFolder(0, $data);
            $f->commit();
            $folderid = $f->get('id');
        }
        return $folderid;
    }

    public static function change_public_folder_name($oldlang, $newlang) {
        $oldname = get_string_from_language($oldlang, 'adminpublicdirname', 'admin');
        $folderid = get_field_sql("
           SELECT
             a.id
           FROM {artefact} a
             INNER JOIN {artefact_file_files} f ON a.id = f.artefact
           WHERE a.title = ?
             AND a.artefacttype = ?
             AND a.institution = 'mahara'
             AND a.parent IS NULL", array($oldname, 'folder'));

        if (!$folderid) {
            return;
        }

        $name = get_string_from_language($newlang, 'adminpublicdirname', 'admin');
        $description = get_string_from_language($newlang, 'adminpublicdirdescription', 'admin');
        if (!empty($name)) {
            $artefact = artefact_instance_from_id($folderid);
            $artefact->set('title', $name);
            $artefact->set('description', $description);
            $artefact->commit();
        }
    }

    /**
     * Retrieves info from the artefact table about the folder with the given 
     * name, in the specified directory, owned by the specified 
     * user/group/institution.
     *
     * @param string $name        The name of the folder to search for
     * @param int $parentfolderid The ID of the parent folder in which to look.
     * @param int $userid         The ID of the user who owns the folder
     * @param int $groupid        The ID of the group who owns the folder
     * @param string $institution The name of the institution who owns the folder
     * @param array $artefactstoignore A list of IDs to not consider as the given folder. See {@link default_parent_for_copy()}
     */
    public static function get_folder_by_name($name, $parentfolderid=null, $userid=null, $groupid=null, $institution=null, $artefactstoignore=array()) {
        $parentclause = $parentfolderid ? 'parent = ' . $parentfolderid : 'parent IS NULL';
        $ownerclause = artefact_owner_sql($userid, $groupid, $institution);
        $ignoreclause = $artefactstoignore ? ' AND id NOT IN(' . implode(', ', array_map('db_quote', $artefactstoignore)) . ')' : '';
        return get_record_sql('SELECT * FROM {artefact}
           WHERE title = ? AND ' . $parentclause . ' AND ' . $ownerclause . "
           AND artefacttype = 'folder'" . $ignoreclause, array($name));
    }

    /**
     * Get the id of a folder, creating the folder if necessary
     *
     * @param string $name        The name of the folder to search for
     * @param string $description The description for the folder, should a new folder need creating
     * @param int $parentfolderid The ID of the parent folder in which to look.
     * @param boolean $create     Whether to create a new folder if one isn't found
     * @param int $userid         The ID of the user who owns the folder
     * @param int $groupid        The ID of the group who owns the folder
     * @param string $institution The name of the institution who owns the folder
     * @param array $artefactstoignore A list of IDs to not consider as the given folder. See {@link default_parent_for_copy()}
     */
    public static function get_folder_id($name, $description, $parentfolderid=null, $create=true, $userid=null, $groupid=null, $institution=null, $artefactstoignore=array()) {
        if (!$record = self::get_folder_by_name($name, $parentfolderid, $userid, $groupid, $institution, $artefactstoignore)) {
            if (!$create) {
                return false;
            }
            $data = new StdClass;
            $data->title = $name;
            $data->description = $description;
            $data->owner = $userid;
            $data->group = $groupid;
            $data->institution = $institution;
            $data->parent = $parentfolderid;
            $f = new ArtefactTypeFolder(0, $data);
            $f->commit();
            return $f->get('id');
        }
        return $record->id;
    }

    // append the view id to to the end of image and anchor urls so they are visible to logged out users also
    public static function append_view_url($postcontent, $view_id) {
        $postcontent = preg_replace('#(<a[^>]+href="[^>]+artefact/file/download\.php\?file=\d+)#', '\1&amp;view=' . $view_id , $postcontent);
        $postcontent = preg_replace('#(<img[^>]+src="[^>]+artefact/file/download\.php\?file=\d+)#', '\1&amp;view=' . $view_id, $postcontent);
        $postcontent = preg_replace('#(<img[^>]+src="([^>]+artefact/file/download\.php\?file=\d+&amp;view=\d+)"[^>]*>)#', '<a href="\2">\1</a>', $postcontent);

        return $postcontent;
    }

    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default' => $wwwroot . 'artefact/file/?folder=' . $id,
        );
    }

    public static function change_language($userid, $oldlang, $newlang) {
        $oldname = get_string_from_language($oldlang, 'feedbackattachdirname', 'view');
        $artefact = ArtefactTypeFolder::get_folder_by_name($oldname, null, $userid);
        if (empty($artefact)) {
            return;
        }

        $name = get_string_from_language($newlang, 'feedbackattachdirname', 'view');
        $description = get_string_from_language($newlang, 'feedbackattachdirdesc', 'view');
        if (!empty($name)) {
            $artefact = artefact_instance_from_id($artefact->id);
            $artefact->set('title', $name);
            $artefact->set('description', $description);
            $artefact->commit();
        }
    }

}

class ArtefactTypeImage extends ArtefactTypeFile {

    protected $width;
    protected $height;

    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);

        if ($this->id && ($filedata = get_record('artefact_file_image', 'artefact', $this->id))) {
            foreach($filedata as $name => $value) {
                if (property_exists($this, $name)) {
                    $this->{$name} = $value;
                }
            }
        }
    }

    /**
     * This function updates or inserts the artefact.  This involves putting
     * some data in the artefact table (handled by parent::commit()), and then
     * some data in the artefact_file_image table.
     */
    public function commit() {
        // Just forget the whole thing when we're clean.
        if (empty($this->dirty)) {
            return;
        }
      
        // We need to keep track of newness before and after.
        $new = empty($this->id);

        // Commit to the artefact table.
        parent::commit();

        // Reset dirtyness for the time being.
        $this->dirty = true;

        $data = (object)array(
            'artefact'      => $this->get('id'),
            'width'         => $this->get('width'),
            'height'        => $this->get('height')
        );

        if ($new) {
            insert_record('artefact_file_image', $data);
        }
        else {
            update_record('artefact_file_image', $data, 'artefact');
        }

        $this->dirty = false;
    }

    public static function collapse_config() {
        return 'file';
   } 

    public static function get_icon($options=null) {
        $url = get_config('wwwroot') . 'artefact/file/download.php?';
        $url .= 'file=' . $options['id'];

        if (isset($options['viewid'])) {
            $url .= '&view=' . $options['viewid'];
        }
        if (isset($options['size'])) {
            $url .= '&size=' . $options['size'];
        }
        else {
            $url .= '&maxheight=20&maxwidth=20';
        }

        return $url;
    }

    public function get_path($data=array()) {
        require_once('file.php');
        $result = get_dataroot_image_path('artefact/file/', $this->fileid, $data);
        return $result;
    }

    public function delete() {
        if (empty($this->id)) {
            return; 
        }
        delete_records('artefact_file_image', 'artefact', $this->id);
        parent::delete();
    }

    public function render_self($options) {
        $result = parent::render_self($options);
        $result['html'] = '<div class="fr filedata-icon" style="text-align: center;"><h4>' . get_string('Preview', 'artefact.file') . '</h4><a href="'
            . hsc(get_config('wwwroot') . 'artefact/file/download.php?file=' . $this->id . '&view=' . (isset($options['viewid']) ? $options['viewid'] : 0)) . '"><img src="'
            . hsc(get_config('wwwroot') . 'artefact/file/download.php?file=' . $this->id . '&view=' . (isset($options['viewid']) ? $options['viewid']  : 0). '&maxwidth=400&maxheight=180')
            . '" alt=""></a></div>' . $result['html'];
        return $result;
    }
}

class ArtefactTypeProfileIcon extends ArtefactTypeImage {

    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default' => $wwwroot . 'artefact/file/profileicons.php',
        );
    }

    public static function get_icon($options=null) {
        $url = get_config('wwwroot') . 'thumb.php?type=profileiconbyid&id=' . hsc($options['id']);

        if (isset($options['size'])) {
            $url .= '&size=' . $options['size'];
        }
        else {
            $url .= '&size=20x20';
        }

        return $url;
    }

    public function get_path($data=array()) {
        require_once('file.php');
        $result = get_dataroot_image_path('artefact/file/profileicons/', $this->fileid, $data);
        return $result;
    }

    public function in_view_list() {
        return true;
    }

    public static function get_quota_usage($artefact) {
        return filesize(get_config('dataroot') . 'artefact/file/profileicons/originals/'
            . ($artefact % 256) . '/' . $artefact);
    }

    public function default_parent_for_copy(&$view, &$template, $artefactstoignore) {
        return null;
    }

}


?>
