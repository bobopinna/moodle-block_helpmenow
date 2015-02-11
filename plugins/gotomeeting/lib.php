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
 * Help me now gotomeeting lib
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');

define('HELPMENOW_G2M_REST_BASE_URI', 'https://api.citrixonline.com/G2M/rest/');

/**
 * Handles g2m api calls
 * @param string $uri
 * @param string $verb POST, PUT, DELETE, GET
 * @param array $params
 * @param int $user user.id
 * @return mixed
 */
function helpmenow_gotomeeting_api($uri, $verb, $params = array(), $userid = null) {
    global $CFG, $USER, $DB;
    if (!isset($userid)) {
        $userid = $USER->id;
    }

    $uri = HELPMENOW_G2M_REST_BASE_URI . $uri;
    if (!$record = $DB->get_record('block_helpmenow_user2plugin', array('userid' => $userid, 'plugin' => 'gotomeeting'))) {
        return false;
    }
    $user2plugin = new helpmenow_user2plugin_gotomeeting(null, $record);

    $headers = array(
        "Accept: application/json",
        "Content-Type: application/json",
        "Authorization: OAuth oauth_token={$user2plugin->access_token}"
    );

    $ch = curl_init();
    switch ($verb) {
    case 'POST':
    case 'PUT':
        # both post and put need postfields opt
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        if ($verb == 'POST') {
            break;
        }
        # but we only want put to fall through to customrequest opt
    case 'DELETE':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        break;
    case 'GET':
        $fields = array();
        foreach ($params as $f => $v) {
            $fields[] = urlencode($f) . '=' . urlencode($v);
        }
        $fields = implode('&', $fields);
        $uri .= "?$fields";
        break;
    default:
        # todo: unkown verb error
    }
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_URL => $uri,
    ));
    $data = curl_exec($ch);

    # todo: handle error codes
    $responsecode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    switch ($responsecode) {
    case 204:
        return true;
    case 403:
        if (($USER->id !== $userid) or ($USER->id === get_admin()->id)) {   # call for different user or cron
            # todo: we need a way to handle getting a new oauth token for cron
            return false;
        }
        $token_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/plugins/gotomeeting/token.php");
        $token_url->param('redirect', qualified_me());
        $token_url = $token_url->out();
        redirect($token_url);
        break;
    default:
        # todo: something?
        break;
    }
    return json_decode($data);
}

/**
 * ajax method for inviting users to gtm session
 * @param object $request ajax request
 * @return object
 */
function helpmenow_gotomeeting_invite($request) {
    global $USER, $CFG, $DB;

    # verify sesion
    if (!helpmenow_verify_session($request->session)) {
        throw new Exception('Invalid session');
    }

    if (!$record = $DB->get_record('block_helpmenow_user2plugin', array('userid' => $USER->id, 'plugin' => 'gotomeeting'))) {
        throw new Exception('No u2p record');
    }
    $user2plugin = new helpmenow_user2plugin_gotomeeting(null, $record);

    $message = fullname($USER) . ' has invited you to GoToMeeting, <a target="_blank" href="'.$user2plugin->join_url.'">click here</a> to join.';
    $message_rec = (object) array(
        'userid' => null,
        'sessionid' => $request->session,
        'time' => time(),
        'message' => $message,
    );
    $DB->insert_record('block_helpmenow_message', $message_rec);

    return new stdClass;
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
 * gotomeeting plugin class
 */
class helpmenow_plugin_gotomeeting extends helpmenow_plugin {
    /**
     * Plugin name
     * @var string $plugin
     */
    public $plugin = 'gotomeeting';

    /**
     * Cron
     * @return boolean
     */
    public static function cron() {
        return true;
    }

    public static function display($sessionid, $privileged = false) {
        if ($privileged) {
            return '<a href="javascript:void(0)" onclick="helpmenow.chat.gotomeetingInvite();">Invite To My GoToMeeting</a>';
        }
        return '';
    }

    public static function on_login() {
        global $CFG;

        $user2plugin = helpmenow_user2plugin_gotomeeting::get_user2plugin();
        # if we don't have a user2plugin record or we don't have a current meeting for the user, redirect to the create meeting script
        if (!$user2plugin or !isset($user2plugin->meetingid)) {
            return "$CFG->wwwroot/blocks/helpmenow/plugins/gotomeeting/create.php";
        }
        return true;
    }

    public static function on_logout() {
        global $CFG, $USER, $DB;

        $user2plugin = helpmenow_user2plugin_gotomeeting::get_user2plugin();

        # see if the user is still logged in to a different queue/office
        $sql = "
            SELECT 1
            WHERE EXISTS (
                SELECT 1
                FROM {block_helpmenow_helper}
                WHERE userid = $USER->id
                AND isloggedin <> 0
            )
            OR EXISTS (
                SELECT 1
                FROM {block_helpmenow_user}
                WHERE userid = $USER->id
                AND isloggedin <> 0
            )
        ";
        # if they aren't, delete the meeting info from user2plugin record and update the db
        if (!$DB->record_exists_sql($sql)) {
            foreach (array('join_url', 'max_participants', 'unique_meetingid', 'meetingid') as $attribute) {
                $user2plugin->$attribute = null;
            }
            return $user2plugin->update();
        }
        return true;
    }

    /**
     * returns array of valid plugin ajax functions
     * @return array
     */
    public static function get_ajax_functions() {
        return array('helpmenow_gotomeeting_invite');
    }

    public static function get_js_libs() {
        global $CFG;

        return array("$CFG->wwwroot/blocks/helpmenow/plugins/gotomeeting/lib_2013052000.js");
    }
}

/**
 * gotomeeting user2plugin class
 */
class helpmenow_user2plugin_gotomeeting extends helpmenow_user2plugin {
    /**
     * Extra fields
     * @var array $extra_fields
     */
    protected $extra_fields = array(
        'access_token',
        'token_expiration',
        'refresh_token',
        'join_url',
        'max_participants',
        'unique_meetingid',
        'meetingid',
    );

    /**
     * Access token
     * @var string $access_token
     */
    public $access_token;

    /**
     * Token expiratation
     * @var int $token_expiration
     */
    public $token_expiration;

    /**
     * Refresh token
     * @var string $refresh_token
     */
    public $refresh_token;

    /**
     * GoToMeeting joinURL
     * @var string $join_url
     */
    public $join_url;

    /**
     * GoToMeeting maxParticipants
     * @var int $max_participants
     */
    public $max_participants;

    /**
     * GoToMeeting uniquemeetingid
     * @var int $unique_meetingid
     */
    public $unique_meetingid;

    /**
     * GoToMeeting meetingid
     * @var int $meetingid
     */
    public $meetingid;


    /**
     * plugin
     * @var string $plugin
     */
    public $plugin = 'gotomeeting';

    /**
     * Create the meeting. Caller will insert record.
     */
    public function create_meeting() {
        global $USER;

        $params = array(
            'subject' => fullname($USER), # todo: change this
            'starttime' => gmdate('Y-m-d\TH:i:s\Z', time() + (24*60*60)),   # do a day from now to be safe
            'endtime' => gmdate('Y-m-d\TH:i:s\Z', time() + (30*60*60)),     # length of 6 hours to be safe
            'passwordrequired' => 'false',
            'conferencecallinfo' => 'Hybrid',
            'timezonekey' => '',
            'meetingtype' => 'Immediate',
        );
        $meetingdata = helpmenow_gotomeeting_api('meetings', 'POST', $params);
        $meetingdata = reset($meetingdata);
        $this->join_url = $meetingdata->joinURL;
        $this->max_participants = $meetingdata->maxParticipants;
        $this->unique_meetingid = $meetingdata->uniqueMeetingId;
        $this->meetingid = $meetingdata->meetingid;
        return true;
    }
}

