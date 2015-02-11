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
 * This script creates g2m sessions
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login(0, false);

# make sure user is instructor or helper
$user = $DB->get_record('block_helpmenow_user', array('userid' => $USER->id));
$helper = $DB->get_records('block_helpmenow_helper', array('userid' => $USER->id));
if (!$user and !$helper) {
    helpmenow_fatal_error('You do not have permission to view this page.');
}

# make sure we've got a gotomeeting user2plugin record with a token
$token_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/gotomeeting/token.php");
$token_url->param('redirect', qualified_me());
$token_url = $token_url->out();
if ($record = $DB->get_record('block_helpmenow_user2plugin', array('userid' => $USER->id, 'plugin' => 'gotomeeting'))) {
    $user2plugin = new helpmenow_user2plugin_gotomeeting(null, $record);
} else {
    $user2plugin = new helpmenow_user2plugin_gotomeeting();
    $user2plugin->userid = $USER->id;
    $user2plugin->insert();
    redirect($token_url);
}
# check to see if the oauth token has expired
if ($user2plugin->token_expiration < time()) {
    redirect($token_url);
}

$user2plugin->create_meeting();
$user2plugin->update();

redirect($user2plugin->join_url);

