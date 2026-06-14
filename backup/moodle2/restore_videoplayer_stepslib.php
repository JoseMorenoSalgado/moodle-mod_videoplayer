<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Restore steps for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @category   backup
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore structure step for the videoplayer activity.
 */
class restore_videoplayer_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define restore paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('videoplayer', '/activity/videoplayer');

        if ($userinfo) {
            $paths[] = new restore_path_element('videoplayer_view', '/activity/videoplayer/views/view');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity instance.
     *
     * @param array|stdClass $data
     */
    protected function process_videoplayer($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();

        if (empty($data->source)) {
            $data->source = 'googledrive';
        }
        if (empty($data->type)) {
            $data->type = 'auto';
        }
        if (!isset($data->completionpercentage) || $data->completionpercentage === '') {
            $data->completionpercentage = 80;
        }

        $newitemid = $DB->insert_record('videoplayer', $data);
        $this->set_mapping('videoplayer', $oldid, $newitemid);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore user view/progress records.
     *
     * @param array|stdClass $data
     */
    protected function process_videoplayer_view($data) {
        global $DB;

        $data = (object) $data;
        $data->videoplayerid = $this->get_new_parentid('videoplayer');
        $data->userid = $this->get_mappingid('user', $data->userid);

        if (empty($data->userid)) {
            return;
        }

        $DB->insert_record('videoplayer_views', $data);
    }

    /**
     * Run after restore execution.
     */
    protected function after_execute() {
        // No file areas currently used by this activity.
    }
}
