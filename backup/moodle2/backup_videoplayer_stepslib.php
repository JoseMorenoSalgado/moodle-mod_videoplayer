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
 * Defines the backup structure for mod_videoplayer.
 *
 * @package   mod_videoplayer
 * @category  backup
 * @copyright 2025 Jose Erasmo Moreno Salgado
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/videoplayer/backup/moodle2/backup_videoplayer_stepslib.php');

class backup_videoplayer_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {

        $videoplayer = new backup_nested_element('videoplayer', ['id'], [
            'name', 'intro', 'introformat', 'video_url', 'timemodified'
        ]);

        $videoplayer->set_source_table('videoplayer', ['id' => backup::VAR_ACTIVITYID]);

        $videoplayer->annotate_ids('user', 'userid');

        return $this->prepare_activity_structure($videoplayer);
    }
}
