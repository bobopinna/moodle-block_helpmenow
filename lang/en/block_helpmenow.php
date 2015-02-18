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
 * This is the english translation file for get_string() for all translatable 
 * text in the help me now block.
 *
 * @package     block_helpmenow
 * @copyright   2010 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Help Me Now Block';
$string['blockname'] = 'Help Me Now';
$string['helpmenow'] = 'Help Me Now';
$string['helpmenow:addinstance'] = 'Add helpmenow block'; 
$string['helpmenow:myaddinstance'] = 'Add helpmenow block to My';

# plugin strings
$plugins = get_list_of_plugins('plugins', '', dirname(dirname(dirname(__FILE__))));
foreach ($plugins as $pluginname) {
    $path = dirname(dirname(dirname(__FILE__))) . "/plugins/$pluginname/lang/en.php";
    if (file_exists($path)) {
        require($path);
    }
}
# contact list plugin strings
$plugins = get_list_of_plugins('contact_list', '', dirname(dirname(dirname(__FILE__))));
foreach ($plugins as $pluginname) {
    $path = dirname(dirname(dirname(__FILE__))) . "/contact_list/$pluginname/lang/en.php";
    if (file_exists($path)) {
        require($path);
    }
}

$string['noscript'] = '<div style=\'background-color:yellow;\'>JavaScript is currently disabled. To use this block, please enable JavaScript</div>';
$string['new_request'] = '(Click to get help)';
$string['pending'] = '(Click to view your request)';
$string['queue_na_short'] = '(Currently Offline)';
$string['helper_link'] = 'Open My Queues';
$string['admin_link'] = 'Manage Queues';
#$string['in_office'] = '';
#$string['out_office'] = 'Out of Office |';
$string['enter_office'] = 'Enter Office';
$string['leave_office'] = 'Leave Office';
$string['offline'] = '(Offline)';
$string['updated'] = 'Updated';

# new_request.php
#$string['new_request_heading'] = 'New Request';
$string['description'] = 'Description (140 characters or less)';
#$string['submitrequest'] = 'Submit request';

# connect.php
#$string['connect'] = 'Connecting';
#$string['please_wait'] = 'Helpers may currently be helping other users. Please wait while we attempt to connect you.';
#$string['missing_request'] = 'Error: request does not exist.';
#$string['too_slow'] = 'Another helper has already answered this request.';
#$string['your_request'] = 'Your pending request:';

# admin.php / edit.php / delete.php
$string['global_admin'] = 'Global Queues';
#$string['course_admin'] = 'Queues: ';
$string['admin'] = 'Manage Queues';
$string['new_queue'] = 'Add Queue';
$string['weight'] = 'Weight';
$string['plugin'] = 'Plugin';
$string['helpers'] = 'Helpers';
$string['queue_edit'] = 'Edit Queue';
$string['global_link'] = 'Manage Global Queues';
$string['confirm_delete'] = 'Are you sure you wish to delete queue ';

# assign.php
$string['assign_title'] = 'Assign Helpers';
$string['assign_heading'] = 'Assigning helpers for: ';
$string['assigned_status'] = 'Is user a helper?';
$string['assigned_link'] = 'Update';
$string['assign'] = 'Assign as helper';
$string['unassign'] = 'Remove as helper';
$string['back'] = 'Back to queue management';

# helper interface
$string['inactive_message'] = 'Due to inactivity you will soon be logged out of the following queues:';
$string['inactive_link'] = 'Remain Logged In';
$string['logged_in_helpers'] = 'Logged In Helpers: ';
$string['none'] = 'None';
$string['meetings'] = 'Current Meetings';
$string['helper'] = 'helper';
$string['requests'] = 'Requests';
$string['no_requests'] = 'There are currently no requests in queue.';
$string['no_meetings'] = 'There are currently no meetings.';
$string['login'] = 'Log In';
$string['logout'] = 'Log Out';
$string['loggedout'] = 'You are currently logged out.';
$string['loggedin'] = '';

$string['auto_queue_desc'] = 'Course Queue';

# launch.php
$string['connecting'] = 'You are being connected to the meeting. Once it opens you may close this window.';
$string['nopopup'] = 'If the meeting window does not appear, please enable popups or ';
$string['click_here'] = 'click here.';
$string['participants'] = 'Participants:';

# settings
$string['block_title'] = 'Block title';
$string['block_title_desc'] = 'Sets the block title';
$string['settings_contact_list'] = 'Contact list plugin';
$string['settings_contact_list_desc'] = 'Which contact list plugin to use to manage contacts';

/*
$string['settings_heading'] = 'Help Me Now Settings';
$string['settings_heading_desc'] = '';
$string['settings_plugin'] = 'Default plugin';
$string['settings_plugin_desc'] = 'Default plugin queues should use';
$string['settings_block_message'] = 'Block message';
$string['settings_block_message_desc'] = 'Message displayed in the block';
$string['settings_meeting_timeout_desc'] = 'Time meeting is \'active\' when using plugins that aren\'t able to track this';
$string['settings_request_timeout'] = 'Request timeout';
$string['settings_request_timeout_desc'] = 'Time before request is deemed abandoned';
$string['settings_refresh_rate'] = 'Request refresh rate';
$string['settings_refresh_rate_desc'] = 'Time in seconds between refreshes when waiting for a meeting';
$string['settings_helper_refresh_rate'] = 'Helper refresh rate';
$string['settings_helper_refresh_rate_desc'] = 'Time in seconds between refreshes on helper interface';
$string['settings_autocreate_queue'] = 'Autocreate instructor queues';
$string['settings_autocreate_queue_desc'] = 'Automatically creates an instructor queue the first time an instructor loads the block';
$string['settings_autoadd_helpers'] = 'Autoadd course queue helpers';
$string['settings_autoadd_helpers_desc'] = 'When autocreating course queues, also add helpers to the queue who have the queue answer capability in the course';
$string['settings_helper_refresh_timeout'] = 'Helper refresh timeout';
$string['settings_helper_refresh_timeout_desc'] = 'Time delay in minutes before logging out a helper who has not refreshed the helper interace page';
$string['settings_helper_activity_timeout'] = 'Helper activity timeout';
$string['settings_helper_activity_timeout_desc'] = 'Time delay in minutes before logging out a helper who has not answered pending requests';
$string['settings_helper_activity_warning'] = 'Helper activity warning';
$string['settings_helper_activity_warning_desc'] = 'Time delay in minutes before warning a helper they will be logged out';
 */

# caps
$string['helpmenow:course_queue_answer'] = 'Answer requests in course queues';
$string['helpmenow:global_queue_answer'] = 'Answer requests in global queues';
$string['helpmenow:manage_queues'] = 'Manage queue creation and helper assigning';
$string['helpmenow:queue_ask'] = 'Ask for meetings in queues';

$string['permission_error'] = 'You do not have permission to view this page. You may have been linked to it in error.';

$string['logout_status'] = 'You\'re Logged Out';
$string['login'] = 'Log In';
$string['who'] = 'Who\'s Here';
$string['popout'] = 'Popout';

$string['textarea_message'] = 'Type your message here and press the &quot;enter&quot; or &quot;return&quot; key.';

$string['motd'] = 'MOTD';
$string['loggedin'] = 'Logged In?';
$string['loggedinsince'] = 'Login date';
$string['lastaccess'] = 'Last updated';

$string['na'] = 'N/A';
$string['not_found'] = 'Not Found';
$string['wander'] = 'Wander In';

$string['enter_office'] = 'Enter Office';
$string['leave_office'] = 'Leave Office';
$string['my_office'] = 'My Office';
$string['out_of_office'] = 'Out of Office';
$string['online_students'] = 'Online Students:';
$string['instructors'] = 'Instructors:';
$string['me'] = 'Me';
$string['sent'] = 'Sent';
$string['chat_history'] = 'Chat history';
$string['chathistories'] = 'Chat Histories';
$string['viewconversation'] = 'View conversations with:';
$string['conversations'] = '\'s conversations with:';
$string['orderby'] = 'Order by';
$string['name'] = 'Name';
$string['mostrecentconversation'] = 'Most Recent Conversation';
$string['history_not_available'] = 'No chat history found';

$string['may_close'] = 'You may now close this window';
$string['multiple_plugins'] = 'Multiple plugins require further action. Please follow the links below to finish logging in.';

$string['default_emailtext'] = <<<EOF
Hello !username!,

You may have missed these messages in !blockname! from !fromusername! while you were offline:

!messages!
EOF;
$string['default_emailhtml'] = <<<EOF
Hello !username!, <br /><br />
You may have missed these messages in !blockname! from !fromusername! while you were offline:<br /><br />
!messages!<br /><br />
<a href='!link!'>Link to chat history page</a>
EOF;
$string['default_emailsubject'] = 'Missed !blockname! messages from !fromusername!';
$string['max_length'] = 'Max length';

