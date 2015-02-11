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
 * This script generates the history window
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once((dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login(0, false);

// Date is a string representation of the starting date for the history to be 
// shown. "-1 year" is default, but a date such as "20130610" will work also
// This limits by the session creation date, so it may not be exact.
$date = optional_param('date', '', PARAM_TEXT);
if (!$date) {
    $date = strtotime("-1 year");
} else {
    $date = strtotime($date);
}

$contact_list = helpmenow_contact_list::get_plugin();
$is_admin = $contact_list::is_admin();

# verify session
$sessionid = required_param('session', PARAM_INT);
if (!(helpmenow_verify_session($sessionid) or $is_admin)) {
    helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
}

$session = $DB->get_record('block_helpmenow_session', array('id' => $sessionid));

# title
$sql = "
    SELECT u.*
    FROM {block_helpmenow_session2user} s2u
    JOIN {user} u ON u.id = s2u.userid
    WHERE s2u.sessionid = $sessionid
    ";
$chat_users = $DB->get_records_sql($sql);
$other_users = array();
$i=0;
$joinlist = '';
foreach ($chat_users as $r) {
    $other_users[] = fullname($r);
    $joinlist .= "JOIN {block_helpmenow_session2user} s2u$i ON s.id = s2u$i.sessionid AND s2u$i.userid=$r->id ";
    $i += 1;
}
$title = get_string('chat_history', 'block_helpmenow') . ': ' . implode(', ', $other_users);


$sql = "
    SELECT s.id
    FROM {block_helpmenow_session} s
    $joinlist
    WHERE s.timecreated > $date
    ";

$messages = '';
$sessionids = array();
if ($sessions = $DB->get_records_sql($sql)) {
    foreach ($sessions as $s) {
        $sessionids[] = $s->id;
    }
    $sessionids = implode(', ', $sessionids);
    if ($history = helpmenow_get_history_list($sessionids)) {
        $messages = helpmenow_format_messages_history(helpmenow_filter_messages_history($history), '');
    }
} else {
    helpmenow_fatal_error(get_string('history_not_available', 'block_helpmenow'));
}


echo <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
    <head>
        <title>$title</title>
        <link rel="stylesheet" type="text/css" href="$CFG->wwwroot/blocks/helpmenow/style/history.css" />
    </head>
    <body>
        <div id="titleDiv"><b>$title</b></div>
        <div id="chatDiv">
            $messages
            <div id="recent"></div>
        </div>
    </body>
</html>
EOF;

