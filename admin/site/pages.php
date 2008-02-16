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
 * @subpackage admin
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configsite/sitepages');
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'admin');
define('SECTION_PAGE', 'sitepages');

require(dirname(dirname(dirname(__FILE__))).'/init.php');
require_once('pieforms/pieform.php');
define('TITLE', get_string('editsitepages', 'admin'));

$sitepages = get_records_array('site_content');
$pageoptions = array();
foreach ($sitepages as $page) {
    $pageoptions[$page->name] = get_string($page->name, 'admin');
}
asort($pageoptions);

$getstring = array('discardpageedits' => json_encode(get_string('discardpageedits', 'admin')));

$form = pieform(array(
    'name'              => 'editsitepage',
    'jsform'            => true,
    'jssuccesscallback' => 'contentSaved',
    'elements'          => array(
        'pagename'    => array(
            'type'    => 'select',
            'title'   => get_string('pagename', 'admin'),
            'defaultvalue' => 'home',
            'options' => $pageoptions
        ),
        'pagetext' => array(
            'name'        => 'pagetext',
            'type'        => 'wysiwyg',
            'rows'        => 20, // TODO: make rows/cols bigger. But only once the CSS style to force the width of the wysiwyg editor dies!
            'cols'        => 80,
            'title'       => get_string('pagetext', 'admin'),
            'rules'       => array(
                'required' => true
            )
        ),
        'submit' => array(
            'type'  => 'submit',
            'value' => get_string('savechanges', 'admin')
        ),
    )
));


function editsitepage_submit(Pieform $form, $values) {
    global $USER;
    $data = new StdClass;
    $data->name    = $values['pagename'];
    $data->content = $values['pagetext'];
    $data->mtime   = db_format_timestamp(time());
    $data->mauthor = $USER->get('id');
    try {
        update_record('site_content', $data, 'name');
    }
    catch (SQLException $e) {
        $form->reply(PIEFORM_ERR, get_string('savefailed', 'admin'));
    }
    $form->reply(PIEFORM_OK, get_string('pagesaved', 'admin'));
}

$smarty = smarty(array('adminsitepages'), array(), array('admin' => array('discardpageedits')));
$smarty->assign('pageeditform', $form);
$smarty->assign('heading', get_string('editsitepages', 'admin'));
$smarty->display('admin/site/pages.tpl');

?>
