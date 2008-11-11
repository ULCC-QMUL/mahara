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

define('INTERNAL', 1);
define('JSON', 1);

require(dirname(dirname(dirname(__FILE__))) . '/init.php');
global $USER;

json_headers();

$title      = param_variable('title');
$draft      = param_boolean('draft');
$blog       = param_integer('blog');
$blogpost   = param_integer('blogpost');
$uploads    = json_decode(param_variable('uploads'));
$artefacts  = json_decode(param_variable('artefacts'));
$body       = param_variable('body');
if (!get_account_preference($USER->get('id'), 'wysiwyg')) {
    $body = format_whitespace($body);
}
$tags       = param_variable('tags');

safe_require('artefact', 'blog');


// Check whether the sum of the sizes of the newly uploaded files
// would exceed the user's quota.
if (!empty($uploads)) {
    $uploadsize = 0;
    foreach ($uploads as $upload) {
        $uploadsize += ArtefactTypeBlogPost::temp_attachment_size($upload->data->tempfilename);
    }
    if (!$USER->quota_allowed($uploadsize)) {
        json_reply('local', get_string('newattachmentsexceedquota', 'artefact.blog'));
    }
}


// Get blogpost instance

$postobj = new ArtefactTypeBlogPost($blogpost, null);
$postobj->set('title', $title);
$postobj->set('description', $body);
$postobj->set('tags', preg_split("/\s*,\s*/", trim($tags)));
$postobj->set('published', !$draft);
if (!$blogpost) {
    $postobj->set('parent', $blog);
    $postobj->set('owner', $USER->id);
}
else if ($postobj->get('owner') != $USER->id) {
    json_reply('local', get_string('youarenottheownerofthisblogpost', 'artefact.blog'));
}
$postobj->commit();
$blogpost = $postobj->get('id');






// Delete old attachments in the db that no longer appear in the list
// of artefacts

$old = $postobj->attachment_id_list();

foreach ($old as $o) {
    if (!in_array($o, $artefacts)) {
        $postobj->detach_file($o);
    }
}



// Add new artefacts as attachments

foreach ($artefacts as $a) {
    if (!in_array($a, $old)) {
        $postobj->attach_file($a);
    }
}



// Add the newly uploaded files to myfiles and then to the blog post.

$uploadartefact = array();

if (!empty($uploads)) {
    foreach ($uploads as $upload) {
        if (!$fileid = $postobj->save_attachment($upload->data)) {
            json_reply('local', get_string('errorsavingattachments', 'artefact.blog'));
            // Things could be in a bad state.
        }
        $uploadartefact[$upload->id] = $fileid;
    }
}

// <img> tags in the body of the post may refer to newly uploaded
// files.  Because these files have been moved to permanent locations,
// we need to go through the body of the post and change the 'src' and
// 'alt' attributes of all images that refer to uploaded files.
if (!empty($uploadartefact)) {
    $originalbody = $body;
    foreach ($uploadartefact as $k => $v) {
        $regexps = array('/<img([^>]+)src="([^>]+)downloadtemp.php\?tempfile=' . $k .'/',
                         '/alt="uploaded:' . $k . '"/');
        $subs = array('<img$1src="' . get_config('wwwroot') . 'artefact/file/download.php?file=' . $v,
                      'alt="artefact:' . $v . '"');
        $body = preg_replace($regexps, $subs, $body);
    }
    if ($body != $originalbody) {
        $postobj = new ArtefactTypeBlogPost($blogpost, null);
        $postobj->set('description', $body);
        $postobj->commit();
    }
}

json_reply(false, get_string('blogpostsaved', 'artefact.blog'));

?>
