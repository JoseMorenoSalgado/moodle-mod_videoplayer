<?php
// This file is part of Moodle - http://moodle.org/

/**
 * View page for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_videoplayer\local\drive;

$id = required_param('id', PARAM_INT);

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

$fileid = drive::extract_file_id($videoplayer->videourl ?? '');

echo $OUTPUT->header();

if (!empty($videoplayer->intro)) {
    echo $OUTPUT->box(
        format_module_intro('videoplayer', $videoplayer, $cm->id),
        'generalbox mod_introbox',
        'videoplayerintro'
    );
}

if (!$fileid) {
    echo html_writer::div(get_string('invaliddriveurl', 'mod_videoplayer'), 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

$type = empty($videoplayer->type) || $videoplayer->type === 'auto'
    ? drive::detect_type($videoplayer->videourl)
    : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);

$typestringkey = 'type' . $type;
$typestring = get_string_manager()->string_exists($typestringkey, 'mod_videoplayer')
    ? get_string($typestringkey, 'mod_videoplayer')
    : get_string('typefile', 'mod_videoplayer');

$previewurl = drive::preview_url($fileid);
$openurl = new moodle_url('https://drive.google.com/file/d/' . rawurlencode($fileid) . '/view');

$progressrecord = null;
if (!isguestuser()) {
    $progressrecord = $DB->get_record('videoplayer_views', [
        'videoplayerid' => $videoplayer->id,
        'userid' => $USER->id,
    ]);
}

$initialprogress = $progressrecord ? (float) $progressrecord->progress : 0;
$completed = $progressrecord ? (bool) $progressrecord->completed : false;
$requiredseconds = max(60, ((int) ($videoplayer->completionpercentage ?? 80)) * 6);

if (!isguestuser()) {
    $PAGE->requires->js_call_amd('mod_videoplayer/progress', 'init', [[
        'cmid' => $cm->id,
        'requiredSeconds' => $requiredseconds,
        'interval' => 30000,
        'initialProgress' => $initialprogress,
        'completed' => $completed,
    ]]);
}

$templatecontext = [
    'type' => $type,
    'cmid' => $cm->id,
    'resourcetype' => get_string('resourcetype', 'mod_videoplayer') . ': ' . $typestring,
    'openurl' => $openurl->out(false),
    'openlabel' => get_string('openindrive', 'mod_videoplayer'),
    'iframeurl' => $previewurl->out(false),
    'title' => format_string($videoplayer->name),
];

echo $OUTPUT->render_from_template('mod_videoplayer/resource', $templatecontext);

echo $OUTPUT->footer();
