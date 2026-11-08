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
 * Create a Book activity with its first chapter.
 *
 * @package    local_fullmcp
 * @copyright  2026 Tsopany
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_book extends external_api {
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
            'chaptertitle' => new external_value(PARAM_TEXT, 'First chapter title'),
            'chaptercontent' => new external_value(PARAM_RAW, 'First chapter HTML'),
            'visible' => new external_value(PARAM_INT, '1 show, 0 hide', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create a Book activity with its first chapter.
     *
     * @param int $courseid
     * @param int $section
     * @param string $name
     * @param string $intro
     * @param string $chaptertitle
     * @param string $chaptercontent
     * @param int $visible
     * @return array
     */
    public static function execute(int $courseid, int $section, string $name, string $intro, string $chaptertitle, string $chaptercontent, int $visible): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/book/locallib.php');
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'section', 'name', 'intro', 'chaptertitle', 'chaptercontent', 'visible'));
        if (trim($params['chaptertitle']) === '') {
            throw new \core\exception\invalid_parameter_exception('chaptertitle must not be empty');
        }
        // Check the chapter capability up front so a refusal does not leave an empty book behind.
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('mod/book:edit', $context);
        $result = helper::add_module('book', $params['courseid'], $params['section'], $params['visible'] ? 1 : 0, [
            'name' => $params['name'],
            'intro' => $params['intro'],
            'numbering' => BOOK_NUM_NUMBERS,
            'navstyle' => 1,
            'customtitles' => 0,
        ]);
        $modcontext = \context_module::instance($result['cmid']);

        // Moodle has no API for creating chapters; this mirrors what mod/book/edit.php does for a new first chapter.
        $book = $DB->get_record('book', ['id' => $result['instanceid']], '*', MUST_EXIST);
        $chapter = (object) [
            'bookid' => $book->id,
            'pagenum' => 1,
            'subchapter' => 0,
            'title' => $params['chaptertitle'],
            'content' => helper::html($modcontext, $params['chaptercontent']),
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'importsrc' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $chapter->id = $DB->insert_record('book_chapters', $chapter);
        $DB->set_field('book', 'revision', $book->revision + 1, ['id' => $book->id]);
        \mod_book\event\chapter_created::create_from_chapter($book, $modcontext, $chapter)->trigger();
        book_preload_chapters($book);

        return $result + ['chapterid' => (int) $chapter->id];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'instanceid' => new external_value(PARAM_INT, 'Book id'),
            'chapterid' => new external_value(PARAM_INT, 'First chapter id'),
        ]);
    }
}
