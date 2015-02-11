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
 * This script creates chat session and connects users to them
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once((dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login(0, false);

$userid = optional_param('userid', 0, PARAM_INT);
$queueid = optional_param('queueid', 0, PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$chat_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/chat.php");

if ($sessionid) {   # helpers connecting to queue sessions
    $session = $DB->get_record('block_helpmenow_session', array('id' => $sessionid));
    if (!$DB->record_exists('block_helpmenow_helper', array('queueid' => $session->queueid, 'userid' => $USER->id))) {
        helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
    }
    // Only add user if the session doesn't already exist.
    if (!$DB->record_exists('block_helpmenow_session2user',
        array('userid' => $USER->id, 'sessionid' => $sessionid))) {
        helpmenow_add_user($USER->id, $session->id);
    }
} else {    # users connecting to users or users making new queue sessions
    # build sql to check for existing sesssions
    $sql = "
        SELECT s.*
        FROM {block_helpmenow_session2user} s2u
        JOIN {block_helpmenow_session} s ON s2u.sessionid = s.id
    ";
    if ($userid) {
        $sql .= "
            JOIN {block_helpmenow_session2user} s2u2 ON s2u2.sessionid = s.id AND s2u2.userid <> s2u.userid
            WHERE s2u2.userid = $userid
            AND s.queueid IS NULL
        ";
    } else if ($queueid) {
        $sql .= " WHERE s.queueid = $queueid ";
    } else {
        helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
    }
    $sql .= "
        AND s2u.userid = $USER->id
        AND s.iscurrent = 1
    ";
    if (!$session = $DB->get_record_sql($sql)) {
        # if we don't have a current session, create one
        $session = (object) array(
            'timecreated' => time(),
            'iscurrent' => 1,
            'createdby' => $USER->id,
        );
        if ($queueid) {
            $session->queueid = $queueid;
        }
        $session->id = $DB->insert_record('block_helpmenow_session', $session);

        # add user(s)
        helpmenow_add_user($USER->id, $session->id, time());
        if ($userid) {
            helpmenow_add_user($userid, $session->id);
        }
    }
}

# redirect to chat session
$chat_url->param('session', $session->id);
redirect($chat_url->out());

