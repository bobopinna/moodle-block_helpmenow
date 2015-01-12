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
 * Core help me now library.
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/db/access.php');
require_once(dirname(__FILE__) . '/form.php');

/**
 * Some defines for queue privileges. This is disjoint from capabilities, as
 * a user can have the helper cap but still needs to be added as a helper to
 * the appropriate queue.
 */
define('HELPMENOW_QUEUE_HELPER', 'helper');
define('HELPMENOW_QUEUE_HELPEE', 'helpee');
define('HELPMENOW_NOT_PRIVILEGED', 'notprivileged');

/**
 * Defines for email sending. This will probably be settings in the future
 */
define('HELPMENOW_EMAIL_EARLYCUTOFF', 30 * 60);     # earliest missed message should be 30+ minutes ago
define('HELPMENOW_EMAIL_LATECUTOFF', 10 * 60);      # latest missed message should be 10+ minutes ago

/**
 * defines for our javascript client version, so we only have to change one thing
 */
define('HELPMENOW_CLIENT_VERSION', 2014042300);

/**
 * period (in seconds) we wait for before we decide that the user is in fact not online
 */
define('HELPMENOW_CUTOFF_DELAY', 120);

define('HELPMENOW_BLOCK_ALERT_DELAY', 5);   # delay so the block isn't alerting when the user is already in the chat

function helpmenow_verify_session($session) {
    global $CFG, $USER, $DB;
    $sql = "
        SELECT 1
        FROM {block_helpmenow_session} s
        JOIN {block_helpmenow_session2user} s2u ON s2u.sessionid = s.id
        WHERE s2u.userid = $USER->id
        AND s.id = $session
    ";
    return $DB->record_exists_sql($sql);
}

/**
 * We can't use popup_action and action_link as helpmenow reload the html.
 * popup_action/action_link have generated YUI3 call into the HTML end code.
 * Their is currently no function that reload this piece of code, and it seems overkill
 * to reload the full HTML end code for this specific block only. So we created this function
 * create_popup.
 *
 * @param $title
 * @param $url
 * @param $target
 * @param int $menubar
 * @param int $location
 * @param bool $scrollbars
 * @param bool $resizable
 * @param int $width
 * @param int $height
 * @return string
 */
function create_popup($title, $url, $target, $menubar=0, $location=0, $scrollbars=true,
                      $resizable=true, $width=500, $height=400, $fullscreen=false,
                      $toolbar=true, $status=true, $dependent=true, $directories=0) {

    if ($scrollbars) {
        $scrollbars = ',scrollbars';
    } else {
        $scrollbars = '';
    }

    if ($resizable) {
        $resizable = ',resizable';
    } else {
        $resizable = '';
    }

    if ($fullscreen) {
        $fullscreen = ',fullscreen';
    } else {
        $fullscreen = '';
    }

    if ($toolbar) {
        $toolbar = ',toolbar';
    } else {
        $toolbar = '';
    }

    if ($status) {
        $status = ',status';
    } else {
        $status = '';
    }

    if ($dependent) {
        $dependent = ',dependent';
    } else {
        $dependent = '';
    }

    $onclick = 'this.target=\''.$target.'\'; return openpopup(null,{"url":"'.$url.'","name":"'
        . $target.'","options":"menubar='.$menubar
        . ',location='.$location.$scrollbars.$resizable.$fullscreen.$toolbar.$status.$dependent
        . ',width='.$width.',height='.$height.',directories='.$directories
        . '"});';

    $html = html_writer::link(new moodle_url($url), $title,
        array('onclick' => $onclick, 'title' => $title));

    // Argh - very ugly hack - we convert the $amp; wrongly converted by html_writer back to &.
    // (there could more issues than just the $amp but we don't use complicate params, so it should be fine.)
    // The proper fix, I think, would be avoid using such long onclick.
    // Note: Chrome browser likes to reformat the onclick content making creating a proper onclick string a nightmare :)
    $html = str_replace('&amp;', '&', $html);

    return $html;
}

/**
 * Check if the user is an admin or teacher or if the user is a queue helper
 */
function helpmenow_check_privileged($session) {
    global $USER, $CFG, $DB;

    $contact_list = helpmenow_contact_list::get_plugin();

    if (isset($session->queueid)) {
        $sql = "
            SELECT 1
            FROM {block_helpmenow_queue} q
            JOIN {block_helpmenow_helper} h ON h.queueid = q.id
            WHERE q.id = $session->queueid
            AND h.userid = $USER->id
        ";
        if ($DB->record_exists_sql($sql)) {
            return true;
        }
    } else if ($contact_list::is_admin_or_teacher()) {
        return true;
    }
    return false;
}

function helpmenow_cutoff() {
    global $CFG;
    if (isset($CFG->helpmenow_no_cutoff) and $CFG->helpmenow_no_cutoff) {    # set this to true to see everyone
        return 0;
    }
    return time() - HELPMENOW_CUTOFF_DELAY;
}

/**
 * adds user to a session
 * @param int $userid user to add
 * @param int $sessionid session.id
 * @param int $last_refresh timestamp
 */
function helpmenow_add_user($userid, $sessionid, $last_refresh = 0) {
    global $CFG, $DB;
    $sql = "
        SELECT *
        FROM {block_helpmenow_message}
        WHERE sessionid = $sessionid
        ORDER BY id ASC
    ";
    $messages = $DB->get_records_sql($sql);

    $session2user_rec = (object) array(
        'sessionid' => $sessionid,
        'userid' => $userid,
        'last_refresh' => $last_refresh,
        'new_messages' => helpmenow_format_messages($messages),
        'cache' => json_encode((object) array(
            'html' => '',
            'beep' => false,
            'last_message' => 0,
        )),
    );
    return $DB->insert_record('block_helpmenow_session2user', $session2user_rec);
}

/**
 * prints an error and ends execution
 * @param string $message message to be printed
 * @param bool $print_header print generic helpmenow header or not
 */
function helpmenow_fatal_error($message, $print_header = true, $close = false) {
    global $OUTPUT, $PAGE;

    if ($print_header) {
        $title = get_string('helpmenow', 'block_helpmenow');
        $nav = array(array('name' => $title));
        foreach($nav as $node) {
            $PAGE->navbar->add($node['name'], isset($node['link'])?$node['link']:null);
        }
        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        echo $OUTPUT->header();
        echo $OUTPUT->box($message);
        echo $OUTPUT->footer();
    } else {
        echo $message;
    }
    if ($close and !debugging()) {
        echo "<script type=\"text/javascript\">close();</script>";
    }
    die;
}

/**
 * ensures users have a helpmenow_user record
 */
function helpmenow_ensure_user_exists() {
    global $USER, $DB;
    if ($DB->record_exists('block_helpmenow_user', array('userid' => $USER->id))) {
        return;
    }

    $helpmenow_user = (object) array(
        'userid' => $USER->id,
        'motd' => '',
    );

    $DB->insert_record('block_helpmenow_user', $helpmenow_user);
}

/**
 * inserts a message into block_helpmenow_log
 * @param int $userid user performing action
 * @param string $action action user is performing
 * @param string $details details of the action
 */
function helpmenow_log($userid, $action, $details) {
    global $DB;
    $new_record = (object) array(
        'userid' => $userid,
        'action' => $action,
        'details' => $details,
        'timecreated' => time(),
    );
    $DB->insert_record('block_helpmenow_log', $new_record);
}

function helpmenow_clean_sessions($all = false) {
    global $CFG, $USER, $DB;

    $sql = "";
    if (!$all) {
        $sql = "JOIN {block_helpmenow_session2user} s2u ON s2u.sessionid = s.id AND s2u.userid = $USER->id";
    }
    $sql = "
        SELECT s.*
        FROM {block_helpmenow_session} s
        $sql
        WHERE s.iscurrent = 1
        ";
    if ($sessions = $DB->get_records_sql($sql)) {
        foreach ($sessions as $s) {
            if (!is_null($s->queueid)) {    # queue specific
                # if there are any messages that no helpers have seen, this isn't old
                $sql = "
                    SELECT 1
                    FROM {block_helpmenow_message} m
                    WHERE sessionid = $s->id
                    AND notify = 1
                    AND time > (
                        SELECT max(last_refresh)
                        FROM {block_helpmenow_session2user}
                        WHERE sessionid = $s->id
                        AND userid <> $s->createdby
                    )
                ";
                if ($DB->record_exists_sql($sql)) {
                    continue;
                }
            }
            $session_users = $DB->get_records('block_helpmenow_session2user', array('sessionid' => $s->id));
            foreach ($session_users as $su) {
                if (!is_null($s->queueid) and $s->createdby == $su->userid and count($session_users) > 1) {
                    continue;
                }
                if (($su->last_refresh + 60) > time()) {
                    continue 2;
                }
            }
            $DB->set_field('block_helpmenow_session', 'iscurrent', 0, array('id' => $s->id));
        }
    }
}

function helpmenow_autologout_helpers() {
    global $CFG, $DB;

    $cutoff = helpmenow_cutoff();
    $sql = "
        SELECT h.*, hu.lastaccess AS lastaccess
        FROM {block_helpmenow_helper} h
        JOIN {block_helpmenow_user} hu ON hu.userid = h.userid
        WHERE h.isloggedin <> 0
        AND hu.lastaccess < $cutoff
        ";
    if (!$helpers = $DB->get_records_sql($sql)) {
        return true;
    }

    $success = true;
    foreach ($helpers as $h) {
        $duration = time() - $h->isloggedin;
        helpmenow_log($h->userid, 'maybe_auto_logged_out', "queueid: $h->queueid, duration: $duration, cutoff: $cutoff, lastaccess: {$h->lastaccess}");
        /*
        $h->isloggedin = 0;
        $success = $success and $DB->update_record('block_helpmenow_helper', $h);
         */
    }

    return $success;
}

function helpmenow_autologout_users() {
    global $CFG, $DB;

    $cutoff = helpmenow_cutoff();
    $sql = "
        SELECT hu.*
        FROM {block_helpmenow_user} hu
        WHERE hu.isloggedin <> 0
        AND hu.lastaccess < $cutoff
        ";
    if (!$users = $DB->get_records_sql($sql)) {
        return true;
    }

    $success = true;
    foreach ($users as $u) {
        $duration = time() - $u->isloggedin;
        helpmenow_log($u->userid, 'maybe_auto_logged_out', "duration: $duration, cutoff: $cutoff, lastaccess: {$u->lastaccess}");
        /*
        $u->isloggedin = 0;
        $success = $success and $DB->update_record('block_helpmenow_user', $u);
         */
    }

    return $success;
}

/**
 * prints hallway lists
 * @param array $users array of users
 */
function helpmenow_print_hallway($users) {
    global $CFG, $OUTPUT;
    static $admin;
    if (!isset($admin)) {
        $admin = has_capability(HELPMENOW_CAP_MANAGE, context_system::instance());
    }
    # start setting up the table
    $head = array(
        get_string('name'),
        get_string('motd', 'block_helpmenow'),
        get_string('loggedin', 'block_helpmenow'),
        get_string('loggedinsince', 'block_helpmenow'),
        get_string('lastaccess', 'block_helpmenow'),
    );

    $num_plugins = 0;
    $plugins = array();
    $plugin_names = array();
    if ($admin) {
        foreach (helpmenow_plugin::get_plugins() as $plugin) {
            $class = "helpmenow_plugin_$plugin";
            if($class::has_user2plugin_data()) {
                $plugins[] = $plugin;
                $plugin_names[] = get_string("{$plugin}_settings_heading", 'block_helpmenow');
                $num_plugins++;
            }
        }

        $head = array_merge($head, $plugin_names);
    };
    $table = new html_table();
    $table->head = $head;
    $table->attributes['class'] = 'generaltable';
    $table->tablealign = 'center';

    usort($users, function($a, $b) {
        if (!($a->isloggedin xor $b->isloggedin)) {
            return strcmp(strtolower("$a->lastname $a->firstname"), strtolower("$b->lastname $b->firstname"));
        }
        return $a->isloggedin ? -1 : 1;
    });

    $na = get_string('na', 'block_helpmenow');
    $yes = get_string('yes');
    $no = get_string('no');
    $not_found = get_string('not_found', 'block_helpmenow');
    $wander = get_string('wander', 'block_helpmenow');

    foreach ($users as $u) {
        $name = fullname($u);
        if ($admin and $u->isloggedin) {
            $connect = new moodle_url("$CFG->wwwroot/blocks/helpmenow/connect.php");
            $connect->param('userid', $u->id);
            $name = create_popup($name, $connect->out(), $u->id);
        }
        $row = array(
            $name,
            isset($u->motd) ? $u->motd : $na,
        );

        if ($u->isloggedin) {
            $row[] = $yes;
            $row[] = $u->isloggedin ? userdate($u->isloggedin) : $na;
            $row[] = $u->lastaccess ? userdate($u->lastaccess) : $na;
            if ($admin) {
                foreach ($plugins as $pluginname) {
                    $class = "helpmenow_plugin_$pluginname";
                    if ($link = $class::get_user2plugin_link($u->userid)) {
                        $row[] = "<a href=\"$link\" target=\"_blank\">$wander</a>";
                    } else {
                        $row[] = $not_found;
                    }
                }
            }
        } else {
            $row[] = $no;
            $row[] = $u->isloggedin ? userdate($u->isloggedin) : $na;
            $row[] = $u->lastaccess ? userdate($u->lastaccess) : $na;
            if ($admin) {
                foreach ($plugins as $plugin) {
                    $row[] = $na;
                }
            }
        }
        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

function helpmenow_block_interface() {
    global $CFG, $USER, $OUTPUT, $DB, $PAGE;

    helpmenow_ensure_user_exists();

    $output = '';

    $output .= <<<EOF
<div id="helpmenow_queue_div"></div>
EOF;

    $contact_list = helpmenow_contact_list::get_plugin();
    if($contact_list::is_teacher()) {
        $helpmenow_user = $DB->get_record('block_helpmenow_user', array('userid' => $USER->id));
        $instyle = $outstyle = '';
        if ($helpmenow_user->isloggedin) {
            $outstyle = 'style="display: none;"';
        } else {
            $instyle = 'style="display: none;"';
        }
        $login_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/login.php");
        $login_url->param('login', 0);
        $action = new popup_action('click', $login_url->out(), "login",
            array('height' => 400, 'width' => 500));
        $logout = $OUTPUT->action_link($login_url->out(),
            get_string('leave_office', 'block_helpmenow'), $action);
        $login_url->param('login', 1);
        $action = new popup_action('click', $login_url->out(), "login",
            array('height' => 400, 'width' => 500));
        $login = $OUTPUT->action_link($login_url->out(),
            get_string('enter_office', 'block_helpmenow'), $action);
        $my_office = get_string('my_office', 'block_helpmenow');
        $out_of_office = get_string('out_of_office', 'block_helpmenow');
        $online_students = get_string('online_students', 'block_helpmenow');

        $output .= <<<EOF
<div id="helpmenow_office">
    <div><b>$my_office</b></div>
    <div id="helpmenow_motd" onclick="helpmenow.block.toggleMOTD(true);" style="border:1px dotted black; width:12em; min-height:1em; padding:.2em; margin-top:.5em;">$helpmenow_user->motd</div>
    <textarea id="helpmenow_motd_edit" onkeypress="return helpmenow.block.keypressMOTD(event);" onblur="helpmenow.block.toggleMOTD(false)" style="display:none; margin-top:.5em;" rows="4" cols="22"></textarea>
    <div style="text-align: center; font-size:small; margin-top:.5em;">
        <div id="helpmenow_logged_in_div_0" $instyle>$logout</div>
        <div id="helpmenow_logged_out_div_0" $outstyle>$out_of_office | $login</div>
    </div>
    <div style="margin-top:.5em;">$online_students</div>
    <div id="helpmenow_users_div"></div>
</div>
EOF;
    } else if($contact_list::is_student()) {
        $output .= '
            <div>'.get_string('instructors', 'block_helpmenow').'</div>
            <div id="helpmenow_users_div"></div>
            ';
    }
    $jplayer = helpmenow_jplayer();
    $version = HELPMENOW_CLIENT_VERSION;

    if (!empty($CFG->helpmenow_title)) {
        $titlename = $CFG->helpmenow_title;
    } else {
        $titlename = get_string('helpmenow', 'block_helpmenow');
    }

    $PAGE->requires->jquery();
    $output .= <<<EOF
<hr />
$jplayer
<script src="$CFG->wwwroot/blocks/helpmenow/javascript/lib/jquery.titlealert.js" type="text/javascript"></script>
<script src="$CFG->wwwroot/blocks/helpmenow/javascript/lib/json2.js" type="text/javascript"></script>
<script type="text/javascript" src="$CFG->wwwroot/blocks/helpmenow/javascript/client/$version/lib.js"></script>
<script type="text/javascript" src="$CFG->wwwroot/blocks/helpmenow/javascript/client/$version/block.js"></script>
<script type="text/javascript">
    helpmenow.setServerURL("$CFG->wwwroot/blocks/helpmenow/ajax.php");
    helpmenow.setTitleName("$titlename");
</script>
<div id="helpmenow_chime"></div>
EOF;

    return $output;
}

function helpmenow_notify_once($messageid) {
    global $SESSION;
    if (!isset($SESSION->helpmenow_notifications)) {
        $SESSION->helpmenow_notifications = array();
    }
    if (!isset($SESSION->helpmenow_notifications[$messageid])) {
        $SESSION->helpmenow_notifications[$messageid] = true;
        return true;
    }
    return false;
}

function helpmenow_jplayer() {
    global $CFG;
    $root = preg_replace('#^https?://.*?(/|$)#', '\1', $CFG->wwwroot);
    $rval = <<<EOF
<script src="$CFG->wwwroot/blocks/helpmenow/javascript/lib/jquery.jplayer.min.js" type="text/javascript"></script>
<script type="text/javascript">
    $(document).ready(function () {
        $("#helpmenow_chime").jPlayer({
            ready: function () {
                $(this).jPlayer("setMedia", {
                    oga: "$root/blocks/helpmenow/media/cowbell.ogg",
                    mp3: "$root/blocks/helpmenow/media/cowbell.mp3"
                });
            },
            swfPath: "$root/blocks/helpmenow/javascript/lib/Jplayer.swf",
            solution: "html,flash",
            supplied: "mp3,oga"
        });
    });
</script>
EOF;

    return $rval;
}

/**
 * inserts message into session and updates session2user records
 *
 * @param int $sessionid session.id
 * @param mixed $userid user.id; null for system messages
 * @param string $message message
 * @param int $notify integer boolean indicating if message should cause client beeps
 * @return boolean success
 */
function helpmenow_message($sessionid, $userid, $message, $notify = 1) {
    global $CFG, $DB;

    $message_rec = (object) array(
        'userid' => $userid,
        'sessionid' => $sessionid,
        'time' => time(),
        'message' => $message,
        'notify' => $notify,
    );
    if (!$last_message = $DB->insert_record('block_helpmenow_message', $message_rec)) {
        return false;
    }

    $session = $DB->get_record('block_helpmenow_session', array('id' => $sessionid));
    $session->last_message = $last_message;
    $session->iscurrent = 1;
    $DB->update_record('block_helpmenow_session', $session);

    $sql = "
        SELECT *
        FROM {block_helpmenow_session2user}
        WHERE sessionid = $sessionid
    ";
    if (isset($userid)) {
        $sql .= "AND userid <> $userid";
    }
    foreach ($DB->get_records_sql($sql) as $s2u) {
        $formatted_message = helpmenow_format_message($message_rec, $s2u->userid);
        $cache = json_decode($s2u->cache);
        $cache->last_message = $last_message;
        if ($notify) {
            $cache->beep = true;
            $cache->title_flash = format_string($message);
        }
        $cache->html = $cache->html . $formatted_message;
        $s2u->cache = json_encode($cache);
        $DB->update_record('block_helpmenow_session2user', $s2u);
    }

    return true;
}

/**
 * returns unread messages
 * @param int $sessionid session.id
 * @param int $user user.id
 * @return mixed array of messages or false
 */
function helpmenow_get_unread($sessionid, $userid) {
    global $CFG, $DB;

    $s2u = $DB->get_record('block_helpmenow_session2user', array('userid' => $userid, 'sessionid' => $sessionid));
    if ($s2u->last_read > 0)
        $last_message = $s2u->last_read;
    else
        $last_message = $s2u->last_message;

    $sql = "
        SELECT *
        FROM {block_helpmenow_message}
        WHERE sessionid = $sessionid
        AND id > $last_message
        AND (
            userid <> $userid
            OR userid IS NULL
        )
        ORDER BY id ASC
    ";
    return $DB->get_records_sql($sql);
}

/**
 * returns entirety of session messages
 * @param int $sessionid
 * @return mixed array of messages or false
 */
function helpmenow_get_history($sessionid) {
    global $DB;

    return $DB->get_records('block_helpmenow_message', array('sessionid' => $sessionid), 'id ASC');
}

/**
 * returns entirety of session messages
 * @param array of (int $sessionid)
 * @return mixed array of messages or false
 */
function helpmenow_get_history_list($sessionids) {
    global $CFG, $DB;
    $sql = "
        SELECT m.*, q.name as queue_name
        FROM {block_helpmenow_message} m
        JOIN {block_helpmenow_session} s on s.id = m.sessionid
        LEFT JOIN {block_helpmenow_queue} q on s.queueid = q.id
        WHERE sessionid in ($sessionids)
        ORDER BY m.id ASC
        ";
    return $DB->get_records_sql($sql);
}

function helpmenow_filter_messages_history($messages) { // $messages is not modified
    return array_filter($messages,
        function($m) {
            if (is_null($m->userid)) {
                return false;
            }
            return true;
        });
}

/**
 * formats array of messages
 */
function helpmenow_format_messages($messages) {
    global $USER;
    $userid = $USER->id;

    $output = '';
    if (!empty($messages)) {
        foreach ($messages as $m) {
            $output .= helpmenow_format_message($m, $userid);
        }
    }
    return $output;
}

function helpmenow_format_messages_history($messages, $userid) {
    $output = '';
    if (!empty($messages)) {
        foreach ($messages as $m) {
            $output .= helpmenow_format_message_history($m, $userid);
        }
    }
    return $output;
}

function helpmenow_format_message_history($m, $userid) {
    $time = "<i> " . userdate($m->time, '%b %e, %Y %r') . "</i> ";

    $queue_name = '';
    if ($m->queue_name) {
        $queue_name = "(" . $m->queue_name . ") ";
    }
    return helpmenow_format_message($m, $userid, $time, $queue_name);
}

function helpmenow_format_message($m, $userid, $time = '', $queue_name = '') {
    global $DB;

    static $users;
    if (!isset($users)) {
        $users = array();
    }

    $msg = $m->message;
    if (is_null($m->userid)) {
        $msg = "<i>$msg</i>";
    } else {
        if ($m->userid == $userid) {
            $name = get_string('me', 'block_helpmenow');
        } else {
            if (!isset($users[$m->userid])) {
                $users[$m->userid] = $DB->get_record('user', array('id' => $m->userid));
            }
            $name = fullname($users[$m->userid]);
        }
        $msg = "$time$queue_name<b>$name:</b> $msg";
    }
    return "<div>$msg</div>";
}

/**
 * email messages users have missed
 */
function helpmenow_email_messages() {
    global $CFG, $DB;

    echo "\n";

    # find where we need to email messages
    $earlycutoff = time() - HELPMENOW_EMAIL_EARLYCUTOFF;
    $latecutoff = time() - HELPMENOW_EMAIL_LATECUTOFF;

    # TODO: make this SQL more robust. If any of the sub-selects returns extra 
    # rows, the whole thing falls over. :/ Generally, this shouldn't happen. But 
    # if there's a data problem, it could.
    $sql = "
        SELECT s2u.id, s2u.userid, s2u.sessionid, s.last_message, (
            SELECT userid
            FROM {block_helpmenow_session2user} s2u2
            WHERE s2u2.sessionid = s2u.sessionid
            AND s2u2.userid <> s2u.userid
        ) AS fromuserid
        FROM {block_helpmenow_session2user} s2u
        JOIN {block_helpmenow_session} s ON s.id = s2u.sessionid
        WHERE s.queueid IS NULL
        AND s.last_message <> 0
        AND s2u.last_message < s.last_message
        AND $latecutoff > (
            SELECT m.time
            FROM {block_helpmenow_message} m
            WHERE m.id = s.last_message
        )
        AND $earlycutoff > (
            SELECT min(m2.time)
            FROM {block_helpmenow_message} m2
            WHERE m2.sessionid = s2u.sessionid
            AND m2.id > s2u.last_message
        )
        ";
    // Query check to see what users have unread messages
    // Check if user is logged out - via lastaccess and isloggedin or
    // use the cutoffs to send messages from 10-30 minutes ago.
    // TODO: will need to modify to remove the checking if user is logged out 
    // and immediatly emailing.
//    $idlecutoff = helpmenow_cutoff();
/*    $sql = "
        SELECT s2u.id, s2u.userid, s2u.sessionid, s.last_message, (
            SELECT userid
            FROM {block_helpmenow_session2user} s2u2
            WHERE s2u2.sessionid = s2u.sessionid
            AND s2u2.userid <> s2u.userid
        ) AS fromuserid
        FROM {block_helpmenow_session2user} s2u
        JOIN {block_helpmenow_session} s ON s.id = s2u.sessionid
        JOIN {block_helpmenow_user} u ON u.userid = s2u.userid
        WHERE s.queueid IS NULL
        AND ((s.last_message <> 0 AND s2u.last_message < s.last_message)
        OR (s2u.last_read = 0 AND s2u.last_message = 0))
        AND s2u.last_read < s.last_message
        AND (
            NOT(u.lastaccess > $idlecutoff AND (u.isloggedin IS NULL OR u.isloggedin <> 0))
            OR
            ($latecutoff > (
                SELECT m.time
                FROM {block_helpmenow_message} m
                WHERE m.id = s.last_message
            )
            AND $earlycutoff > (
                SELECT min(m2.time)
                FROM {block_helpmenow_message} m2
                WHERE m2.sessionid = s2u.sessionid
                AND m2.id > s2u.last_read
            ))
        )
        ";
 */

    echo $sql . "\n";
    if (!$session2users = $DB->get_records_sql($sql)) {
        echo "we don't have any users to email\n";
        return true;    # we got nothin' to do
    }

    $users = array();
    if (!empty($CFG->helpmenow_title)) {
        $blockname = $CFG->helpmenow_title;
    } else {
        $blockname = get_string('helpmenow', 'block_helpmenow');
    }

    # get messages, format and send the email
    foreach ($session2users as $s2u) {
        $rval = true;
        if (!isset($users[$s2u->userid])) {
            $users[$s2u->userid] = $DB->get_record('user', array('id' => $s2u->userid));
        }
        if (!isset($users[$s2u->fromuserid])) {
            $users[$s2u->fromuserid] = $DB->get_record('user', array('id' => $s2u->fromuserid));
        }


        $formatted = '';
        $formattedhtml = '';
        $content = false;
        if ($messages = helpmenow_get_unread($s2u->sessionid, $s2u->userid)) {
            foreach ($messages as $m) {
                if (!is_null($m->userid)) { $content = true; }
                $msg = (is_null($m->userid) ?
                $m->message :
                fullname($users[$m->userid]) . ": $m->message");

                $formatted .= "$msg\n";
                $formattedhtml .= "$msg <br />";
            }
        }

        if (!$content) {    # missed messages are only system messages, don't email
            if (!defined('HMN_TESTING')) {
                $DB->set_field('block_helpmenow_session2user', 'last_message', $s2u->last_message, array('id' => $s2u->id));   # but do update the last_message so we don't keep catching them
                $DB->set_field('block_helpmenow_session2user', 'last_read', $s2u->last_message, array('id' => $s2u->id));   # but do update the last_read so we don't keep catching them
            }
            continue;
        }


        $history_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/history.php#recent");
        $history_url->param('session', $s2u->sessionid);
        $history_url->param('date', '-1 year');

        $subject = get_string('default_emailsubject', 'block_helpmenow');
        $subject = str_replace('!blockname!', $blockname, $subject);
        $subject = str_replace('!fromusername!', fullname($users[$s2u->fromuserid]), $subject);

        $text = get_string('default_emailtext', 'block_helpmenow');
        $text = str_replace('!username!', fullname($users[$s2u->userid]), $text);
        $text = str_replace('!blockname!', $blockname, $text);
        $text = str_replace('!fromusername!', fullname($users[$s2u->fromuserid]), $text);
        $text = str_replace('!messages!', $formatted, $text);
        $texthtml = get_string('default_emailhtml', 'block_helpmenow');
        $texthtml = str_replace('!username!', fullname($users[$s2u->userid]), $texthtml);
        $texthtml = str_replace('!blockname!', $blockname, $texthtml);
        $texthtml = str_replace('!fromusername!', fullname($users[$s2u->fromuserid]), $texthtml);
        $texthtml = str_replace('!messages!', $formattedhtml, $texthtml);
        $texthtml = str_replace('!link!', $history_url->out(), $texthtml);

        if (!defined('HMN_TESTING')) {
            $status = email_to_user($users[$s2u->userid], $blockname, $subject, $text, $texthtml);
        } else {
            $status = true;
            print "HMN_TESTING: Pretending email was sent...\n";
        }

        if ($status) {
            helpmenow_log($s2u->userid, "missed message email", "subject:$subject text:$text last_message:$s2u->last_message");
            echo "emailed ".fullname($users[$s2u->userid]).": ".$subject."\n".$text."\nlast_message: $s2u->last_message\n";
            if (!defined('HMN_TESTING')) {
                $DB->set_field('block_helpmenow_session2user', 'last_message', $s2u->last_message, array('id' => $s2u->id));
                $DB->set_field('block_helpmenow_session2user', 'last_read', $s2u->last_message, array('id' => $s2u->id));
            }
        } else {
            helpmenow_log($s2u->userid, "missed message email", "failed to send email subject:$subject text:$text last_message:$s2u->last_message");
            echo "failed to email user $s2u->userid\n";
        }
    }

    return true;
}

/**
 * gets s2u recs, with caching
 *
 * @param int $sessionid s2u.sessionid
 * @param int $userid s2u.userid if non given assumes USER.id
 * @return mixed object if success, false if fail
 */
function helpmenow_get_s2u($sessionid, $userid = null) {
    global $DB;

    static $s2u_cache;
    if (!isset($s2u_cache)) {
        $s2u_cache = array();
    }

    if (!is_int($userid)) {
        global $USER;
        $userid = $USER->id;
    }

    if (isset($s2u_cache[$sessionid . $userid])) {
        return $s2u_cache[$sessionid . $userid];
    }
    if ($s2u = $DB->get_record('block_helpmenow_session2user', array('userid' => $userid, 'sessionid' => $sessionid))) {
        $s2u_cache[$sessionid . $userid] = $s2u;
        return $s2u;
    }
    return false;
}

function helpmenow_is_tester() {
    global $USER;
    switch ($USER->id) {
    case 52650:
        return true;
    default:
        return false;
    }
}

/**
 * chat messages
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_message($request, &$response) {
    global $USER;

    if (!helpmenow_message($request->session, $USER->id, htmlspecialchars($request->message))) {
        throw new Exception('Could insert message record');
    }
}

/**
 * system messages
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_sysmessage($request, &$response) {
    global $USER;

    if (!helpmenow_message($request->session, null, $request->message)) {
        throw new Exception('Could insert system message record');
    }
}


/**
 * chat last_read message from client - records that last read message that the
 * user has seen - save to email unread messages to user
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_last_read($request, &$response) {
    global $USER, $DB;

    $s2u = helpmenow_get_s2u($request->session);

    # update session2user
    $s2u->last_read = $request->last_read;

    if (!$DB->update_record('block_helpmenow_session2user', $s2u)) {
        throw new Exception('Could not update session2user record');
    }
}

/**
 * chat refresh
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_refresh($request, &$response) {
    global $USER, $CFG, $DB;

    $session2user = helpmenow_get_s2u($request->session);

    # unless something has gone wrong, we should already have a response ready:
    if ($request->last_message == $session2user->optimistic_last_message) {
        $response_cache = json_decode($session2user->cache);
        $response = (object) array_merge((array) $response, (array) $response_cache);
    } else {
        # unread messages
        $messages = helpmenow_get_unread($request->session, $USER->id);

        # todo: move this to the client
        if ($messages) {
            if (!isset($response->html)) {
                $response->html = '';
            }
            $response->html .= helpmenow_format_messages($messages);

            # determine if we need to beep
            foreach ($messages as $m) {
                if ($m->notify) {
                    $response->title_flash = format_string($m->message);
                    $response->beep = true;
                }
                $response->last_message = $m->id;
            }
        } else {
            $response->last_message = $request->last_message;
        }
    }

    if (!isset($response->last_message)) {
        $response->last_message = null;
    }

    /**
     * if there aren't any new messages check to see if we should add a "sent: _time_" message
     *
     * yes, it sucks, it's one of the many things todo: move to client
     * -dzaharee
     */
    if ($response->last_message == $request->last_message) {
        $sql = "
            SELECT *
            FROM {block_helpmenow_message}
            WHERE id = (
                SELECT max(id)
                FROM {block_helpmenow_message}
                WHERE sessionid = {$request->session}
            )";
        if ($last_message = $DB->get_record_sql($sql)) {
            if (!is_null($last_message->userid) and $last_message->time < time() - 30) {
                $message = get_string('sent', 'block_helpmenow').': '.userdate($last_message->time, '%r');
                helpmenow_message($request->session, null, $message, 0);
            }
        }
    }

    # update session2user
    $session2user->last_message = $request->last_message;
    $session2user->optimistic_last_message = $response->last_message;
    $session2user->last_refresh = time();
    $session2user->cache = json_encode((object) array(
        'html' => '',
        'beep' => false,
        'last_message' => $request->last_message,
    ));
    if (!$DB->update_record('block_helpmenow_session2user', $session2user)) {
        $response->html = '';
        $response->beep = false;
        $response->last_message = $request->last_message;
        throw new Exception('Could not update session2user record');
    }

    # call subplugin on_chat_refresh methods
    foreach (helpmenow_plugin::get_plugins() as $pluginname) {
        $class = "helpmenow_plugin_$pluginname";
        $class::on_chat_refresh($request, $response);
    }
}

/**
 * chat block
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_block($request, &$response) {
    global $USER, $CFG, $OUTPUT, $DB;

    #echo "entered serverfunc_block: " . microtime() . "\n";     # DEBUGGING

    $DB->set_field('block_helpmenow_user', 'lastaccess', time(), array('userid' => $USER->id));   # update our user lastaccess
    helpmenow_log($USER->id, 'block refresh', '');
    $response->last_refresh = get_string('updated', 'block_helpmenow').': '.userdate(time(), '%r');   # datetime for debugging
    $response->pending = 0;
    $response->alert = false;

    #echo "bumped lastaccess/logged: " . microtime() . "\n";     # DEBUGGING

    /**
     * queues
     */
    $response->queues_html = '';
    $connect = new moodle_url("$CFG->wwwroot/blocks/helpmenow/connect.php");
    $queues = helpmenow_queue::get_queues();
    foreach ($queues as $q) {
        $response->queues_html .= '<div>';
        switch ($q->get_privilege()) {
        case HELPMENOW_QUEUE_HELPEE:
        case HELPMENOW_QUEUE_HELPER:
            $sql = "
                SELECT s.*, m.message, m.id AS messageid
                FROM {block_helpmenow_session} s
                JOIN {block_helpmenow_session2user} s2u ON s2u.sessionid = s.id AND s2u.userid = s.createdby
                JOIN {block_helpmenow_message} m ON m.id = (
                    SELECT MAX(id) FROM {block_helpmenow_message} m2 WHERE m2.sessionid = s.id AND m2.userid <> s.createdby
                )
                WHERE s.iscurrent = 1
                AND s.createdby = $USER->id
                AND s.queueid = $q->id
                AND (s2u.last_refresh + 20) < ".time()."
                AND s2u.last_refresh < m.time
                ";
            if ($session = $DB->get_record_sql($sql) or $q->is_open()) {
                $connect->remove_params('sessionid');
                $connect->param('queueid', $q->id);
                $message = $style = '';
                if ($session) {
                    $response->pending++;
                    $style = ' style="background-color:yellow"';
                    $message = '<div style="margin-left: 1em;">' . $session->message . '</div>' . $message;
                    if (helpmenow_notify_once($session->messageid)) {
                        $response->alert = true;
                    }
                }
                $response->queues_html .= "<div$style>" .
                    create_popup($q->name, $connect->out(), "queue{$q->id}") .
                    "$message</div>";
            } else {
                $response->queues_html .= "<div>$q->name</div>";
            }

            if ($q->get_privilege() == HELPMENOW_QUEUE_HELPEE) {
                break;
            }

            $instyle = $outstyle = '';
            if ($q->helpers[$USER->id]->isloggedin) {
                $outstyle = 'style="display: none;"';
            } else {
                $instyle = 'style="display: none;"';
            }
            $login_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/login.php");
            $login_url->param('queueid', $q->id);
            $login_url->param('login', 0);
            $logout = create_popup(get_string('logout', 'block_helpmenow'),
                $login_url->out(), "login");
            $login_url->param('login', 1);
            $login = create_popup(get_string('login', 'block_helpmenow'),
                $login_url->out(), "login");
            $logout_status = get_string('logout_status', 'block_helpmenow');

            $response->queues_html .= <<<EOF
    <div style="text-align: center; font-size:small; margin-top:.5em; margin-bottom:.5em;">
        <div id="helpmenow_logged_in_div_$q->id" $instyle>$logout</div>
        <div id="helpmenow_logged_out_div_$q->id" $outstyle>$logout_status | $login</div>
    </div>
EOF;

            # sessions
            $sql = "
                SELECT u.*, s.id AS sessionid, m.message, m.time, m.id AS messageid
                FROM {block_helpmenow_session} s
                JOIN {user} u ON u.id = s.createdby
                JOIN {block_helpmenow_message} m ON m.id = (
                    SELECT MAX(id) FROM {block_helpmenow_message} m2 WHERE m2.sessionid = s.id AND m2.userid = s.createdby
                )
                WHERE s.queueid = $q->id
                AND s.iscurrent = 1
                ";
            if (!$sessions = $DB->get_records_sql($sql)) {
                break;
            }
            $response->queues_html .= '<div style="margin-left: 1em;">';

            foreach ($sessions as &$s) {
                $s->pending = true;
                $sql = "
                    SELECT *
                    FROM {block_helpmenow_session2user} s2u
                    JOIN {user} u ON u.id = s2u.userid
                    WHERE s2u.sessionid = $s->sessionid
                    AND s2u.userid <> $s->id
                    ";
                $s->helpers = $DB->get_records_sql($sql);
                foreach ($s->helpers as $h) {
                    if ($s->pending) {
                        if (($h->last_refresh + 20) > time()) {
                            $s->pending = false;
                        }
                        if (($h->last_refresh > $s->time)) {
                            $s->pending = false;
                        }
                    }
                    if (!isset($s->helper_names)) {
                        $s->helper_names = fullname($h);
                    } else {
                        $s->helper_names .= ', ' . fullname($h);
                    }
                }
            }

            unset($s);

            # sort by unseen messages, lastname, firstname
            usort($sessions, function($a, $b) {
                if (!($a->pending xor $b->pending)) {
                    return strcmp(strtolower("$a->lastname $a->firstname"), strtolower("$b->lastname $b->firstname"));
                }
                return $a->pending ? -1 : 1;
            });

            foreach ($sessions as $s) {
                $connect->remove_params('queueid');
                $connect->param('sessionid', $s->sessionid);
                $message = $style = '';
                if ($s->pending) {
                    $response->pending++;
                    $style = ' style="background-color:yellow"';
                    $message .= '"'.$s->message.'"<br />';
                    if ($q->helpers[$USER->id]->isloggedin) {
                        if (helpmenow_notify_once($s->messageid)) {
                            $response->alert = true;
                        }
                    }
                }
                if (isset($s->helper_names)) {
                    $message .= '<small>'.$s->helper_names.'</small><br />';
                }
                $message = '<div style="margin-left: 1em;">'.$message.'</div>';
                $response->queues_html .= "<div$style>" . create_popup(fullname($s),
                        $connect->out(), $s->sessionid) . "$message</div>";
            }
            $response->queues_html .= '</div>';
            break;
        }
        $desc_message = '<div style="margin-left: 1em; font-size: smaller;">' . $q->description . '</div>';
        $response->queues_html .= '</div>' . $desc_message . '<hr />';
    }

    #echo "processed queues: " . microtime() . "\n";     # DEBUGGING

    # show the correct login state for instructors
    $isloggedin = $DB->get_field('block_helpmenow_user', 'isloggedin', array('userid' => $USER->id));
    if (!is_null($isloggedin)) {
        $response->isloggedin = $isloggedin ? true : false;
    }

    # build contact list
    $response->users_html = '';
    $sql = "
        SELECT u.*, hu.isloggedin, hu.motd, hu.lastaccess AS hmn_lastaccess
        FROM {block_helpmenow_contact} c
        JOIN {user} u ON u.id = c.contact_userid
        JOIN {block_helpmenow_user} hu ON c.contact_userid = hu.userid
        WHERE c.userid = $USER->id
    ";
    $contacts = $DB->get_records_sql($sql);
    if (!$contacts) {
        return;
    }

    #echo "selected contacts: " . microtime() . "\n";     # DEBUGGING

    $cutoff = helpmenow_cutoff();
    foreach ($contacts as $u) {
        $u->online = false;
        if ($u->hmn_lastaccess > $cutoff and (is_null($u->isloggedin) or $u->isloggedin != 0)) {
            $u->online = true;
            $sql = "
                SELECT s.id, m.message, m.time, m.id AS messageid, s2u2.last_refresh
                FROM mdl_block_helpmenow_session2user s2u
                JOIN mdl_block_helpmenow_session s ON s.iscurrent = 1 AND s.queueid IS NULL AND s.id = s2u.sessionid
                JOIN mdl_block_helpmenow_session2user s2u2 ON s2u2.sessionid = s.id AND s2u2.userid = {$USER->id}
                JOIN mdl_block_helpmenow_message m ON m.userid = s2u.userid AND m.sessionid = s2u.sessionid
                WHERE s2u.userid = {$u->id}
                ORDER BY m.id DESC
            ";
            if (!$message = $DB->get_record_sql($sql)) {
                continue;
            }
            print_object($message);
            if ($message->time < ($message->last_refresh + HELPMENOW_BLOCK_ALERT_DELAY)) {
                continue;
            }
            $u->message = $message->message;
            $u->messageid = $message->messageid;
        }
    }

    #echo "selected contact messages: " . microtime() . "\n";     # DEBUGGING

    # sort by unseen messages, online, lastname, firstname
    usort($contacts, function($a, $b) {
        if ((isset($a->message) xor isset($b->message))) {
            return isset($a->message) ? -1 : 1;
        }
        if (($a->online) xor ($b->online)) {
            return ($a->online) ? -1 : 1;
        }
        return strcmp(strtolower("$a->lastname $a->firstname"), strtolower("$b->lastname $b->firstname"));
    });

    #echo "sorted contacts: " . microtime() . "\n";     # DEBUGGING

    $connect->remove_params('queueid');
    $connect->remove_params('sessionid');
    foreach ($contacts as $u) {
        $connect->param('userid', $u->id);
        $message = '';
        $style = 'margin-left: 1em;';
        if (!isset($u->isloggedin)) {   # if isloggedin is null, the user is always logged in when they are online
            if ($u->online) {
                if ($isloggedin) {
                    $name = create_popup(fullname($u), $connect->out(), $u->id);
                } else {
                    $name = fullname($u);
                }
            } else {
                continue;
            }
        } else {                        # if isloggedin is set, then 0 = loggedout, any other number is the timestamp of when they logged in
            if ($u->online) {
                $name = create_popup(fullname($u), $connect->out(), $u->id);
                $motd = $u->motd;
            } else {
                $name = fullname($u);
                $motd = get_string('offline', 'block_helpmenow');
            }
            $message .= '<div style="font-size: smaller;">' . $motd . '</div>';
        }
        if (isset($u->message)) {
            $response->pending++;
            $style .= 'background-color:yellow;';
            $message .= '<div>' . $u->message . '</div>';
            if (helpmenow_notify_once($u->messageid)) {
                $response->alert = true;
            }
        }
        $message = '<div style="margin-left: 1em;">'.$message.'</div>';
        $response->users_html .= "<div style=\"$style\">".$name.$message."</div>";
    }

    #echo "printed contacts: " . microtime() . "\n";     # DEBUGGING
}

/**
 * chat motd
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_motd($request, &$response) {
    global $USER, $DB;

    if (!$helpmenow_user = $DB->get_record('block_helpmenow_user', array('userid' => $USER->id))) {
        throw new Exception('No helpmenow_user record');
    }
    $helpmenow_user->motd = clean_text($request->motd, FORMAT_HTML);
    if (!$DB->update_record('block_helpmenow_user', $helpmenow_user)) {
        throw new Exception('Could not update user record');
    }
    $response->motd = $request->motd;
}

/**
 * plugin functions
 *
 * @param object $request request from client
 * @param object $response response
 */
function helpmenow_serverfunc_plugin($request, &$response) {
    $plugin = $request->plugin;
    $class = "helpmenow_plugin_$plugin";
    $plugin_function = $request->plugin_function;
    if (!in_array($plugin, helpmenow_plugin::get_plugins())) {
        throw new Exception('Unknown plugin');
    }
    if (!in_array($plugin_function, $class::get_ajax_functions())) {
        throw new Exception('Unknown function');
    }
    $response = (object) array_merge((array) $response, (array) $plugin_function($request));
}

/**
 * log error from the client
 */
function helpmenow_log_error($error) {
    global $USER, $DB;
    $new_record = (object) array(
        'error' => $error->error,
        'details' => $error->details,
        'timecreated' => time(),
        'userid' => $USER->id,
    );
    $DB->insert_record('block_helpmenow_error_log', $new_record);

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
 * Help me now queue class
 */
class helpmenow_queue {
    /**
     * queue.id
     * @var int $id
     */
    public $id;

    /**
     * The name of the queue.
     * @var string $name
     */
    public $name;

    /**
     * Weight for queue display order
     * @var int $weight
     */
    public $weight;

    /**
     * Description of the queue
     * @var string $desription
     */
    public $description;

    /**
     * Queue helpers
     * @var array $helper
     */
    public $helpers;

    /**
     * Queue sessions
     * @var array $sessions
     */
    public $sessions;

    /**
     * Constructor. If we get an id, load from the database. If we get a object
     * from the db and no id, use that.
     * @param int $id id of the queue in the db
     * @param object $record db record
     */
    public function __construct($id=null, $record=null) {
        global $DB;

        if (isset($id)) {
            $record = $DB->get_record('block_helpmenow_queue', array('id' => $id));
        }
        foreach ($record as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     * Returns USER's privilege
     * @return string queue privilege
     */
    public function get_privilege() {
        global $USER, $CFG;

        $this->load_helpers();

        # if it's set now, they're a helper
        if (isset($this->helpers[$USER->id])) {
            return HELPMENOW_QUEUE_HELPER;
        }

        $context = context_system::instance();

        if (has_capability(HELPMENOW_CAP_QUEUE_ASK, $context)) {
            return HELPMENOW_QUEUE_HELPEE;
        }

        return HELPMENOW_NOT_PRIVILEGED;
    }

    /**
     * Returns boolean of helper availability
     * @return boolean
     */
    public function is_open() {
        $this->load_helpers();
        if (!count($this->helpers)) {
            return false;
        }
        foreach ($this->helpers as $h) {
            if ($h->isloggedin) {
                return true;
            }
        }
        return false;
    }

    /**
     * Creates helper for queue using passed userid
     * @param int $userid user.id
     * @return boolean success
     */
    public function add_helper($userid) {
        global $DB;

        $this->load_helpers();

        if (isset($this->helpers[$userid])) {
            return false;   # already a helper
        }

        $helper = (object) array(
            'queueid' => $this->id,
            'userid' => $userid,
            'isloggedin' => 0,
        );

        if (!$helper->id = $DB->insert_record('block_helpmenow_helper', $helper)) {
            return false;
        }
        $this->helpers[$userid] = $helper;

        return true;
    }

    /**
     * Deletes helper
     * @param int $userid user.id
     * @return boolean success
     */
    public function remove_helper($userid) {
        global $DB;

        $this->load_helpers();

        if (!isset($this->helpers[$userid])) {
            return false;
        }

        if (!$DB->delete_records('block_helpmenow_helper', array('id' => $this->helpers[$userid]->id))) {
            return false;
        }
        unset($this->helpers[$userid]);

        return true;
    }

    /**
     * Loads helpers into $this->helpers array
     */
    public function load_helpers() {
        global $DB;

        if (isset($this->helpers)) {
            return true;
        }

        if (!$helpers = $DB->get_records('block_helpmenow_helper', array('queueid' => $this->id))) {
            return false;
        }

        foreach ($helpers as $h) {
            $this->helpers[$h->userid] = $h;
        }

        return true;
    }

    public static function get_queues() {
        global $CFG, $DB;
        if (!$records = $DB->get_records_sql("SELECT * FROM {block_helpmenow_queue} ORDER BY weight ASC")) {
            return false;
        }
        return self::queues_from_recs($records);
    }

    private static function queues_from_recs($records) {
        $queues = array();
        foreach ($records as $r) {
            $queues[$r->id] = new helpmenow_queue(null, $r);
        }
        return $queues;
    }
}

/**
 * Help me now plugin object abstract class. Base class for plugin, user2plugin,
 * and session2plugin. Lets plugin writers define extra fields to be stored in
 * the database (serialized as a data field, not joinable D: ).
 */
abstract class helpmenow_plugin_object {
    const table = false;

    /**
     * Array of extra fields that must be defined by the child if the plugin
     * requires more data be stored in the database. If this anything is
     * defined here, then the child should also define the member variable
     * @var array $extra_fields
     */
    protected $extra_fields = array();

    /**
     * Data simulates database fields in child classes by serializing data.
     * This is only used if extra_fields is used, and does not need to be
     * in the database if it's not being used.
     * @var string $data
     */
    public $data;

    /**
     * The id of the object.
     * @var int $id
     */
    public $id;

    /**
     * Plugin of the object; child should override this, if using a plugin class
     * @var string $plugin
     */
    public $plugin = '';

    /**
     * Constructor. If we get an id, load from the database. If we get a object
     * from the db and no id, use that.
     * @param int $id id of the queue in the db
     * @param object $record db record
     */
    public function __construct($id=null, $record=null) {
        global $DB;

        if (isset($id)) {
            $record = $DB->get_record('block_helpmenow_'.static::table, array('id' => $id));
        }
        if (isset($record)) {
            foreach ($record as $k => $v) {
                $this->$k = $v;
            }
            $this->load_extras();
        }
    }

    /**
     * Updates object in db, using object variables. Requires id.
     * @return boolean success
     */
    public function update() {
        global $USER, $DB;

        if (empty($this->id)) {
            debugging("Can not update " . static::table . ", no id!");
            return false;
        }

        $this->serialize_extras();

        return $DB->update_record("block_helpmenow_" . static::table, $this);
    }

    /**
     * Records the object in the db, and sets the id from the return value.
     * @return int PK ID if successful, false otherwise
     */
    public function insert() {
        global $USER, $DB;

        if (!empty($this->id)) {
            debugging(static::table . " already exists in db.");
            return false;
        }

        $this->serialize_extras();

        if (!$this->id = $DB->insert_record("block_helpmenow_" . static::table, $this)) {
            debugging("Could not insert " . static::table);
            return false;
        }

        return $this->id;
    }

    /**
     * Deletes object in db, using object variables. Requires id.
     * @return boolean success
     */
    public function delete() {
        global $DB;

        if (empty($this->id)) {
            debugging("Can not delete " . static::table . ", no id!");
            return false;
        }

        return $DB->delete_records("block_helpmenow_" . static::table, array('id' => $this->id));
    }

    /**
     * Factory function to get existing object of the correct child class
     * @param int $id *.id
     * @return object
     */
    public final static function get_instance($id=null, $record=null) {
        global $CFG, $DB;

        # we have to get the record instead of passing the id to the
        # constructor as we have no idea what class the record belongs to
        if (isset($id)) {
            if (!$record = $DB->get_record("block_helpmenow_" . static::table, array('id' => $id))) {
                return false;
            }
        }

        $class = static::get_class($record->plugin);

        return new $class(null, $record);
    }

    /**
     * Factory function to create an object of the correct plugin
     * @param string $plugin optional plugin parameter, if none supplied uses
     *      configured default
     * @return object
     */
    public final static function new_instance($plugin) {
        global $CFG;

        $class = static::get_class($plugin);

        $object = new $class;
        $object->plugin = $plugin;

        return $object;
    }

    /**
     * Returns an array of objects from an array of records
     * @param array $records
     * @return array of objects
     */
    public final static function objects_from_records($records) {
        $objects = array();
        foreach ($records as $r) {
            $objects[$r->id] = static::get_instance(null, $r);
        }
        return $objects;
    }

    /**
     * Return the class name and require_once the file that contains it
     * @param string $plugin
     * @return string classname
     */
    public final static function get_class($plugin) {
        global $CFG;

        $classpath = "$CFG->dirroot/blocks/helpmenow/plugins/$plugin/" . static::table . ".php";
        if (!file_exists($classpath)) {
            return "helpmenow_" . static::table;
        }
        $pluginclass = "helpmenow_" . static::table . "_$plugin";
        require_once($classpath);

        return $pluginclass;
    }

    /**
     * Loads the fields from a passed record. Also unserializes simulated fields
     * @param object $record db record
     */
    protected function load_extras() {
        # bail at this point if we don't have extra fields
        if (!count($this->extra_fields)) { return; }

        $extras = unserialize($this->data);
        foreach ($this->extra_fields as $field) {
            $this->$field = $extras[$field];
        }
    }

    /**
     * Serializes simulated fields if necessary
     */
    protected final function serialize_extras() {
        # bail immediately if we don't have any extra fields
        if (!count($this->extra_fields)) { return; }
        $extras = array();
        foreach ($this->extra_fields as $field) {
            $extras[$field] = $this->$field;
        }
        $this->data = serialize($extras);
        return;
    }
}

/**
 * Help me now plugin abstract class. Plugins define things like cron and
 * code that is run on installation.
 */
abstract class helpmenow_plugin extends helpmenow_plugin_object {
    const table = 'plugin';

    /**
     * Cron delay in seconds; 0 represents no cron
     * @var int $cron_interval
     */
    public $cron_interval = 0;

    /**
     * Last cron timestamp
     * @var int $last_cron
     */
    public $last_cron;

    /**
     * "Installs" the plugin
     * @return boolean success
     */
    public static function install() {
        $plugin = new static();
        $plugin->last_cron = 0;
        $plugin->insert();
    }

    /**
     * Cron that will run everytime block cron is run.
     * @return boolean
     */
    public static function cron() {
        return true;
    }

    /**
     * Used to define what is displayed in the the plugin section of the chat window
     * @param bool $privileged
     * @return string
     */
    public static function display($sessionid, $privileged = false) {
        throw new coding_error('You should have implemented a display() function.');
    }

    /**
     * Code to be run when USER logs in
     * @return mixed string url to redirect, true for other success
     */
    public static function on_login() {
        return true;
    }

    /**
     * Code to be run when USER logs out
     * @return mixed string url to redirect, true for other success
     */
    public static function on_logout() {
        return true;
    }

    /**
     * Code to be run when the chat makes a refresh call
     */
    public static function on_chat_refresh($request, &$response) {
        return;
    }

    /**
     * returns array of valid plugin ajax functions
     * @return array
     */
    public static function get_ajax_functions() {
        return array();
    }

    /**
     * returns array of full url paths to needed javascript libraries
     * @return array
     */
    public static function get_js_libs() {
        return array();
    }

    /**
     * returns parameter to pass to init function for plugin's js
     * @return parameter for js init
     */
    public static function get_js_init_param() {
        return false;
    }

    /**
     * Calls install for all plugins
     * @return boolean success
     */
    public final static function install_all() {
        $success = true;
        foreach (self::get_plugins() as $pluginname) {
            $class = "helpmenow_plugin_$pluginname";
            $success = $success and $class::install();
        }
        return $success;
    }

    /**
     * Calls any existing cron functions of plugins
     * @return boolean
     */
    public final static function cron_all() {
        global $DB;

        $success = true;
        foreach (self::get_plugins() as $pluginname) {
            $class = "helpmenow_plugin_$pluginname";
            $record = $DB->get_record('block_helpmenow_plugin', array('plugin' => $pluginname));
            $plugin = new $class(null, $record);
            if (($plugin->cron_interval != 0) and (time() >= $plugin->last_cron + $plugin->cron_interval)) {
                $class = "helpmenow_plugin_$pluginname";
                $success = $success and $class::cron();
                $plugin->last_cron = time();
                $plugin->update();
            }
        }
        return $success;
    }

    /**
     * Handles requiring the necessary files and returns array of plugins
     * @return array of strings that are the plugin classnames
     */
    public final static function get_plugins() {
        global $CFG;
        global $USER;

        $plugins = array();
        foreach (get_list_of_plugins('plugins', '', dirname(__FILE__)) as $pluginname) {
            $enabled = "helpmenow_{$pluginname}_enabled";
            if (!isset($CFG->$enabled) or !$CFG->$enabled) {
                continue;
            }
            require_once("$CFG->dirroot/blocks/helpmenow/plugins/$pluginname/lib.php");
            $plugins[] = "$pluginname";
        }
        return $plugins;
    }

    public static function has_user2plugin_data() {
        return false;
    }

    /**
     * Gets a link to the user's specific plugin page
     * @return link if there is one, false otherwise
     */
    public static function get_user2plugin_link($userid) {
        return false;
    }

    /**
     * individual plugins may return formatted information to put in the main block
     * otherwise return false
     */
    public static function block_display() {
        return false;
    }
}

/**
 * Help me now user2plugin abstract class.
 */
abstract class helpmenow_user2plugin extends helpmenow_plugin_object {
    const table = 'user2plugin';

    /**
     * user.id
     * @var int $userid
     */
    public $userid;

    /**
     * Returns user2plugin object for USER
     * @return object
     */
    public static function get_user2plugin($userid = null) {
        global $DB;

        if (!isset($userid)) {
            global $USER;
            $userid = $USER->id;
        }

        $plugin = preg_replace('/helpmenow_user2plugin_/', '', get_called_class());

        if ($record = $DB->get_record('block_helpmenow_user2plugin', array('userid' => $userid, 'plugin' => $plugin))) {
            return new static(null, $record);
        }
        return false;
    }

    public function get_link() {
        return false;
    }
}

/**
 * Help me now session2plugin abstract class.
 */
abstract class helpmenow_session2plugin extends helpmenow_plugin_object {
    const table = 's2p';

    /**
     * session.id
     * @var int $sessionid
     */
    public $sessionid;
}

/**
 * abstract class for contact list plugins
 */
abstract class helpmenow_contact_list {
    /**
     * require the configured plugin file and return the contact list plugin
     * class name
     *
     * @return string class name
     */
    public final static function get_plugin() {
        global $CFG;
        $plugin = 'native';
        if (isset($CFG->helpmenow_contact_list) and strlen($CFG->helpmenow_contact_list) > 0) {
            $plugin = $CFG->helpmenow_contact_list;
            if(!file_exists("$CFG->dirroot/blocks/helpmenow/contact_list/$plugin/lib.php")) {
                # TODO: Warn config error
                $plugin = 'native';
            }
        }
        $class = "helpmenow_contact_list_$plugin";
        require_once("$CFG->dirroot/blocks/helpmenow/contact_list/$plugin/lib.php");
        return $class;
    }

    /**
     * update block_helpmenow_contacts for the given user
     *
     * @param int $userid user.id
     * @return bool success
     */
    public static function update_contacts($userid) {
        throw new coding_error('You should have implemented a update_contacts() function.');
    }

    /**
     * run update_contacts for everybody
     *
     * this will likely be pretty brutal, so we might not ever actually use it
     *
     * @return bool success
     */
    public static function update_all_contacts() {
        global $DB;

        $rval = true;
        foreach ($DB->get_records_select('user', "auth <> 'nologin'") as $u) {
            $rval = true and static::update_contacts($u->id);
        }
        return $rval;
    }

    /**
     * display to add to the bottom of the block
     *
     * @return mixed html string/false
     */
    public static function block_display() {
        return false;
    }

    /**
     * Check if user is a teacher - teachers have capabilities to see their
     * enrolled students and may sometimes be added to queue helper lists
     */
    public static function is_teacher($userid=null) {
        throw new coding_error('You should have implemented a is_teacher() function.');
    }

    /*
     * Check if user is an admin - admins have additional management capabilites 
     * over other users
     */
    public static function is_admin($userid=null) {
        throw new coding_error('You should have implemented a is_admin() function.');
    }

    /**
     * Check if user is admin or teacher - sometimes optimized over calling
     * bother functions
     */
    public static function is_admin_or_teacher($userid=null) {
        throw new coding_error('You should have implemented a is_admin_or_teacher() function.');
    }

    /**
     * Check if user is a student
     */
    public static function is_student($userid=null) {
        throw new coding_error('You should have implemented a is_student() function.');
    }
}

?>
