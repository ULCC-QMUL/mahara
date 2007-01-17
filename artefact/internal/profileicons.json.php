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
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

define('INTERNAL', 1);
require(dirname(dirname(dirname(__FILE__))) . '/init.php');

$prefix = get_config('dbprefix');
$result = get_records_sql_array('SELECT a.id, a.title, a.note, (u.profileicon = a.id) AS default
    FROM ' . $prefix . 'artefact a
    LEFT OUTER JOIN ' . $prefix . 'usr u
    ON (u.id = a.owner)
    WHERE artefacttype = \'profileicon\'
    AND a.owner = ?
    ORDER BY a.id', array($USER->get('id')));

json_headers();
$data['error'] = false;
$data['data'] = $result;
$data['count'] = ($result) ? count($result) : 0;
echo json_encode($data);

?>
