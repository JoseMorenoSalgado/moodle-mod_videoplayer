<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Save user progress for a Drive Resource activity.
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

        $completionpercentage = max(0, min(100, (float)$params['completionpercentage']));
        $lastpage = max(0, (int)$params['lastpage']);
        $totalpages = max(0, (int)$params['totalpages']);
        $timespent = max(0, (int)$params['timespent']);

        if ($totalpages > 0 && $lastpage > 0) {
            $completionpercentage = max($completionpercentage, min(100, ($lastpage / $totalpages) * 100));
        }

        $progress = max(0, (float)$params['progress']);
        $completed = (bool)$params['completed'];
        $required = isset($videoplayer->completionpercentage) ? (int)$videoplayer->completionpercentage : 80;
        if ($completionpercentage >= $required) {
            $completed = true;
        }

        $now = time();
        $conditions = [
            'videoplayerid' => $videoplayer->id,
            'userid' => $USER->id,
        ];

        if ($record = $DB->get_record('videoplayer_views', $conditions)) {
            $record->progress = max((float)$record->progress, $progress);
            $record->completionpercentage = max((float)$record->completionpercentage, $completionpercentage);
            $record->completed = $record->completed || $completed ? 1 : 0;
            $record->lastpage = max((int)($record->lastpage ?? 0), $lastpage);
            $record->totalpages = max((int)($record->totalpages ?? 0), $totalpages);
            $record->timespent = max((int)($record->timespent ?? 0), $timespent);
            $record->timemodified = $now;
        } else {
            $record = (object) [
                'videoplayerid' => $videoplayer->id,
                'userid' => $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
                'progress' => $progress,
                'completed' => $completed ? 1 : 0,
                'completionpercentage' => $completionpercentage,
                'lastpage' => $lastpage,
                'totalpages' => $totalpages,
                'timespent' => $timespent,
                'points' => 0,
            ];
            $record->id = $DB->insert_record('videoplayer_views', $record);
        }

        $rewards = [];
        if (!empty($videoplayer->enablegamification)) {
            $rewards = self::award_rewards($videoplayer, $record, (int)$USER->id);
            $record->points = (int)$DB->get_field('videoplayer_rewards', 'COALESCE(SUM(points), 0)', $conditions) ?: 0;
        }

        if (!empty($record->id)) {
            $DB->update_record('videoplayer_views', $record);
        }

        if (!empty($record->completed)) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }
        }

        return [
            'status' => true,
            'completed' => (bool)$record->completed,
            'progress' => (float)$record->progress,
            'completionpercentage' => (float)$record->completionpercentage,
            'lastpage' => (int)($record->lastpage ?? 0),
            'totalpages' => (int)($record->totalpages ?? 0),
            'timespent' => (int)($record->timespent ?? 0),
            'points' => (int)($record->points ?? 0),
            'rewards' => $rewards,
            'timemodified' => (int)$record->timemodified,
        ];
    }

    /**
     * Award milestone rewards without duplicates.
     *
     * @param object $videoplayer
     * @param object $record
     * @param int $userid
     * @return array
     */
    private static function award_rewards(object $videoplayer, object $record, int $userid): array {
        global $DB;

        $earned = [];
        $pointsperpage = max(0, (int)($videoplayer->pointsperpage ?? 1));
        $candidates = [];

        if ((int)($record->lastpage ?? 0) > 0) {
            $candidates[] = ['reading', 'first_page', $pointsperpage, get_string('rewardfirstpage', 'mod_videoplayer')];
        }
        foreach ([25, 50, 75] as $milestone) {
            if ((float)$record->completionpercentage >= $milestone) {
                $candidates[] = ['milestone', 'percent_' . $milestone, $milestone, get_string('rewardpercent', 'mod_videoplayer', $milestone)];
            }
        }
        if (!empty($record->completed)) {
            $candidates[] = ['completion', 'completed', 100, get_string('rewardcompleted', 'mod_videoplayer')];
        }

        foreach ($candidates as $candidate) {
            [$type, $key, $points, $label] = $candidate;
            $conditions = [
                'videoplayerid' => $videoplayer->id,
                'userid' => $userid,
                'rewardtype' => $type,
                'rewardkey' => $key,
            ];
            if ($DB->record_exists('videoplayer_rewards', $conditions)) {
                continue;
            }
            $reward = (object)[
                'videoplayerid' => $videoplayer->id,
                'userid' => $userid,
                'rewardtype' => $type,
                'rewardkey' => $key,
                'points' => $points,
                'timecreated' => time(),
            ];
            $DB->insert_record('videoplayer_rewards', $reward);
            $earned[] = [
                'key' => $key,
                'label' => $label,
                'points' => $points,
            ];
        }

        return $earned;
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
