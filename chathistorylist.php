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

$userid = required_param('userid', PARAM_INT);
$recent = optional_param('recent', 0, PARAM_INT);

require_login(0, true);

# contexts and cap check
$context = context_system::instance();
$admin = has_capability(HELPMENOW_CAP_MANAGE, $context);
if (!($admin or $userid==$USER->id)) {
    helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
}
$PAGE->set_context($context);
$PAGE->set_url('/blocks/helpmenow/chathistorylist.php');
$PAGE->set_pagelayout('standard');

# title, navbar, and a nice box
if (!empty($CFG->helpmenow_title)) {
    $blockname = $CFG->helpmenow_title;
} else {
    $blockname = get_string('helpmenow', 'block_helpmenow'); 
}
$title = get_string('chathistories', 'block_helpmenow');
$nav = array(
    array('name' => $blockname),
    array('name' => $title)
);
foreach($nav as $node) {
    $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
}
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
echo $OUTPUT->box_start();

$this_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/chathistorylist.php");
$this_url->param('userid', $userid);
$orderbyname_url = $this_url->out();

$this_url->param('recent', 1);
$orderbydate_url = $this_url->out();

# Get a list of all session2user sessions to query for users who have had 
# conversations with $userid
$last_year = strtotime("-1 year");
$sql = "SELECT sessionid
        FROM {block_helpmenow_session2user}
        WHERE userid = $userid
        AND last_message > 0
        AND last_refresh > $last_year";
$sessions = $DB->get_records_sql($sql);
$sessionids = array();
if ($sessions) {
    foreach ($sessions as $s) {
        $sessionids[] = $s->sessionid;
    }
}

if (count($sessionids) < 1) {
    // No histories availible
    print get_string('history_not_available', 'block_helpmenow');

} else {
    $sessions = implode(', ', $sessionids);

    $orderby = "u.lastname, u.firstname";
    if ($recent) {
        $orderby = "s2u.last_refresh DESC";
    }

    // In the following SQL we concatenate the sessionid + userid because in some session a user can talk with multiple users.
    // However Moodle 2 needs a unique id when using get_records_sql, so we create a unique id with these fields. This unique id field
    // is not using for any other reason than avoiding the uniqure id field warnings. A lot of explanation just for that, isn't it?
    $sql = "
        SELECT concat(s2u.userid, s2u.sessionid), s2u.sessionid, u.*
        FROM {block_helpmenow_session2user} s2u
        JOIN {user} u ON u.id = s2u.userid
        WHERE s2u.userid <> $userid
        AND s2u.sessionid IN ($sessions)
        ORDER BY $orderby
        ";
    $other_user_recs = $DB->get_records_sql($sql);
    $heading = get_string('viewconversation', 'block_helpmenow');
    if ($userid != $USER->id) {
        $mainuser = $DB->get_record('user', array('id' => $userid));
        $name = fullname($mainuser);
        $heading = $name . get_string('conversations', 'block_helpmenow');
    }
    echo $OUTPUT->heading($heading);
    $orderbystring = get_string('orderby', 'block_helpmenow') . " ( <a href=$orderbyname_url>" . get_string('name', 'block_helpmenow') . "</a> | <a href=$orderbydate_url>" . get_string('mostrecentconversation', 'block_helpmenow') . '</a> )';
    print "<div>$orderbystring</div><br />";

    if ($other_user_recs) {
        $displayedcontacts = array();
        foreach ($other_user_recs as $u) {
            if (!isset($displayedcontacts[$u->id])) {
                $history_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/history.php#recent");
                $history_url->param('session', $u->sessionid);
                $history_url->param('date', '-1 year');
                $name = fullname($u);
                $action = new popup_action('click', $history_url->out(), $u->sessionid,
                    array('height' => 400, 'width' => 500));
                $history_link = $OUTPUT->action_link($history_url->out(), $name, $action);
                $history_link = '<div>'.$history_link." ($u->username)</div>";
                print $history_link;
                $displayedcontacts[$u->id] = true;
            }
        }
    }
}

echo $OUTPUT->box_end();

# footer
echo $OUTPUT->footer();

