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
 * Library callbacks for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declare module feature support.
 *
 * @param string $feature
 * @return mixed
 */
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

/**
 * Add a new videoplayer instance.
 *
 * @param stdClass $data
 * @param mod_videoplayer_mod_form|null $mform
 * @return int
 */
function videoplayer_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    return $DB->insert_record('videoplayer', $data);
}

/**
 * Update an existing videoplayer instance.
 *
 * @param stdClass $data
 * @param mod_videoplayer_mod_form|null $mform
 * @return bool
 */
function videoplayer_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('videoplayer', $data);
}

/**
 * Delete a videoplayer instance and related user view records.
 *
 * @param int $id
 * @return bool
 */
function videoplayer_delete_instance($id) {
    global $DB;

    if (!$videoplayer = $DB->get_record('videoplayer', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('videoplayer_views', ['videoplayerid' => $videoplayer->id]);

    return $DB->delete_records('videoplayer', ['id' => $videoplayer->id]);
}
