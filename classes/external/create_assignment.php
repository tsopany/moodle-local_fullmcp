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


/**
 * Create an Assignment activity with online text and file submissions.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_assignment extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'intro' => new external_value(PARAM_RAW, 'Description HTML', VALUE_DEFAULT, ''),
            'duedate' => new external_value(PARAM_INT, 'Due date, unix, 0 none', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_INT, 'Maximum grade', VALUE_DEFAULT, 100),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create an Assignment activity with online text and file submissions.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param int $duedate
     * @param int $grade
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro, int $duedate, int $grade, int $visible): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'intro', 'duedate', 'grade', 'visible'));
        if ($params['duedate'] < 0 || $params['grade'] < 0) {
            throw new \core\exception\invalid_parameter_exception('duedate and grade must not be negative');
        }
        return helper::add_module('assign', $params['courseid'], $params['section'], $params['visible'] ? 1 : 0, [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'alwaysshowdescription' => 1,
            'submissiondrafts' => 0,
            'requiresubmissionstatement' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'sendstudentnotifications' => 1,
            'duedate' => $params['duedate'],
            'allowsubmissionsfromdate' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'grade' => $params['grade'],
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'hidegrader' => 0,
            'attemptreopenmethod' => 'none',
            'maxattempts' => -1,
            'markingworkflow' => 0,
            'markingallocation' => 0,
            'markinganonymous' => 0,
            'timelimit' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 1,
            'assignsubmission_file_maxsizebytes' => 0,
            'assignfeedback_comments_enabled' => 1,
        ]);
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'instanceid' => new external_value(PARAM_INT, 'Instance id'),
        ]);
    }
}
