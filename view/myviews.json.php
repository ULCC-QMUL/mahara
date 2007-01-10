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
 * @subpackage core
 * @author     Richard Mansfield <richard.mansfield@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
define('JSON', 1);

require(dirname(dirname(__FILE__)) . '/init.php');

json_headers();

$limit = param_integer('limit', 5);
$offset = param_integer('offset', 0);

$userid = $USER->get('id');

$count = count_records('view', 'owner', $userid);

/* Get $limit views from the view table, then get all these views'
   associated artefacts */

/* Do this in one query sometime */

$prefix = get_config('dbprefix');
$viewdata = get_records_sql_array('SELECT v.id,v.title,v.startdate,v.stopdate,v.description,c.name
        FROM ' . $prefix . 'view v
        LEFT OUTER JOIN ' . $prefix . 'community c ON v.submittedto = c.id
        WHERE v.owner = ' . $userid . '
        ORDER BY v.title', '', $offset, $limit);

if ($viewdata) {
    $viewidlist = implode(', ', array_map(create_function('$a', 'return $a->id;'), $viewdata));
    $artefacts = get_records_sql_array('SELECT va.view, va.artefact, a.title, a.artefacttype, t.plugin
        FROM ' . $prefix . 'view_artefact va
        INNER JOIN ' . $prefix . 'artefact a ON va.artefact = a.id
        INNER JOIN ' . $prefix . 'artefact_installed_type t ON a.artefacttype = t.name
        WHERE va.view IN (' . $viewidlist . ')', '');
}


$data = array();
if ($viewdata) {
    // The table renderer seems to expect array indices to be
    // contiguous starting at 0, so we cannot use the view id as the
    // array index and need to remember which id belongs to which
    // index.
    for ($i = 0; $i < count($viewdata); $i++) {
        $index[$viewdata[$i]->id] = $i;
        $data[$i]['id'] = $viewdata[$i]->id;
        $data[$i]['title'] = $viewdata[$i]->title;
        $data[$i]['startdate'] = format_date(strtotime($viewdata[$i]->startdate), 'strftimedate');
        $data[$i]['stopdate'] = format_date(strtotime($viewdata[$i]->stopdate), 'strftimedate');
        $data[$i]['description'] = $viewdata[$i]->description;
        $data[$i]['submittedto'] = $viewdata[$i]->name;
        $data[$i]['artefacts'] = array();
    }
    // Go through all the artefact records and put them in with the
    // views they belong to.
    if ($artefacts) {
        foreach ($artefacts as $artefactrec) {
            safe_require('artefact', $artefactrec->plugin);
            // Perhaps I shouldn't have to construct the entire
            // artefact object to render the name properly.
            $classname = generate_artefact_class_name($artefactrec->artefacttype);
            $artefactobj = new $classname(0, array('title' => $artefactrec->title));
            $artefactobj->set('dirty', false);
            $artname = $artefactobj->render(FORMAT_ARTEFACT_LISTSELF, array('link' => false));
            $data[$index[$artefactrec->view]]['artefacts'][] = array('id'    => $artefactrec->artefact,
                                                                     'title' => $artname);
        }
    }
}


/* Get a list of communities that the user belongs to which also have
   a tutor member.  This is the list of communities that the user is
   able to submit views to. */

if (!$tutorcommunitydata = @get_records_sql_array('SELECT c.id, c.name
       FROM ' . $prefix . 'community_member u
       INNER JOIN ' . $prefix . 'community c ON u.community = c.id 
       INNER JOIN ' . $prefix . 'community_member t ON t.community = c.id 
       WHERE u.member = ' . $userid . '
       AND t.tutor = 1;', '')) {
    $tutorcommunitydata = array();
}

$result = array(
    'count'       => $count,
    'limit'       => $limit,
    'offset'      => $offset,
    'data'        => $data,
    'communities' => $tutorcommunitydata,
);


json_headers();
print json_encode($result);

?>
