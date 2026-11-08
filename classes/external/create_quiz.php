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


/**
 * Create an empty Quiz activity.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_quiz extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'section' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'intro' => new external_value(PARAM_RAW, 'Description HTML', VALUE_DEFAULT, ''),
            'timeopen' => new external_value(PARAM_INT, 'Open time, unix, 0 none', VALUE_DEFAULT, 0),
            'timeclose' => new external_value(PARAM_INT, 'Close time, unix, 0 none', VALUE_DEFAULT, 0),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds, 0 none', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade', VALUE_DEFAULT, 100.0),
            'attempts' => new external_value(PARAM_INT, 'Allowed attempts, 0 unlimited', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create an empty Quiz activity.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param int $timeopen
     * @param int $timeclose
     * @param int $timelimit
     * @param float $grade
     * @param int $attempts
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro, int $timeopen, int $timeclose, int $timelimit, float $grade, int $attempts, int $visible): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'courseid', 'section', 'name', 'intro', 'timeopen', 'timeclose', 'timelimit', 'grade', 'attempts', 'visible'));
        if ($params['timeclose'] && $params['timeopen'] > $params['timeclose']) {
            throw new \core\exception\invalid_parameter_exception('timeclose must be after timeopen');
        }
        if ($params['timelimit'] < 0 || $params['attempts'] < 0 || $params['grade'] < 0) {
            throw new \core\exception\invalid_parameter_exception('timelimit, attempts and grade must not be negative');
        }
        // Review options: show everything in every phase except overall feedback while the attempt is in progress.
        $review = [];
        foreach (['during', 'immediately', 'open', 'closed'] as $when) {
            foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer',
                    'overallfeedback'] as $what) {
                $review[$what . $when] = (int) !($when === 'during' && $what === 'overallfeedback');
            }
        }
        return helper::add_module('quiz', $params['courseid'], $params['section'], $params['visible'] ? 1 : 0, $review + [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'timeopen' => $params['timeopen'],
            'timeclose' => $params['timeclose'],
            'timelimit' => $params['timelimit'],
            'grade' => $params['grade'],
            'attempts' => $params['attempts'],
            'duedate' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'questionsperpage' => 1,
            'shuffleanswers' => 1,
            'sumgrades' => 0,
            'overduehandling' => 'autosubmit',
            'graceperiod' => 86400,
            'quizpassword' => '',
            'subnet' => '',
            'browsersecurity' => '',
            'delay1' => 0,
            'delay2' => 0,
            'showuserpicture' => 0,
            'showblocks' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ]);
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'instanceid' => new external_value(PARAM_INT, 'Quiz id'),
        ]);
    }
}
