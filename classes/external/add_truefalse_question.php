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
 * Add a true/false question to a quiz.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_truefalse_question extends external_api {
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
            'correctanswer' => new external_value(PARAM_INT, '1 true, 0 false'),
            'defaultmark' => new external_value(PARAM_FLOAT, 'Mark', VALUE_DEFAULT, 1.0),
        ]);
    }

    /**
     * Add a true/false question to a quiz.
     *
     * @param int $quizcmid
     * @param string $name
     * @param string $questiontext
     * @param int $correctanswer
     * @param float $defaultmark
     * @return array
     */
    public static function execute(
        int $quizcmid,
        string $name,
        string $questiontext,
        int $correctanswer,
        float $defaultmark
    ): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('quizcmid', 'name', 'questiontext', 'correctanswer', 'defaultmark')
        );
        $fields = [
            'correctanswer' => $params['correctanswer'] ? 1 : 0,
            'feedbacktrue' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'feedbackfalse' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'penalty' => 1,
        ];
        return question_helper::add(
            'truefalse',
            $params['quizcmid'],
            $params['name'],
            $params['questiontext'],
            $params['defaultmark'],
            $fields
        );
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
