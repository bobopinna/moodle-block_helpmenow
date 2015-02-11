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
 * ui for browsing through block_helpmenow_error_log
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

# contexts and cap check
$sitecontext = context_system::instance();
if (!has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
    redirect();
}
$PAGE->set_context($sitecontext);
$url = '/blocks/helpmenow/admin/error_log.php';
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

# parameters
$page = optional_param('page', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);

# urls
$this_url = new moodle_url($url);

# title and nav
$title = 'Error Log';
$nav = array(array('name' => $title));
foreach($nav as $node) {
    $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
}

# paging
$per_page = 50;
$offset = $per_page * $page;

# get stuff from the db
$sql = "
    FROM {block_helpmenow_error_log} e
    LEFT JOIN {user} u ON e.userid = u.id
";
$params = array();
if ($search) {
    $sql .= " WHERE "
            . $DB->sql_like('e.error', ':substringerror', false) . " OR "
            . $DB->sql_like('u.firstname', ':substringfirstname', false) . " OR "
            . $DB->sql_like('u.lastname', ':substringlastname', false);
    $params['substringerror'] = '%'.$search.'%';
    $params['substringfirstname'] = '%'.$search.'%';
    $params['substringlastname'] = '%'.$search.'%';
}
$count = $DB->count_records_sql('SELECT COUNT(*) '.$sql, $params);
if ($count) {
    $sql = "
        SELECT e.*, u.firstname, u.lastname
        $sql
        LIMIT $per_page
        OFFSET $offset
    ";
    $errors = $DB->get_records_sql($sql);

    # start setting up the table
    $table = new html_table();
    $table->head = array(
        get_string('user'),
        get_string('error'),
        'object',
        'raw',
    );
    $table->align = array('left', 'left', 'center', 'center');
    $table->attributes['class'] = 'generaltable';
    $table->tablealign = 'center';
    foreach ($errors as $e) {
        $object = json_decode($e->details);
        $table->data[] = array(
            "$e->lastname, $e->firstname",
            $e->error,
            $object ? "<pre>".htmlspecialchars(print_r($object, true))."</pre>" : "Error decoding JSON",
            "<div class='helpmenow_errorlog'><pre>".htmlspecialchars($e->details)."</pre></div>",
        );
    }
}

/**
 * output
 */
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox centerpara');

$submit = get_string('submit');
echo $OUTPUT->box("<form><input type='text' name='search' value='$search' /><input type='submit' value='$submit' /></form>");

if ($count) {
    $pagingbar = new paging_bar($count, $page, $per_page, $this_url);
    $pagingbar->pagevar = $pagevar;
    echo $OUTPUT->render($pagingbar);
    echo html_writer::table($table);
    echo $OUTPUT->render($pagingbar);
} else {
    echo $OUTPUT->box('No errors matched your search');
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

