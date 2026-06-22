<?php
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

$source = $videoplayer->source ?? 'googledrive';
$type = 'pdf';
$previewurl = null;
$protectedurl = new moodle_url('/mod/videoplayer/protected.php', ['id' => $cm->id]);

if ($source === 'localpdf') {
    if (!videoplayer_get_localpdf_file($context)) {
        echo $OUTPUT->header();
        echo html_writer::div(get_string('protectedresourceunavailable', 'mod_videoplayer'), 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }
} else {
    $fileid = drive::extract_file_id($videoplayer->videourl ?? '');
    if (!$fileid) {
        echo $OUTPUT->header();
        if (!empty($videoplayer->intro)) {
            echo $OUTPUT->box(
                format_module_intro('videoplayer', $videoplayer, $cm->id),
                'generalbox mod_introbox',
                'videoplayerintro'
            );
        }
        echo html_writer::div(get_string('invaliddriveurl', 'mod_videoplayer'), 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }

    $type = empty($videoplayer->type) || $videoplayer->type === 'auto'
        ? drive::detect_type($videoplayer->videourl)
        : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);
    $previewurl = drive::preview_url($fileid);
}

$typestringkey = 'type' . $type;
$typestring = get_string_manager()->string_exists($typestringkey, 'mod_videoplayer')
    ? get_string($typestringkey, 'mod_videoplayer')
    : get_string('typefile', 'mod_videoplayer');

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

if ($type === 'pdf') {
    $PAGE->requires->js_call_amd('mod_videoplayer/pdfviewer', 'init');
} else if ($type === 'video') {
    $PAGE->requires->css('/mod/videoplayer/thirdpartylibs/plyr/plyr.css');
    $PAGE->requires->js_call_amd('mod_videoplayer/plyr', 'init');
}

$playerstyle = '';
if (get_config('mod_videoplayer', 'playercolormode') === 'custom') {
    $playercolor = trim((string) get_config('mod_videoplayer', 'playercolor'));
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $playercolor)) {
        $playerstyle = '--mod-videoplayer-player-color: ' . $playercolor . ';';
    }
}

$initialpage = $progressrecord && !empty($progressrecord->lastpage) ? (int)$progressrecord->lastpage : 1;
$totalpages = $progressrecord && !empty($progressrecord->totalpages) ? (int)$progressrecord->totalpages : 0;
$points = $progressrecord && !empty($progressrecord->points) ? (int)$progressrecord->points : 0;
$completionpercent = $progressrecord ? (float)$progressrecord->completionpercentage : 0;
$watermark = fullname($USER) . ' · ' . userdate(time(), get_string('strftimedatetimeshort', 'langconfig'));

$templatecontext = [
    'type' => $type,
    'source' => $source,
    'cmid' => $cm->id,
    'resourcetype' => get_string('resourcetype', 'mod_videoplayer') . ': ' . $typestring,
    'iframeurl' => $previewurl ? $previewurl->out(false) : '',
    'pdfurl' => $protectedurl->out(false),
    'videourl' => $protectedurl->out(false),
    'title' => format_string($videoplayer->name),
    'playerstyle' => $playerstyle,
    'displaymode' => clean_param($videoplayer->displaymode ?? 'standard', PARAM_ALPHANUMEXT),
    'ebookmode' => ($videoplayer->displaymode ?? '') === 'ebook',
    'disabledownload' => !empty($videoplayer->disabledownload),
    'disablecontextmenu' => !empty($videoplayer->disablecontextmenu),
    'enablewatermark' => !empty($videoplayer->enablewatermark),
    'enablegamification' => !empty($videoplayer->enablegamification),
    'pointsperpage' => (int)($videoplayer->pointsperpage ?? 1),
    'initialpage' => max(1, $initialpage),
    'totalpages' => $totalpages,
    'points' => $points,
    'completionpercent' => round($completionpercent, 2),
    'watermark' => $watermark,
];

echo $OUTPUT->header();

if (!empty($videoplayer->intro)) {
    echo $OUTPUT->box(
        format_module_intro('videoplayer', $videoplayer, $cm->id),
        'generalbox mod_introbox',
        'videoplayerintro'
    );
}

if ($type === 'pdf') {
    echo $OUTPUT->render_from_template('mod_videoplayer/pdfjs', $templatecontext);
} else if ($type === 'video') {
    echo $OUTPUT->render_from_template('mod_videoplayer/video', $templatecontext);
} else {
    echo $OUTPUT->render_from_template('mod_videoplayer/resource', $templatecontext);
}

echo $OUTPUT->footer();
