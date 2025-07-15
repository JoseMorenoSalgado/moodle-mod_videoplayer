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
    if (!$videoplayer = $DB->get_record('videoplayer', array('id' => $id))) {
        return false;
    }
    return $DB->delete_records('videoplayer', array('id' => $id));
}