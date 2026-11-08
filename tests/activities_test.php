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
use local_fullmcp\external\create_assignment;
use local_fullmcp\external\create_book;
use local_fullmcp\external\create_choice;
use local_fullmcp\external\create_forum;
use local_fullmcp\external\create_label;
use local_fullmcp\external\create_page;
use local_fullmcp\external\create_section;
use local_fullmcp\external\create_url;
use local_fullmcp\external\delete_activity;
use local_fullmcp\external\move_activity;
use local_fullmcp\external\update_activity;
use local_fullmcp\external\update_page;

/**
 * Tests for the activity functions.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNamespace('local_fullmcp')]
final class activities_test extends \advanced_testcase {
    /**
     * Course with admin logged in.
     *
     * @return \stdClass
     */
    private function course(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        return $this->getDataGenerator()->create_course();
    }

    /**
     * Assert a result points at a course module of the given type, in the given section.
     *
     * @param array $result
     * @param string $modname
     * @param int $sectionnum
     * @return \stdClass the course module
     */
    private function assert_cm(array $result, string $modname, int $sectionnum = 0): \stdClass {
        $cm = get_coursemodule_from_id($modname, $result['cmid'], 0, false, MUST_EXIST);
        $this->assertEquals($result['instanceid'], $cm->instance);
        $this->assertEquals($sectionnum, get_fast_modinfo($cm->course)->get_cm($cm->id)->sectionnum);
        return $cm;
    }

    public function test_create_page_label_url(): void {
        global $DB;
        $course = $this->course();
        $page = external_api::clean_returnvalue(create_page::execute_returns(),
            create_page::execute($course->id, 1, 'My page', '<p>Body</p>', '<p>Intro</p>', 1));
        $cm = $this->assert_cm($page, 'page', 1);
        $this->assertEquals('My page', $cm->name);
        $this->assertEquals('<p>Body</p>', $DB->get_field('page', 'content', ['id' => $page['instanceid']]));

        $label = create_label::execute($course->id, 1, '<b>Read first</b>', '', 1);
        $this->assert_cm($label, 'label', 1);

        $url = create_url::execute($course->id, 1, 'Link', 'https://example.org/x', '', 0, 0);
        $cm = $this->assert_cm($url, 'url', 1);
        $this->assertEquals(0, $cm->visible, 'visible=0 creates a hidden activity');
        $this->assertEquals('https://example.org/x', $DB->get_field('url', 'externalurl', ['id' => $url['instanceid']]));
    }

    public function test_bad_url_and_unknown_section_are_rejected(): void {
        $course = $this->course();
        try {
            create_url::execute($course->id, 0, 'Bad', 'javascript:alert(1)', '', 0, 1);
            $this->fail('Expected an invalid URL to be rejected');
        } catch (invalid_parameter_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->expectException(invalid_parameter_exception::class);
        create_page::execute($course->id, 99, 'x', 'x', '', 1);
    }

    public function test_create_book_forum_choice_assignment(): void {
        global $DB;
        $course = $this->course();

        $book = external_api::clean_returnvalue(create_book::execute_returns(),
            create_book::execute($course->id, 0, 'Book', '', 'Chapter 1', '<p>First</p>', 1));
        $this->assert_cm($book, 'book');
        $chapter = $DB->get_record('book_chapters', ['id' => $book['chapterid']], '*', MUST_EXIST);
        $this->assertEquals($book['instanceid'], $chapter->bookid);
        $this->assertEquals('Chapter 1', $chapter->title);

        $forum = create_forum::execute($course->id, 0, 'Forum', '', 'qanda', 1);
        $this->assert_cm($forum, 'forum');
        $this->assertEquals('qanda', $DB->get_field('forum', 'type', ['id' => $forum['instanceid']]));

        $choice = create_choice::execute($course->id, 0, 'Poll', '<p>Colour?</p>', ['Red', 'Blue', ' '], 1, 1);
        $this->assert_cm($choice, 'choice');
        $this->assertEquals(['Red', 'Blue'],
            array_values($DB->get_records_menu('choice_options', ['choiceid' => $choice['instanceid']], 'id', 'id, text')));

        $assign = create_assignment::execute($course->id, 0, 'Essay', '', 1900000000, 50, 1);
        $this->assert_cm($assign, 'assign');
        $record = $DB->get_record('assign', ['id' => $assign['instanceid']], '*', MUST_EXIST);
        $this->assertEquals(1900000000, $record->duedate);
        $this->assertEquals(50, $record->grade);
        $this->assertEquals(1, $DB->get_field('assign_plugin_config', 'value',
            ['assignment' => $record->id, 'plugin' => 'onlinetext', 'subtype' => 'assignsubmission', 'name' => 'enabled']));
    }

    public function test_invalid_forum_choice_and_book_are_rejected(): void {
        $course = $this->course();
        foreach ([
            fn() => create_forum::execute($course->id, 0, 'F', '', 'bogus', 1),
            fn() => create_choice::execute($course->id, 0, 'C', 'q', ['Only one'], 1, 1),
            fn() => create_book::execute($course->id, 0, 'B', '', ' ', 'x', 1),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected invalid_parameter_exception');
            } catch (invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_update_move_delete_activity(): void {
        global $DB;
        $course = $this->course();
        $page = create_page::execute($course->id, 0, 'Old', 'x', '', 1);
        $section = create_section::execute($course->id, 'Target', '', 0);

        $updated = external_api::clean_returnvalue(update_activity::execute_returns(),
            update_activity::execute($page['cmid'], 'New name', 0));
        $this->assertEquals('New name', $updated['name']);
        $this->assertEquals(0, $updated['visible']);
        $unchanged = update_activity::execute($page['cmid'], '', -1);
        $this->assertEquals('New name', $unchanged['name'], 'Empty name keeps the old one');
        $this->assertEquals(0, $unchanged['visible'], 'visible=-1 keeps the old state');

        $moved = move_activity::execute($page['cmid'], $section['sectionnum']);
        $this->assertEquals($section['sectionnum'], $moved['section']);
        $this->assert_cm($page, 'page', $section['sectionnum']);

        $newcontent = update_page::execute($page['cmid'], '<p>Changed</p>', '');
        $this->assertTrue($newcontent['success']);
        $this->assertEquals('<p>Changed</p>', $DB->get_field('page', 'content', ['id' => $page['instanceid']]));

        $deleted = delete_activity::execute($page['cmid']);
        $this->assertTrue($deleted['success']);
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $page['cmid'], 'deletioninprogress' => 0]));
    }

    public function test_update_page_rejects_other_activities(): void {
        $course = $this->course();
        $label = create_label::execute($course->id, 0, 'x', '', 1);
        $this->expectException(invalid_parameter_exception::class);
        update_page::execute($label['cmid'], 'x', '');
    }

    public function test_student_is_denied(): void {
        $course = $this->course();
        $page = create_page::execute($course->id, 0, 'P', 'x', '', 1);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        foreach ([
            fn() => create_page::execute($course->id, 0, 'Nope', 'x', '', 1),
            fn() => update_activity::execute($page['cmid'], 'Nope', -1),
            fn() => delete_activity::execute($page['cmid']),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected required_capability_exception');
            } catch (required_capability_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
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
        $page = create_page::execute($course->id, 0, 'P', '<p>ok</p><script>alert(1)</script>', '', 1);
        $this->assertEquals('<p>ok</p>', $DB->get_field('page', 'content', ['id' => $page['instanceid']]));
    }
}
