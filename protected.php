<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Protected resource proxy for mod_videoplayer.
 *
 * This endpoint serves supported Google Drive resources through Moodle after
 * validating course access and module capabilities. It reduces direct exposure
 * of the original Google Drive URL in the student interface.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

use mod_videoplayer\local\drive;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/videoplayer:view', $context);

if (!get_config('mod_videoplayer', 'protectedmode')) {
    throw new moodle_exception('protectedmodedisabled', 'mod_videoplayer');
}

$fileid = drive::extract_file_id($videoplayer->videourl ?? '');
if (!$fileid) {
    throw new moodle_exception('invaliddriveurl', 'mod_videoplayer');
}

$type = empty($videoplayer->type) || $videoplayer->type === 'auto'
    ? drive::detect_type($videoplayer->videourl)
    : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);

$url = drive::protected_content_url($videoplayer->videourl, $fileid, $type);
if (!$url) {
    throw new moodle_exception('unsupportedprotectedresource', 'mod_videoplayer');
}

$curl = new curl();
$curl->setopt([
    'CURLOPT_FOLLOWLOCATION' => true,
    'CURLOPT_MAXREDIRS' => 5,
    'CURLOPT_TIMEOUT' => 60,
    'CURLOPT_CONNECTTIMEOUT' => 20,
    'CURLOPT_SSL_VERIFYPEER' => true,
    'CURLOPT_SSL_VERIFYHOST' => 2,
]);

$content = $curl->get($url);
$info = $curl->get_info();

if ($content === false || empty($info['http_code']) || $info['http_code'] >= 400) {
    throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
}

$contenttype = !empty($info['content_type']) ? $info['content_type'] : drive::default_mimetype($type);
$filename = clean_filename(format_string($videoplayer->name, true, ['context' => $context]));
if ($filename === '') {
    $filename = 'drive-resource';
}

@header('Content-Type: ' . $contenttype);
@header('Content-Disposition: inline; filename="' . $filename . '"');
@header('X-Content-Type-Options: nosniff');
@header('Cache-Control: private, max-age=300, no-transform');
@header('Pragma: private');

echo $content;
die;
