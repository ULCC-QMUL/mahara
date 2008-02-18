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
 * @subpackage artefact
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

/**
 * Base artefact plugin class
 * @abstract
 */
abstract class PluginArtefact extends Plugin {

    /** 
     * This function returns a list of classnames 
     * of artefact types this plugin provides.
     * @abstract
     * @return array
     */
    public static abstract function get_artefact_types();

    
    /**
    * This function returns a list of classnames
    * of block types this plugin provides
    * they must match directories inside artefact/$name/blocktype
    * @abstract
    * @return array
    */
    public static abstract function get_block_types();


    /**
     * This function returns the name of the plugin.
     * @abstract
     * @return string
     */
    public static abstract function get_plugin_name();


    /**
     * This function returns an array of menu items
     * to be displayed
     * Each item should be a StdClass object containing -
     * - name language pack key
     * - url relative to wwwroot
     * @return array
     */
    public static function menu_items() {
        return array();
    }
}

/** 
 * Base artefact type class
 * @abstract
 */
abstract class ArtefactType {
    
    protected $dirty;
    protected $parentdirty;
    protected $deleted = false;
    protected $id;
    protected $artefacttype;
    protected $owner;
    protected $container;
    protected $parent;
    protected $ctime;
    protected $mtime;
    protected $atime;
    protected $locked;
    protected $title;
    protected $description;
    protected $note;
    protected $tags = array();

    protected $viewsinstances;
    protected $viewsmetadata;
    protected $childreninstances;
    protected $childrenmetadata;
    protected $parentinstance;
    protected $parentmetadata;

    /** 
     * Constructer. 
     * If an id is supplied, will query the database
     * to build up the basic information about the object.
     * If an id is not supplied, we just create an empty
     * artefact, ready to be filled up
     * @param int $id artefact.id
     */
    public function __construct($id=0, $data=null) {
        if (!empty($id)) {
            if (empty($data)) {
                if (!$data = get_record('artefact','id',$id)) {
                    throw new ArtefactNotFoundException(get_string('artefactnotfound', 'error', $id));
                }
            }
            $this->id = $id;
        }
        else {
            $this->ctime = $this->mtime = time();
            $this->dirty = true;
        }
        if (empty($data)) {
            $data = array();
        }
        foreach ((array)$data as $field => $value) {
            if (property_exists($this, $field)) {
                if (in_array($field, array('atime', 'ctime', 'mtime'))) {
                    $value = strtotime($value);
                } 
                if ($field == 'tags' && !is_array($field)) {
                    $value = preg_split("/\s*,\s*/", trim($value));
                }
                $this->{$field} = $value;
            }
        }

        // load tags
        if ($this->id) {
            $tags = get_column('artefact_tag', 'tag', 'artefact', $this->id);
            if (is_array($tags)) {
                $this->tags = $tags;
            }
        }

        $this->atime = time();
        $this->artefacttype = $this->get_artefact_type();
    }

    public function get_views_instances() {
        // @todo
    }
    
    public function get_views_metadata() {
        // @todo
    }

    public function count_children() {
        return count_records('artefact', 'parent', $this->get('id'));
    }

    public function has_children() {
        if ($this->get_children_metadata()) {
            return true;
        }
        return false;
    }

    public function get_plugin_name() {
        return get_field('artefact_installed_type', 'plugin', 'name', $this->get('artefacttype'));
    }

    /** 
     * This function returns the instances 
     * of all children of this artefact
     * If you just want the basic info, 
     * use {@link get_children_metadata} instead.
     * 
     * @return array of instances.
     */

    public function get_children_instances() {
        if (!isset($this->childreninstances)) {
            $this->childreninstances = false;
            if ($children = $this->get_children_metadata()) {
                $this->childreninstances = array();
                foreach ($children as $child) {
                    $classname = generate_artefact_class_name($child->artefacttype);
                    $instance = new $classname($child->id, $child);
                    $this->childreninstances[] = $instance;
                }
            }
        }
        return $this->childreninstances;
    }

    /**
     * This function returns the db rows 
     * from the artefact table that have this 
     * artefact as the parent.
     * If you want instances, use {@link get_children_instances}
     * but bear in mind this will have a performance impact.
     * 
     * @return array
     */
    public function get_children_metadata() {
        if (!isset($this->childrenmetadata)) {
            $this->childrenmetadata = get_records_array('artefact', 'parent', $this->id);
        }
        return $this->childrenmetadata;
    }

    /**
     * This function returns the instance relating to the parent
     * of this object, or false if there isn't one.
     * If you just want basic information about it,
     * use {@link get_parent_metadata} instead.
     *
     * @return ArtefactType
     */
    public function get_parent_instance() {
        if (!isset($this->parentinstance)) {
            $this->parentinstance = false;
            if ($parent = $this->get_parent_metadata()) {
                $classname = generate_artefact_class_name($parent->artefacttype);
                $this->parentinstance = new $classname($parent->id, $parent);
            }
        }
        return $this->parentinstance;
    }

    /** 
     * This function returns the db row 
     * (if there is one) of the parent
     * artefact for this instance.
     * If you want the instance, use 
     * {@link get_parent_instance} instead.
     * 
     * @return object - db row
     */
    public function get_parent_metadata() {
        return get_record('artefact','id',$this->parent);
    }

    public function get($field) {
        if (!property_exists($this, $field)) {
            throw new InvalidArgumentException("Field $field wasn't found in class " . get_class($this));
        }
        return $this->{$field};
    }

    public function set($field, $value) {
        if (property_exists($this, $field)) {
            if ($this->{$field} != $value) {
                // only set it to dirty if it's changed
                $this->dirty = true;
            }
            $this->{$field} = $value;
            if ($field == 'parent') {
                $this->parentdirty = true;
            }
            $this->mtime = time();
            return true;
        }
        throw new InvalidArgumentException("Field $field wasn't found in class " . get_class($this));
    }
    
    /**
     * Artefact destructor. Calls commit and marks the
     * artefact cache as dirty if necessary.
     *
     * A special case is when the object has just been deleted.  In this case,
     * we do nothing.
     */
    public function __destruct() {
        if ($this->deleted) {
            return;
        }
      
        if (!empty($this->dirty)) {
            $this->commit();
        }
    }
    
    public function is_container() {
        return false;
    }

    /** 
     * This method updates the contents of the artefact table only.  If your
     * artefact has extra information in other tables, you need to override
     * this method, and call parent::commit() in your own function.
     */
    public function commit() {
        if (empty($this->dirty)) {
            return;
        }
        $fordb = new StdClass;
        foreach (get_object_vars($this) as $k => $v) {
            $fordb->{$k} = $v;
            if (in_array($k, array('mtime', 'ctime', 'atime')) && !empty($v)) {
                $fordb->{$k} = db_format_timestamp($v);
            }
        }
        if (empty($this->id)) {
            $this->id = insert_record('artefact', $fordb, 'id', true);
            if (!empty($this->parent)) {
                $this->parentdirty = true;
            }
        }
        else {
            update_record('artefact', $fordb, 'id');
        }

        delete_records('artefact_tag', 'artefact', $this->id);
        if (is_array($this->tags)) {
            foreach (array_unique($this->tags) as $tag) {
                if (empty($tag)) {
                    continue;
                }
                insert_record(
                    'artefact_tag',
                    (object) array(
                        'artefact' => $this->id,
                        'tag'      => $tag,
                    )
                );
            }
        }

        artefact_watchlist_notification($this->id);

        handle_event('saveartefact', $this);

        if (!empty($this->parentdirty)) {
            if (!empty($this->parent) && !record_exists('artefact_parent_cache', 'artefact', $this->id)) {
                $apc = new StdClass;
                $apc->artefact = $this->id;
                $apc->parent = $this->parent;
                $apc->dirty  = 1; // set this so the cronjob will pick it up and go set all the other parents.
                insert_record('artefact_parent_cache', $apc);
            }
            set_field_select('artefact_parent_cache', 'dirty', 1,
                             'artefact = ? OR parent = ?', array($this->id, $this->id));
        }
        $this->dirty = false;
        $this->deleted = false;
        $this->parentdirty = false;
    }

    /** 
     * This function provides basic delete functionality.  It gets rid of the
     * artefact's row in the artefact table, and the tables that reference the
     * artefact table.  It also recursively deletes child artefacts.
     *
     * If your artefact has additional data in another table, you should
     * override this function, but you MUST call parent::delete() after you
     * have done your own thing.
     */
    public function delete() {
        if (empty($this->id)) {
            $this->dirty = false;
            return;
        }
      
        db_begin();

        // Call delete() on children (if there are any)
        if ($children = $this->get_children_instances()) {
            foreach ($children as $child) {
                $child->delete();
            }
        }

        artefact_watchlist_notification($this->id);

        // Delete any references to this artefact from non-artefact places.
        delete_records_select('artefact_parent_cache', 'artefact = ? OR parent = ?', array($this->id, $this->id));

        // Make sure that the artefact is removed from any view blockinstances that have it
        if ($records = get_column('view_artefact', 'block', 'artefact', $this->id)) {
            foreach ($records as $blockid) {
                require_once(get_config('docroot') . 'blocktype/lib.php');
                $bi = new BlockInstance($blockid);
                $bi->delete_artefact($this->id);
            }
        }
        delete_records('view_artefact', 'artefact', $this->id);
        delete_records('artefact_feedback', 'artefact', $this->id);
        delete_records('artefact_tag', 'artefact', $this->id);
      
        // Delete the record itself.
        delete_records('artefact', 'id', $this->id);
        
        handle_event('deleteartefact', $this);

        // Set flags.
        $this->dirty = false;
        $this->parentdirty = true;
        $this->deleted = true;

        db_commit();
    }

    /**
    * this function provides the way to link to viewing very deeply nested artefacts
    * within a view
    *
    * @todo not sure the comment here is appropriate
    */
    public function add_to_render_path(&$options) {
        if (empty($options['path'])) {
            $options['path'] = $this->get('id');
        }
        else {
            $options['path'] .= ',' . $this->get('id');
        }
    }


    /**
     * By default public feedback can be placed on all artefacts.
     * Artefact types which don't want to allow public feedback should
     * redefine this function.
     */
    public function public_feedback_allowed() {
        return true;
    }


    /**
     * By default users are notified of all feedback on artefacts
     * which they own.  Artefact types which want to allow this
     * notification to be turned off should redefine this function.
     */
    public function feedback_notify_owner() {
        return true;
    }


    /**
     * Returns a URL for an icon for the appropriate artefact
     *
     * @param array $options Options for the artefact. The array MUST have the 
     *                       'id' key, representing the ID of the artefact for 
     *                       which the icon is being generated. Other keys 
     *                       include 'size' for a [width]x[height] version of 
     *                       the icon, as opposed to the default 20x20, and 
     *                       'view' for the id of the view in which the icon is 
     *                       being displayed.
     * @abstract 
     * @return string URL for the icon
     */
    public static abstract function get_icon($options=null);
    

    // ******************** STATIC FUNCTIONS ******************** //

    public static function get_instances_by_userid($userid, $order, $offset, $limit) {
        // @todo
    }

    public static function get_metadata_by_userid($userid, $order, $offset, $limit) {
        // @todo
    }

    /**
     * whether a user will have exactly 0 or 1 of this artefact type
     * @abstract
     */
    public static abstract function is_singular();

    /**
     * Whether the 'note' field is for the artefact's private use
     */
    public static function is_note_private() {
        return false;
    }

    /**
     * Returns a list of key => value pairs where the key is either '_default'
     * or a langauge string, and value is a URL linking to that behaviour for
     * this artefact type
     * 
     * @param integer This is the ID of the artefact being linked to
     */
    public static abstract function get_links($id);

    // @TODO maybe uncomment this later and implement it everywhere
    // when we know a bit more about what blocks we want.
    //public abstract function render_self($options);


    /**
    * Returns the printable name of this artefact
    * (used in lists and such)
    */
    public function get_name() {
        return $this->get('title');
    }

    /**
    * Should the artefact be linked to from the listing on my views?
    */
    public function in_view_list() {
        return true;
    }

    /**
    * Returns a short name for the artefact to be used in a list of artefacts in a view 
    */
    public function display_title($maxlen=null) {
        if ($maxlen) {
            return str_shorten($this->get('title'), $maxlen, true);
        }
        return $this->get('title');
    }

    // ******************** HELPER FUNCTIONS ******************** //

    protected function get_artefact_type() {
        $classname = get_class($this);
        
        $type = strtolower(substr($classname, strlen('ArtefactType')));

        if (!record_exists('artefact_installed_type', 'name', $type)) {
            throw new InvalidArgumentException("Classname $classname not a valid artefact type");
        }

        return $type;
    }

    public function to_stdclass() {
       return (object)get_object_vars($this); 
    }

    public static function has_config() {
        return false;
    }

    public static function get_config_options() {
        return array();
    }

    public static function collapse_config() {
        return false;
    }
}

/**
 * Given an artefact plugin name, this function will test if 
 * it's installable or not.  If not, InstallationException will be thrown.
 */
function artefact_check_plugin_sanity($pluginname) {
    $classname = generate_class_name('artefact', $pluginname);
    safe_require('artefact', $pluginname);
    if (!is_callable(array($classname, 'get_artefact_types'))) {
        throw new InstallationException(get_string('artefactpluginmethodmissing', 'error', $classname, 'get_artefact_types'));
    }
    if (!is_callable(array($classname, 'get_block_types'))) {
        throw new InstallationException(get_string('artefactpluginmethodmissing', 'error', $classname, 'get_block_types'));
    }
    $types = call_static_method($classname, 'get_artefact_types');
    foreach ($types as $type) {
        $typeclassname = generate_artefact_class_name($type);
        if (get_config('installed')) {
            if ($taken = get_record_select('artefact_installed_type', 'name = ? AND plugin != ?', 
                                           array($type, $pluginname))) {
                throw new InstallationException(get_string('artefacttypenametaken', 'error', $type, $taken->plugin));
            }
        }
        if (!class_exists($typeclassname)) {
            throw new InstallationException(get_string('classmissing', 'error', $typeclassname, $type, $plugin));
        }
    }
    $types = call_static_method($classname, 'get_block_types');
    foreach ($types as $type) {
        $pluginclassname = generate_class_name('blocktype', 'image');
        if (get_config('installed')) {
            if (table_exists(new XMLDBTable('blocktype_installed')) && $taken = get_record_select('blocktype_installed', 
                'name = ? AND artefactplugin != ? ',
                array($type, $pluginname))) {
                throw new InstallationException(get_string('blocktypenametaken', 'error', $type,
                    ((!empty($taken->artefactplugin)) ? $taken->artefactplugin : get_string('system'))));
            }
        }
        // go look for the lib file to include
        try {
            safe_require('blocktype', $pluginname . '/' . $type);
        }
        catch (Exception $_e) {
            throw new InstallationException(get_string('blocktypelibmissing', 'error', $type, $pluginname));
        }
        if (!class_exists($pluginclassname)) {
            throw new InstallationException(get_string('classmissing', 'error', $pluginclassname, $type, $pluginname));
        }
    }
}

function rebuild_artefact_parent_cache_dirty() {
    // this will give us a list of artefacts, as the first returned column
    // is not unqiue, but that's ok, it's what we want.
    if (!$dirty = get_records_array('artefact_parent_cache', 'dirty', 1, '', 'DISTINCT(artefact)')) {
        return;
    }
    db_begin();
    delete_records('artefact_parent_cache', 'dirty', 1);
    foreach ($dirty as $d) {
        $parentids = array();
        $current = $d->artefact;
        delete_records('artefact_parent_cache', 'artefact', $current);
        $parentids = array_keys(artefact_get_parents_for_cache($current));
        foreach ($parentids as $p) {
            $apc = new StdClass;
            $apc->artefact = $d->artefact;
            $apc->parent   = $p;
            $apc->dirty    = 0;
            insert_record('artefact_parent_cache', $apc);
        }
    }
    db_commit();
}

function rebuild_artefact_parent_cache_complete() {
    db_begin();
    delete_records('artefact_parent_cache');
    if ($artefactids = get_column('artefact', 'id')) {
        foreach ($artefactids as $id) {
            $parentids = array_keys(artefact_get_parents_for_cache($id));
            foreach ($parentids as $p) {
                $apc = new StdClass;
                $apc->artefact = $id;
                $apc->parent   = $p;
                $apc->dirty    = 0;
                insert_record('artefact_parent_cache', $apc);
            }
        }
    }
    db_commit();
}


function artefact_get_parents_for_cache($artefactid, &$parentids=false) {
    static $blogsinstalled;
    if (!isset($blogsinstalled)) {
        $blogsinstalled = get_field('artefact_installed', 'active', 'name', 'blog');
    }
    $current = $artefactid;
    if (empty($parentids)) { // first call
        $parentids = array();
    }
    while (true) {
        if (!$parent = get_record('artefact', 'id', $current)) {
            break;
        }
        // get any blog posts it may be attached to 
        if (($parent->artefacttype == 'file' || $parent->artefacttype == 'image') && $blogsinstalled
            && $associated = get_column('artefact_blog_blogpost_file', 'blogpost', 'file', $parent->id)) {
            foreach ($associated as $a) {
                $parentids[$a] = 1;
                artefact_get_parents_for_cache($a, $parentids);
            }
        }
        if (!$parent->parent) {
            break;
        }
        $parentids[$parent->parent] = 1;
        $current = $parent->parent;
    }
    return $parentids;
}

function artefact_can_render_to($type, $format) {
    return in_array($format, call_static_method(generate_artefact_class_name($type), 'get_render_list'));
}

function artefact_instance_from_id($id) {
    $sql = 'SELECT a.*, i.plugin 
            FROM {artefact} a 
            JOIN {artefact_installed_type} i ON a.artefacttype = i.name
            WHERE a.id = ?';
    if (!$data = get_record_sql($sql, array($id))) {
        throw new ArtefactNotFoundException(get_string('artefactnotfound', 'mahara', $id));
    }
    $classname = generate_artefact_class_name($data->artefacttype);
    safe_require('artefact', $data->plugin);
    return new $classname($id, $data);
}

/**
 * This function will return an instance of any "0 or 1" artefact. That is any
 * artefact that each user will have at most one instance of (e.g. profile
 * fields).
 *
 * @param string Is the type of artefact to return
 * @param string The user_id who owns the fetched artefact. (defaults to the
 * current user)
 *
 * @returns ArtefactType Instance of the artefact.
 */
function artefact_instance_from_type($artefact_type, $user_id=null) {
    global $USER;

    if ($user_id === null) {
        $user_id = $USER->get('id');
    }

    safe_require('artefact', get_field('artefact_installed_type', 'plugin', 'name', $artefact_type));

    if (!call_static_method(generate_artefact_class_name($artefact_type), 'is_singular')) {
        throw new ArtefactNotFoundException("This artefact type is not a 'singular' artefact type");
    }

    // email is special (as in the user can have more than one of them, but
    // it's treated as a 0 or 1 artefact and the primary is returned
    if ($artefact_type == 'email') {
        $id = get_field('artefact_internal_profile_email', 'artefact', 'owner', $user_id, 'principal', 1);

        if (!$id) {
            throw new ArtefactNotFoundException("Artefact of type '${artefact_type}' doesn't exist");
        }

        $classname = generate_artefact_class_name($artefact_type);
        safe_require('artefact', 'internal');
        return new $classname($id);
    }
    else {
        $sql = 'SELECT a.*, i.plugin 
                FROM {artefact} a 
                JOIN {artefact_installed_type} i ON a.artefacttype = i.name
                WHERE a.artefacttype = ? AND a.owner = ?';
        if (!$data = get_record_sql($sql, array($artefact_type, $user_id))) {
            throw new ArtefactNotFoundException("Artefact of type '${artefact_type}' doesn't exist");
        }

        $classname = generate_artefact_class_name($artefact_type);
        safe_require('artefact', $data->plugin);
        return new $classname($data->id, $data);
    }

    throw new ArtefactNotFoundException("Artefact of type '${artefact_type}' doesn't exist");
}

function artefact_watchlist_notification($artefactid) {
    // gets all the views containing this artefact or a parent of this artefact and creates a watchlist activity for each view
    if ($views = get_column_sql('SELECT DISTINCT view FROM {view_artefact} WHERE artefact IN (' . implode(',', array_merge(array_keys(artefact_get_parents_for_cache($artefactid)), array($artefactid))) . ')')) {
        foreach ($views as $view) {
            activity_occurred('watchlist', (object)array('view' => $view));
        }
    }
}

?>
