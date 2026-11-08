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
 * Delete a course section and every activity in it.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number, 1 or higher'),
        ]);
    }

    /**
     * Delete a course section and every activity in it.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @return array
     */
    public static function execute(int $courseid, int $sectionnum): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'sectionnum'));
        [$course, $context] = helper::course_require($params['courseid'], 'moodle/course:update');
        if ($params['sectionnum'] < 1) {
            throw new \core\exception\invalid_parameter_exception('The general section cannot be deleted');
        }
        $section = helper::section($course, $params['sectionnum']);
        if (!empty($section->sequence)) {
            require_capability('moodle/course:manageactivities', $context);
        }
        return ['success' => formatactions::section($course)->delete($section, true)];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the section was deleted'),
        ]);
    }
}
