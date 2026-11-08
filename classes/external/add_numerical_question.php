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
 * Add a numerical question to a quiz.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_numerical_question extends external_api {
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
            'answers' => new external_multiple_structure(new external_single_structure([
                'answer' => new external_value(PARAM_FLOAT, 'Correct value'),
                'tolerance' => new external_value(PARAM_FLOAT, 'Accepted +/- error', VALUE_DEFAULT, 0),
                'fraction' => new external_value(PARAM_FLOAT, 'Grade fraction, 1.0 = full credit', VALUE_DEFAULT, 1.0),
                'feedback' => new external_value(PARAM_RAW, 'Feedback HTML', VALUE_DEFAULT, ''),
            ]), 'Accepted answers'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Add a numerical question to a quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param array $answers
     * @param float $defaultmark
     * @return array
     */
    public static function execute(int $quizcmid, string $name, string $questiontext, array $answers, float $defaultmark): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('quizcmid', 'name', 'questiontext', 'answers', 'defaultmark'));
        $context = \context_module::instance($params['quizcmid']);
        if (!$params['answers']) {
            throw new \core\exception\invalid_parameter_exception('At least one answer is required');
        }
        $fields = [
            'answer' => [], 'tolerance' => [], 'fraction' => [], 'feedback' => [],
            'unitrole' => 3, 'unitpenalty' => 0.1, 'unitgradingtypes' => 0, 'unitsleft' => 0, 'nounits' => 0,
        ];
        foreach (array_values($params['answers']) as $i => $a) {
            $fields['answer'][$i] = (string) $a['answer'];
            $fields['tolerance'][$i] = abs($a['tolerance']);
            $fields['fraction'][$i] = question_helper::fraction($a['fraction']);
            $fields['feedback'][$i] = question_helper::text($context, $a['feedback']);
        }
        return question_helper::add('numerical', $params['quizcmid'], $params['name'], $params['questiontext'],
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
