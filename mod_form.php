<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Form definition for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_videoplayer\local\drive;

/**
 * Activity settings form.
 */
class mod_videoplayer_mod_form extends moodleform_mod {

    /**
     * Define form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('resourcename', 'mod_videoplayer'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'videourl', get_string('driveurl', 'mod_videoplayer'), ['size' => 90]);
        $mform->setType('videourl', PARAM_URL);
        $mform->addRule('videourl', null, 'required', null, 'client');
        $mform->addHelpButton('videourl', 'driveurl', 'mod_videoplayer');

        $types = [
            'auto' => get_string('typeauto', 'mod_videoplayer'),
            'video' => get_string('typevideo', 'mod_videoplayer'),
            'pdf' => get_string('typepdf', 'mod_videoplayer'),
            'image' => get_string('typeimage', 'mod_videoplayer'),
            'document' => get_string('typedocument', 'mod_videoplayer'),
            'spreadsheet' => get_string('typespreadsheet', 'mod_videoplayer'),
            'presentation' => get_string('typepresentation', 'mod_videoplayer'),
            'file' => get_string('typefile', 'mod_videoplayer'),
        ];
        $mform->addElement('select', 'type', get_string('resourcetype', 'mod_videoplayer'), $types);
        $mform->setDefault('type', 'auto');

        $mform->addElement('text', 'completionpercentage', get_string('completionpercentage', 'mod_videoplayer'), ['size' => 5]);
        $mform->setType('completionpercentage', PARAM_INT);
        $mform->setDefault('completionpercentage', 80);
        $mform->addRule('completionpercentage', null, 'numeric', null, 'client');
        $mform->addHelpButton('completionpercentage', 'completionpercentage', 'mod_videoplayer');

        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['videourl']) || !drive::is_supported_url($data['videourl'])) {
            $errors['videourl'] = get_string('invaliddriveurl', 'mod_videoplayer');
        }

        if (isset($data['completionpercentage']) && ($data['completionpercentage'] < 0 || $data['completionpercentage'] > 100)) {
            $errors['completionpercentage'] = get_string('invalidcompletionpercentage', 'mod_videoplayer');
        }

        return $errors;
    }
}
