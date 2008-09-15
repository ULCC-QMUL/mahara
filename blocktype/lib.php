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
 * @subpackage blocktype
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();


/**
 * Base blocktype plugin class
 * @abstract
 */
abstract class PluginBlocktype extends Plugin {

    public static function extra_xmldb_substitution($xml) {
        return str_replace(
        '<!-- PLUGINTYPE_INSTALLED_EXTRAFIELDS -->', 
        ' <FIELD NAME="artefactplugin" TYPE="char" LENGTH="255" NOTNULL="false" />',
        str_replace(
            '<!-- PLUGINTYPE_INSTALLED_EXTRAKEYS -->', 
            '<KEY NAME="artefactpluginfk" TYPE="foreign" FIELDS="artefactplugin" REFTABLE="artefact_installed" REFFIELDS="name" />',
            $xml
            )
        );
    }

    public static abstract function get_title();

    public static abstract function get_description();

    public static abstract function get_categories();

    public static abstract function render_instance(BlockInstance $instance);

    /**
     * If this blocktype contains artefacts, and uses the artefactchooser 
     * Pieform element to choose them, this method must return the definition 
     * for the element.
     *
     * This is used in view/artefactchooser.json.php to build pagination for 
     * the element.
     *
     * The element returned MUST have the name key set to either 'artefactid' 
     * or 'artefactids', depending on whether 'selectone' is true or false.
     *
     * The element must also have the 'blocktype' key set to the name of the 
     * blocktype that the form is for.
     *
     * @param mixed $default The default value for the element
     */
    public static abstract function artefactchooser_element($default=null);

    /**
    * subclasses can override this if they need to do something a bit special
    * eg more than just what the BlockInstance->delete function does.
    * 
    * @param BlockInstance $instance
    */
    public static function delete_instance(BlockInstance $instance) { }

    /**
    * This function must be implemented in the subclass if it has config
    */
    public static function instance_config_form(BlockInstance $instance) {
        throw new SystemException(get_string('blocktypemissingconfigform', 'error', $instance->get('blocktype')));
    }

    /**
     * Blocktype plugins can implement this to perform custom pieform 
     * validation, should they need it
     */
    public static function instance_config_validate(Pieform $form, $values) { }

    /**
    * most Blocktype plugins will attach to artefacts.
    * They should implement this function to keep a list of which ones
    * note that it should just handle top level artefacts.
    * the cache rebuilder will figure out the children.
    *
    * @return array ids of artefacts in this block instance
    */
    public static function get_artefacts(BlockInstance $instance) {
        $configdata = $instance->get('configdata');
        if (isset($configdata['artefactids']) && is_array($configdata['artefactids'])) {
            return $configdata['artefactids'];
        }
        if (!empty($configdata['artefactid'])) {
            return array($configdata['artefactid']);
        }
        return false;
    }

    /** 
    * this is different to has_config - has_config is plugin wide config settings
    * this is specific to this TYPE of plugin and relates to whether individual instances
    * can be configured within a view
    */
    public static function has_instance_config() {
        return false;
    }

    public static function category_title_from_name($name) {
        $title = get_string('blocktypecategory.'. $name, 'view');
        if (strpos($title, '[[') !== 0) {
            return $title;
        }
        // else we're an artefact
        return get_string('pluginname', 'artefact.' . $name);
    }

    public static function get_blocktypes_for_category($category) {
        $sql = 'SELECT bti.name, bti.artefactplugin
            FROM {blocktype_installed} bti 
            JOIN {blocktype_installed_category} btic ON btic.blocktype = bti.name
            WHERE btic.category = ?
            ORDER BY bti.name';
        if (!$bts = get_records_sql_array($sql, array($category))) {
            return false;
        }

        $blocktypes = array();

        foreach ($bts as $bt) {
            $namespaced = blocktype_single_to_namespaced($bt->name, $bt->artefactplugin);
            safe_require('blocktype', $namespaced); 
            $temp = array(
                'name'           => $bt->name,
                'title'          => call_static_method(generate_class_name('blocktype', $namespaced), 'get_title'),
                'description'    => call_static_method(generate_class_name('blocktype', $namespaced), 'get_description'),
                'artefactplugin' => $bt->artefactplugin,
                'thumbnail_path' => get_config('wwwroot') . 'thumb.php?type=blocktype&bt=' . $bt->name . ((!empty($bt->artefactplugin)) ? '&ap=' . $bt->artefactplugin : ''),
            );
            $blocktypes[] = $temp;
        }
        return $blocktypes;
    }

    public static function copy_allowed($ownertype=null) {
        return true;
    }

    public static function copy_artefacts_allowed($ownertype=null) {
        return true;
    }

    public static function default_artefact_config($ownertype=null, $ownerid=null, $configdata) {
        if (isset($configdata['artefactid'])) {
            $configdata['artefactid'] = null;
        }
        if (isset($configdata['artefactids'])) {
            $configdata['artefactids'] = array();
        }
        return $configdata;
    }

}

abstract class SystemBlockType extends PluginBlockType {

    public final static function get_artefacts(BlockInstance $instance) {
        return array();
    }

    public final static function artefactchooser_element($default=null) {
    }

}


class BlockInstance {

    private $id;
    private $blocktype;
    private $title;
    private $configdata;
    private $dirty;
    private $view;
    private $view_obj;
    private $column;
    private $order; 
    private $canmoveleft;
    private $canmoveright;
    private $canmoveup;
    private $canmovedown;
    private $maxorderincolumn; 

    public function __construct($id=0, $data=null) {
         if (!empty($id)) {
            if (empty($data)) {
                if (!$data = get_record('block_instance','id',$id)) {
                    // TODO: 1) doesn't need get string here if this is the 
                    // only place the exception is used - can be done in the 
                    // class itself. 2) String needs to be defined, or taken 
                    // from lang/*/view.php where there is already one for it
                    throw new BlockInstanceNotFoundException(get_string('blockinstancenotfound', 'error', $id));
                }
            }
            $this->id = $id;
        }
        else {
            $this->dirty = true;
        }
        if (empty($data)) {
            $data = array();
        }
        foreach ((array)$data as $field => $value) {
            if (property_exists($this, $field)) {
                $this->{$field} = $value;
            }
        }
    }

    public function get($field) {
        if (!property_exists($this, $field)) {
            throw new InvalidArgumentException("Field $field wasn't found in class " . get_class($this));
        }
        if ($field == 'configdata') {
            // make sure we unserialise it
            if (!is_array($this->configdata)) {
                $this->configdata = unserialize($this->configdata);
            }
        }
        if (strpos($field, 'canmove') === 0) {
            return $this->can_move(substr($field, strlen('canmove'))); // needs to be calculated.
        }
        if ($field == 'maxorderincolumn') {
            // only fetch this when we're asked, it's a db query.
            if (empty($this->maxorderincolumn)) {
                $this->maxorderincolumn = get_field(
                    'block_instance', 
                    'max("order")', 
                    'view', $this->view, 'column', $this->column);
            }
        }
        return $this->{$field};
    }

    public function set($field, $value) {
        if (property_exists($this, $field)) {
            if ($field == 'configdata') {
                $value = serialize($value);
            }
            if ($this->{$field} !== $value) {
                // only set it to dirty if it's changed
                $this->dirty = true;
                $this->{$field} = $value;
            }
            return true;
        }
        throw new ParamOutOfRangeException("Field $field wasn't found in class " . get_class($this));
    }

    public function instance_config_store(Pieform $form, $values) {
        global $SESSION;

        // Destroy form values we don't care about
        unset($values['sesskey']);
        unset($values['blockinstance']);
        unset($values['action_configureblockinstance_id_' . $this->get('id')]);

        if (is_callable(array(generate_class_name('blocktype', $this->get('blocktype')), 'instance_config_save'))) {
            $values = call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'instance_config_save', $values);
        }

        $title = (isset($values['title'])) ? $values['title'] : '';
        unset($values['title']);
        $this->set('configdata', $values);

        $blocktypeclass = generate_class_name('blocktype', $this->get('blocktype'));
        if (!$title && $title !== '0' && method_exists($blocktypeclass, 'get_instance_title')) {
            // Get the default title for the block if one isn't set
            $title = call_static_method($blocktypeclass, 'get_instance_title', $this);
        }

        $this->set('title', $title);
        $this->commit();

        $SESSION->add_ok_msg(get_string('blockinstanceconfiguredsuccessfully', 'view'));
        $new = param_boolean('new');
        $category = param_alpha('c', '');
        redirect('/view/blocks.php?id=' . $this->get('view') . '&c=' . $category . '&new=' . $new);
    }

    /**
     * Builds the HTML for the block, inserting the blocktype content at the 
     * appropriate place
     *
     * @param bool $configure Whether to render the block instance in configure 
     *                        mode
     * @return array Array with two keys: 'html' for raw html, 'javascript' for
     *               javascript to run
     */
    public function render_editing($configure=false, $new=false) {
        safe_require('blocktype', $this->get('blocktype'));
        $js = '';
        if ($configure) {
            list($content, $js) = array_values($this->build_configure_form($new));
        }
        else {
            try {
                $content = call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'render_instance', $this);
            }
            catch (ArtefactNotFoundException $e) {
                // Whoops - where did the image go? There is possibly a bug 
                // somewhere else that meant that this blockinstance wasn't 
                // told that the image was previously deleted. But the block 
                // instance is not allowed to treat this as a failure
                log_debug('Artefact not found when rendering a block instance. '
                    . 'There might be a bug with deleting artefacts of this type? '
                    . 'Original error follows:');
                log_debug($e->getMessage());
                $content = '';
            }
        }

        $movecontrols = array();
        if (!defined('JSON')) {
            if ($this->get('canmoveleft')) {
                $movecontrols[] = array(
                    'column' => $this->get('column') - 1,
                    'order'  => $this->get('order'),
                    'title'  => get_string('moveblockleft', 'view'),
                    'arrow'  => '&larr;',
                    'dir'    => 'left',
                );
            }
            if ($this->get('canmovedown')) {
                $movecontrols[] = array(
                    'column' => $this->get('column'),
                    'order'  => $this->get('order') + 1,
                    'title'  => get_string('moveblockdown', 'view'),
                    'arrow'  => '&darr;',
                    'dir'    => 'down',
                );
            }
            if ($this->get('canmoveup')) {
                $movecontrols[] = array(
                    'column' => $this->get('column'),
                    'order'  => $this->get('order') - 1,
                    'title'  => get_string('moveblockup', 'view'),
                    'arrow'  => '&uarr;',
                    'dir'    => 'up',
                );
            }
            if ($this->get('canmoveright')) {
                $movecontrols[] = array(
                    'column' => $this->get('column') + 1,
                    'order'  => $this->get('order'),
                    'title'  => get_string('moveblockright', 'view'),
                    'arrow'  => '&rarr;',
                    'dir'    => 'right',
                );
            }
        }
        $smarty = smarty_core();

        $smarty->assign('id',     $this->get('id'));
        $smarty->assign('title',  $this->get('title'));
        $smarty->assign('column', $this->get('column'));
        $smarty->assign('order',  $this->get('order'));

        $smarty->assign('movecontrols', $movecontrols);
        $smarty->assign('configurable', call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'has_instance_config'));
        $smarty->assign('content', $content);
        $smarty->assign('javascript', defined('JSON'));
        $smarty->assign('strnotitle', get_string('notitle', 'view'));

        return array('html' => $smarty->fetch('view/blocktypecontainerediting.tpl'), 'javascript' => $js);
    }

    public function render_viewing() {

        safe_require('blocktype', $this->get('blocktype'));
        try {
            $content = call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'render_instance', $this);
        }
        catch (ArtefactNotFoundException $e) {
            // Whoops - where did the image go? There is possibly a bug 
            // somewhere else that meant that this blockinstance wasn't 
            // told that the image was previously deleted. But the block 
            // instance is not allowed to treat this as a failure
            log_debug('Artefact not found when rendering a block instance. '
                . 'There might be a bug with deleting artefacts of this type? '
                . 'Original error follows:');
            log_debug($e->getMessage());
            $content = '';
        }

        $smarty = smarty_core();
        $smarty->assign('id',     $this->get('id'));
        $smarty->assign('title',  $this->get('title'));

        // If this block is for just one artefact, we set the title of the 
        // block to be a link to view more information about that artefact
        $configdata = $this->get('configdata');
        if (!empty($configdata['artefactid'])) {
            $smarty->assign('viewartefacturl', get_config('wwwroot') . 'view/artefact.php?artefact='
                . $configdata['artefactid'] . '&view=' . $this->get('view'));
        }

        $smarty->assign('content', $content);
        
        return $smarty->fetch('view/blocktypecontainerviewing.tpl');
    }

    /**
     * Builds the configuration pieform for this blockinstance
     *
     * @return array Array with two keys: 'html' for raw html, 'javascript' for
     *               javascript to run
     */
    public function build_configure_form($new=false) {
        safe_require('blocktype', $this->get('blocktype'));
        $elements = call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'instance_config_form', $this);

        $blocktypeclass = generate_class_name('blocktype', $this->get('blocktype'));
        if ($this->get('title') != call_static_method($blocktypeclass, 'get_title')) {
            // If the title for this block has been set to something other than 
            // the block title, use it unconditionally
            $title = $this->get('title');
        }
        else if (method_exists($blocktypeclass, 'get_instance_title')) {
            // Block types can specify a default title for a block
            $title = call_static_method($blocktypeclass, 'get_instance_title', $this);
        }
        else {
            // A block that doesn't have a method for setting an instance 
            // title, and hasn't had its title changed (e.g. a new textbox)
            $title = $this->get('title');
        }

        $elements = array_merge(
            array(
                'title' => array(
                    'type' => 'text',
                    'title' => get_string('blocktitle', 'view'),
                    'description' => (method_exists($blocktypeclass, 'get_instance_title'))
                        ? get_string('defaulttitledescription', 'blocktype.' . blocktype_name_to_namespaced($this->get('blocktype'))) : null,
                    'defaultvalue' => $title,
                ),
            ),
            $elements
        );

        if ($new) {
            $cancel = get_string('remove');
            $elements['removeoncancel'] = array('type' => 'hidden', 'value' => 1);
            $elements['sure']           = array('type' => 'hidden', 'value' => 1);
        }
        else {
            $cancel = get_string('cancel');
        }

        // Add submit/cancel buttons
        $elements['action_configureblockinstance_id_' . $this->get('id')] = array(
            'type' => 'submitcancel',
            'value' => array(get_string('save'), $cancel),
            'goto' => View::make_base_url(),
        );

        $form = array(
            'name' => 'cb_' . $this->get('id'),
            'renderer' => 'maharatable',
            'validatecallback' => array(generate_class_name('blocktype', $this->get('blocktype')), 'instance_config_validate'),
            'successcallback'  => array($this, 'instance_config_store'),
            'elements' => $elements,
            'viewgroup' => $this->get_view()->get('group'),
            'viewinstitution' => $this->get_view()->get('institution'),
        );

        if (param_variable('action_acsearch_id_' . $this->get('id'), false)) {
            $form['validate'] = false;
        }

        require_once('pieforms/pieform.php');
        $pieform = new Pieform($form);

        if ($pieform->is_submitted()) {
            global $SESSION;
            $SESSION->add_error_msg(get_string('errorprocessingform'));
        }
        else {
            // This is a bit hacky. Because pieforms will take values from
            // $_POST before 'defaultvalue's of form elements, we need to nuke
            // all of the post values for the form. The situation where this
            // becomes relevant is when someone clicks the configure button for
            // one block, then immediately configures another block
            foreach (array_keys($elements) as $name) {
                unset($_POST[$name]);
            }
        }

        $html = $pieform->build(false);

        // We need to load any javascript required for the pieform. We do this
        // by checking for an api function that has been added especially for 
        // the purpose, but that is not part of Pieforms. Maybe one day later 
        // it will be though
        $js = '';
        foreach ($elements as $key => $element) {
            $element['name'] = $key;
            $function = 'pieform_element_' . $element['type'] . '_views_js';
            if (is_callable($function)) {
                $js .= call_user_func_array($function, array($pieform, $element));
            }
        }

        return array('html' => $html, 'javascript' => $js);
    }

    public function commit() {
        if (empty($this->dirty)) {
            return;
        }
        $fordb = new StdClass;
        foreach (get_object_vars($this) as $k => $v) {
            // The configdata is initially fetched from the database in string 
            // form. Calls to get() will convert it to an array on the fly. We 
            // ensure that it is a string again here
            if ($k == 'configdata' && is_array($v)) {
                $fordb->{$k} = serialize($v);
            }
            else {
                $fordb->{$k} = $v;
            }
        }
        if (empty($this->id)) {
            $this->id = insert_record('block_instance', $fordb, 'id', true);
        }
        else {
            update_record('block_instance', $fordb, 'id');
        }

        $this->rebuild_artefact_list();

        // Tell stuff about this
        handle_event('blockinstancecommit', $this);

        $this->dirty = false;
    }

    public function rebuild_artefact_list() {
        db_begin();
        delete_records('view_artefact', 'block', $this->id);
        safe_require('blocktype', $this->get('blocktype'));
        if (!$artefacts = call_static_method(
            generate_class_name('blocktype', $this->get('blocktype')),
            'get_artefacts', $this)) {
            db_commit();
            return true;
        }
            
        $va = new StdClass;
        $va->view = $this->get('view');
        $va->block = $this->id;

        foreach ($artefacts as $id) {
            $va->artefact = $id;
            insert_record('view_artefact', $va);
        }

        db_commit();
    }

    /**
     * @return View the view object this block instance is in
     */
    public function get_view() {
        if (empty($this->view_obj)) {
            $this->view_obj = new View($this->get('view'));
        }
        return $this->view_obj;
    }

    public function can_move($direction) {
        switch ($direction) {
            case 'left':
                return ($this->column > 1);
            case 'right':
                return ($this->column < $this->get_view()->get('numcolumns'));
            case 'up':
                return ($this->order > 1);
                break;
            case 'down':
                return ($this->order < $this->get('maxorderincolumn'));
            default:
                throw new InvalidArgumentException(get_string('invaliddirection', 'error', $direction));
        }
    }

    public function delete() {
        if (empty($this->id)) {
            $this->dirty = false;
            return;
        }
        
        delete_records('view_artefact', 'block', $this->id);
        delete_records('block_instance', 'id', $this->id);

        $this->dirty = false;
        safe_require('blocktype', $this->get('blocktype'));
        call_static_method(generate_class_name('blocktype', $this->get('blocktype')), 'delete_instance', $this);
    }

    /**
     * Deletes an artefact from the blockinstance.
     *
     * This is implemented in the baseclass by looking for arrays in the block 
     * instance configuration called 'artefactid' or 'artefactids', and 
     * removing the one we were looking to delete. This means two things:
     *
     * 1) In order to not have to re-implement this method for new blocktypes, 
     *    your blocktype should ALWAYS store its artefact IDs in the config data 
     *    value 'artefactid' or in the array 'artefactids'
     * 2) The block must ALWAYS continue to work even when artefacts are 
     *    removed from it
     */
    public function delete_artefact($artefact) {
        $configdata = $this->get('configdata');
        $changed = false;

        if (isset($configdata['artefactid'])) {
            if ($configdata['artefactid'] == $artefact) {
                $configdata['artefactid'] = null;
            }
            $changed = true;
        }

        if (isset($configdata['artefactids']) && is_array($configdata['artefactids'])) {
            $configdata['artefactids'] = array_diff($configdata['artefactids'], array($artefact));
            $changed = true;
        }

        if ($changed) {
            $this->set('configdata', $configdata);

            // We would commit here but we don't want to rebuild the artefact list
            set_field('block_instance', 'configdata', serialize($configdata), 'id', $this->get('id'));
        }
    }


    /** 
     * Get an artefact instance, checking republish permissions
     */
    public function get_artefact_instance($id) {
        require_once(get_config('docroot') . 'artefact/lib.php');
        $a = artefact_instance_from_id($id);
        $viewowner = $this->get_view()->get('owner');
        $group = $a->get('group');
        if ($viewowner && $group) {
            // Only group artefacts can have artefact_access_role & artefact_access_usr records
            if (!count_records_sql("SELECT COUNT(ar.can_republish) FROM {artefact_access_role} ar
                INNER JOIN {group_member} g ON ar.role = g.role
                WHERE ar.artefact = ? AND g.member = ? AND g.group = ? AND ar.can_republish = 1", array($a->get('id'), $viewowner, $group))
                and !record_exists('artefact_access_usr', 'usr', $viewowner, 'artefact', $a->get('id'), 'can_republish', 1)) {
                throw new ArtefactNotFoundException(get_string('artefactnotpublishable', 'mahara', $id, $this->get_view()->get('id')));
            }
        }
        return $a;
    }


    public function copy(&$view, &$artefactcopies) {
        $blocktypeclass = generate_class_name('blocktype', $this->get('blocktype'));

        $copyconfig = $view->get('copyconfig');
        if (!$copyconfig->sameowner && !call_static_method($blocktypeclass, 'copy_allowed', $copyconfig->ownertype)) {
            return false;
        }
        $newblock = new BlockInstance(0, array(
            'blocktype'  => $this->get('blocktype'),
            'title'      => $this->get('title'),
            'view'       => $view->get('id'),
            'column'     => $this->get('column'),
            'order'      => $this->get('order'),
        ));

        $configdata = $this->get('configdata');
        if ($copyconfig->sameowner) {
            $newblock->set('configdata', $configdata);
            $newblock->commit();
            return true;
        }
        $artefactids = PluginBlockType::get_artefacts($this);
        if (!empty($artefactids) && call_static_method($blocktypeclass, 'copy_artefacts_allowed', $copyconfig->ownertype)) {
            // Copy artefacts & put the new artefact ids into the new
            // block.
            // Get all children of the artefacts.
            $descendants = artefact_get_descendants($artefactids);
            foreach ($descendants as $aid) {
                if (!isset($artefactcopies[$aid])) {
                    // Copy the artefact
                    $a = artefact_instance_from_id($aid);
                    // Save the id of the original artefact's parent
                    $artefactcopies[$aid] = (object) array('oldid' => $aid, 'oldparent' => $a->get('parent'));
                    $artefactcopies[$aid]->newid = $a->copy_for_new_owner($view->get('owner'), $view->get('group'), $view->get('institution'));
                }
            }
            if (isset($configdata['artefactid'])) {
                $configdata['artefactid'] = $artefactcopies[$configdata['artefactid']]->newid;
            }
            else {
                foreach ($configdata['artefactids'] as &$oldid) {
                    $oldid = $artefactcopies[$oldid]->newid;
                }
            }
        }
        else {
            $configdata = call_static_method($blocktypeclass, 'default_artefact_config', $copyconfig->ownertype, $copyconfig->ownerid, $configdata);
        }
        $newblock->set('configdata', $configdata);
        $newblock->commit();
        return true;
    }

}


?>
