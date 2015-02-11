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

# our library
require_once(dirname(__FILE__) . '/lib.php');

class block_helpmenow extends block_base {
    function init() {
        global $CFG, $PAGE;
        
        $PAGE->requires->jquery();

        if (!empty($CFG->helpmenow_title)) {
            $this->title = $CFG->helpmenow_title;
        } else {
            $this->title = get_string('helpmenow', 'block_helpmenow'); 
        }

        $plugin = new stdClass();
        require(dirname(__FILE__) . "/version.php");
        $this->version = $plugin->version;
        $this->cron = $plugin->cron;
    }

    function get_content() {
        global $OUTPUT;
	
        if ($this->content !== NULL) {
            return $this->content;
        }

        global $CFG, $USER, $SESSION, $DB;

        $this->content = (object) array(
            'text' => '',
            'footer' => '',
        );

        $this->content->text .= '<noscript>'.get_string('noscript', 'block_helpmenow').'</noscript>';

        # the first time a user loads the block this session try to popout
        helpmenow_clean_sessions();     # clean up users sessions

        $popout_url = "$CFG->wwwroot/blocks/helpmenow/popout.php";
        $contact_list = helpmenow_contact_list::get_plugin();
        $contact_list::update_contacts($USER->id);
        # do stuff that stuff that should be done when a user first logs in
        if (!isset($SESSION->helpmenow_first_load)) {
            $SESSION->helpmenow_first_load = true;
            # try to popout the interface (except if the user is not logged in)
            if (!empty($USER->id)) {
                $this->content->text .= <<<EOF
<script>
    try {
        var popout = window.open('', 'hmn_popout', 'menubar=0,location=0,scrollbars,resizable,width=250,height=400');
        if (popout.location.href == "about:blank" || typeof popout.location === 'undefined') {
            popout.location = "$popout_url";
        }
    } catch (error) {
    }
</script>
EOF;
            }
            # update the users contacts
        }

        $this->content->text .= helpmenow_block_interface();
        $break = false;
        if ($contact_list_display = $contact_list::block_display()) {
            $this->contect->footer .= $contact_list_display;
            $break = true;
        }
        # admin link
        $sitecontext = context_system::instance();
        if (has_capability(HELPMENOW_CAP_MANAGE, $sitecontext)) {
            $admin = "$CFG->wwwroot/blocks/helpmenow/admin/manage_queues.php";
            $admin_text = get_string('admin_link', 'block_helpmenow');
            if ($break) {
                $this->content->footer .= "<br />";
            } else {
                $break = true;
            }
            $this->content->footer .= "<a href='$admin'>$admin_text</a>";
        }

        # "hallway" link
        if (has_capability(HELPMENOW_CAP_MANAGE, $sitecontext) or $DB->record_exists('block_helpmenow_helper', array('userid' => $USER->id))) {
            $who = get_string('who', 'block_helpmenow');
            if ($break) {
                $this->content->footer .= "<br />";
            } else {
                $break = true;
            }
            $this->content->footer .= "<a href='$CFG->wwwroot/blocks/helpmenow/hallway.php'>$who</a>";
        }

        # Chat histories link
        $chathistories = get_string('chathistories', 'block_helpmenow');
        if ($break) {
            $this->content->footer .= "<br />";
        } else {
            $break = true;
        }
        $chat_history_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/chathistorylist.php");
        $chat_history_url->param('userid', $USER->id);
        $this->content->footer .= "<a href=" . $chat_history_url->out() . ">$chathistories</a>";

        if ($contact_list::is_admin_or_teacher()) {
            # call plugin methods to check for additional display information
            foreach (helpmenow_plugin::get_plugins() as $pluginname) {
                $class = "helpmenow_plugin_$pluginname";
                if($plugindisplay = $class::block_display()) {
                    if ($break) {
                        $this->content->footer .= "<br />";
                    } else {
                        $break = true;
                    }
                    $this->content->footer .= $plugindisplay;
                }
            }
        }
        $this->content->footer .= <<<EOF
<div id="helpmenow_last_refresh_div"></div>
<div class="helpmenow_textalignright">
    <div class="helpmenow_floatleft">
        <a href="javascript:void(0)" onclick="helpmenow.chime();">
            <img src="$CFG->wwwroot/blocks/helpmenow/media/Bell.png" />
        </a>
    </div>
EOF;
        $popout = get_string('popout', 'block_helpmenow');
        $action = new popup_action('click', $popout_url, 'hmn_popout',
            array('height' => 400, 'width' => 250));
        $this->content->footer .=
            $OUTPUT->action_link($popout_url, $popout, $action)  . '</div>';

        return $this->content;
    }

    function cron() {
        $success = true;
        $success = $success and helpmenow_autologout_helpers();
        $success = $success and helpmenow_autologout_users();
        $success = $success and helpmenow_email_messages();
        $success = $success and helpmenow_clean_sessions(true);
        return $success;
    }

    function has_config() {
        return true;
    }
}

