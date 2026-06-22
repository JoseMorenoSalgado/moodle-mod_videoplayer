<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_videoplayer\local\progress\progress_service;

/**
 * External API for saving Drive Resource progress.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_progress extends external_api {

    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'progress' => new external_value(PARAM_FLOAT, 'Progress value', VALUE_DEFAULT, 0),
            'completed' => new external_value(PARAM_BOOL, 'Completion state', VALUE_DEFAULT, false),
            'completionpercentage' => new external_value(PARAM_FLOAT, 'Completion percentage from 0 to 100', VALUE_DEFAULT, 0),
            'lastpage' => new external_value(PARAM_INT, 'Last read page', VALUE_DEFAULT, 0),
            'totalpages' => new external_value(PARAM_INT, 'Total PDF pages', VALUE_DEFAULT, 0),
            'timespent' => new external_value(PARAM_INT, 'Active time spent in seconds', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save progress.
     *
     * @param int $cmid
     * @param float $progress
     * @param bool $completed
     * @param float $completionpercentage
     * @param int $lastpage
     * @param int $totalpages
     * @param int $timespent
     * @return array
     */
    public static function execute(
        int $cmid,
        float $progress = 0,
        bool $completed = false,
        float $completionpercentage = 0,
        int $lastpage = 0,
        int $totalpages = 0,
        int $timespent = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'progress' => $progress,
            'completed' => $completed,
            'completionpercentage' => $completionpercentage,
            'lastpage' => $lastpage,
            'totalpages' => $totalpages,
            'timespent' => $timespent,
        ]);

        $cm = get_coursemodule_from_id('videoplayer', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_login($course, false, $cm);
        require_capability('mod/videoplayer:view', $context);

        if (isguestuser() || empty($USER->id)) {
            throw new \moodle_exception('guestsarenotallowed', 'error');
        }

        return (new progress_service())->save_progress($cm, $course, $videoplayer, $context, (int)$USER->id, $params);
    }

    /**
     * Define return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Operation status'),
            'completed' => new external_value(PARAM_BOOL, 'Completion state'),
            'progress' => new external_value(PARAM_FLOAT, 'Saved progress'),
            'completionpercentage' => new external_value(PARAM_FLOAT, 'Saved completion percentage'),
            'lastpage' => new external_value(PARAM_INT, 'Last read page'),
            'totalpages' => new external_value(PARAM_INT, 'Total pages'),
            'timespent' => new external_value(PARAM_INT, 'Active time spent'),
            'points' => new external_value(PARAM_INT, 'Total points'),
            'rewards' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Reward key'),
                'label' => new external_value(PARAM_TEXT, 'Reward label'),
                'points' => new external_value(PARAM_INT, 'Awarded points'),
            ])),
            'timemodified' => new external_value(PARAM_INT, 'Last modification timestamp'),
        ]);
    }
}
