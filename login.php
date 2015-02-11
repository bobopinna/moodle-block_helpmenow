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
 * This script logs helpers and instructors in and out
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once((dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

# require login
require_login(0, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/helpmenow/login.php');
$PAGE->set_pagelayout('standard');

# get our parameters
$login = required_param('login', PARAM_INT);
$queueid = optional_param('queueid', 0, PARAM_INT);

# todo: log this

$message = '';
if ($queueid) {     # helper
    if (!$record = $DB->get_record('block_helpmenow_helper', array('queueid' => $queueid, 'userid' => $USER->id))) {
        helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
    }
    $message = "queueid: $queueid, ";
} else {    # instructor
    if (!$record = $DB->get_record('block_helpmenow_user', array('userid' => $USER->id))) {
        helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
    }
}

if ($login) {
    helpmenow_log($USER->id, 'logged_in', $message); 
    $record->isloggedin = time();
} else {
    $duration = time() - $record->isloggedin;
    helpmenow_log($USER->id, 'logged_out', "{$message}duration: $duration seconds");
    $record->isloggedin = 0;
}

if ($queueid) {     # helper
    $DB->update_record('block_helpmenow_helper', $record);
    helpmenow_log($USER->id, 'updated block_helpmenow_helper', "$record->isloggedin");
} else {    # instructor
    $DB->update_record('block_helpmenow_user', $record);
    helpmenow_log($USER->id, 'updated block_helpmenow_user', "$record->isloggedin");
}

/**
 * handle plugins' on_login/on_logout
 *
 * plugins that return true don't need anymore
 * plugins that return a string are giving us a url to redirect too
 *
 * if multiple plugins give us a url to redirect to, we're going to have have 
 * to handle that by presenting links to the user instead of auto redirecting
 */
$redirects = array();
foreach (helpmenow_plugin::get_plugins() as $pluginname) {
    $class = "helpmenow_plugin_$pluginname";
    $method = $login ? 'on_login' : 'on_logout';
    if (!method_exists($class, $method)) {
        continue;
    }
    $returned = $class::$method();
    if (!is_bool($returned)) {
        $redirects[$pluginname] = $returned;
    }
}

if (count($redirects) == 0) {
    helpmenow_fatal_error(get_string('may_close', 'block_helpmenow'), true, true);
}
if (count($redirects) == 1) {
    redirect(reset($redirects));
}

$output = '<p>'.get_string('multiple_plugins', 'block_helpmenow').'</p>';
foreach ($redirects as $pluginname => $redirect) {
    $action = new popup_action('click', $redirect, $pluginname,
        array('height' => 400, 'width' => 500));
    $output .= $OUTPUT->action_link($redirect, $pluginname, $action) . "<br />";
}
$title = get_string('helpmenow', 'block_helpmenow');
$nav = array(array('name' => $title));
foreach($nav as $node) {
    $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
}
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->box($output);
echo $OUTPUT->footer();

