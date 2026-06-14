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

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course module ID.

$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videoplayer:view', $context);

$PAGE->set_url('/mod/videoplayer/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videoplayer->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$event = \mod_videoplayer\event\course_module_viewed::create([
    'objectid' => $videoplayer->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videoplayer', $videoplayer);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

if (!empty($videoplayer->intro)) {
    echo $OUTPUT->box(
        format_module_intro('videoplayer', $videoplayer, $cm->id),
        'generalbox mod_introbox',
        'videoplayerintro'
    );
}

if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $videoplayer->videourl, $matches)) {
    $driveid = clean_param($matches[1], PARAM_ALPHANUMEXT);
    $iframeurl = new moodle_url('https://drive.google.com/file/d/' . rawurlencode($driveid) . '/preview');

    echo html_writer::start_div('mod-videoplayer-wrapper', ['style' => 'max-width: 800px; margin: auto;']);
    echo html_writer::tag('iframe', '', [
        'src' => $iframeurl->out(false),
        'width' => '100%',
        'height' => '480',
        'allow' => 'autoplay; fullscreen',
        'allowfullscreen' => 'allowfullscreen',
        'sandbox' => 'allow-scripts allow-same-origin',
        'style' => 'border:none;',
        'title' => format_string($videoplayer->name),
    ]);
    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('invalidurl', 'videoplayer'), 'alert alert-danger');
}

echo $OUTPUT->footer();
