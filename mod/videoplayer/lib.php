<?php
defined('MOODLE_INTERNAL') || die();

function videoplayer_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    return $DB->insert_record('videoplayer', $data);
}

function videoplayer_update_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    return $DB->update_record('videoplayer', $data);
}

function videoplayer_delete_instance($id) {
    global $DB;
    return $DB->delete_records('videoplayer', array('id' => $id));
}

function videoplayer_get_coursemodule_info($coursemodule) {
    global $DB;
    if (!$videoplayer = $DB->get_record('videoplayer', array('id' => $coursemodule->instance))) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $videoplayer->name;
    return $info;
}
