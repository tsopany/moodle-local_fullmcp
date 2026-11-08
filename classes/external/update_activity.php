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

namespace local_fullmcp\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_fullmcp\helper;

use core_courseformat\formatactions;

/**
 * Rename and/or show/hide an activity; empty name and visible -1 leave the value unchanged.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_activity extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'New name, empty keeps', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide, -1 keep', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Rename and/or show/hide an activity; empty name and visible -1 leave the value unchanged.
     *
     * @param int $cmid
     * @param string $name
     * @param int $visible
     * @return array
     */
    public static function execute(int $cmid, string $name, int $visible): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'name', 'visible'));
        [$course, $cm, $context] = helper::cm_require($params['cmid']);
        $actions = formatactions::cm($course);
        if ($params['name'] !== '') {
            $actions->rename($cm->id, $params['name']);
        }
        if (in_array($params['visible'], [0, 1])) {
            require_capability('moodle/course:activityvisibility', $context);
            $actions->set_visibility($cm->id, $params['visible'], $params['visible']);
        }
        $fresh = get_coursemodule_from_id('', $cm->id, 0, false, MUST_EXIST);
        return ['cmid' => (int) $cm->id, 'name' => $fresh->name, 'visible' => (int) $fresh->visible];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'visible' => new external_value(PARAM_INT, 'Visible'),
        ]);
    }
}
