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
 * Create a course section.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Section name'),
            'summary' => new external_value(PARAM_RAW, 'Summary HTML', VALUE_DEFAULT, ''),
            'position' => new external_value(PARAM_INT, 'Position, 0 appends', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create a course section.
     *
     * @param int $courseid
     * @param string $name
     * @param string $summary
     * @param int $position
     * @return array
     */
    public static function execute(int $courseid, string $name, string $summary, int $position): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'name', 'summary', 'position'));
        [$course, $context] = helper::course_require($params['courseid'], 'moodle/course:update');
        if ($params['position'] < 0) {
            throw new \core\exception\invalid_parameter_exception('position must be >= 0');
        }
        $actions = formatactions::section($course);
        $record = $actions->create($params['position']);
        $section = get_fast_modinfo($course->id)->get_section_info_by_id($record->id, MUST_EXIST);
        $actions->update($section, [
            'name' => $params['name'],
            'summary' => helper::html($context, $params['summary']),
            'summaryformat' => FORMAT_HTML,
        ]);
        return ['sectionid' => (int) $record->id, 'sectionnum' => (int) $record->section];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
        ]);
    }
}
