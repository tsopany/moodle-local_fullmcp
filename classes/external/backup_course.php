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
 * Back a course up and return the .mbz as base64 (refused above 100 MB).
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'includeusers' => new external_value(PARAM_INT, '1 = include enrolments and user data', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Back a course up and return the .mbz as base64 (refused above 100 MB).
     *
     * @param int $courseid
     * @param int $includeusers
     * @return array
     */
    public static function execute(int $courseid, int $includeusers): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'includeusers'));
        [$course, $context] = helper::course_require($params['courseid'], 'moodle/backup:backupcourse');
        if ($params['includeusers']) {
            require_capability('moodle/backup:userinfo', $context);
        }
        $maxbytes = 100 * 1024 * 1024;
        raise_memory_limit(MEMORY_HUGE);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $plan = $bc->get_plan();
            // Without moodle/backup:userinfo Moodle locks this setting to "no users" itself.
            $users = $plan->get_setting('users');
            if ($users->get_status() === \base_setting::NOT_LOCKED) {
                $users->set_value($params['includeusers'] ? 1 : 0);
            }
            $bc->execute_plan();
            $file = $bc->get_results()['backup_destination'] ?? null;
            if (!$file) {
                throw new \core\exception\moodle_exception('backupfailed', 'local_fullmcp');
            }
            try {
                $size = (int) $file->get_filesize();
                if ($size > $maxbytes) {
                    throw new \core\exception\moodle_exception('backuptoolarge', 'local_fullmcp', '', display_size($maxbytes));
                }
                $result = [
                    'filename' => $file->get_filename(),
                    'size' => $size,
                    'content_base64' => base64_encode($file->get_content()),
                ];
            } finally {
                $file->delete();
            }
        } catch (\Throwable $e) {
            $bc->set_status(\backup::STATUS_FINISHED_ERR);
            throw $e;
        } finally {
            $bc->destroy();
        }
        return $result;
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_FILE, 'Backup file name'),
            'size' => new external_value(PARAM_INT, 'Size in bytes'),
            'content_base64' => new external_value(PARAM_RAW, 'The .mbz, base64 encoded'),
        ]);
    }
}
