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
 * Help me now wiziq lib
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');

define('HELPMENOW_WIZIQ_API_URL', 'http://class.api.wiziq.com/');
define('HELPMENOW_WIZIQ_DURATION', 60);     # this is minutes as that's what the api takes


function helpmenow_wiziq_add_attendee($class_id) {
    global $USER;

    $attendee_list = new SimpleXMLElement('<attendee_list/>');
    $attendee = $attendee_list->addChild('attendee');
    $attendee->addChild('attendee_id', $USER->id);
    $attendee->addChild('screen_name', fullname($USER));
    $attendee->addChild('language_culture_name', 'en-us');

    $params = array(
        'class_id' => $class_id,
        'attendee_list' => $attendee_list->asXML(),
    );
    return helpmenow_wiziq_api('add_attendees', $params);
}

function helpmenow_wiziq_api($method, $params) {
    global $CFG;

    $signature = array();
    $signature['access_key'] = $CFG->helpmenow_wiziq_access_key;
    $signature['timestamp'] = time();
    $signature['method'] = $method;
    $signature['signature'] = helpmenow_wiziq_api_signature($signature);

    $params = array_merge($signature, $params);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_POSTFIELDS => http_build_query($params, '', '&'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_URL => HELPMENOW_WIZIQ_API_URL . "?method=$method",
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    if (debugging()) {
        print_object($response);
    }

    return new SimpleXMLElement($response);
}

function helpmenow_wiziq_api_signature($sig_params) {
    global $CFG;

    $sig_base = array();
    foreach ($sig_params as $f => $v) {
        $sig_base[] = "$f=$v";
    }
    $sig_base = implode('&', $sig_base);

    return base64_encode(helpmenow_wiziq_hmacsha1(urlencode($CFG->helpmenow_wiziq_secret_key), $sig_base));
}

/**
 * using wiziq's "hmac_sha1" function, as it doesn't match php's hash_hmac
 */
function helpmenow_wiziq_hmacsha1($key, $data) {
    $blocksize = 64;
    $hashfunc = 'sha1';
    if (strlen($key)>$blocksize) {
        $key = pack('H*', $hashfunc($key));
    }
    $key = str_pad($key, $blocksize,chr(0x00));
    $ipad = str_repeat(chr(0x36), $blocksize);
    $opad = str_repeat(chr(0x5c), $blocksize);
    $hmac = pack(
        'H*',$hashfunc(
            ($key^$opad).pack(
                'H*',$hashfunc(
                    ($key^$ipad).$data
                )
            )
        )
    );
    return $hmac;
}

/**
 * invites user to a wiziq class
 *
 * @param int $session_id helpmenow_session.id
 * @param int $class_id wiziq class id
 */
function helpmenow_wiziq_invite($session_id, $class_id) {
    global $CFG, $USER, $DB;

    if ($s2p_rec = $DB->get_record('block_helpmenow_s2p', array('sessionid' => $session_id, 'plugin' => 'wiziq'))) {
        $s2p = new helpmenow_session2plugin_wiziq(null, $s2p_rec);
        $method = 'update';
    } else {
        $s2p = new helpmenow_session2plugin_wiziq(null, (object) array('sessionid' => $session_id));
        $method = 'insert';
    }
    if (!in_array($class_id, $s2p->classes)) {
        $s2p->classes[] = $class_id;
        $s2p->$method();
    }

    $join_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/join.php");
    $join_url->param('classid', $class_id);
    $join_url->param('sessionid', $session_id);
    $join_url = $join_url->out();

    $message = fullname($USER) . ' has invited you to use voice, video, and whiteboarding, <a target="wiziq_session" href="'.$join_url.'">click here</a> to join.';
    return helpmenow_message($session_id, null, $message);
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
 * wiziq helpmenow plugin class
 */
class helpmenow_plugin_wiziq extends helpmenow_plugin {
    /**
     * Plugin name
     * @var string $plugin
     */
    public $plugin = 'wiziq';

    /**
     * Cron
     * @return boolean
     */
    public static function cron() {
        return true;
    }

    public static function display($sessionid, $privileged = false) {
        global $CFG, $USER, $OUTPUT;

        if ($privileged) {
            $connect = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/connect.php");
            $connect->param('sessionid', $sessionid);
            $action = new popup_action('click', $connect->out(), "wiziq",
                array('height' => 400, 'width' => 500));
            $output = $OUTPUT->action_link($connect->out(), 'Invite to WizIQ', $action);

            $user2plugin = helpmenow_user2plugin_wiziq::get_user2plugin();
            if ($user2plugin->verify_active_meeting(true)) {
                $connect->param('reopen', 1);
                $action = new popup_action('click', $connect->out(), "wiziq_session",
                    array('height' => 400, 'width' => 500));
                $output .= ' | ' . $OUTPUT->action_link($connect->out(), 'Re-open WizIQ Window', $action);
            }
            return $output;
        }
        return '';
    }

    public static function on_chat_refresh($request, &$response) {
        global $DB;

        $session = $DB->get_record('block_helpmenow_session', array('id' => $request->session));
        if (helpmenow_check_privileged($session)) {
            $response->wiziq = self::display($request->session, true);
        }
    }

    /**
     * returns array of full url paths to needed javascript libraries
     * @return array
     */
    public static function get_js_libs() {
        global $CFG;

        return array("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/lib_2013052000.js");
    }

    public static function has_user2plugin_data() {
        return true;
    }

    public static function get_user2plugin_link($userid) {
        global $DB;

        $plugin = 'wiziq';
        if (!$u2p = $DB->get_record('block_helpmenow_user2plugin', array('userid' => $userid, 'plugin' => $plugin))) {
            return false;
        } else {
            $class = "helpmenow_user2plugin_".$plugin;
            $u2p = new $class(null, $u2p);
            if ($link = $u2p->get_link()) {
                return $link;
            } else {
                return false;
            }
        }
    }

    /**
     * returns formatted information to put in the main block
     * @return a link for testing wiziq
     */
    public static function block_display() {
        global $CFG;
        $test = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/connect.php");
        $test->param('test', 1);
        $action = new popup_action('click', $test->out(), "wiziq",
            array('height' => 800, 'width' => 900));
        return $OUTPUT->action_link($test->out(), 'Test WizIQ', $action);
    }
}

/**
 * wiziq user2plugin class
 */
class helpmenow_user2plugin_wiziq extends helpmenow_user2plugin {
    /**
     * Extra fields
     * @var array $extra_fields
     */
    protected $extra_fields = array(
        'class_id',
        'presenter_url',
        'duration',
        'timecreated',
        'last_updated',
    );

    /**
     * wiziq class_id
     * @var int $class_id
     */
    public $class_id;

    /**
     * wiziq presenter_url
     * @var string $presenter_url
     */
    public $presenter_url;

    /**
     * duration in seconds of meeting
     * @var integer $duration
     */
    public $duration;

    /**
     * timestamp of creation
     * @var integer $timecreated
     */
    public $timecreated;

    /**
     * timestamp of when we last got some word about the meeting
     * @var last_updated
     */
    public $last_updated;

    /**
     * plugin
     * @var string $plugin
     */
    public $plugin = 'wiziq';

    /**
     * Create the meeting.
     */
    public function create_meeting() {
        global $CFG, $USER;

        $update_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/update.php");
        $update_url->param('user_id', $USER->id);

        $params = array(
            'title' => fullname($USER),
            'start_time' => date('m/d/Y G:i:s'),
            'time_zone' => date('e'),
            'presenter_id' => $USER->id,
            'presenter_name' => fullname($USER),
            'duration' => HELPMENOW_WIZIQ_DURATION,
            'status_ping_url' => $update_url->out(),
        );
        $response = helpmenow_wiziq_api('create', $params);

        if ((string) $response['status'] == 'fail') {
            $error = (integer) $response->error['code'];
            $error_msg = (string) $response->error['msg'];

            helpmenow_log($USER->id, 'wiziq_error', "code: $error; msg: $error_msg");
            switch ($error) {
            case 1012:
                helpmenow_fatal_error('License limit has been reached. Please contact your administrator');
            default:
                helpmenow_fatal_error("WizIQ error: $error. Please contact your administrator.<br />Error: $error_msg");
            }
        }

        $this->class_id = (integer) $response->create->class_details->class_id;
        $this->presenter_url = (string) $response->create->class_details->presenter_list->presenter[0]->presenter_url;
        $this->duration = HELPMENOW_WIZIQ_DURATION * 60;    # we're saving in seconds instead of minutes
        $this->timecreated = time();
        $this->last_updated = time();

        $this->update();

        return true;
    }

    /**
     * override get_user2plugin to create a record if we don't have one
     * todo: consider making this the behaviour of the parent class
     */
    public static function get_user2plugin($userid = null) {
        if (!isset($userid)) {
            global $USER;
            $userid = $USER->id;
        }
        if (!$user2plugin = parent::get_user2plugin($userid)) {
            $user2plugin = new helpmenow_user2plugin_wiziq();
            $user2plugin->userid = $USER->id;
            $user2plugin->insert();
        }
        return $user2plugin;
    }

    /**
     * see if the meeting is still ative
     * @param $call boolean whether or not to make a call to wiziq for the answer
     * @return bool true = active
     */
    public function verify_active_meeting($call = false) {
        if (!isset($this->class_id)) { return false; }  # clearly if the class_id isn't set we don't have an active class
        if (!$call and ($last_updated > time() - 60)) { return true; }  # limit calls to wiziq to once a minute, unless we force it

        global $USER;

        $params = array(
            'class_id' => $this->class_id,
            'title' => fullname($USER),
        );
        $response = helpmenow_wiziq_api('modify', $params);

        # if we can modify it at all, it's cause it hasn't started
        if ((string) $response['status'] == 'ok') {
            $this->last_updated = time();
            $this->update();
            return true;
        }

        switch ((integer) $response->error['code']) {
        case 1015:  # in-progress
            $this->last_updated = time();
            $this->update();
            return true;
        case 1016:  # completed
        case 1017:  # expired
        case 1018:  # deleted
        default:
            $this->delete_meeting();
            return false;
        }
    }

    /**
     * "delete" the meeting
     */
    public function delete_meeting() {
        foreach ($this->extra_fields as $attribute) {
            $this->$attribute = null;
        }
        return $this->update();
    }


    public function get_link() {
        global $CFG;
        if (isset($this->class_id)) {
            $join_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/wiziq/join.php");
            $join_url->param('classid', $this->class_id);
            $join_url = $join_url->out();
            return $join_url;
        }
        return false;
    }

}

/**
 * wiziq session2plugin class
 */
class helpmenow_session2plugin_wiziq extends helpmenow_session2plugin {
    /**
     * Extra fields
     * @var array $extra_fields
     */
    protected $extra_fields = array(
        'classes',
    );

    /**
     * array of wiziq classes that have been linked in this session
     * @var array $classes
     */
    public $classes = array();

    /**
     * plugin
     * @var string $plugin
     */
    public $plugin = 'wiziq';
}

