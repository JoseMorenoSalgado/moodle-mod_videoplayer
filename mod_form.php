<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_videoplayer_mod_form extends moodleform_mod {
    function definition() {
        $mform = $this->_form;

        // Nombre de la actividad
        $mform->addElement('text', 'name', get_string('videoname', 'videoplayer'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // URL de Google Drive
        $mform->addElement('text', 'video_url', get_string('videourl', 'videoplayer'), array('size' => '80'));
        $mform->setType('video_url', PARAM_RAW);
        $mform->addRule('video_url', null, 'required', null, 'client');

        // Descripción
        $this->standard_intro_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
