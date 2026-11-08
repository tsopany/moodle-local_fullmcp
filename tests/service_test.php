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

use PHPUnit\Framework\Attributes\CoversNamespace;

/**
 * Tests for the service definition.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNamespace('local_fullmcp')]
final class service_test extends \advanced_testcase {
    /**
     * The function names of the Full MCP Service, as declared in db/services.php.
     *
     * @return string[]
     */
    private function service_functions(): array {
        global $CFG;
        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/fullmcp/db/services.php');
        $this->assertArrayHasKey('Full MCP Service', $services);
        return $services['Full MCP Service']['functions'];
    }

    public function test_every_function_exists(): void {
        $this->resetAfterTest();
        $names = $this->service_functions();
        $this->assertNotEmpty($names);
        foreach ($names as $name) {
            $this->assertNotFalse(\core_external\external_api::external_function_info($name, IGNORE_MISSING),
                "$name is not a registered web service function");
        }
    }

    public function test_service_exposes_no_personal_data_or_dangerous_functions(): void {
        // Version 1 deliberately keeps these out: they read or change other people's data, or delete things.
        $forbidden = [
            'core_user_create_users', 'core_user_update_users', 'core_user_delete_users',
            'core_role_assign_roles', 'core_role_unassign_roles',
            'core_course_delete_courses', 'core_course_delete_categories',
            'core_enrol_get_enrolled_users', 'core_enrol_get_users_courses',
            'enrol_manual_enrol_users', 'enrol_manual_unenrol_users',
            'core_cohort_create_cohorts', 'core_cohort_add_cohort_members',
            'core_group_create_groups', 'core_group_add_group_members',
            'core_message_send_instant_messages',
            'core_completion_get_activities_completion_status', 'core_completion_get_course_completion_status',
            'core_completion_override_activity_completion_status',
            'core_completion_update_activity_completion_status_manually',
            'core_competency_list_user_plans',
            'gradereport_overview_get_course_grades', 'gradereport_user_get_grade_items',
            'message_popup_get_popup_notifications', 'message_popup_get_unread_popup_notification_count',
            'mod_assign_get_submission_status', 'mod_assign_save_grade',
            'mod_forum_add_discussion', 'mod_forum_add_discussion_post', 'mod_forum_get_forum_discussions',
            'mod_quiz_get_attempt_review', 'mod_quiz_get_user_attempts', 'mod_quiz_get_user_best_grade',
        ];
        $this->assertSame([], array_values(array_intersect($forbidden, $this->service_functions())));
    }

    public function test_every_plugin_function_is_in_the_service(): void {
        global $CFG;
        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/fullmcp/db/services.php');
        $this->assertEqualsCanonicalizing(array_keys($functions),
            array_filter($services['Full MCP Service']['functions'], fn($n) => str_starts_with($n, 'local_fullmcp_')));
    }
}
