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

        $sources = [
            'googledrive' => get_string('sourcegoogledrive', 'mod_videoplayer'),
            'localpdf' => get_string('sourcelocalpdf', 'mod_videoplayer'),
        ];
        $mform->addElement('select', 'source', get_string('resourcesource', 'mod_videoplayer'), $sources);
        $mform->setDefault('source', 'googledrive');

        $mform->addElement('text', 'videourl', get_string('driveurl', 'mod_videoplayer'), ['size' => 90]);
        $mform->setType('videourl', PARAM_URL);
        $mform->addHelpButton('videourl', 'driveurl', 'mod_videoplayer');
        $mform->hideIf('videourl', 'source', 'eq', 'localpdf');
        $mform->disabledIf('videourl', 'source', 'eq', 'localpdf');

        $filemanageroptions = $this->get_localpdf_filemanager_options();
        $mform->addElement('filemanager', 'localpdffile', get_string('localpdffile', 'mod_videoplayer'), null, $filemanageroptions);
        $mform->addHelpButton('localpdffile', 'localpdffile', 'mod_videoplayer');
        $mform->hideIf('localpdffile', 'source', 'eq', 'googledrive');

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
        $mform->disabledIf('type', 'source', 'eq', 'localpdf');

        $displaymodes = [
            'standard' => get_string('displaymodestandard', 'mod_videoplayer'),
            'ebook' => get_string('displaymodeebook', 'mod_videoplayer'),
        ];
        $mform->addElement('select', 'displaymode', get_string('displaymode', 'mod_videoplayer'), $displaymodes);
        $mform->setDefault('displaymode', 'ebook');
        $mform->addHelpButton('displaymode', 'displaymode', 'mod_videoplayer');
        $mform->hideIf('displaymode', 'type', 'neq', 'pdf');

        $mform->addElement('advcheckbox', 'disabledownload', get_string('disabledownload', 'mod_videoplayer'));
        $mform->setDefault('disabledownload', 1);
        $mform->addHelpButton('disabledownload', 'disabledownload', 'mod_videoplayer');

        $mform->addElement('advcheckbox', 'disablecontextmenu', get_string('disablecontextmenu', 'mod_videoplayer'));
        $mform->setDefault('disablecontextmenu', 1);

        $mform->addElement('advcheckbox', 'enablewatermark', get_string('enablewatermark', 'mod_videoplayer'));
        $mform->setDefault('enablewatermark', 1);

        $mform->addElement('header', 'gamificationheader', get_string('gamification', 'mod_videoplayer'));

        $mform->addElement('advcheckbox', 'enablegamification', get_string('enablegamification', 'mod_videoplayer'));
        $mform->setDefault('enablegamification', 1);
        $mform->addHelpButton('enablegamification', 'enablegamification', 'mod_videoplayer');

        $mform->addElement('text', 'pointsperpage', get_string('pointsperpage', 'mod_videoplayer'), ['size' => 5]);
        $mform->setType('pointsperpage', PARAM_INT);
        $mform->setDefault('pointsperpage', 1);
        $mform->addRule('pointsperpage', null, 'numeric', null, 'client');

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
     * Prepare draft file area before editing the form.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues): void {
        if ($this->current && !empty($this->current->id)) {
            $draftitemid = file_get_submitted_draft_itemid('localpdffile');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_videoplayer',
                'localpdf',
                0,
                $this->get_localpdf_filemanager_options()
            );
            $defaultvalues['localpdffile'] = $draftitemid;
        }
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
        $source = $data['source'] ?? 'googledrive';

        if ($source === 'googledrive' && (empty($data['videourl']) || !drive::is_supported_url($data['videourl']))) {
            $errors['videourl'] = get_string('invaliddriveurl', 'mod_videoplayer');
        }

        if ($source === 'localpdf') {
            $draftitemid = (int)($data['localpdffile'] ?? 0);
            $fs = get_file_storage();
            $context = context_user::instance($GLOBALS['USER']->id);
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, 'id', false);
            if (empty($files)) {
                $errors['localpdffile'] = get_string('requiredlocalpdf', 'mod_videoplayer');
            }
            foreach ($files as $file) {
                if ($file->get_mimetype() !== 'application/pdf') {
                    $errors['localpdffile'] = get_string('invalidlocalpdf', 'mod_videoplayer');
                    break;
                }
            }
        }

        if (isset($data['completionpercentage']) && ($data['completionpercentage'] < 0 || $data['completionpercentage'] > 100)) {
            $errors['completionpercentage'] = get_string('invalidcompletionpercentage', 'mod_videoplayer');
        }

        if (isset($data['pointsperpage']) && ($data['pointsperpage'] < 0 || $data['pointsperpage'] > 100)) {
            $errors['pointsperpage'] = get_string('invalidpointsperpage', 'mod_videoplayer');
        }

        return $errors;
    }

    /**
     * Return filemanager options for the local protected PDF area.
     *
     * @return array
     */
    private function get_localpdf_filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => get_max_upload_file_size($GLOBALS['CFG']->maxbytes, $this->course->maxbytes ?? 0),
            'maxfiles' => 1,
            'accepted_types' => ['.pdf'],
            'return_types' => FILE_INTERNAL,
        ];
    }
}
