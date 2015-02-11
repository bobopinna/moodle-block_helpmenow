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
 * Help me now adobe connect plugin
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');

/**
 * Test to see if the adobeconnect url for the user is working, if it is, then 
 * show the link, otherwise show nothing.
 *
 * Cache this in the $SESSION to avoid lots of repeat queries to adobeconnect 
 * across the Interblag. The user can log out and back in if an update is 
 * necessary.
 */
function helpmenow_adobeconnect_urlexists($userid = false) {
    global $USER, $CFG, $SESSION, $DB;

    if (!$userid) {
        $userid = $USER->id;
        $username = $USER->username;
    } else {
        $username = $DB->get_field('user', 'username', array('id' => $userid));
    }

    if (!isset($SESSION->helpmenow_adobeconnect_urlexists[$userid])) {
        $SESSION->helpmenow_adobeconnect_urlexists = array();
    }
    if (!isset($SESSION->helpmenow_adobeconnect_urlexists[$userid])) {
        if (empty($CFG->helpmenow_adobeconnect_url)) {
            $SESSION->helpmenow_adobeconnect_urlexists[$userid] = false;
        } else {
            $url = "$CFG->helpmenow_adobeconnect_url/$username";
            $ci = curl_init($url);
            curl_setopt($ci, CURLOPT_HEADER, TRUE);
            curl_setopt($ci, CURLOPT_NOBODY, TRUE);
            curl_setopt($ci, CURLOPT_FOLLOWLOCATION, FALSE);
            curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
            $status = array();
            preg_match('/HTTP\/.* ([0-9]+) .*/', curl_exec($ci) , $status);
            $SESSION->helpmenow_adobeconnect_urlexists[$userid] = ($status[1] == 302);
        }
    }

    return $SESSION->helpmenow_adobeconnect_urlexists[$userid];
}

/**
 *     _____ _
 *    / ____| |
 *   | |    | | __ _ ___ ___  ___  ___
 *   | |    | |/ _` / __/ __|/ _ \/ __|
 *   | |____| | (_| \__ \__ \  __/\__ \
 *    \_____|_|\__,_|___/___/\___||___/
 */

/**
 * adobeconnect helpmenow plugin class
 */
class helpmenow_plugin_adobeconnect extends helpmenow_plugin {
    /**
     * Plugin name
     * @var string $plugin
     */
    public $plugin = 'adobeconnect';

    public static function display($sessionid, $privileged = false) {
        global $CFG, $USER;

        if (!helpmenow_adobeconnect_urlexists()) {
            return '';
        }

        if ($privileged) {
            return '<a id="adobeconnect_invite" href="#">'.get_string('adobeconnect_invite', 'block_helpmenow').'</a>';
        }
        return '';
    }

    /**
     * returns formatted information to put in the main block
     * @return a link to adobe connect user page
     */
    public static function block_display() {
        global $CFG, $USER, $OUTPUT;

        if (!helpmenow_adobeconnect_urlexists()) {
            return false;
        }

        $test = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/adobeconnect/meetnow.php");
        $test->param('username', $USER->username);
        $action = new popup_action('click', $test->out(), "adobeconnect",
            array('height' => 800, 'width' => 900));
        return $OUTPUT->action_link($test->out(), 'My Classroom', $action);
    }

    public static function has_user2plugin_data() {
        return true;
    }

    public static function get_user2plugin_link($userid) {
        global $CFG, $DB;
        if (helpmenow_adobeconnect_urlexists($userid)) {
            $join_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/adobeconnect/meetnow.php");
            $username = $DB->get_field('user', 'username', array('id' => $userid));
            $join_url->param('username', $username);
            $join_url = $join_url->out();
            return $join_url;
        }
        return false;
    }
    /**
     * returns array of full url paths to needed javascript libraries
     * @return array
     */
    public static function get_js_libs() {
        global $CFG;

        return array("$CFG->wwwroot/blocks/helpmenow/plugins/adobeconnect/lib_2013052000.js");
    }

    public static function get_js_init_param() {
        global $CFG, $USER;
        $username = preg_replace('/admin$/', '', $USER->username);

        $url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/adobeconnect/meetnow.php");
        $url->param('username', $username);
        $url = $url->out();

        $message = '\'' . fullname($USER) . get_string('adobeconnect_invited1', 'block_helpmenow') . "<a target=\"adobe_connect\" href=\"".$url."\">click here</a> ". get_string('adobeconnect_invited2', 'block_helpmenow') . '\'';
        return $message;
    }
}


