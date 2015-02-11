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
 * This script gets updates from wiziq
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

# we do get more from wiziq, but these are all we care about for now
$user_id = required_param('user_id', PARAM_INT);
$class_id = required_param('class_id', PARAM_INT);
$class_status = required_param('class_status', PARAM_Text);

$user2plugin = helpmenow_wiziq_user2plugin::get_user2plugin($user_id);

# if the class that we're getting a status on is not the one we have attached
# to this user, don't do anything
if ($user2plugin->class_id != $class_id) {
    die;
}

# these status indicate the class is over
if ($class_status == 'expired' or $class_status == 'completed') {
    $user2plugin->delete_meeting();
}

