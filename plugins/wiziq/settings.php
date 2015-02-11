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
 * This script handles wiziq Help Me Now settings.
 *
 * @package     block_helpmenow
 * @copyright   2012 VLACS
 * @author      David Zaharee <dzaharee@vlacs.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

$settings->add(new admin_setting_configtext('helpmenow_wiziq_access_key',
                                            get_string('wiziq_settings_access_key', 'block_helpmenow'),
                                            get_string('wiziq_settings_access_key_desc', 'block_helpmenow'),
                                            '',
                                            PARAM_TEXT,
                                            50));

$settings->add(new admin_setting_configtext('helpmenow_wiziq_secret_key',
                                            get_string('wiziq_settings_secret_key', 'block_helpmenow'),
                                            get_string('wiziq_settings_secret_key_desc', 'block_helpmenow'),
                                            '',
                                            PARAM_TEXT,
                                            50));

