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
 * @subpackage artefact-resume
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', true);
define('MENUITEM', 'profile/myresume');
define('SECTION_PLUGINTYPE', 'artefact');
define('SECTION_PLUGINNAME', 'resume');
define('SECTION_PAGE', 'index');

require_once(dirname(dirname(dirname(__FILE__))) . '/init.php');
define('TITLE', get_string('myresume', 'artefact.resume'));
require_once('pieforms/pieform.php');
require_once(get_config('docroot') . 'artefact/lib.php');

// There is a problem with collapsible fieldsets in pieforms that mean they 
// don't work properly in IE. This is a hack until that problem is fixed
$collapsedfieldsets = (isset($_SERVER['HTTP_USER_AGENT']) && false === stripos($_SERVER['HTTP_USER_AGENT'], 'msie'));

// load up all the artefacts this user already has....
$coverletter = null;
$personalinformation = null;
$contactinformation = null;
$interest = null;
try {
    $coverletter = artefact_instance_from_type('coverletter');
}
catch (Exception $e) { }
try {
    $personalinformation = artefact_instance_from_type('personalinformation');
}
catch (Exception $e) { }
try {
    $contactinformation = artefact_instance_from_type('contactinformation');
}
catch (Exception $e) { 
    $contactinformation = ArtefactTypeContactinformation::setup_new($USER->get('id'));
}
try {
    $interest = artefact_instance_from_type('interest');
}
catch (Exception $e) { }

$contactinformation_value = $contactinformation->render_self(array('editing' => true));
$contactinformation_value = $contactinformation_value['html'];

$coverletterform = pieform(array(
    'name'        => 'coverletter',
    'jsform'      => true,
    'plugintype'  => 'artefact',
    'pluginname'  => 'resume',
    'jsform'      => true,
    'method'      => 'post',
    'elements'    => array(
        'coverletterfs' => array(
            'type' => 'fieldset',
            'legend' => get_string('coverletter', 'artefact.resume'),
            'collapsible' => $collapsedfieldsets,
            'collapsed' => $collapsedfieldsets,
            'elements' => array(
                'coverletter' => array(
                    'type'  => 'wysiwyg',
                    'cols'  => 70,
                    'rows'  => 10,
                    'defaultvalue' => ((!empty($coverletter)) ? $coverletter->get('description') : null),
                    'help' => true,
                ),
                'save' => array(
                    'type' => 'submit',
                    'value' => get_string('save'),
                ),
            )
        )
    )
));

$interestsform = pieform(array(
    'name'        => 'interests',
    'jsform'      => true,
    'plugintype'  => 'artefact',
    'pluginname'  => 'resume',
    'jsform'      => true,
    'method'      => 'post',
    'elements'    => array(
        'interestsfs' => array(
            'type' => 'fieldset',
            'legend' => get_string('interest', 'artefact.resume'),
            'collapsible' => $collapsedfieldsets,
            'collapsed' => $collapsedfieldsets,
            'elements' => array(
                'interest' => array(
                    'type' => 'wysiwyg',
                    'defaultvalue' => ((!empty($interest)) ? $interest->get('description') : null),
                    'cols'  => 70,
                    'rows'  => 10,
                    'help'  => true,
                ),
                'save' => array(
                    'type' => 'submit',
                    'value' => get_string('save'),
                ),
            )
        )
    )
));

$contactinformationform = pieform(array(
    'name'        => 'contactinformation',
    'jsform'      => true,
    'plugintype'  => 'artefact',
    'pluginname'  => 'resume',
    'jsform'      => true,
    'method'      => 'post',
    'elements'    => array(
        'contactinformationfs' => array(
            'type' => 'fieldset',
            'legend' => get_string('contactinformation', 'artefact.resume'),
            'collapsible' => $collapsedfieldsets,
            'collapsed' => $collapsedfieldsets,
            'elements' => array(
                'contactinformation' => array(
                    'type'  => 'html',
                    'value' => $contactinformation_value,
                    'help'  => true,
                ),
            )
        )
    )
));

$personalinformationform = pieform(array(
    'name'        => 'personalinformation',
    'jsform'      => true,
    'plugintype'  => 'artefact',
    'pluginname'  => 'resume',
    'jsform'      => true,
    'method'      => 'post',
    'elements'    => array(
        'personalinformation' => array(
            'type'        => 'fieldset',
            'legend'      => get_string('personalinformation', 'artefact.resume'),
            'collapsible' => $collapsedfieldsets,
            'collapsed'   => $collapsedfieldsets,
            'elements'    => array(
               'dateofbirth' => array(
                    'type'       => 'calendar',
                    'caloptions' => array(
                        'showsTime'      => false,
                        'ifFormat'       => '%Y/%m/%d'
                    ),
                    'defaultvalue' => ((!empty($personalinformation)) 
                        ? $personalinformation->get_composite('dateofbirth') : null),
                    'title' => get_string('dateofbirth', 'artefact.resume'),
                    'help'  => true,
                ),
                'placeofbirth' => array(
                    'type' => 'text',
                    'defaultvalue' => ((!empty($personalinformation)) 
                        ? $personalinformation->get_composite('placeofbirth') : null),
                    'title' => get_string('placeofbirth', 'artefact.resume'),
                ),  
                'citizenship' => array(
                    'type' => 'text',
                    'defaultvalue' => ((!empty($personalinformation))
                        ? $personalinformation->get_composite('citizenship') : null),
                    'title' => get_string('citizenship', 'artefact.resume'),
                ),
                'visastatus' => array(
                    'type' => 'text', 
                    'defaultvalue' => ((!empty($personalinformation))
                        ? $personalinformation->get_composite('visastatus') : null),
                    'title' => get_string('visastatus', 'artefact.resume'),
                    'help'  => true,
                ),
                'gender' => array(
                    'type' => 'radio', 
                    'defaultvalue' => ((!empty($personalinformation))
                        ? $personalinformation->get_composite('gender') : null),
                    'options' => array(
                        'female' => get_string('female', 'artefact.resume'),
                        'male'   => get_string('male', 'artefact.resume'),
                    ),
                    'title' => get_string('gender', 'artefact.resume'),
                ),
                'maritalstatus' => array(
                    'type' => 'text',
                    'defaultvalue' => ((!empty($personalinformation))
                        ? $personalinformation->get_composite('maritalstatus') :  null),
                    'title' => get_string('maritalstatus', 'artefact.resume'),
                ),
                'save' => array(
                    'type' => 'submit',
                    'value' => get_string('save'),
                ),
            )
        )
    )
));

$cancelstr = get_string('cancel');
$addstr = get_string('add');
$editstr = get_string('edit');
$delstr = get_string('delete');
$confirmdelstr = get_string('compositedeleteconfirm', 'artefact.resume');
$imagepath = theme_get_url('images');
$upstr = get_string('moveup', 'artefact.resume');
$downstr = get_string('movedown', 'artefact.resume');

$wwwroot = get_config('wwwroot');

$smarty = smarty(array('tablerenderer'));

$smarty->assign('coverletterform', $coverletterform);
$smarty->assign('interestsform', $interestsform);
$smarty->assign('contactinformationform', $contactinformationform);
$smarty->assign('personalinformationform',$personalinformationform);

$inlinejs = <<<EOF
var tableRenderers = {};

function toggleCompositeForm(type) {
    var elemName = ''; 
    elemName = type + 'form';
    if (hasElementClass(elemName, 'hidden')) {
        removeElementClass(elemName, 'hidden');
        $('add' + type + 'button').innerHTML = '{$cancelstr}';
    }
    else {
        $('add' + type + 'button').innerHTML = '{$addstr}';
        addElementClass(elemName, 'hidden'); 
    }
}

function compositeSaveCallback(form, data) {
    key = form.id.substr(3);
    tableRenderers[key].doupdate(); 
    toggleCompositeForm(key);
    $('add' + key).reset();
}

function deleteComposite(type, id, artefact) {
    if (confirm('{$confirmdelstr}')) {
        sendjsonrequest('compositedelete.json.php',
            {'id': id, 'artefact': artefact},
            'GET', 
            function(data) {
                tableRenderers[type].doupdate();
            },
            function() {
                // @todo error
            }
        );
    }
    return false;
}

function moveComposite(type, id, artefact, direction) {
    sendjsonrequest('compositemove.json.php',
        {'id': id, 'artefact': artefact, 'direction':direction},
        'GET', 
        function(data) {
            tableRenderers[type].doupdate();
        },
        function() {
            // @todo error
        }
    );
    return false;
}

function editprofilebutton() {
    document.location='{$wwwroot}artefact/internal/index.php?fs=contact';
    return false;
}

EOF;
$inlinejs .= ArtefactTypeResumeComposite::get_showhide_composite_js();

$compositeforms = array();
foreach (ArtefactTypeResumeComposite::get_composite_artefact_types() as $compositetype) {
    $inlinejs .= <<<EOF
tableRenderers.{$compositetype} = new TableRenderer(
    '{$compositetype}list',
    'composite.json.php',
    [
EOF;
    $inlinejs .= call_static_method(generate_artefact_class_name($compositetype), 'get_tablerenderer_js');
    $inlinejs .= <<<EOF

        function (r) {
            return TD(null, A({'href': 'editcomposite.php?id=' + r.id + '&artefact=' + r.artefact}, '{$editstr}'));
        },
        function (r, d) {
           var link = A({'href': ''}, '{$delstr}');
            connect(link, 'onclick', function (e) {
                e.stop();
                return deleteComposite(d.type, r.id, r.artefact);
            });
            return TD(null, link);
        },
        function (r, d) {
            var buttons = [];
            if (r._rownumber > 1) {
                var up = A({'href': ''}, IMG({'src': '{$imagepath}/move-block-up.png', 'alt':'{$upstr}'}));
                connect(up, 'onclick', function (e) {
                    e.stop();
                    return moveComposite(d.type, r.id, r.artefact, 'up');
                });
                buttons.push(up);
            }
            if (!r._last) {
                var down = A({'href': ''}, IMG({'src': '{$imagepath}/move-block-down.png', 'alt':'{$downstr}'}));
                connect(down, 'onclick', function (e) {
                    e.stop();
                    return moveComposite(d.type, r.id, r.artefact, 'down');
                });
                buttons.push(' ');
                buttons.push(down);
            }
            return TD({'style':'text-align:center;'}, buttons);
        }
    ]
);

tableRenderers.{$compositetype}.type = '{$compositetype}';
tableRenderers.{$compositetype}.statevars.push('type');
tableRenderers.{$compositetype}.emptycontent = '';
tableRenderers.{$compositetype}.updateOnLoad();

EOF;
    $elements = call_static_method(generate_artefact_class_name($compositetype), 'get_addform_elements');
    $elements['submit'] = array(
        'type' => 'submit',
        'value' => get_string('save'),
    );
    $elements['compositetype'] = array(
        'type' => 'hidden',
        'value' => $compositetype,
    );
    $cform = array(
        'name' => 'add' . $compositetype,
        'plugintype' => 'artefact',
        'pluginname' => 'resume',
        'elements' => $elements, 
        'jsform' => true,
        'successcallback' => 'compositeform_submit',
        'jssuccesscallback' => 'compositeSaveCallback',
    );
    $compositeforms[$compositetype] = pieform($cform);
} // end composite loop

$smarty->assign('compositeforms', $compositeforms);
$smarty->assign('INLINEJAVASCRIPT', $inlinejs);
$smarty->assign('heading', get_string('myresume', 'artefact.resume'));
$smarty->display('artefact:resume:index.tpl');

function coverletter_submit(Pieform $form, $values) {
    global $coverletter, $personalinformation, $interest, $USER;

    $userid = $USER->get('id');
    $errors = array();

    try {
        if (empty($coverletter) && !empty($values['coverletter'])) {
            $coverletter = new ArtefactTypeCoverletter(0, array( 
                'owner' => $userid, 
                'description' => $values['coverletter']
            ));
            $coverletter->commit();
        }
        else if (!empty($coverletter) && !empty($values['coverletter'])) {
            $coverletter->set('description', $values['coverletter']);
            $coverletter->commit();
        }
        else if (!empty($coverletter) && empty($values['coverletter'])) {
            $coverletter->delete();
        }
    }
    catch (Exception $e) {
        $errors['coverletter'] = true;
    }
    if (empty($errors)) {
        $form->json_reply(PIEFORM_OK, get_string('resumesaved','artefact.resume'));
    }
    else {
        $message = '';
        foreach (array_keys($errors) as $key) {
            $message .= get_string('resumesavefailed', 'artefact.resume')."\n";
        }
        $form->json_reply(PIEFORM_ERR, $message);
    }
}

function interests_submit(Pieform $form, $values) {
    global $coverletter, $personalinformation, $interest, $USER;

    $userid = $USER->get('id');
    $errors = array();

    try {
        if (empty($interest) && !empty($values['interest'])) {
            $interest = new ArtefactTypeInterest(0, array( 
                'owner' => $userid, 
                'description' => $values['interest']
            ));
            $interest->commit();
        }
        else if (!empty($interest) && !empty($values['interest'])) {
            $interest->set('description', $values['interest']);
            $interest->commit();
        }
        else if (!empty($interest) && empty($values['interest'])) {
            $interest->delete();
        }
    }
    catch (Exception $e) {
        $errors['interest'] = true;
    }   

    if (empty($errors)) {
        $form->json_reply(PIEFORM_OK, get_string('resumesaved','artefact.resume'));
    }
    else {
        $message = '';
        foreach (array_keys($errors) as $key) {
            $message .= get_string('resumesavefailed', 'artefact.resume')."\n";
        }
        $form->json_reply(PIEFORM_ERR, $message);
    }
}

function personalinformation_submit(Pieform $form, $values) {
    global $personalinformation, $USER;
    $userid = $USER->get('id');
    $errors = array();

    try {
        if (empty($personalinformation)) {
            $personalinformation = new ArtefactTypePersonalinformation(0, array(
                'owner' => $userid,
                'title' => get_string('personalinformation', 'artefact.resume'),
            ));
        }
        foreach (array_keys(ArtefactTypePersonalInformation::get_composite_fields()) as $field) {
            $personalinformation->set_composite($field, $values[$field]);
        }
        $personalinformation->commit();
    }
    catch (Exception $e) {
        $errors['personalinformation'] = true;
    }

    if (empty($errors)) {
        $form->json_reply(PIEFORM_OK, get_string('resumesaved','artefact.resume'));
    }
    else {
        $message = '';
        foreach (array_keys($errors) as $key) {
            $message .= get_string('resumesavefailed', 'artefact.resume')."\n";
        }
        $form->json_reply(PIEFORM_ERR, $message);
    }
}

?>
