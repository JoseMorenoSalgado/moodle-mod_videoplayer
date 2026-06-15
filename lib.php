<?php
defined('MOODLE_INTERNAL') || die();

function videoplayer_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

function videoplayer_queue_pdf_precache(int $instanceid): void {
    $task = new \mod_videoplayer\task\precache_pdf();
    $task->set_component('mod_videoplayer');
    $task->set_custom_data(['instanceid' => $instanceid]);
    \core\task\manager::queue_adhoc_task($task, true);
}

function videoplayer_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $id = $DB->insert_record('videoplayer', $data);
    videoplayer_queue_pdf_precache((int)$id);
    return $id;
}

function videoplayer_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $result = $DB->update_record('videoplayer', $data);
    if ($result) {
        videoplayer_queue_pdf_precache((int)$data->id);
    }
    return $result;
}

function videoplayer_delete_instance($id) {
    global $DB;

    if (!$videoplayer = $DB->get_record('videoplayer', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('videoplayer_views', ['videoplayerid' => $videoplayer->id]);

    return $DB->delete_records('videoplayer', ['id' => $videoplayer->id]);
}
