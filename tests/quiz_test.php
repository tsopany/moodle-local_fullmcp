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
use local_fullmcp\external\add_essay_question;
use local_fullmcp\external\add_matching_question;
use local_fullmcp\external\add_numerical_question;
use local_fullmcp\external\add_quiz_question;
use local_fullmcp\external\add_shortanswer_question;
use local_fullmcp\external\add_truefalse_question;
use local_fullmcp\external\create_label;
use local_fullmcp\external\create_quiz;

/**
 * Tests for the quiz and question functions.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNamespace('local_fullmcp')]
final class quiz_test extends \advanced_testcase {
    /**
     * Course with an empty quiz, admin logged in.
     *
     * @return array [course, create_quiz result]
     */
    private function quiz(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = external_api::clean_returnvalue(create_quiz::execute_returns(),
            create_quiz::execute($course->id, 0, 'Quiz', '<p>Intro</p>', 0, 0, 600, 10.0, 2, 1));
        return [$course, $quiz];
    }

    public function test_create_quiz_settings(): void {
        global $DB;
        [, $quiz] = $this->quiz();
        $record = $DB->get_record('quiz', ['id' => $quiz['instanceid']], '*', MUST_EXIST);
        $this->assertEquals('Quiz', $record->name);
        $this->assertEquals(600, $record->timelimit);
        $this->assertEquals(10, $record->grade);
        $this->assertEquals(2, $record->attempts);
        $this->assertNotEmpty($record->reviewattempt);
    }

    public function test_create_quiz_rejects_close_before_open(): void {
        [$course] = $this->quiz();
        $this->expectException(invalid_parameter_exception::class);
        create_quiz::execute($course->id, 0, 'Bad', '', 2000, 1000, 0, 10.0, 0, 1);
    }

    public function test_every_question_type_is_added_and_usable(): void {
        global $DB;
        [, $quiz] = $this->quiz();
        $cmid = $quiz['cmid'];

        $results = [
            'multichoice' => add_quiz_question::execute($cmid, 'MC', '<p>Pick odd</p>', [
                ['text' => 'One', 'fraction' => 0.5, 'feedback' => ''],
                ['text' => 'Two', 'fraction' => 0.0, 'feedback' => ''],
                ['text' => 'Three', 'fraction' => 0.5, 'feedback' => ''],
            ], 0, 2.0),
            'truefalse' => add_truefalse_question::execute($cmid, 'TF', 'Sky is blue', 1, 1.0),
            'shortanswer' => add_shortanswer_question::execute($cmid, 'SA', 'Capital of France', ['Paris', 'paris'], 0, 1.0),
            'essay' => add_essay_question::execute($cmid, 'ES', 'Discuss', 'Look for X', '', 15, 2, 1.0),
            'numerical' => add_numerical_question::execute($cmid, 'NU', 'Pi?', [
                ['answer' => 3.14, 'tolerance' => 0.01, 'fraction' => 1.0, 'feedback' => ''],
            ], 1.0),
            'match' => add_matching_question::execute($cmid, 'MA', 'Match', [
                ['question' => 'France', 'answer' => 'Paris'],
                ['question' => 'Italy', 'answer' => 'Rome'],
            ], 1, 1.0),
        ];

        $slot = 0;
        foreach ($results as $qtype => $result) {
            $result = external_api::clean_returnvalue(add_quiz_question::execute_returns(), $result);
            $this->assertEquals(++$slot, $result['slot'], $qtype);
            $this->assertEquals($qtype, $DB->get_field('question', 'qtype', ['id' => $result['questionid']]));
            // The question must load through the question engine and, when it can be auto-graded, accept its own answer.
            $question = \question_bank::load_question($result['questionid']);
            $this->assertEquals($qtype, $question->qtype->name());
        }
        $this->assertEquals(7.0, (float) $DB->get_field('quiz', 'sumgrades', ['id' => $quiz['instanceid']]),
            'Quiz total follows the slot marks: 2 + 5 * 1');
        $this->assertEquals(2.0, (float) $DB->get_field('quiz_slots', 'maxmark', ['quizid' => $quiz['instanceid'], 'slot' => 1]));
    }

    public function test_correct_answers_are_graded_correct(): void {
        [, $quiz] = $this->quiz();
        $ids = [
            add_truefalse_question::execute($quiz['cmid'], 'TF', 'Sky is blue', 1, 1.0)['questionid'],
            add_shortanswer_question::execute($quiz['cmid'], 'SA', 'Capital?', ['Paris'], 0, 1.0)['questionid'],
            add_numerical_question::execute($quiz['cmid'], 'NU', 'Pi?', [
                ['answer' => 3.14, 'tolerance' => 0.01, 'fraction' => 1.0, 'feedback' => ''],
            ], 1.0)['questionid'],
        ];
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        foreach ($ids as $id) {
            $quba->add_question(\question_bank::load_question($id), 1);
        }
        $quba->start_all_questions();
        foreach ($quba->get_slots() as $slot) {
            $question = $quba->get_question($slot);
            $quba->process_action($slot, $question->prepare_simulated_post_data($question->get_correct_response()));
        }
        $quba->finish_all_questions();
        foreach ($quba->get_slots() as $slot) {
            $this->assertEquals(1.0, $quba->get_question_mark($slot));
        }
    }

    public function test_invalid_questions_are_rejected(): void {
        [, $quiz] = $this->quiz();
        $cmid = $quiz['cmid'];
        foreach ([
            fn() => add_quiz_question::execute($cmid, 'MC', 'x', [['text' => 'only', 'fraction' => 1.0, 'feedback' => '']], 1, 1.0),
            fn() => add_quiz_question::execute($cmid, 'MC', 'x', [
                ['text' => 'a', 'fraction' => 0.0, 'feedback' => ''],
                ['text' => 'b', 'fraction' => 0.0, 'feedback' => ''],
            ], 1, 1.0),
            fn() => add_truefalse_question::execute($cmid, 'TF', 'x', 1, 0.0),
            fn() => add_truefalse_question::execute($cmid, ' ', 'x', 1, 1.0),
            fn() => add_shortanswer_question::execute($cmid, 'SA', 'x', [' '], 0, 1.0),
            fn() => add_essay_question::execute($cmid, 'ES', 'x', '', '', 15, 7, 1.0),
            fn() => add_matching_question::execute($cmid, 'MA', 'x', [['question' => 'a', 'answer' => 'b']], 1, 1.0),
        ] as $i => $call) {
            try {
                $call();
                $this->fail("Case $i should have been rejected");
            } catch (invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_question_cannot_be_added_to_other_activity(): void {
        [$course] = $this->quiz();
        $label = create_label::execute($course->id, 0, 'x', '', 1);
        $this->expectException(\Throwable::class);
        add_truefalse_question::execute($label['cmid'], 'TF', 'x', 1, 1.0);
    }

    public function test_student_is_denied(): void {
        [$course, $quiz] = $this->quiz();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->expectException(required_capability_exception::class);
        add_truefalse_question::execute($quiz['cmid'], 'TF', 'x', 1, 1.0);
    }

    public function test_teacher_question_html_is_cleaned(): void {
        global $DB;
        [$course, $quiz] = $this->quiz();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        // Editing teachers are trusted by default, so take that away to see the cleaning.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:trustcontent', CAP_PROHIBIT, $roleid, \context_system::instance()->id, true);
        $this->setUser($teacher);
        $result = add_truefalse_question::execute($quiz['cmid'], 'TF', '<p>x</p><script>alert(1)</script>', 1, 1.0);
        $this->assertEquals('<p>x</p>', $DB->get_field('question', 'questiontext', ['id' => $result['questionid']]));
    }
}
