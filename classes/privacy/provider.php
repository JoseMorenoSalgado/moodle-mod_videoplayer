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

namespace mod_videoplayer\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videoplayer_views', [
            'videoplayerid' => 'privacy:metadata:videoplayer_views:videoplayerid',
            'userid' => 'privacy:metadata:videoplayer_views:userid',
            'progress' => 'privacy:metadata:videoplayer_views:progress',
            'completed' => 'privacy:metadata:videoplayer_views:completed',
            'completionpercentage' => 'privacy:metadata:videoplayer_views:completionpercentage',
            'timecreated' => 'privacy:metadata:videoplayer_views:timecreated',
            'timemodified' => 'privacy:metadata:videoplayer_views:timemodified',
        ], 'privacy:metadata:videoplayer_views');

        return $collection;
    }

    /**
     * Get contexts that contain user information for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoplayer} v ON v.id = cm.instance
                  JOIN {videoplayer_views} vv ON vv.videoplayerid = v.id
                 WHERE vv.userid = :userid";

        $params = [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'videoplayer',
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('videoplayer', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $records = $DB->get_records('videoplayer_views', [
                'videoplayerid' => $cm->instance,
                'userid' => $userid,
            ]);

            if (!$records) {
                continue;
            }

            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'progress' => $record->progress,
                    'completed' => (bool) $record->completed,
                    'completionpercentage' => $record->completionpercentage,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            $contextdata = helper::get_context_data($context, $contextlist->get_user());
            $contextdata->views = $data;
            writer::with_context($context)->export_data([], $contextdata);
        }
    }

    /**
     * Delete all user data for a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('videoplayer', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videoplayer_views', ['videoplayerid' => $cm->instance]);
    }

    /**
     * Delete user data by approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('videoplayer', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('videoplayer_views', [
                'videoplayerid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT vv.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {videoplayer} v ON v.id = cm.instance
                  JOIN {videoplayer_views} vv ON vv.videoplayerid = v.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'videoplayer',
            'cmid' => $context->instanceid,
        ]);
    }

    /**
     * Delete data for approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('videoplayer', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['videoplayerid'] = $cm->instance;
        $DB->delete_records_select('videoplayer_views', "videoplayerid = :videoplayerid AND userid {$insql}", $params);
    }
}