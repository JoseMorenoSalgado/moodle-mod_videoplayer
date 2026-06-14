<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Progress report for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videoplayer:viewreport', $context);

$PAGE->set_url('/mod/videoplayer/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('progressreport', 'mod_videoplayer'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(format_string($videoplayer->name), new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('progressreport', 'mod_videoplayer'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('progressreport', 'mod_videoplayer') . ': ' . format_string($videoplayer->name));

$table = new flexible_table('mod-videoplayer-report-' . $cm->id);
$table->define_columns(['fullname', 'email', 'progress', 'completionpercentage', 'completed', 'timemodified']);
$table->define_headers([
    get_string('fullnameuser'),
    get_string('email'),
    get_string('progress', 'mod_videoplayer'),
    get_string('completionpercentage', 'mod_videoplayer'),
    get_string('completed', 'completion'),
    get_string('lastmodified'),
]);
$table->define_baseurl($PAGE->url);
$table->sortable(true, 'fullname', SORT_ASC);
$table->collapsible(false);
$table->set_attribute('class', 'generaltable generalbox mod-videoplayer-report');
$table->setup();

$fields = user_picture::fields('u', ['email']);
$sql = "SELECT {$fields}, vv.progress, vv.completionpercentage, vv.completed, vv.timemodified
          FROM {videoplayer_views} vv
          JOIN {user} u ON u.id = vv.userid
         WHERE vv.videoplayerid = :videoplayerid
      ORDER BY u.lastname ASC, u.firstname ASC";
$params = ['videoplayerid' => $videoplayer->id];
$records = $DB->get_records_sql($sql, $params);

if (!$records) {
    echo $OUTPUT->notification(get_string('noprogressrecords', 'mod_videoplayer'), 'info');
} else {
    foreach ($records as $record) {
        $user = (object) $record;
        $profileurl = new moodle_url('/user/view.php', ['id' => $record->id, 'course' => $course->id]);
        $completed = !empty($record->completed)
            ? html_writer::span(get_string('yes'), 'badge badge-success')
            : html_writer::span(get_string('no'), 'badge badge-secondary');

        $table->add_data([
            html_writer::link($profileurl, fullname($user)),
            s($record->email),
            format_float((float) $record->progress, 2) . 's',
            format_float((float) $record->completionpercentage, 2) . '%',
            $completed,
            userdate($record->timemodified),
        ]);
    }
    $table->finish_output();
}

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/videoplayer/view.php', ['id' => $cm->id]), get_string('backtoresource', 'mod_videoplayer'), ['class' => 'btn btn-secondary mt-3']),
    'mod-videoplayer-report-actions'
);

echo $OUTPUT->footer();
