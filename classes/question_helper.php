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

use core_question\local\bank\question_version_status;

/**
 * Creates question bank questions and adds them to a quiz.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Question creation helper.
 */
class question_helper {
    /**
     * Create a question in the quiz's own question bank category and add it to the quiz.
     *
     * @param string $qtype Question type name.
     * @param int $quizcmid Quiz course module id.
     * @param string $name Question name.
     * @param string $questiontext Question text HTML.
     * @param float $defaultmark Mark of the question, also its slot mark.
     * @param array $fields Type-specific form fields, merged over the common ones.
     * @return array [questionid, slot]
     */
    public static function add(
        string $qtype,
        int $quizcmid,
        string $name,
        string $questiontext,
        float $defaultmark,
        array $fields
    ): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        [, $cm, $context] = helper::cm_require($quizcmid, 'mod/quiz:manage');
        if ($cm->modname !== 'quiz') {
            throw new \core\exception\invalid_parameter_exception('Activity is not a quiz');
        }
        require_capability('moodle/question:add', $context);
        if ($defaultmark <= 0) {
            throw new \core\exception\invalid_parameter_exception('defaultmark must be greater than 0');
        }
        if (trim($name) === '') {
            throw new \core\exception\invalid_parameter_exception('name must not be empty');
        }

        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $category = question_get_default_category($context->id, true);

        $form = (object) ($fields + [
            'name' => $name,
            'questiontext' => ['text' => helper::html($context, $questiontext), 'format' => FORMAT_HTML, 'itemid' => 0],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'defaultmark' => $defaultmark,
            'penalty' => 0.3333333,
            'status' => question_version_status::QUESTION_STATUS_READY,
            'category' => $category->id . ',' . $context->id,
        ]);
        $question = new \stdClass();
        $question->qtype = $qtype;
        $question->createdby = $USER->id;
        $question->idnumber = null;
        $question->status = question_version_status::QUESTION_STATUS_READY;

        $transaction = $DB->start_delegated_transaction();
        $question = \question_bank::get_qtype($qtype)->save_question($question, $form);
        quiz_add_quiz_question($question->id, $quiz, 0, $defaultmark);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $slot = (int) $DB->get_field_sql('SELECT MAX(slot) FROM {quiz_slots} WHERE quizid = ?', [$quiz->id]);
        $transaction->allow_commit();

        return ['questionid' => (int) $question->id, 'slot' => $slot];
    }

    /**
     * Rich text array for the question form, cleaned for the user.
     *
     * @param \context $context
     * @param string $html
     * @return array
     */
    public static function text(\context $context, string $html): array {
        return ['text' => helper::html($context, $html), 'format' => FORMAT_HTML, 'itemid' => 0];
    }

    /**
     * Validate and normalise a fraction.
     *
     * @param float $fraction
     * @return string
     */
    public static function fraction(float $fraction): string {
        if ($fraction < -1 || $fraction > 1) {
            throw new \core\exception\invalid_parameter_exception('fraction must be between -1 and 1');
        }
        return (string) $fraction;
    }
}
