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
 * Create a URL resource.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_url extends external_api {
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
            'externalurl' => new external_value(PARAM_RAW, 'Target URL'),
            'intro' => new external_value(PARAM_RAW, 'Description HTML', VALUE_DEFAULT, ''),
            'display' => new external_value(PARAM_INT, 'Display mode, 0 automatic', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create a URL resource.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $externalurl
     * @param string $intro
     * @param int $display
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $externalurl, string $intro, int $display, int $visible): array {
        global $CFG;
        require_once($CFG->libdir . '/resourcelib.php');
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'externalurl', 'intro', 'display', 'visible'));
        $url = clean_param($params['externalurl'], PARAM_URL);
        if ($url === '') {
            throw new \core\exception\invalid_parameter_exception('Invalid externalurl');
        }
        return helper::add_module('url', $params['courseid'], $params['section'], $params['visible'] ? 1 : 0, [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'externalurl' => $url,
            'display' => $params['display'],
            'printintro' => 0,
            'popupwidth' => 620,
            'popupheight' => 450,
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
            'instanceid' => new external_value(PARAM_INT, 'URL id'),
        ]);
    }
}
