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

use core\exception\invalid_parameter_exception;
use core\exception\required_capability_exception;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversNothing;
use local_fullmcp\external\create_section;
use local_fullmcp\external\delete_section;
use local_fullmcp\external\update_section;

/**
 * Tests for the section functions.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class sections_test extends \advanced_testcase {
    public function test_create_update_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $existing = count(get_fast_modinfo($course)->get_section_info_all());

        $created = external_api::clean_returnvalue(
            create_section::execute_returns(),
            create_section::execute($course->id, 'Week A', '<p>Summary</p>', 0)
        );
        $this->assertEquals($existing, $created['sectionnum']);
        $record = $DB->get_record('course_sections', ['id' => $created['sectionid']], '*', MUST_EXIST);
        $this->assertEquals('Week A', $record->name);
        $this->assertEquals('<p>Summary</p>', $record->summary);

        external_api::clean_returnvalue(
            update_section::execute_returns(),
            update_section::execute($course->id, $created['sectionnum'], 'Renamed', '', 0)
        );
        $record = $DB->get_record('course_sections', ['id' => $created['sectionid']], '*', MUST_EXIST);
        $this->assertEquals('Renamed', $record->name);
        $this->assertEquals('<p>Summary</p>', $record->summary, 'Empty summary keeps the old one');
        $this->assertEquals(0, $record->visible);

        $deleted = external_api::clean_returnvalue(
            delete_section::execute_returns(),
            delete_section::execute($course->id, $created['sectionnum'])
        );
        $this->assertTrue($deleted['success']);
        $this->assertFalse($DB->record_exists('course_sections', ['id' => $created['sectionid']]));
    }

    public function test_general_section_cannot_be_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(invalid_parameter_exception::class);
        delete_section::execute($course->id, 0);
    }

    public function test_unknown_section_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(invalid_parameter_exception::class);
        update_section::execute($course->id, 99, 'x', '', -1);
    }

    public function test_student_is_denied(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(required_capability_exception::class);
        create_section::execute($course->id, 'Nope', '', 0);
    }

    public function test_teacher_html_is_cleaned(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        // Editing teachers are trusted by default, so take that away to see the cleaning.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:trustcontent', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        $this->setUser($teacher);
        $created = create_section::execute($course->id, 'S', '<p>ok</p><script>alert(1)</script>', 0);
        $this->assertEquals('<p>ok</p>', $DB->get_field('course_sections', 'summary', ['id' => $created['sectionid']]));
    }
}
