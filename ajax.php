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
 * ajax server
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

# capture output
ob_start();

#echo "entry: " . microtime() . "\n";     # DEBUGGING

require_once((dirname(dirname(dirname(__FILE__)))) . '/config.php');

#echo "moodle started: " . microtime() . "\n";     # DEBUGGING

require_once(dirname(__FILE__) . '/lib.php');

if (!isloggedin()) {
    header('HTTP/1.1 401 Unauthorized');
    die;
}

#echo "isloggedin: " . microtime() . "\n";     # DEBUGGING

# requests are sent as JSON object from the client
$requests = json_decode(file_get_contents('php://input'));

#echo "json decoded: " . microtime() . "\n";     # DEBUGGING

# special case for logging errors
if (isset($requests->error)) {
    helpmenow_log_error($requests);
    header('HTTP/1.1 200 OK');
    die;
}

# iterate through the requests and create responses
$responses = array();
foreach ($requests->requests as $request) {
    try {
        # all responses need id of the request and client instance
        $response = (object) array(
            'id' => $request->id,
        );
        if (isset($request->instanceId)) {
            $response->instanceId = $request->instanceId;
        }

        # verify session where applicable
        switch ($request->function) {
        case 'message':
        case 'sysmessage':
        case 'refresh':
        case 'last_read':
            if (!$session2user = helpmenow_get_s2u($request->session)) {
                throw new Exception('Could not get session2user record');
            }
            #echo "verrified session2user: " . microtime() . "\n";     # DEBUGGING
            break;
        }

        # generate response
        switch ($request->function) {
        case 'message':
        case 'sysmessage':
        case 'refresh':
        case 'block':
        case 'motd':
        case 'plugin':
        case 'last_read':
            $function = 'helpmenow_serverfunc_' . $request->function;
            $function($request, $response);
            break;
        default:
            throw new Exception('Unknown function');
        }
    } catch (Exception $e) {
        $response->error = $e->getMessage();
        // echo json_encode($response);
        // die;
    }
    $debugging = ob_get_clean();
    if (debugging()) {
        #echo "done: " . microtime() . "\n";     # DEBUGGING
        $response->debugging = $debugging;
    }
    $responses[] = $response;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
header('Pragma: no-cache');
echo json_encode($responses);

