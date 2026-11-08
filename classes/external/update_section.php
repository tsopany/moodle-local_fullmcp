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
 * Update a course section; empty name/summary and visible -1 leave the value unchanged.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
            'name' => new external_value(PARAM_TEXT, 'New name, empty keeps', VALUE_DEFAULT, ''),
            'summary' => new external_value(PARAM_RAW, 'New summary HTML, empty keeps', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide, -1 keep', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Update a course section; empty name/summary and visible -1 leave the value unchanged.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $summary
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $sectionnum, string $name, string $summary, int $visible): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'sectionnum', 'name', 'summary', 'visible'));
        [$course, $context] = helper::course_require($params['courseid'], 'moodle/course:update');
        $section = helper::section($course, $params['sectionnum']);
        $fields = [];
        if ($params['name'] !== '') {
            $fields['name'] = $params['name'];
        }
        if ($params['summary'] !== '') {
            $fields['summary'] = helper::html($context, $params['summary']);
            $fields['summaryformat'] = FORMAT_HTML;
        }
        if (in_array($params['visible'], [0, 1])) {
            require_capability('moodle/course:sectionvisibility', $context);
            $fields['visible'] = $params['visible'];
        }
        if ($fields) {
            formatactions::section($course)->update($section, $fields);
        }
        return ['sectionid' => (int) $section->id, 'sectionnum' => (int) $section->sectionnum];
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
