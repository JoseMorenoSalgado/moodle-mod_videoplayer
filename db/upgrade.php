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
/**
 * View page for the Video Player activity.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud  <jose@elearningcloud.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_videoplayer_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024092204) {
        $table = new xmldb_table('videoplayer');
        $field = new xmldb_field('start', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'displayasstartscreen');
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('end', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'start');
        $dbman->change_field_type($table, $field);

        upgrade_mod_savepoint(true, 2024092204, 'videoplayer');
    }

    if ($oldversion < 2024092214) {
        $table = new xmldb_table('videoplayer');
        $field = new xmldb_field('extendedcompletion', XMLDB_TYPE_TEXT, null, null, null, null, null, 'posterimage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2024092214, 'videoplayer');
    }

    if ($oldversion < 2024092222) {
        $table = new xmldb_table('videoplayer');
        $field = new xmldb_field('start', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'displayasstartscreen');
        $dbman->rename_field($table, $field, 'starttime');
        upgrade_mod_savepoint(true, 2024092222, 'videoplayer');
    }

    if ($oldversion < 2024092223) {
        $table = new xmldb_table('videoplayer');
        $field = new xmldb_field('end', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'starttime');
        $dbman->rename_field($table, $field, 'endtime');
        upgrade_mod_savepoint(true, 2024092223, 'videoplayer');
    }

    if ($oldversion < 2025010100) {
        $table = new xmldb_table('videoplayer_items');
        $field = new xmldb_field('intg1', XMLDB_TYPE_INTEGER, '20', null, null, null, '0', 'advanced');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('intg2', XMLDB_TYPE_INTEGER, '20', null, null, null, '0', 'intg1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('intg3', XMLDB_TYPE_INTEGER, '20', null, null, null, '0', 'intg2');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025010100, 'videoplayer');
    }

    if ($oldversion < 2025010101) {
        $table = new xmldb_table('videoplayer_completion');
        $field = new xmldb_field('lastviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completiondetails');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2025010101, 'videoplayer');
    }

    if ($oldversion < 2025011309) {
        $table = new xmldb_table('videoplayer_settings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endscreentext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('displayasstartscreen', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('completionpercentage', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('displayoptions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('extendedcompletion', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('completion', XMLDB_TYPE_INTEGER, '1', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2025011309, 'videoplayer');
    }

    if ($oldversion < 2025033001) {
        $table = new xmldb_table('videoplayer_settings');
        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $dbman->drop_key($table, $key);

        $field = new xmldb_field('usermodified');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2025033001, 'videoplayer');
    }

    if ($oldversion < 2025041202) {
        $table = new xmldb_table('videoplayer_completion');
        $field = new xmldb_field('timeended', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'timecompleted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2025041202, 'videoplayer');
    }

    if ($oldversion < 2025052803) {
        $table = new xmldb_table('videoplayer_log');

        $fields = [
            new xmldb_field('intg4', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'intg3'),
            new xmldb_field('intg5', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'intg4'),
            new xmldb_field('intg6', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'intg5'),
            new xmldb_field('char4', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'char3'),
            new xmldb_field('char5', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'char4'),
            new xmldb_field('char6', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'char5'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('videoplayer_settings');
        $field = new xmldb_field('defaults', XMLDB_TYPE_TEXT, null, null, null, null, null, 'completion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025052803, 'videoplayer');
    }

    if ($oldversion < 2025052805) {
        $table = new xmldb_table('videoplayer_defaults');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestamp', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('displayoptions', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'popup');
        $table->add_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'richtext');
        $table->add_field('hascompletion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('completiontracking', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'manual');
        $table->add_field('advanced', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('intg1', XMLDB_TYPE_INTEGER, '20', null, null, null, '0');
        $table->add_field('intg2', XMLDB_TYPE_INTEGER, '20', null, null, null, '0');
        $table->add_field('intg3', XMLDB_TYPE_INTEGER, '20', null, null, null, '0');
        $table->add_field('char1', XMLDB_TYPE_CHAR, '255', null, null, null, 'null');
        $table->add_field('char2', XMLDB_TYPE_CHAR, '255', null, null, null, 'null');
        $table->add_field('char3', XMLDB_TYPE_CHAR, '255', null, null, null, 'null');
        $table->add_field('text1', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('text2', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('text3', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('requiremintime', XMLDB_TYPE_INTEGER, '20', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2025052805, 'videoplayer');
    }

    return true;
}
