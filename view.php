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

echo $OUTPUT->header();

if (!empty($videoplayer->intro)) {
    echo $OUTPUT->box(
        format_module_intro('videoplayer', $videoplayer, $cm->id),
        'generalbox mod_introbox',
        'videoplayerintro'
    );
}

$fileid = drive::extract_file_id($videoplayer->videourl ?? '');

if (!$fileid) {
    echo html_writer::div(get_string('invaliddriveurl', 'mod_videoplayer'), 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

$type = empty($videoplayer->type) || $videoplayer->type === 'auto'
    ? drive::detect_type($videoplayer->videourl)
    : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);

$previewurl = drive::preview_url($fileid);
$openurl = new moodle_url('https://drive.google.com/file/d/' . rawurlencode($fileid) . '/view');

$attributes = [
    'src' => $previewurl->out(false),
    'width' => '100%',
    'height' => '620',
    'allow' => 'autoplay; fullscreen',
    'allowfullscreen' => 'allowfullscreen',
    'sandbox' => 'allow-scripts allow-same-origin allow-forms allow-popups',
    'style' => 'border:0; width:100%; min-height:620px;',
    'title' => format_string($videoplayer->name),
    'loading' => 'lazy',
];

echo html_writer::start_div('mod-videoplayer-container', [
    'data-resource-type' => s($type),
    'data-cmid' => $cm->id,
]);

echo html_writer::start_div('mod-videoplayer-toolbar mb-3 d-flex flex-wrap align-items-center justify-content-between');
echo html_writer::tag('div', get_string('resourcetype', 'mod_videoplayer') . ': ' . get_string('type' . $type, 'mod_videoplayer'), [
    'class' => 'text-muted small',
]);
echo html_writer::link($openurl, get_string('openindrive', 'mod_videoplayer'), [
    'class' => 'btn btn-secondary btn-sm',
    'target' => '_blank',
    'rel' => 'noopener noreferrer',
]);
echo html_writer::end_div();

echo html_writer::start_div('mod-videoplayer-frame-wrapper', [
    'style' => 'max-width: 1100px; margin: 0 auto;',
]);
echo html_writer::tag('iframe', '', $attributes);
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
