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
 * This script handles the queue edit form.
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

# require login
require_login(0, false);

# get our parameters
$queueid = required_param('queueid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$assign = optional_param('assign', 0, PARAM_INT);

# urls
$admin_url = "$CFG->wwwroot/blocks/helpmenow/admin/manage_queues.php";
$url = '/blocks/helpmenow/admin/assign_helper.php';
$this_url = new moodle_url($url);
$this_url->param('queueid', $queueid); 

# sitecontext and cap check
$sitecontext = context_system::instance();
if (!has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
    redirect();
}
$PAGE->set_context($sitecontext);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

$queue = new helpmenow_queue($queueid);
$queue->load_helpers();
$cap = HELPMENOW_CAP_QUEUE_ANSWER;

# todo: check returned values for success
if ($userid) {      # assigning/unassigning users
    if ($assign) {  # assigning
        if (has_capability($cap, $sitecontext, $userid)) {
            $queue->add_helper($userid);
        } else {
            # todo: error: doesn't have cap to be a helper
        }
    } else {        # unassigning
        $queue->remove_helper($userid);
    }
    # redirect back to the list
    redirect($this_url->out());
} 

# title, navbar, and a nice box
$title = get_string('assign_title', 'block_helpmenow');
$nav = array(
    array('name' => get_string('admin', 'block_helpmenow'), 'link' => $admin_url),
    array('name' => $title),
);
foreach($nav as $node) {
    $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
}
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox centerpara');

echo $OUTPUT->heading(get_string('assign_heading', 'block_helpmenow') . $queue->name);

$users = get_users_by_capability($sitecontext, $cap, 'u.id, u.username, u.firstname, u.lastname', '', '', '', '', '', false);
# todo: if we don't get any users we should print a helpful messages about capabilities

# start setting up the table
$table = new html_table();
$table->head = array(
    get_string('name'),
    get_string('username'),
    get_string('assigned_status', 'block_helpmenow'),
    get_string('assigned_link', 'block_helpmenow'),
);
$table->align = array('left', 'left', 'center', 'center');
$table->attributes['class'] = 'generaltable';
$table->tablealign = 'center';

if (!empty($users)) {
    foreach ($users as $u) {
        if (isset($queue->helpers[$u->id])) {
            $assign = 0;
            $status = get_string('yes');
            $link_text = get_string('unassign', 'block_helpmenow');
        } else {
            $assign = 1;
            $status = get_string('no');
            $link_text = get_string('assign', 'block_helpmenow');
        }
        $this_url->param('userid', $u->id);
        $this_url->param('assign', $assign);
        $tmp = $this_url->out();
        $table->data[] = array(
            "$u->lastname, $u->firstname",
            $u->username,
            $status,
            "<a href='$tmp'>$link_text</a>",
        );
    }
}

echo html_writer::table($table);

$back = get_string('back', 'block_helpmenow');
echo "<p><a href='$admin_url'>$back</a></p>";

echo $OUTPUT->box_end();

# footer
echo $OUTPUT->footer();

