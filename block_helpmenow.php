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
 * block_helpmenow class definition, which extends Moodle's block_base.
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

require_once(dirname(__FILE__) . '/lib.php');

class block_helpmenow extends block_base {
    /**
     * Overridden block_base method that sets block title and version.
     *
     * @return null
     */
    function init() {
        global $CFG;
        $this->title = get_string('helpmenow', 'block_helpmenow'); 

        $plugin = new object;
        require(dirname(__FILE__) . "/version.php");
        $this->version = $plugin->version;
        $this->cron = $plugin->cron;
    }

    /**
     * Overridden block_base method that generates the content diplayed in the
     * block and returns it.
     *
     * @return stdObject
     */
    function get_content() {
        if (isset($this->content)) { return $this->content; }

        global $CFG, $COURSE, $USER;

        $this->content = (object) array(
            'text' => '',
            'footer' => '',
        );

        # For now, restrict to tech dept for testing.
        /*
        switch ($USER->id) {
            # test accounts:
        case 57219:
        case 56956:
            # tech staff
        case 8712:
        case 58470:
        case 930:
        case 919:
        case 57885:
        case 52650:
        case 37479:
        case 56385:
        case 56528:
        case 5:
            break;
        default:
            if ($USER->id % 2) {
                return $this->content;
            }
        }
         */

        // helpmenow_ensure_queue_exists(); # autocreates a course queue if necessary

        # contexts
        $sitecontext = get_context_instance(CONTEXT_SYSTEM, SITEID);
        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);

        $first = true;

        # queues
        $queues = helpmenow_queue::get_queues_by_context(array($sitecontext->id, $context->id));
        foreach ($queues as $q) {
            if ($q->get_privilege() !== HELPMENOW_QUEUE_HELPEE) {
                continue;
            }

            if ($first) {
                $first = false;
            } else {
                $this->content->text .= '<hr />';
            }
            # if the user has a request, display it, otherwise give a link
            # to create one
            if (isset($q->request[$USER->id])) {
                $connect = new moodle_url("$CFG->wwwroot/blocks/helpmenow/connect.php");
                $connect->param('requestid', $q->request[$USER->id]->id);
                $linktext = "<b>$q->name</b><br /><div style='text-align:center;font-size:small;'>" . get_string('pending', 'block_helpmenow') . "</div>";
                $this->content->text .= link_to_popup_window($connect->out(), 'connect', $linktext, 400, 700, null, null, true);
            } else {
                if ($q->check_available()) {
                    $request = new moodle_url("$CFG->wwwroot/blocks/helpmenow/new_request.php");
                    $request->param('queueid', $q->id);
                    $linktext = "<b>$q->name</b><br /><div style='text-align:center;font-size:small;'>" . get_string('new_request', 'block_helpmenow') . "</div>";
                    $this->content->text .= link_to_popup_window($request->out(), 'connect', $linktext, 400, 700, null, null, true);
                } else {
                    # todo: make this smarter (helpers leave message or configurable)
                    $this->content->text .= "<b>$q->name</b><br /><div style='text-align:center;font-size:small;'>" . get_string('queue_na_short', 'block_helpmenow') . "</div>";
                }
            }
            $this->content->text .= $q->description . "<br />";
        }

        # instructor
        $sql = "
            SELECT q.*
            FROM {$CFG->prefix}block_helpmenow_queue q
            WHERE q.userid = $USER->id
        ";
        if ($instructor_queue = get_record_sql($sql)) {
            $instructor_queue = helpmenow_queue::get_instance(null, $instructor_queue);
            if ($first) {
                $first = false;
            } else {
                $this->content->text .= '<hr />';
            }

            $url = $CFG->wwwroot . "/blocks/helpmenow/ajax.php";
            $this->content->text .= "
                <script type=\"text/javascript\">
                    var helpmenow_url = \"$url\";
                    var helpmenow_interval = ".HELPMENOW_AJAX_REFRESH.";
                </script>
                <script type=\"text/javascript\" src=\"{$CFG->wwwroot}/blocks/helpmenow/lib.js\"></script>
                <b>My Office</b>
                <div id=\"helpmenow_motd\" onclick=\"helpmenow_toggle_motd(true);\" style=\"border:1px dotted black;\">$instructor_queue->description</div>
                <textarea id=\"helpmenow_motd_edit\" onkeypress=\"return helpmenow_enter_motd(event);\" onblur=\"helpmenow_toggle_motd(false)\" style=\"display:none;\" rows=\"4\" cols=\"23\"></textarea>
            ";
            $login = new moodle_url("$CFG->wwwroot/blocks/helpmenow/login.php");
            $login->param('queueid', $instructor_queue->id);
            $login->param('redirect', qualified_me());
            if ($instructor_queue->helper[$USER->id]->isloggedin) {
                $login->param('login', 0);
                $login_status = get_string('loggedin_short', 'block_helpmenow');
                $login_text = get_string('logout', 'block_helpmenow');
            } else {
                $login->param('login', 1);
                $login_status = get_string('loggedout_short', 'block_helpmenow');
                $login_text = get_string('login', 'block_helpmenow');
            }
            $login = $login->out();
            $this->content->text .= "<div style='text-align:center;font-size:small;'>$login_status <a href='$login'>$login_text</a></div>";
            $this->content->text .= "Online students:<br />";

            $students = helpmenow_get_students();
            foreach ($students as $s) {
                $url = $request->out(); # todo: totally fake
                $this->content->text .= link_to_popup_window($request->out(), 'connect', fullname($s), 400, 700, null, null, true) . "<br />";
            }
        }

        # helper link
        if (false) {
        # if (record_exists('block_helpmenow_helper', 'userid', $USER->id)) { # todo: filter instructor queues
            $helper = new moodle_url("$CFG->wwwroot/blocks/helpmenow/helpmenow.php");
            $helper_text = get_string('helper_link', 'block_helpmenow');
            if ($first) {
                $first = false;
            } else {
                $this->content->text .= '<hr />';
            }
            $this->content->text .= link_to_popup_window($helper->out(), 'helper', $helper_text, 400, 700, null, null, true) . "<br />";
        }

        # block message
        if (strlen($CFG->helpmenow_block_message)) {
            if ($first) {
                $first = false;
            } else {
                $this->content->text .= '<hr />';
            }
            $this->content->text .= $CFG->helpmenow_block_message;
        }

        # admin link
        if (has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
            $admin = new moodle_url("$CFG->wwwroot/blocks/helpmenow/admin.php");
            $admin->param('courseid', $COURSE->id);
            $admin = $admin->out();
            $admin_text = get_string('admin_link', 'block_helpmenow');
            $this->content->footer .= "<a href='$admin'>$admin_text</a><br />";
        }

        return $this->content;
    }

    /**
     * Overriden block_base method that is called when Moodle's cron runs.
     *
     * @return boolean
     */
    function cron() {
        $success = true;

        # clean up helpers
        $success = $success and helpmenow_helper::auto_logout();

        # clean up old meetings
        $success = $success and helpmenow_meeting::clean_meetings();

        # clean up abandoned requests
        $success = $success and helpmenow_request::clean_requests();

        # call plugin crons
        $success = $success and helpmenow_plugin::cron_all();

        return $success;
    }

    /**
     * Overriden block_base method that is called when block is installed
     */
    function after_install() {
        helpmenow_plugin::install_all();
    }
}

?>
