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

namespace local_fullmcp;

use core_external\external_api;


/**
 * Shared helpers for the Full MCP web service functions.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Shared helpers.
 */
class helper {
    /**
     * Load a course and validate it, checking a capability in its context.
     *
     * @param int $courseid
     * @param string $capability
     * @return array [stdClass course, \context_course]
     */
    public static function course_require(int $courseid, string $capability): array {
        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        external_api::validate_context($context);
        require_capability($capability, $context);
        return [$course, $context];
    }

    /**
     * Load a course module, validate its context and check a capability there.
     *
     * @param int $cmid
     * @param string $capability
     * @return array [\stdClass course, \stdClass cm, \context_module]
     */
    public static function cm_require(int $cmid, string $capability = 'moodle/course:manageactivities'): array {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $context = \context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability($capability, $context);
        return [$course, $cm, $context];
    }

    /**
     * Get a non-delegated section by number.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @return \section_info
     */
    public static function section(\stdClass $course, int $sectionnum): \section_info {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        if (!$section || $section->is_delegated()) {
            throw new \core\exception\invalid_parameter_exception("Section $sectionnum not found in course {$course->id}");
        }
        return $section;
    }

    /**
     * Clean HTML unless the user may submit trusted content.
     *
     * @param \context $context
     * @param string $html
     * @return string
     */
    public static function html(\context $context, string $html): string {
        return has_capability('moodle/site:trustcontent', $context) ? $html : clean_text($html, FORMAT_HTML);
    }

    /**
     * Create an activity with add_moduleinfo() and return its ids.
     *
     * @param string $modname Module name, e.g. 'page'.
     * @param int $courseid
     * @param int $sectionnum
     * @param int $visible
     * @param array $fields Module-specific fields (must include name and intro).
     * @return array [cmid, instanceid]
     */
    public static function add_module(string $modname, int $courseid, int $sectionnum, int $visible, array $fields): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        [$course, $context] = self::course_require($courseid, 'moodle/course:manageactivities');
        require_capability("mod/$modname:addinstance", $context);
        if (!$visible) {
            require_capability('moodle/course:activityvisibility', $context);
        }
        $section = self::section($course, $sectionnum);
        $module = $DB->get_record('modules', ['name' => $modname, 'visible' => 1], 'id', MUST_EXIST);

        $info = (object) ($fields + [
            'modulename' => $modname,
            'module' => $module->id,
            'course' => $course->id,
            'section' => $section->sectionnum,
            'visible' => $visible,
            'visibleoncoursepage' => 1,
            'introformat' => FORMAT_HTML,
            'showdescription' => 0,
            'cmidnumber' => '',
        ]);
        foreach (['intro', 'content'] as $key) {
            if (isset($info->$key)) {
                $info->$key = self::html($context, $info->$key);
            }
        }
        $info = add_moduleinfo($info, $course);
        return ['cmid' => (int) $info->coursemodule, 'instanceid' => (int) $info->instance];
    }
}
