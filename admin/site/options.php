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
 * @subpackage admin
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('ADMIN', 1);
define('MENUITEM', 'configsite');
define('SUBMENUITEM', 'siteoptions');

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
require_once('pieforms/pieform.php');

$langoptions = get_languages();
$themeoptions = get_themes();
$yesno = array(true  => get_string('yes'),
               false => get_string('no'));

$siteoptionform = pieform(array(
    'name'     => 'siteoptions',
    'ajaxpost' => true,
    'renderer' => 'table',
    'elements' => array(
        'sitename' => array(
            'type'         => 'text',
            'title'        => get_string('sitename','admin'),
            'description'  => get_string('sitenamedescription','admin'),
            'defaultvalue' => get_config('sitename'),
        ),
        'language' => array(
            'type'         => 'select',
            'title'        => get_string('language','admin'),
            'description'  => get_string('sitelanguagedescription','admin'),
            'defaultvalue' => get_config('language'),
            'collapseifoneoption' => true,
            'options'      => $langoptions,
        ),
        'theme' => array(
            'type'         => 'select',
            'title'        => get_string('theme','admin'),
            'description'  => get_string('sitethemedescription','admin'),
            'defaultvalue' => get_config('theme'),
            'collapseifoneoption' => true,
            'options'      => $themeoptions,
        ),
        'viruschecking' => array(
            'type'         => 'checkbox',
            'title'        => get_string('viruschecking','admin'),
            'description'  => get_string('viruscheckingdescription','admin'),
            'defaultvalue' => get_config('viruschecking'),
        ),
        'pathtoclam' => array(
            'type'         => 'text',
            'title'        => get_string('pathtoclam','admin'),
            'description'  => get_string('pathtoclamdescription','admin'),
            'defaultvalue' => get_config('pathtoclam'),
        ),
        'sessionlifetime' => array(
            'type'         => 'text',
            'size'         => 4,
            'title'        => get_string('sessionlifetime','admin'),
            'description'  => get_string('sessionlifetimedescription','admin'),
            'defaultvalue' => get_config('session_timeout') / 60,
        ),
        'allowpublicviews' => array(
            'type'         => 'select',
            'title'        => get_string('allowpublicviews','admin'),
            'description'  => get_string('allowpublicviewsdescription','admin'),
            'defaultvalue' => get_config('allowpublicviews'),
            'options'      => $yesno,
        ),
        'artefactviewinactivitytime' => array(
            'type'         => 'expiry',
            'title'        => get_string('artefactviewinactivitytime','admin'),
            'description'  => get_string('artefactviewinactivitytimedescription','admin'),
            'defaultvalue' => get_config('artefactviewinactivitytime'),
        ),
        'submit' => array(
            'type'  => 'submit',
            'value' => get_string('updatesiteoptions','admin')
        ),
    )
));

function siteoptions_fail($field, Pieform $form) {
    $form->json_reply(PIEFORM_ERR, get_string('siteoptionsfailed','admin', get_string($field)), array($field => get_string($field . 'invalid', 'admin')));
}

function siteoptions_submit($values, Pieform $form) {
    $fields = array('sitename','language','theme','pathtoclam',
                    'allowpublicviews','artefactviewinactivitytime');
    foreach ($fields as $field) {
        if (!set_config($field, $values[$field])) {
            siteoptions_fail($field);
        }
    }
    // submitted sessionlifetime is in minutes; db entry session_timeout is in seconds
    if (!set_config('session_timeout', $values['sessionlifetime'] * 60)) {
        siteoptions_fail('sessionlifetime', $form);
    }
    // Submitted value is on/off; database entry should be 1/0
    if (!set_config('viruschecking', (int) ($values['viruschecking'] == 'on'))) {
        siteoptions_fail('viruschecking', $form);
    }
    $form->json_reply(PIEFORM_OK, get_string('siteoptionsset','admin'));
}

$smarty = smarty();
$smarty->assign('SITEOPTIONFORM', $siteoptionform);
$smarty->display('admin/site/options.tpl');

?>
