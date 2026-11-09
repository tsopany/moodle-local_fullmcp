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
 * Replace the content of a Page activity.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'content' => new external_value(PARAM_RAW, 'New page HTML'),
            'intro' => new external_value(PARAM_RAW, 'New description HTML, empty keeps', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Replace the content of a Page activity.
     *
     * @param int $cmid
     * @param string $content
     * @param string $intro
     * @return array
     */
    public static function execute(int $cmid, string $content, string $intro): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'content', 'intro'));
        [$course, $cm, $context] = helper::cm_require($params['cmid']);
        if ($cm->modname !== 'page') {
            throw new \core\exception\invalid_parameter_exception('Activity is not a page');
        }
        $cmrecord = get_coursemodule_from_id('page', $cm->id, 0, false, MUST_EXIST);
        [, , , $data] = get_moduleinfo_data($cmrecord, $course);
        // The form-less page_update_instance() expects the editor array and the unpacked display options.
        $data->page = ['text' => helper::html($context, $params['content']), 'format' => FORMAT_HTML, 'itemid' => 0];
        // Core stores page display options serialized; no objects are allowed.
        // phpcs:ignore moodle.PHP.ForbiddenFunctions.Found
        $options = (array) unserialize($data->displayoptions ?? '') + ['printintro' => 0, 'printlastmodified' => 1];
        $data->printintro = $options['printintro'];
        $data->printlastmodified = $options['printlastmodified'];
        $data->popupwidth = $options['popupwidth'] ?? 620;
        $data->popupheight = $options['popupheight'] ?? 450;
        if ($params['intro'] !== '') {
            $data->intro = helper::html($context, $params['intro']);
            $data->introformat = FORMAT_HTML;
        }
        update_moduleinfo($cmrecord, $data, $course);
        return ['cmid' => (int) $cm->id, 'success' => true];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'success' => new external_value(PARAM_BOOL, 'Whether it was updated'),
        ]);
    }
}
