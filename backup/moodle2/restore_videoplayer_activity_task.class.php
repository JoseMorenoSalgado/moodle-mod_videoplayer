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
 * Restore task for the Drive Resource activity.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoplayer/backup/moodle2/restore_videoplayer_stepslib.php');

/**
 * Defines the restore task for a Drive Resource activity.
 */
class restore_videoplayer_activity_task extends restore_activity_task {

    /**
     * Define activity-specific restore settings.
     */
    protected function define_my_settings(): void {
        // No activity-specific settings are required.
    }

    /**
     * Define activity-specific restore steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_videoplayer_activity_structure_step(
            'videoplayer_structure',
            'videoplayer.xml'
        ));
    }

    /**
     * Define content fields that need link decoding during restore.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('videoplayer', ['intro'], 'videoplayer'),
        ];
    }

    /**
     * Define portable Drive Resource URL decoding rules.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule(
                'VIDEOPLAYERVIEWBYID',
                '/mod/videoplayer/view.php?id=$1',
                'course_module'
            ),
        ];
    }
}
