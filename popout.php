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
 * This script displays the block interface as it's own page
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
$PAGE->set_url('/blocks/helpmenow/login.php');
$PAGE->set_pagelayout('standard');

// Add style.css.
$PAGE->requires->css('/blocks/helpmenow/style.css');
$PAGE->requires->jquery();

if (!empty($CFG->helpmenow_title)) {
    $title = $CFG->helpmenow_title;
} else {
    $title = get_string('helpmenow', 'block_helpmenow'); 
}
$PAGE->set_title($title);
echo $OUTPUT->header();

$output = <<<EOF
<div class="helpmenow_popup">
<div id="chatDiv" class="helpmenow_chat">
EOF;

$output .= helpmenow_block_interface();
$output .= <<<EOF
<div id="helpmenow_last_refresh_div" class="helpmenow_last_refresh_div"></div>
</div></div></div></body></html>
EOF;

echo $output;

