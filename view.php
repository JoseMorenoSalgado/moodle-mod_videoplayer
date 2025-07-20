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
 * View page for the Video Player activity.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/videoplayer/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videoplayer->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Registrar evento de vista.
$event = \mod_videoplayer\event\course_module_viewed::create([
    'objectid' => $videoplayer->id,
    'context' => $context
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('videoplayer', $videoplayer);
$event->trigger();

echo $OUTPUT->header();

// Contexto para plantilla Mustache.
$templatecontext = [
    'intro' => format_module_intro('videoplayer', $videoplayer, $cm->id)
];

$videourl = trim($videoplayer->videourl);
if (preg_match('/\/d\/([a-zA-Z0-9_-]{25,})/', $videourl, $matches) ||
    preg_match('/id=([a-zA-Z0-9_-]{25,})/', $videourl, $matches)) {
    $templatecontext['validurl'] = true;
    $templatecontext['driveid'] = $matches[1];
} else {
    $templatecontext['validurl'] = false;
    $templatecontext['invalidurl'] = get_string('invalidurl', 'videoplayer');
}

echo $OUTPUT->render_from_template('mod_videoplayer/view', $templatecontext);
echo $OUTPUT->footer();
