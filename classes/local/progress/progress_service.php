<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\local\progress;

use mod_videoplayer\local\gamification\reward_service;

/**
 * Handles Drive Resource progress persistence and completion integration.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_service {

    /**
     * Save progress and return the persisted state.
     *
     * @param \cm_info|object $cm
     * @param object $course
     * @param object $videoplayer
     * @param \context_module $context
     * @param int $userid
     * @param array $input
     * @return array
     */
    public function save_progress(object $cm, object $course, object $videoplayer, \context_module $context, int $userid, array $input): array {
        global $DB;

        $completionpercentage = max(0, min(100, (float)($input['completionpercentage'] ?? 0)));
        $lastpage = max(0, (int)($input['lastpage'] ?? 0));
        $totalpages = max(0, (int)($input['totalpages'] ?? 0));
        $timespent = max(0, (int)($input['timespent'] ?? 0));
        $progress = max(0, (float)($input['progress'] ?? 0));
        $completed = !empty($input['completed']);

        if ($totalpages > 0 && $lastpage > 0) {
            $completionpercentage = max($completionpercentage, min(100, ($lastpage / $totalpages) * 100));
        }

        $required = isset($videoplayer->completionpercentage) ? (int)$videoplayer->completionpercentage : 80;
        if ($completionpercentage >= $required) {
            $completed = true;
        }

        $now = time();
        $conditions = [
            'videoplayerid' => $videoplayer->id,
            'userid' => $userid,
        ];

        $wascompleted = false;
        if ($record = $DB->get_record('videoplayer_views', $conditions)) {
            $wascompleted = !empty($record->completed);
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
                'userid' => $userid,
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

        $rewarddata = [
            'rewards' => [],
            'totalpoints' => (int)($record->points ?? 0),
        ];
        if (!empty($videoplayer->enablegamification)) {
            $rewarddata = (new reward_service())->award_rewards($videoplayer, $record, $userid, $context);
            $record->points = $rewarddata['totalpoints'];
        }

        $DB->update_record('videoplayer_views', $record);

        \mod_videoplayer\event\progress_updated::create([
            'objectid' => $record->id,
            'context' => $context,
            'userid' => $userid,
            'other' => [
                'videoplayerid' => $videoplayer->id,
                'completionpercentage' => (float)$record->completionpercentage,
                'lastpage' => (int)($record->lastpage ?? 0),
                'totalpages' => (int)($record->totalpages ?? 0),
            ],
        ])->trigger();

        if (!empty($record->completed) && !$wascompleted) {
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
            }

            \mod_videoplayer\event\resource_completed::create([
                'objectid' => $record->id,
                'context' => $context,
                'userid' => $userid,
                'other' => [
                    'videoplayerid' => $videoplayer->id,
                    'completionpercentage' => (float)$record->completionpercentage,
                ],
            ])->trigger();
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
            'rewards' => $rewarddata['rewards'],
            'timemodified' => (int)$record->timemodified,
        ];
    }
}
