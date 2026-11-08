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

use core_external\external_multiple_structure;

/**
 * Create a Choice (poll) activity.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_choice extends external_api {
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
            'question' => new external_value(PARAM_RAW, 'The question, shown as the description HTML'),
            'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option text'), 'Options, at least two'),
            'allowupdate' => new external_value(PARAM_INT, '1 = students may change their answer', VALUE_DEFAULT, 1),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create a Choice (poll) activity.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $question
     * @param array $options
     * @param int $allowupdate
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $question, array $options, int $allowupdate, int $visible): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'question', 'options', 'allowupdate', 'visible'));
        $options = array_values(array_filter(array_map('trim', $params['options']), fn($o) => $o !== ''));
        if (count($options) < 2) {
            throw new \core\exception\invalid_parameter_exception('At least two options are required');
        }
        return helper::add_module('choice', $params['courseid'], $params['section'], $params['visible'] ? 1 : 0, [
            'name' => $params['name'],
            'intro' => $params['question'],
            'option' => $options,
            'limitanswers' => 0,
            'allowupdate' => $params['allowupdate'] ? 1 : 0,
            'allowmultiple' => 0,
            'showresults' => 1,
            'publish' => 0,
            'showunanswered' => 0,
            'display' => 0,
            'timeopen' => 0,
            'timeclose' => 0,
            'includeinactive' => 1,
            'showpreview' => 0,
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
