<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script invites users to adobe connect sessions and then redirects the
 * inviter as well.
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$testing = optional_param('testing', 0, PARAM_BOOL);
$username = required_param('username', PARAM_TEXT);

if (!empty($CFG->helpmenow_adobeconnect_url)) {
    $url = $CFG->helpmenow_adobeconnect_url."/$username";
} else {
    helpmenow_fatal_error('This page has not been configured for adobe connect.');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/helpmenow/plugins/adobeconnect/meetnow.php');
$PAGE->set_pagelayout('standard');

$heading = $CFG->helpmenow_adobeconnect_orgname.'-Adobe Connect Redirector';
$PAGE->set_title($heading);
$PAGE->set_heading($heading);
echo $OUTPUT->header();

$me = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/adobeconnect/meetnow.php");
$me->param('username', $username);
$me = $me->out();

$logo = $CFG->helpmenow_adobeconnect_logourl;

print <<<EOF
<img src="$logo" width="287px"/>
<div id="message"></div>
<script type="text/javascript">
<!--
if ($testing || navigator.userAgent.indexOf("Chrome") != -1) {
    document.getElementById('message').innerHTML = "<p>You appear to be using Google's Chrome browser. We like it, too!</p><p>Unfortunately, Adobe Connect does not yet support Chrome, so we cannot connect you until you switch to a different browser.</p><p>Please open a different browser (Firefox, Safari, and Internet Explorer are recommended) and copy and paste the following link into that browser to continue: <p style=\"font-weight:bold; margin-left: 5em; \">$me</p></p><p>If you need any help, please <a target=\"_blank\" href=\"$CFG->helpmenow_adobeconnect_helpurl\">contact our technical help desk</a> by email or phone.</p>";

} else {
    window.location = "$url";
}
//-->
</script>
EOF;

echo $OUTPUT->footer();

