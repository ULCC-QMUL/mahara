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
 * @subpackage lang
 * @author     Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2006-2008 Catalyst IT Ltd http://catalyst.net.nz
 *
 */

defined('INTERNAL') || die();

$string['pluginname'] = 'Profile';

$string['profile'] = 'Profile';
$string['myfiles'] = 'My Files';

$string['mandatory'] = 'Mandatory';
$string['public'] = 'Public';
$string['profileiconsize'] = 'Icon size';

// profile categories
$string['aboutme'] = 'About me';
$string['contact'] = 'Contact information';
$string['messaging'] = 'Messaging';
$string['general'] = 'General';

// profile fields
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['fullname'] = 'Full Name';
$string['institution'] = 'Institution';
$string['studentid'] = 'Student ID';
$string['preferredname'] = 'Preferred Name';
$string['introduction'] = 'Introduction';
$string['email'] = 'Email Address';
$string['officialwebsite'] = 'Official Website Address';
$string['personalwebsite'] = 'Personal Website Address';
$string['blogaddress'] = 'Blog Address';
$string['address'] = 'Postal Address';
$string['town'] = 'Town';
$string['city'] = 'City/Region';
$string['country'] = 'Country';
$string['homenumber'] = 'Home Phone';
$string['businessnumber'] = 'Business Phone';
$string['mobilenumber'] = 'Mobile Phone';
$string['faxnumber'] = 'Fax Number';
$string['icqnumber'] = 'ICQ Number';
$string['msnnumber'] = 'MSN Chat';
$string['aimscreenname'] = 'AIM Screen Name';
$string['yahoochat'] = 'Yahoo Chat';
$string['skypeusername'] = 'Skype Username';
$string['jabberusername'] = 'Jabber Username';
$string['occupation'] = 'Occupation';
$string['industry'] = 'Industry';

// Field names for view user and search user display
$string['name'] = 'Name';
$string['principalemailaddress'] = 'Primary email';
$string['emailaddress'] = 'Alternative email';

$string['saveprofile'] = 'Save Profile';
$string['profilesaved'] = 'Profile saved successfully';
$string['profilefailedsaved'] = 'Profile saving failed';


$string['emailvalidation_subject'] = 'Email validation';
$string['emailvalidation_body'] = <<<EOF
Hello %s,

The email address %s has been added to your user account in Mahara. Please visit the link below to activate this address.

%s
EOF;

$string['validationemailwillbesent'] = 'a validation email will be sent when you save your profile';
$string['emailactivation'] = 'Email Activation';
$string['emailactivationsucceeded'] = 'Email Activation Successful';
$string['emailactivationfailed'] = 'Email Activation Failed';
$string['unvalidatedemailalreadytaken'] = 'The e-mail address you are trying to validate is already taken';

$string['emailingfailed'] = 'Profile saved, but emails were not sent to: %s';

// Profile icons
$string['editprofile']  = 'Edit Profile';
$string['profileicons'] = 'Profile Icons';
$string['Default'] = 'Default';
$string['deleteselectedicons'] = 'Delete selected icons';
$string['profileicon'] = 'Profile Icon';
$string['noimagesfound'] = 'No images found';
$string['uploadedprofileiconsuccessfully'] = 'Uploaded new profile icon successfully';
$string['profileiconsetdefaultnotvalid'] = 'Could not set the default profile icon, the choice was not valid';
$string['profileiconsdefaultsetsuccessfully'] = 'Default profile icon set successfully';
$string['profileiconsdeletedsuccessfully'] = 'Profile icon(s) deleted successfully';
$string['profileiconsnoneselected'] = 'No profile icons were selected to be deleted';
$string['onlyfiveprofileicons'] = 'You may upload only five profile icons';
$string['or'] = 'or';
$string['profileiconuploadexceedsquota'] = 'Uploading this profile icon would exceed your disk quota. Try deleting some files you have uploaded';
$string['profileiconimagetoobig'] = 'The image you uploaded was too big (%sx%s pixels). It must not be larger than %sx%s pixels';
$string['uploadingfile'] = 'uploading file...';
$string['uploadprofileicon'] = 'Upload Profile Icon';
$string['profileiconsiconsizenotice'] = 'You may upload up to <strong>five</strong> profile icons here, and choose one to be displayed as your default icon at any one time. Your icons must be between 16x16 and %sx%s pixels';
$string['setdefault'] = 'Set Default';
$string['Title'] = 'Title';
$string['usenodefault'] = 'Use no default';
$string['usingnodefaultprofileicon'] = 'Now using no default profile icon';

$string['Created'] = 'Created';
$string['Description'] = 'Description';
$string['Download'] = 'Download';
$string['lastmodified'] = 'Last Modified';
$string['Owner'] = 'Owner';
$string['Preview'] = 'Preview';
$string['Size'] = 'Size';
$string['Type'] = 'Type';

?>
