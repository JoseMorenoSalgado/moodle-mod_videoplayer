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
 * Index of all videoplayer instances in a course
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/mod/videoplayer/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);

$PAGE->set_url('/mod/videoplayer/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'videoplayer'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'videoplayer'), 2);

// Trigger evento estándar.
$event = \mod_videoplayer\event\course_module_instance_list_viewed::create([
    'context' => $context
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

// Obtener instancias del módulo.
if (!$instances = get_all_instances_in_course('videoplayer', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'videoplayer')), "../../course/view.php?id=$course->id");
    exit;
}

$table = new html_table();
$table->head = [get_string('name'), get_string('intro', 'videoplayer')];
$table->align = ['left', 'left'];

foreach ($instances as $instance) {
    $link = html_writer::link(new moodle_url('/mod/videoplayer/view.php', ['id' => $instance->coursemodule]), format_string($instance->name));
    $intro = format_module_intro('videoplayer', $instance, $instance->coursemodule);
    $table->data[] = [$link, $intro];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
