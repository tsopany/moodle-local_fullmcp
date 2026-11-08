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

use core_external\external_multiple_structure;
use local_fullmcp\question_helper;

/**
 * Add a matching question to a quiz.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_matching_question extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizcmid' => new external_value(PARAM_INT, 'Quiz course module id'),
            'name' => new external_value(PARAM_TEXT, 'Question name'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text HTML'),
            'pairs' => new external_multiple_structure(new external_single_structure([
                'question' => new external_value(PARAM_RAW, 'Left-hand item HTML'),
                'answer' => new external_value(PARAM_RAW, 'Matching right-hand item'),
            ]), 'Pairs, at least two'),
            'shuffleanswers' => new external_value(PARAM_INT, '1 = shuffle the right-hand items', VALUE_DEFAULT, 1),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Add a matching question to a quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param array $pairs
     * @param int $shuffleanswers
     * @param float $defaultmark
     * @return array
     */
    public static function execute(int $quizcmid, string $name, string $questiontext, array $pairs, int $shuffleanswers, float $defaultmark): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('quizcmid', 'name', 'questiontext', 'pairs', 'shuffleanswers', 'defaultmark'));
        $context = \context_module::instance($params['quizcmid']);
        $pairs = array_values(array_filter($params['pairs'], fn($p) => trim($p['question']) !== '' && trim($p['answer']) !== ''));
        if (count($pairs) < 2) {
            throw new \core\exception\invalid_parameter_exception('At least two complete pairs are required');
        }
        $fields = [
            'shuffleanswers' => $params['shuffleanswers'] ? 1 : 0,
            'noanswers' => count($pairs),
            'correctfeedback' => question_helper::text($context, get_string('correctfeedbackdefault', 'question')),
            'partiallycorrectfeedback' => question_helper::text($context, get_string('partiallycorrectfeedbackdefault', 'question')),
            'incorrectfeedback' => question_helper::text($context, get_string('incorrectfeedbackdefault', 'question')),
            'shownumcorrect' => 1,
            'subquestions' => [], 'subanswers' => [],
        ];
        foreach ($pairs as $i => $p) {
            $fields['subquestions'][$i] = question_helper::text($context, $p['question']);
            $fields['subanswers'][$i] = clean_param($p['answer'], PARAM_TEXT);
        }
        return question_helper::add('match', $params['quizcmid'], $params['name'], $params['questiontext'],
            $params['defaultmark'], $fields);
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'slot' => new external_value(PARAM_INT, 'Slot in the quiz'),
        ]);
    }
}
