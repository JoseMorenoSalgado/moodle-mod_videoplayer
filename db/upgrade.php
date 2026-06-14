<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Upgrade steps for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_videoplayer_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061400) {
        $table = new xmldb_table('videoplayer');

        if ($dbman->table_exists($table)) {
            $legacyfield = new xmldb_field('start');
            $newfield = new xmldb_field('starttime', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'displayasstartscreen');
            if ($dbman->field_exists($table, $legacyfield) && !$dbman->field_exists($table, $newfield)) {
                $dbman->rename_field($table, $legacyfield, 'starttime');
            }

            $legacyfield = new xmldb_field('end');
            $newfield = new xmldb_field('endtime', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'starttime');
            if ($dbman->field_exists($table, $legacyfield) && !$dbman->field_exists($table, $newfield)) {
                $dbman->rename_field($table, $legacyfield, 'endtime');
            }

            $fields = [
                new xmldb_field('source', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'googledrive', 'introformat'),
                new xmldb_field('videourl', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null, 'source'),
                new xmldb_field('type', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'auto', 'videourl'),
                new xmldb_field('video', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'type'),
                new xmldb_field('endscreentext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'video'),
                new xmldb_field('displayasstartscreen', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'endscreentext'),
                new xmldb_field('starttime', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'displayasstartscreen'),
                new xmldb_field('endtime', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'starttime'),
                new xmldb_field('completionpercentage', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '80', 'endtime'),
                new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completionpercentage'),
                new xmldb_field('displayoptions', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grade'),
                new xmldb_field('posterimage', XMLDB_TYPE_TEXT, null, null, null, null, null, 'displayoptions'),
                new xmldb_field('extendedcompletion', XMLDB_TYPE_TEXT, null, null, null, null, null, 'posterimage'),
            ];

            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }

            $videourlfield = new xmldb_field('videourl', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null, 'source');
            if ($dbman->field_exists($table, $videourlfield)) {
                $dbman->change_field_type($table, $videourlfield);
            }

            $sourcefield = new xmldb_field('source', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'googledrive', 'introformat');
            if ($dbman->field_exists($table, $sourcefield)) {
                $dbman->change_field_type($table, $sourcefield);
                $dbman->change_field_default($table, $sourcefield);
            }

            $typefield = new xmldb_field('type', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'auto', 'videourl');
            if ($dbman->field_exists($table, $typefield)) {
                $dbman->change_field_type($table, $typefield);
                $dbman->change_field_default($table, $typefield);
            }

            $completionfield = new xmldb_field('completionpercentage', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '80', 'endtime');
            if ($dbman->field_exists($table, $completionfield)) {
                $dbman->change_field_type($table, $completionfield);
                $dbman->change_field_default($table, $completionfield);
            }

            $DB->execute("UPDATE {videoplayer} SET source = 'googledrive' WHERE source IS NULL OR source = ''");
            $DB->execute("UPDATE {videoplayer} SET type = 'auto' WHERE type IS NULL OR type = ''");
            $DB->execute("UPDATE {videoplayer} SET completionpercentage = 80 WHERE completionpercentage IS NULL OR completionpercentage = 0");

            $indexes = [
                new xmldb_index('course_idx', XMLDB_INDEX_NOTUNIQUE, ['course']),
                new xmldb_index('source_idx', XMLDB_INDEX_NOTUNIQUE, ['source']),
                new xmldb_index('type_idx', XMLDB_INDEX_NOTUNIQUE, ['type']),
            ];

            foreach ($indexes as $index) {
                if (!$dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }

        $viewstable = new xmldb_table('videoplayer_views');
        if (!$dbman->table_exists($viewstable)) {
            $viewstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $viewstable->add_field('videoplayerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $viewstable->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $viewstable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $viewstable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $viewstable->add_field('progress', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $viewstable->add_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $viewstable->add_field('completionpercentage', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
            $viewstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $viewstable->add_key('fk_videoplayerid', XMLDB_KEY_FOREIGN, ['videoplayerid'], 'videoplayer', ['id']);
            $viewstable->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $viewstable->add_key('uniq_videoplayer_user', XMLDB_KEY_UNIQUE, ['videoplayerid', 'userid']);
            $viewstable->add_index('videoplayerid_idx', XMLDB_INDEX_NOTUNIQUE, ['videoplayerid']);
            $viewstable->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $viewstable->add_index('completed_idx', XMLDB_INDEX_NOTUNIQUE, ['completed']);
            $viewstable->add_index('timemodified_idx', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);
            $dbman->create_table($viewstable);
        } else {
            $fields = [
                new xmldb_field('progress', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0', 'timemodified'),
                new xmldb_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'progress'),
                new xmldb_field('completionpercentage', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0', 'completed'),
            ];

            foreach ($fields as $field) {
                if (!$dbman->field_exists($viewstable, $field)) {
                    $dbman->add_field($viewstable, $field);
                } else {
                    $dbman->change_field_type($viewstable, $field);
                    $dbman->change_field_default($viewstable, $field);
                }
            }

            $indexes = [
                new xmldb_index('videoplayerid_idx', XMLDB_INDEX_NOTUNIQUE, ['videoplayerid']),
                new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']),
                new xmldb_index('completed_idx', XMLDB_INDEX_NOTUNIQUE, ['completed']),
                new xmldb_index('timemodified_idx', XMLDB_INDEX_NOTUNIQUE, ['timemodified']),
            ];

            foreach ($indexes as $index) {
                if (!$dbman->index_exists($viewstable, $index)) {
                    $dbman->add_index($viewstable, $index);
                }
            }

            $unique = new xmldb_key('uniq_videoplayer_user', XMLDB_KEY_UNIQUE, ['videoplayerid', 'userid']);
            if (!$dbman->key_exists($viewstable, $unique)) {
                $duplicates = $DB->get_records_sql("SELECT videoplayerid, userid, COUNT(*) AS total
                                                      FROM {videoplayer_views}
                                                  GROUP BY videoplayerid, userid
                                                    HAVING COUNT(*) > 1");
                foreach ($duplicates as $duplicate) {
                    $records = $DB->get_records('videoplayer_views', [
                        'videoplayerid' => $duplicate->videoplayerid,
                        'userid' => $duplicate->userid,
                    ], 'timemodified DESC, id DESC', 'id');
                    $keepfirst = true;
                    foreach ($records as $record) {
                        if ($keepfirst) {
                            $keepfirst = false;
                            continue;
                        }
                        $DB->delete_records('videoplayer_views', ['id' => $record->id]);
                    }
                }
                $dbman->add_key($viewstable, $unique);
            }
        }

        upgrade_mod_savepoint(true, 2026061400, 'videoplayer');
    }

    return true;
}
