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
 * @subpackage artefact-blog
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

/** 
 * Users can create blogs and blog posts using this plugin.
 */
class PluginArtefactBlog extends PluginArtefact {

    public static function get_artefact_types() {
        return array(
            'blog',
            'blogpost',
        );
    }
    
    public static function get_block_types() {
        return array();
    }

    public static function get_plugin_name() {
        return 'blog';
    }

    public static function menu_items() {
        return array(
            array(
                'path'   => 'myportfolio/blogs',
                'url'    => 'artefact/blog/',
                'title'  => get_string('myblogs', 'artefact.blog'),
                'weight' => 30,
            ),
        );
    }

    public static function get_cron() {
        return array(
            (object)array(
                'callfunction' => 'clean_post_files',
                'hour'         => '4',
                'minute'       => '40'
            )
        );
    }

    /**
     * This function cleans out any files that have been uploaded, but which
     * are not associated with a blog, because of an aborted blog creation.
     */
    public static function clean_post_files() {

        $bloguploadbase = get_config('dataroot') . ArtefactTypeBlogPost::$blogattachmentroot;
        if (!$basedir = opendir($bloguploadbase)) {
            // If the directory doesn't exist, it's likely that it hasn't been 
            // created yet (i.e. nobody has uploaded any files)
            return;
        }

        $currenttime = time();

        // Read through all the upload session directories
        while (false !== ($sessionupload = readdir($basedir))) {
            if ($sessionupload != "." && $sessionupload != "..") {
                $sessionupload = $bloguploadbase . $sessionupload;
                $subdir = opendir($sessionupload);

                // Remove all files older than the session timeout plus two hours.
                while (false !== ($uploadfile = readdir($subdir))) {
                    if ($uploadfile != "." && $uploadfile != "..") {
                        $uploadfile = $sessionupload . '/' . $uploadfile;
                        if ($currenttime - filemtime($uploadfile) > get_config('session_timeout') + 7200) {
                            unlink($uploadfile);
                        }
                    }
                }

                closedir($subdir);
                rmdir($sessionupload);
            }
        }

        closedir($basedir);
    }
}

/**
 * A Blog artefact is a collection of BlogPost artefacts.
 */
class ArtefactTypeBlog extends ArtefactType {

    /**
     * This constant gives the per-page pagination for listing blogs.
     */
    const pagination = 10;


    /**
     * We override the constructor to fetch the extra data.
     *
     * @param integer
     * @param object
     */
    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);

        if (empty($this->id)) {
            $this->container = 1;
        }
    }

    public function is_container() {
        return true;
    }

    /**
     * This function updates or inserts the artefact.  This involves putting
     * some data in the artefact table (handled by parent::commit()), and then
     * some data in the artefact_blog_blog table.
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

        $this->dirty = false;
    }

    /**
     * This function extends ArtefactType::delete() by deleting blog-specific
     * data.
     */
    public function delete() {
        if (empty($this->id)) {
            return;
        }

        // Delete the artefact and all children.
        parent::delete();
    }

    /**
     * Checks that the person viewing this blog is the owner. If not, throws an 
     * AccessDeniedException. Used in the blog section to ensure only the 
     * owners of the blogs can view or change them there. Other people see 
     * blogs when they are placed in views.
     */
    public function check_permission() {
        global $USER;
        if ($USER->get('id') != $this->owner) {
            throw new AccessDeniedException(get_string('youarenottheownerofthisblog', 'artefact.blog'));
        }
    }


    public function describe_size() {
        return $this->count_children() . ' ' . get_string('posts', 'artefact.blog');
    }

    /**
     * Renders a blog for a view. This involves using a tablerenderer to paginate the posts.
     *
     * This uses some legacy stuff from the old views interface, including its 
     * dependence on javascript and the table renderer, which would be nice to 
     * fix using the new pagination stuff some time.
     *
     * @param  array  Options for rendering
     * @return array  A two key array, 'html' and 'javascript'.
     */
    public function render_self($options) {
        // This is because if there are multiple blocks on a page, they need separate
        // js variables.
        $blockid = isset($options['blockid'])
            ? $options['blockid']
            : mt_rand();

        $this->add_to_render_path($options);

        $smarty = smarty_core();
        if (isset($options['viewid'])) {
            $smarty->assign('artefacttitle', '<a href="' . get_config('wwwroot') . 'view/artefact.php?artefact='
                                             . $this->get('id') . '&view=' . $options['viewid']
                                             . '">' . $this->get('title') . '</a>');
        }
        else {
            $smarty->assign('artefacttitle', $this->get('title'));
        }

        $smarty->assign('blockid', $blockid);
        $smarty->assign('options', $options);
        $smarty->assign('enc_id', json_encode($this->id));
        $smarty->assign('limit', self::pagination);
        $smarty->assign('loading_img', theme_get_url('images/loading.gif'));
        $smarty->assign('description', $this->get('description'));

        // Remove unnecessary options for blog posts
        unset($options['hidetitle']);
        $smarty->assign('enc_options', json_encode(json_encode($options)));

        return array('html' => $smarty->fetch('blocktype:blog:blog_render_self.tpl'), 'javascript' => '');
    }

                
    public static function get_icon($options=null) {
    }

    public static function is_singular() {
        return false;
    }

    public static function collapse_config() {
    }

    /**
     * This function returns a list of the given user's blogs.
     *
     * @param User
     * @return array (count: integer, data: array)
     */
    public static function get_blog_list(User $user, $limit = self::pagination, $offset = 0) {
        ($result = get_records_sql_array("
         SELECT id, title, description
         FROM {artefact}
         WHERE owner = ?
          AND artefacttype = 'blog'
         ORDER BY title
         LIMIT ? OFFSET ?", array($user->get('id'), $limit, $offset)))
            || ($result = array());

        $count = (int)get_field('artefact', 'COUNT(*)', 'owner', $user->get('id'), 'artefacttype', 'blog');

        return array($count, $result);
    }

    /**
     * This function creates a new blog.
     *
     * @param User
     * @param array
     */
    public static function new_blog(User $user, array $values) {
        $artefact = new ArtefactTypeBlog();
        $artefact->set('title', $values['title']);
        $artefact->set('description', $values['description']);
        $artefact->set('owner', $user->get('id'));
        $artefact->set('tags', $values['tags']);
        $artefact->commit();
    }

    /**
     * This function updates an existing blog.
     *
     * @param User
     * @param array
     */
    public static function edit_blog(User $user, array $values) {
        if (empty($values['id']) || !is_numeric($values['id'])) {
            return;
        }

        $artefact = new ArtefactTypeBlog($values['id']);
        if ($user->get('id') != $artefact->get('owner')) {
            return;
        }
        
        $artefact->set('title', $values['title']);
        $artefact->set('description', $values['description']);
        $artefact->set('tags', $values['tags']);
        $artefact->commit();
    }

    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default'                                  => $wwwroot . 'artefact/blog/view/?id=' . $id,
            get_string('blogsettings', 'artefact.blog') => $wwwroot . 'artefact/blog/settings/?id=' . $id,
        );
    }
}

/**
 * BlogPost artefacts occur within Blog artefacts
 */
class ArtefactTypeBlogPost extends ArtefactType {

    /**
     * This defines whether the blogpost is published or not.
     *
     * @var boolean
     */
    protected $published = false;

    /**
     * We override the constructor to fetch the extra data.
     *
     * @param integer
     * @param object
     */
    public function __construct($id = 0, $data = null) {
        parent::__construct($id, $data);

        if (!$data) {
            if ($this->id) {
                if ($bpdata = get_record('artefact_blog_blogpost', 'blogpost', $this->id)) {
                    foreach($bpdata as $name => $value) {
                        if (property_exists($this, $name)) {
                            $this->$name = $value;
                        }
                    }
                }
                else {
                    // This should never happen unless the user is playing around with blog post IDs in the location bar or similar
                    throw new ArtefactNotFoundException(get_string('blogpostdoesnotexist', 'artefact.blog'));
                }
            }
        }
    }

    /**
     * This function extends ArtefactType::commit() by adding additional data
     * into the artefact_blog_blogpost table.
     */
    public function commit() {
        if (empty($this->dirty)) {
            return;
        }

        $new = empty($this->id);
      
        parent::commit();

        $this->dirty = true;

        $data = (object)array(
            'blogpost'  => $this->get('id'),
            'published' => ($this->get('published') ? 1 : 0)
        );

        if ($new) {
            insert_record('artefact_blog_blogpost', $data);
        }
        else {
            update_record('artefact_blog_blogpost', $data, 'blogpost');
        }

        $this->dirty = false;
    }

    /**
     * This function extends ArtefactType::delete() by also deleting anything
     * that's in blogpost.
     */
    public function delete() {
        if (empty($this->id)) {
            return;
        }

        delete_records('artefact_blog_blogpost_file', 'blogpost', $this->id);
        delete_records('artefact_blog_blogpost', 'blogpost', $this->id);
      
        parent::delete();
    }

    /**
     * Checks that the person viewing this blog is the owner. If not, throws an 
     * AccessDeniedException. Used in the blog section to ensure only the 
     * owners of the blogs can view or change them there. Other people see 
     * blogs when they are placed in views.
     */
    public function check_permission() {
        global $USER;
        if ($USER->get('id') != $this->owner) {
            throw new AccessDeniedException(get_string('youarenottheownerofthisblogpost', 'artefact.blog'));
        }
    }
  
    public function describe_size() {
        return $this->count_attachments() . ' ' . get_string('attachments', 'artefact.blog');
    }

    public function render_self($options) {
        $smarty = smarty_core();
        if (empty($options['hidetitle'])) {
            if (isset($options['viewid'])) {
                $smarty->assign('artefacttitle', '<a href="' . get_config('wwwroot') . 'view/artefact.php?artefact='
                     . $this->get('id') . '&view=' . $options['viewid']
                     . '">' . $this->get('title') . '</a>');
            }
            else {
                $smarty->assign('artefacttitle', $this->get('title'));
            }
        }

        // We need to make sure that the images in the post have the right viewid associated with them
        $postcontent = $this->get('description');
        if (isset($options['viewid'])) {
            safe_require('artefact', 'file');
            $postcontent = ArtefactTypeFolder::append_view_url($postcontent, $options['viewid']);
        }
        $smarty->assign('artefactdescription', $postcontent);
        $smarty->assign('artefact', $this);
        $attachments = $this->get_attached_files();
        if ($attachments) {
            $this->add_to_render_path($options);
            require_once(get_config('docroot') . 'artefact/lib.php');
            foreach ($attachments as &$attachment) {
                $f = artefact_instance_from_id($attachment->id);
                $attachment->size = $f->describe_size();
                $attachment->iconpath = $f->get_icon(array('id' => $attachment->id, 'viewid' => $options['viewid']));
                $attachment->viewpath = get_config('wwwroot') . 'view/artefact.php?artefact=' . $attachment->id . '&view=' . $options['viewid'];
                $attachment->downloadpath = get_config('wwwroot') . 'artefact/file/download.php?file=' . $attachment->id;
                if (isset($options['viewid'])) {
                    $attachment->downloadpath .= '&id=' . $options['viewid'];
                }
            }
            $smarty->assign('attachments', $attachments);
        }
        $smarty->assign('postedbyon', get_string('postedbyon', 'artefact.blog',
                                                 display_name($this->owner),
                                                 format_date($this->ctime)));
        return array('html' => $smarty->fetch('artefact:blog:render/blogpost_renderfull.tpl'),
                     'javascript' => '');
    }


    /**
     * Returns an array of IDs of artefacts attached to this blogpost
     */
    public function attachment_id_list() {
        if (!$list = get_column('artefact_blog_blogpost_file', 'file', 'blogpost', $this->get('id'))) {
            $list = array();
        }
        return $list;
    }

    public function attach_file($artefactid) {
        $data = new StdClass;
        $data->blogpost = $this->get('id');
        $data->file = $artefactid;
        insert_record('artefact_blog_blogpost_file', $data);

        $data = new StdClass;
        $data->artefact = $artefactid;
        $data->parent = $this->get('id');
        $data->dirty = true;
        insert_record('artefact_parent_cache', $data);

        // Ensure the attachment is recorded as being related to the blog as well
        $data = new StdClass;
        $data->artefact = $artefactid;
        $data->parent = $this->get('parent');
        $data->dirty = 0;

        $where = $data;
        unset($where->dirty);
        ensure_record_exists('artefact_parent_cache', $where, $data);
    }

    public function detach_file($artefactid) {
        delete_records('artefact_blog_blogpost_file', 'blogpost', $this->get('id'), 'file', $artefactid);
        delete_records('artefact_parent_cache', 'parent', $this->get('id'), 'artefact', $artefactid);
        // Remove the record relating the attachment with the blog
        delete_records('artefact_parent_cache', 'parent', $this->get('parent'), 'artefact', $artefactid);
    }


    protected function count_attachments() {
        return count_records('artefact_blog_blogpost_file', 'blogpost', $this->get('id'));
    }


    public static function get_icon($options=null) {
    }

    public static function is_singular() {
        return false;
    }

    public static function collapse_config() {
    }

    /**
     * This function returns a list of the current user's blog posts, for the
     * given blog.
     *
     * @param User
     * @param integer
     * @param integer
     */
    public static function get_posts(User $user, $id, $limit = self::pagination, $offset = 0) {
        ($result = get_records_sql_assoc("
         SELECT a.id, a.title, a.description, a.ctime, a.mtime, bp.published
         FROM {artefact} a
          LEFT OUTER JOIN {artefact_blog_blogpost} bp
           ON a.id = bp.blogpost
         WHERE a.parent = ?
          AND a.artefacttype = 'blogpost'
          AND a.owner = ?
         ORDER BY bp.published ASC, a.ctime DESC
         LIMIT ? OFFSET ?;", array(
            $id,
            $user->get('id'),
            $limit,
            $offset
        )))
            || ($result = array());

        $count = (int)get_field('artefact', 'COUNT(*)', 'owner', $user->get('id'), 
                                'artefacttype', 'blogpost', 'parent', $id);

        // Get the attached files.
        if (count($result) > 0) {
            $idlist = implode(', ', array_map(create_function('$a', 'return $a->id;'), $result));
            $files = get_records_sql_array('
               SELECT
                  bf.blogpost, bf.file, a.artefacttype, a.title, a.description
               FROM {artefact_blog_blogpost_file} bf
                  INNER JOIN {artefact} a ON bf.file = a.id
               WHERE bf.blogpost IN (' . $idlist . ')', '');
            if ($files) {
                foreach ($files as $file) {
                    $result[$file->blogpost]->files[] = $file;
                }
            }
        }

        return array($count, array_values($result));
    }

    /** 
    /**
     * This function creates a new blog post.
     *
     * @param User
     * @param array
     */
    public static function new_post(User $user, array $values) {
        $artefact = new ArtefactTypeBlogPost();
        $artefact->set('title', $values['title']);
        $artefact->set('description', $values['description']);
        $artefact->set('published', $values['published']);
        $artefact->set('owner', $user->get('id'));
        $artefact->set('parent', $values['parent']);
        $artefact->commit();
        return true;
    }

    /** 
     * This function updates an existing blog post.
     *
     * @param User
     * @param array
     */
    public static function edit_post(User $user, array $values) {
        $artefact = new ArtefactTypeBlogPost($values['id']);
        if ($user->get('id') != $artefact->get('owner')) {
            return false;
        }

        $artefact->set('title', $values['title']);
        $artefact->set('description', $values['description']);
        $artefact->set('published', $values['published']);
        $artefact->set('tags', $values['tags']);
        $artefact->commit();
        return true;
    }

    // Where to store temporary blog post files under dataroot
    static $blogattachmentroot = 'artefact/blog/uploads/';


    public static function get_temp_file_path($uploadnumber) {
        return get_config('dataroot') . self::$blogattachmentroot . ($uploadnumber % 256) . '/' . $uploadnumber;
    }


    /**
     * Returns the size of a temporary attachment
     */
    public static function temp_attachment_size($uploadnumber) {
        return filesize(self::get_temp_file_path($uploadnumber));
    }


    /** 
     * This function saves an uploaded file to a temporary directory in dataroot
     * and saves the mime type info in the db for downloadtemp.php to read from.
     */
    public static function save_attachment_temporary($inputname) {
        require_once('uploadmanager.php');
        $um = new upload_manager($inputname);
        db_begin();
        $record = new StdClass;
        $result = new StdClass;
        $result->tempfilename = insert_record('artefact_blog_blogpost_file_pending', $record, 'id', true);

        $tempdir = self::$blogattachmentroot . ($result->tempfilename % 256);
        $result->error = $um->process_file_upload($tempdir, $result->tempfilename);

        if ($result->error) {
            delete_records('artefact_blog_blogpost_file_pending', 'id', $result->tempfilename);
        }
        else {
            $record = (object) array(
                'id'           => $result->tempfilename,
                'oldextension' => $um->original_filename_extension(),
                'filetype'     => $um->file['type'],
            );
            update_record('artefact_blog_blogpost_file_pending', $record);
        }

        db_commit();
        safe_require('artefact', 'file');
        $result->type = ArtefactTypeFile::detect_artefact_type($um->file['type']);
        return $result;
    }


    /**
     * Save a temporary uploaded file to the myfiles area.
     */
    public function save_attachment($uploaddata) {

        // Create the blogfiles folder if it doesn't exist yet.
        $blogfilesid = self::blogfiles_folder_id();
        if (!$blogfilesid) {
            return false;
        }

        global $USER;

        safe_require('artefact', 'file');

        $data = new StdClass;
        $data->title = $uploaddata->title;
        $data->description = $uploaddata->description;
        $data->tags = $uploaddata->tags;
        $data->owner = $USER->get('id');
        $data->parent = $blogfilesid;

        $filedata = get_record('artefact_blog_blogpost_file_pending', 'id', $uploaddata->tempfilename);
        $data->oldextension = $filedata->oldextension;
        $data->filetype = $filedata->filetype;
        
        $path = self::$blogattachmentroot . ($uploaddata->tempfilename % 256) . '/' . $uploaddata->tempfilename;

        if (!$fileid = ArtefactTypeFile::save_file($path, $data)) {
            return false;
        }

        $this->attach_file($fileid);
        delete_records('artefact_blog_blogpost_file_pending', 'id', $uploaddata->tempfilename);
        return $fileid;
    }

    public static function blogfiles_folder_id($create = true) {
        global $USER;
        $name = get_string('blogfilesdirname', 'artefact.blog');
        $description = get_string('blogfilesdirdescription', 'artefact.blog');
        safe_require('artefact', 'file');
        return ArtefactTypeFolder::get_folder_id($name, $description, null, $create, $USER->get('id'));
    }

    // Change the name & description of a user's blogfiles folder when the user changes language pref
    public static function change_language($userid, $oldlang, $newlang) {
        $oldname = get_string_from_language($oldlang, 'blogfilesdirname', 'artefact.blog');
        safe_require('artefact', 'file');
        $blogfiles = ArtefactTypeFolder::get_folder_by_name($oldname, null, $userid, null, null);
        if (empty($blogfiles)) {
            return;
        }

        $name = get_string_from_language($newlang, 'blogfilesdirname', 'artefact.blog');
        $description = get_string_from_language($newlang, 'blogfilesdirdescription', 'artefact.blog');
        if (!empty($name)) {
            $blogfiles = artefact_instance_from_id($blogfiles->id);
            $blogfiles->set('title', $name);
            $blogfiles->set('description', $description);
            $blogfiles->commit();
        }
    }

    /**
     * This function publishes the blog post.
     *
     * @return boolean
     */
    public function publish() {
        if (!$this->id) {
            return false;
        }
        
        $data = (object)array(
            'blogpost'  => $this->id,
            'published' => 1
        );

        if (get_field('artefact_blog_blogpost', 'COUNT(*)', 'blogpost', $this->id)) {
            update_record('artefact_blog_blogpost', $data, 'blogpost');
        }
        else {
            insert_record('artefact_blog_blogpost', $data);
        }
        return true;
    }

    /**
     * This function returns a list of files attached to a post to use
     * when displaying or editing a blog post
     *
     * @return array
     */
    public function get_attached_files() {
        $list = get_records_sql_array('SELECT a.id, a.artefacttype, a.title, a.description 
            FROM {artefact_blog_blogpost_file} f
            INNER JOIN {artefact} a ON a.id = f.file
            WHERE f.blogpost = ?
            ORDER BY a.title', array($this->id));

        // load tags
        if ($list) {
            foreach ( $list as &$attachment ) {
                $attachment->tags = join(', ', get_column('artefact_tag', 'tag', 'artefact', $attachment->id));
            }
        }
        return $list;
    }
    
    public static function get_links($id) {
        $wwwroot = get_config('wwwroot');

        return array(
            '_default'                                  => $wwwroot . 'artefact/blog/post.php?blogpost=' . $id,
        );
    }
}

?>
