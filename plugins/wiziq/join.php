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
 * This script connects students to wiziq
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login(0, false);

$session_id = optional_param('sessionid', 0, PARAM_INT);
$class_id = required_param('classid', PARAM_INT);

if (!$session_id) {
    $sitecontext = context_system::instance();
    if (!has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
        redirect();
    }
} else {
    # verify sesion
    if (!helpmenow_verify_session($session_id)) {
        helpmenow_fatal_error('You do not have permission to view this page.');
    }

    if (!$s2p_rec = $DB->get_record('block_helpmenow_s2p', array('sessionid' => $session_id, 'plugin' => 'wiziq'))) {
        helpmenow_fatal_error('Invalid session.');
    }
    $s2p = new helpmenow_session2plugin_wiziq(null, $s2p_rec);

    if (!in_array($class_id, $s2p->classes)) {
        helpmenow_fatal_error('Invalid class.');
    }
}

if ($user2plugin = helpmenow_user2plugin_wiziq::get_user2plugin()) {
    if ($user2plugin->class_id == $class_id) {
        redirect($user2plugin->presenter_url);
    }
}

$response = helpmenow_wiziq_add_attendee($class_id);

if (debugging()) {
    print_object($response);
}

if ((string) $response['status'] == 'fail') {
    helpmenow_fatal_error('You were unable to join the session. It may no longer be active.');
}

redirect((string) $response->add_attendees->attendee_list->attendee[0]->attendee_url);

