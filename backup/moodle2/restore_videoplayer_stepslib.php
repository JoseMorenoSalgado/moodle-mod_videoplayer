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
 * Restore steps for mod_videoplayer.
 *
 * @package   mod_videoplayer
 * @category  backup
 * @copyright 2025 Jose Erasmo Moreno Salgado
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class restore_videoplayer_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('videoplayer', '/activity/videoplayer');

        return $this->prepare_activity_structure($paths);
    }

    protected function process_videoplayer($data) {
        global $DB;

        $data = (object)$data;

        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('videoplayer', $data);

        $this->apply_activity_instance($newitemid);
    }
}
