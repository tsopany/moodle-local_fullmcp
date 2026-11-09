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
 * Restore a .mbz (base64) into a new course.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content_base64' => new external_value(PARAM_RAW, 'The .mbz, base64 encoded (max 100 MB decoded)'),
            'categoryid' => new external_value(PARAM_INT, 'Category for the new course', VALUE_DEFAULT, 1),
            'fullname' => new external_value(PARAM_TEXT, 'Full name, empty keeps the one in the backup', VALUE_DEFAULT, ''),
            'shortname' => new external_value(PARAM_TEXT, 'Short name, empty keeps the one in the backup', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Restore a .mbz (base64) into a new course.
     *
     * @param string $content_base64
     * @param int $categoryid
     * @param string $fullname
     * @param string $shortname
     * @return array
     */
    public static function execute(
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        string $content_base64,
        int $categoryid,
        string $fullname,
        string $shortname
    ): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filestorage/zip_packer.php');
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('content_base64', 'categoryid', 'fullname', 'shortname')
        );
        $category = \core_course_category::get($params['categoryid']);
        $context = $category->get_context();
        self::validate_context($context);
        require_capability('moodle/restore:restorecourse', $context);
        require_capability('moodle/course:create', $context);
        $maxbytes = 100 * 1024 * 1024;
        if (strlen($params['content_base64']) > intdiv($maxbytes * 4, 3) + 4) {
            throw new \core\exception\moodle_exception('backuptoolarge', 'local_fullmcp', '', display_size($maxbytes));
        }
        $binary = base64_decode($params['content_base64'], true);
        if ($binary === false || $binary === '') {
            throw new \core\exception\invalid_parameter_exception('content_base64 is not valid base64');
        }
        raise_memory_limit(MEMORY_HUGE);

        $backupid = 'fullmcp_' . \core\uuid::generate();
        $dir = $CFG->tempdir . '/backup/' . $backupid;
        $courseid = 0;
        $rc = null;
        try {
            $archive = make_request_directory() . '/upload.mbz';
            file_put_contents($archive, $binary);
            unset($binary);
            check_dir_exists($dir);
            if (!get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($archive, $dir)) {
                throw new \core\exception\invalid_parameter_exception('Not a valid Moodle backup file');
            }
            $info = \backup_general_helper::get_backup_information($backupid);
            if (
                $info->type !== \backup::TYPE_1COURSE
                    || \backup_general_helper::detect_backup_format($backupid) !== \backup::FORMAT_MOODLE
            ) {
                throw new \core\exception\invalid_parameter_exception('Only Moodle course backups can be restored');
            }

            $fullname = $params['fullname'] !== '' ? $params['fullname'] : $info->original_course_fullname;
            $shortname = $params['shortname'] !== '' ? $params['shortname'] : $info->original_course_shortname;
            if ($DB->record_exists('course', ['shortname' => $shortname])) {
                throw new \core\exception\invalid_parameter_exception("A course with shortname '$shortname' already exists");
            }

            $courseid = \restore_dbops::create_new_course($fullname, $shortname, $category->id);
            $rc = new \restore_controller(
                $backupid,
                $courseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL,
                $USER->id,
                \backup::TARGET_NEW_COURSE
            );
            $plan = $rc->get_plan();
            foreach (['course_fullname' => $fullname, 'course_shortname' => $shortname] as $setting => $value) {
                $setting = $plan->get_setting($setting);
                if ($setting->get_status() === \base_setting::NOT_LOCKED) {
                    $setting->set_value($value);
                }
            }
            // Users in the backup are restored only if the user holds moodle/restore:userinfo; Moodle locks the setting otherwise.
            // execute_precheck() is false on warnings too; only errors stop the restore.
            $rc->execute_precheck();
            $errors = $rc->get_precheck_results()['errors'] ?? [];
            if ($errors) {
                throw new \core\exception\moodle_exception('restorefailed', 'local_fullmcp', '', implode('; ', $errors));
            }
            $rc->execute_plan();
            // The name settings are locked without moodle/course:changefullname; creating a course allows choosing names.
            $restored = get_course($courseid);
            if ($restored->fullname !== $fullname || $restored->shortname !== $shortname) {
                update_course((object) ['id' => $courseid, 'fullname' => $fullname, 'shortname' => $shortname]);
            }
        } catch (\Throwable $e) {
            // An unfinished controller makes delete_course() refuse, so close it as failed first.
            $rc?->set_status(\backup::STATUS_FINISHED_ERR);
            $rc?->destroy();
            $rc = null;
            if ($courseid) {
                delete_course($courseid, false, false);
            }
            throw $e;
        } finally {
            $rc?->destroy();
            fulldelete($dir);
        }

        $course = get_course($courseid);
        return ['courseid' => (int) $course->id, 'fullname' => $course->fullname, 'shortname' => $course->shortname];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'New course id'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name'),
        ]);
    }
}
