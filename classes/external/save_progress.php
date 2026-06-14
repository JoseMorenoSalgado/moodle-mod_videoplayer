<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Save user progress for a Google Drive resource.
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
            'progress' => new external_value(PARAM_FLOAT, 'Progress value from 0 to 100', VALUE_DEFAULT, 0),
            'completed' => new external_value(PARAM_BOOL, 'Completion state', VALUE_DEFAULT, false),
            'completionpercentage' => new external_value(PARAM_FLOAT, 'Completion percentage from 0 to 100', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save progress.
     *
     * @param int $cmid
     * @param float $progress
     * @param bool $completed
     * @param float $completionpercentage
     * @return array
     */
    public static function execute(int $cmid, float $progress = 0, bool $completed = false, float $completionpercentage = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'progress' => $progress,
            'completed' => $completed,
            'completionpercentage' => $completionpercentage,
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

        $progress = max(0, min(100, (float) $params['progress']));
        $completionpercentage = max(0, min(100, (float) $params['completionpercentage']));
        $completed = (bool) $params['completed'];

        $required = isset($videoplayer->completionpercentage) ? (int) $videoplayer->completionpercentage : 80;
        if ($completionpercentage >= $required) {
            $completed = true;
        }

        $now = time();
        $conditions = [
            'videoplayerid' => $videoplayer->id,
            'userid' => $USER->id,
        ];

        if ($record = $DB->get_record('videoplayer_views', $conditions)) {
            $record->progress = max((float) $record->progress, $progress);
            $record->completionpercentage = max((float) $record->completionpercentage, $completionpercentage);
            $record->completed = $record->completed || $completed ? 1 : 0;
            $record->timemodified = $now;
            $DB->update_record('videoplayer_views', $record);
        } else {
            $record = (object) [
                'videoplayerid' => $videoplayer->id,
                'userid' => $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
                'progress' => $progress,
                'completed' => $completed ? 1 : 0,
                'completionpercentage' => $completionpercentage,
            ];
            $record->id = $DB->insert_record('videoplayer_views', $record);
        }

        if (!empty($record->completed)) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }
        }

        return [
            'status' => true,
            'completed' => (bool) $record->completed,
            'progress' => (float) $record->progress,
            'completionpercentage' => (float) $record->completionpercentage,
            'timemodified' => (int) $record->timemodified,
        ];
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
            'timemodified' => new external_value(PARAM_INT, 'Last modification timestamp'),
        ]);
    }
}
