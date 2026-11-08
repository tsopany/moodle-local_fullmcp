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
use PHPUnit\Framework\Attributes\CoversNamespace;
use local_fullmcp\external\add_truefalse_question;
use local_fullmcp\external\backup_course;
use local_fullmcp\external\create_page;
use local_fullmcp\external\create_quiz;
use local_fullmcp\external\restore_course;

/**
 * Tests for backup_course and restore_course.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNamespace('local_fullmcp')]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Course holding a page and a quiz with one question; admin logged in.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['shortname' => 'src']);
        create_page::execute($course->id, 0, 'A page', '<p>Body</p>', '', 1);
        $quiz = create_quiz::execute($course->id, 0, 'A quiz', '', 0, 0, 0, 10.0, 0, 1);
        add_truefalse_question::execute($quiz['cmid'], 'TF', 'Sky is blue', 1, 1.0);
        return $course;
    }

    public function test_round_trip(): void {
        global $DB;
        $course = $this->course();

        $backup = external_api::clean_returnvalue(backup_course::execute_returns(), backup_course::execute($course->id, 0));
        $this->assertStringEndsWith('.mbz', $backup['filename']);
        $binary = base64_decode($backup['content_base64'], true);
        $this->assertNotFalse($binary);
        $this->assertEquals($backup['size'], strlen($binary));

        $restored = external_api::clean_returnvalue(restore_course::execute_returns(),
            restore_course::execute($backup['content_base64'], 1, 'Restored', 'copy'));
        $this->assertNotEquals($course->id, $restored['courseid']);
        $this->assertEquals('Restored', $restored['fullname']);
        $this->assertEquals('copy', $restored['shortname']);

        $names = fn(int $courseid) => array_values(array_map(fn($cm) => $cm->name,
            get_fast_modinfo($courseid)->get_cms()));
        $this->assertEqualsCanonicalizing($names($course->id), $names($restored['courseid']));
        $quiz = $DB->get_record('quiz', ['course' => $restored['courseid']], '*', MUST_EXIST);
        $this->assertEquals(1, $DB->count_records('quiz_slots', ['quizid' => $quiz->id]));
        $this->assertEquals(1.0, (float) $quiz->sumgrades);
        $this->assertEquals(0, $DB->count_records_select('backup_controllers', 'status < ?', [\backup::STATUS_FINISHED_ERR]),
            'No unfinished controllers are left behind');
    }

    public function test_restore_with_existing_shortname_creates_nothing(): void {
        global $DB;
        $course = $this->course();
        $backup = backup_course::execute($course->id, 0);
        $before = $DB->count_records('course');
        try {
            restore_course::execute($backup['content_base64'], 1, 'Dup', 'src');
            $this->fail('Expected a duplicate shortname to be rejected');
        } catch (invalid_parameter_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertEquals($before, $DB->count_records('course'));
    }

    public function test_restore_rejects_garbage(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $DB->count_records('course');
        foreach (['!!! not base64 !!!', base64_encode('not a backup'), ''] as $content) {
            try {
                restore_course::execute($content, 1, 'X', 'garbage');
                $this->fail('Expected invalid_parameter_exception');
            } catch (invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        // Core reports the unreadable archive, with a temp path that differs per run.
        $debugging = $this->getDebuggingMessages();
        $this->assertCount(1, $debugging);
        $this->assertStringContainsString('Not a zip archive.', $debugging[0]->message);
        $this->resetDebugging();
        $this->assertEquals($before, $DB->count_records('course'));
    }

    public function test_student_is_denied(): void {
        $course = $this->course();
        $backup = backup_course::execute($course->id, 0);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        try {
            backup_course::execute($course->id, 0);
            $this->fail('Expected the student backup to be denied');
        } catch (required_capability_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->expectException(required_capability_exception::class);
        restore_course::execute($backup['content_base64'], 1, 'X', 'denied');
    }

    public function test_user_data_needs_its_own_capability(): void {
        $course = $this->course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertNotEmpty(backup_course::execute($course->id, 0)['filename'], 'Content-only backup is allowed');
        $this->expectException(required_capability_exception::class);
        backup_course::execute($course->id, 1);
    }
}
