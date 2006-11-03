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
 * @subpackage lang
 * @author     Nigel McNie <nigel@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006,2007 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

$string['configsanityexception'] = '<p>It appears that your server\'s PHP configuration contains a setting that will prevent Mahara from working, or make your installation insecure.'
    . ' More details follow:</p><div id="reason">%s</div><p>Once you have made the appropriate changes, reload this page.</p>';
// @todo<nigel>: most likely need much better descriptions here for these environment issues
$string['phpversion'] = 'Mahara will not run on PHP < 5.1.0. Please upgrade your PHP version, or move Mahara to a different host.';
$string['jsonextensionnotloaded'] = 'Your server configuration does not include the JSON extension. Mahara requires this in order to send some data to and from the browser. Please make sure that it is loaded in php.ini, or install it if it is not installed.';
$string['dbextensionnotloaded'] = 'Your server configuration does not include either the pgsql or mysqli extension. Mahara requires one of these in order to store data in a relational database. Please make sure that it is loaded in php.ini, or install it if it is not installed.';
$string['libxmlextensionnotloaded'] = 'Your server configuration does not include the libxml extension. Mahara requires this in order to parse XML data for the installer and for backups. Please make sure that it is loaded in php.ini, or install it if it is not installed.';
$string['gdextensionnotloaded'] = 'Your server configuration does not include the gd extension. Mahara requires this in order to perform resizes and other operations on uploaded images. Please make sure that it is loaded in php.ini, or install it if it is not installed.';
$string['sessionextensionnotloaded'] = 'Your server configuration does not include the session extension. Mahara requires this in order to support users logging in. Please make sure that it is loaded in php.ini, or install it if it is not installed.';
$string['registerglobals'] = 'You have dangerous PHP settings, register_globals is on. Mahara is trying to work around this, but you should really fix it';
$string['magicquotesgpc'] = 'You have dangerous PHP settings, magic_quotes_gpc is on. Mahara is trying to work around this, but you should really fix it';
$string['magicquotesruntime'] = 'You have dangerous PHP settings, magic_quotes_runtime is on. Mahara is trying to work around this, but you should really fix it';
$string['magicquotessybase'] = 'You have dangerous PHP settings, magic_quotes_sybase is on. Mahara is trying to work around this, but you should really fix it';

$string['safemodeon'] = '<p>Your server appears to be running safe mode. Mahara does not support running in safe mode. You must turn this off in either the php.ini file, or in your apache config for the site.</p><p>If you are on shared hosting, it is likely that there is little you can do to get safe_mode turned off, other than ask your hosting provider. Perhaps you could consider moving to a different host.</p>';
$string['datarootinsidedocroot'] = 'You have set up your data root to be inside your document root. This is a large security problem, as then anyone can directly request session data (in order to hijack other peoples\' sessions), or files that they are not allowed to access that other people have uploaded. Please configure the data root to be outside of the document root.';
$string['datarootnotwritable'] = 'Your defined data root directory, %s, is not writable. This means that neither session data, user files nor anything else that needs to be uploaded can be saved on your server. Please make the directory if it does not exist, or give ownership of the directory to the web server user if it does';

$string['dbconnfailed'] = 'Failed to connect to database, error message was %s';

?>
