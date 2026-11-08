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
 * Web service definitions for local_fullmcp.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_fullmcp_create_section' => [
        'classname' => 'local_fullmcp\\external\\create_section',
        'description' => 'Create a course section.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_fullmcp_update_section' => [
        'classname' => 'local_fullmcp\\external\\update_section',
        'description' => 'Update a course section.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_fullmcp_delete_section' => [
        'classname' => 'local_fullmcp\\external\\delete_section',
        'description' => 'Delete a course section and its activities.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:update',
    ],
    'local_fullmcp_create_page' => [
        'classname' => 'local_fullmcp\\external\\create_page',
        'description' => 'Create a Page activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities, mod/page:addinstance',
    ],
    'local_fullmcp_create_label' => [
        'classname' => 'local_fullmcp\\external\\create_label',
        'description' => 'Create a Label.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities, mod/label:addinstance',
    ],
    'local_fullmcp_create_url' => [
        'classname' => 'local_fullmcp\\external\\create_url',
        'description' => 'Create a URL resource.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities, mod/url:addinstance',
    ],
    'local_fullmcp_update_activity' => [
        'classname' => 'local_fullmcp\\external\\update_activity',
        'description' => 'Rename and/or show/hide an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_fullmcp_move_activity' => [
        'classname' => 'local_fullmcp\\external\\move_activity',
        'description' => 'Move an activity to another section.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_fullmcp_delete_activity' => [
        'classname' => 'local_fullmcp\\external\\delete_activity',
        'description' => 'Delete an activity.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
    'local_fullmcp_update_page' => [
        'classname' => 'local_fullmcp\\external\\update_page',
        'description' => 'Replace the content of a Page.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
];

$services = [
    'Full MCP Service' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 1,
        'enabled' => 1,
        'shortname' => 'local_fullmcp',
    ],
];
