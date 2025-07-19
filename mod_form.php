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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_videoplayer_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Name of the activity instance.
        $mform->addElement('text', 'name', get_string('videoname', 'mod_videoplayer'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Video URL (from Google Drive or elsewhere).
        $mform->addElement('text', 'video_url', get_string('videourl', 'mod_videoplayer'), ['size' => 80]);
        $mform->setType('video_url', PARAM_URL);
        $mform->addRule('video_url', null, 'required', null, 'client');

        // Standard intro and coursemodule elements.
        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
