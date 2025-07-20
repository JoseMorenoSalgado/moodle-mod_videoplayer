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

/**
 * Form definition for the Video Player activity.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_videoplayer_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // Nombre de la actividad.
        $mform->addElement('text', 'name', get_string('videoname', 'mod_videoplayer'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Campo para la URL del video.
        $mform->addElement('text', 'videourl', get_string('videourl', 'mod_videoplayer'), ['size' => 80]);
        $mform->setType('videourl', PARAM_URL);
        $mform->addRule('videourl', null, 'required', null, 'client');

        // Campos estándar de Moodle.
        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}