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
 * This script handles administration of queues.
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

# assign.php and edit.php urls
$assign = new moodle_url("$CFG->wwwroot/blocks/helpmenow/admin/assign_helper.php");
$edit = new moodle_url("$CFG->wwwroot/blocks/helpmenow/admin/edit_queue.php");
$delete = new moodle_url("$CFG->wwwroot/blocks/helpmenow/admin/delete_queue.php");

# contexts and cap check
$sitecontext = context_system::instance();
if (!has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
    redirect();
}
$PAGE->set_context($sitecontext);
$PAGE->set_url('/blocks/helpmenow/admin/manage_queues.php');
$PAGE->set_pagelayout('standard');

# title, navbar, and a nice box
$title = get_string('admin', 'block_helpmenow');
$nav = array(array('name' => $title));
foreach($nav as $node) {
    $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
}
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox centerpara');

echo $OUTPUT->heading(get_string('global_admin', 'block_helpmenow'));
$queues = helpmenow_queue::get_queues();

# start setting up the table
# todo: figure out a good way to include plugin specific column(s)

$table = new html_table();

$table->head = array(
        get_string('name'),
        get_string('description'),
        get_string('weight', 'block_helpmenow'),
        get_string('helpers', 'block_helpmenow'),
        get_string('delete'),
    );
$table->align = array('left', 'left', 'center', 'center', 'center');
$table->attributes['class'] = 'generaltable';
$table->tablealign = 'center';

if (!empty($queues)) {
    foreach ($queues as $q) {
        $assign->param('queueid', $q->id);
        $assign_url = $assign->out();
        $edit->param('queueid', $q->id);
        $edit_url = $edit->out();
        $delete->param('queueid', $q->id);
        $delete_url = $delete->out();

        $q->load_helpers();
        $helper_count = count($q->helpers);

        $table->data[] = array(
            "<a href='$edit_url'>$q->name</a>",
            $q->description,
            $q->weight,
            "<a href='$assign_url'>$helper_count</a>",
            "<a href='$delete_url'>".get_string('delete')."</a>",
        );
    }
}

echo html_writer::table($table);

# link to add queue
$edit->param('queueid', 0);
$edit_url = $edit->out();
$new_queue_text = get_string('new_queue', 'block_helpmenow');
echo "<p><a href='$edit_url'>$new_queue_text</a></p>";

echo $OUTPUT->box_end();

# footer
echo $OUTPUT->footer();

