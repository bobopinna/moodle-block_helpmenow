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
 * This script generates the chat window
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once((dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

require_login(0, false);

$PAGE->set_context(context_system::instance());

# verify session
$sessionid = required_param('session', PARAM_INT);
if (!helpmenow_verify_session($sessionid)) {
    helpmenow_fatal_error(get_string('permission_error', 'block_helpmenow'));
}

# figure out if the user should see plugins
$session = $DB->get_record('block_helpmenow_session', array('id' => $sessionid));
$privileged = helpmenow_check_privileged($session);

# title
if (!$privileged and isset($session->queueid)) {
    $title = $DB->get_field('block_helpmenow_queue', 'name', array('id' => $session->queueid));
} else {
    $sql = "
        SELECT u.*
        FROM {block_helpmenow_session2user} s2u
        JOIN {user} u ON u.id = s2u.userid
        WHERE s2u.sessionid = $sessionid
        AND s2u.userid <> $USER->id
    ";
    $other_user_recs = $DB->get_records_sql($sql);
    $other_users = array();
    if (!empty($other_user_recs)) {
        foreach ($other_user_recs as $r) {
            $other_users[] = fullname($r);
        }
    }
    $title = implode(', ', $other_users);
    $history_name = get_string('chat_history', 'block_helpmenow') . ': ' . $title;
}

# get plugin links and load necessary javascript
$plugins_display = $plugins_js = $plugins_init = array();
foreach (helpmenow_plugin::get_plugins() as $pluginname) {
    # display
    $class = "helpmenow_plugin_$pluginname";
    $plugin_text = $class::display($sessionid, $privileged);
    if (!strlen($plugin_text)) {
        continue;
    }
    $plugins_display[] = "<div id='helpmenow_$pluginname'>$plugin_text</div>";

    # js
    $plugin_libs = $class::get_js_libs();
    if (count($plugin_libs)) {
        foreach ($plugin_libs as $lib) {
            $plugins_js[] = "<script src=\"$lib\" type=\"text/javascript\"></script>";
        }
    }
    $js_init_param = $class::get_js_init_param();
    if ($js_init_param) {
        $plugins_init[] = "<script type=\"text/javascript\">helpmenow_plugin_$pluginname.init($js_init_param);</script>";
    }
}
if (count($plugins_display)) {
    $plugins_display = '<div id="pluginDiv">'.implode(' | ', $plugins_display).'</div>';
} else {
    $plugins_display = '';
}
if (count($plugins_js)) {
    $plugins_js = implode("\n", $plugins_js);
} else {
    $plugins_js = '';
}
if (count($plugins_init)) {
    $plugins_init = implode("\n", $plugins_init);
} else {
    $plugins_init = '';
}

if (!isset($session->queueid)) {
    $history_url = new moodle_url("$CFG->wwwroot/blocks/helpmenow/history.php#recent");
    $history_url->param('session', $sessionid);
    $history_url->param('date', '-1 year');
    $action = new popup_action('click', $history_url->out(), $sessionid,
        array('height' => 400, 'width' => 500));
    $history_link = $OUTPUT->action_link($history_url->out(), $history_name, $action);
    $history_link = '<div>'.$history_link.'</div>';
} else {
    $history_link = '';
}

$textarea_message = get_string('textarea_message', 'block_helpmenow');  # default text in chat input box
$jplayer = helpmenow_jplayer();     # jquery plugin for bell sound
$version = HELPMENOW_CLIENT_VERSION;

if ($history = helpmenow_get_history($sessionid)) {
    $messages = helpmenow_format_messages($history);
    foreach ($history as $m) {
        $last_message = $m->id;
    }
} else {
    $messages = '';
    $last_message = 0;
}
$messages = $history_link . $messages;

echo <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
    <head>
        <title>$title</title>
        <link rel="stylesheet" type="text/css" href="$CFG->wwwroot/blocks/helpmenow/style/chat.css" />
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js" type="text/javascript"></script>
        $jplayer
        <script src="$CFG->wwwroot/blocks/helpmenow/javascript/lib/jquery.titlealert.js" type="text/javascript"></script>
        <script src="$CFG->wwwroot/blocks/helpmenow/javascript/lib/json2.js" type="text/javascript"></script>
        <script src="$CFG->wwwroot/blocks/helpmenow/javascript/client/$version/lib.js" type="text/javascript"></script>
        <script src="$CFG->wwwroot/blocks/helpmenow/javascript/client/$version/chat.js" type="text/javascript"></script>
        <script type="text/javascript">
            helpmenow.setServerURL("$CFG->wwwroot/blocks/helpmenow/ajax.php");
            helpmenow.chat.setLastMessage($last_message);
            helpmenow.chat.setSessionId($sessionid);
            var plugin_refresh = new Array();
        </script>
        $plugins_js
        $plugins_init
    </head>
    <body>
        <div id="helpmenow_chime"></div>
        $plugins_display
        <div id="chatDiv">$messages</div>
        <div id="inputDiv">
            <textarea id="inputTextarea" cols="30" rows="3">$textarea_message</textarea>
        </div>
    </body>
</html>
EOF;

