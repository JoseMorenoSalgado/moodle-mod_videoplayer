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

$fileid = drive::extract_file_id($videoplayer->videourl ?? '');
if (!$fileid) {
    throw new moodle_exception('invaliddriveurl', 'mod_videoplayer');
}

$type = empty($videoplayer->type) || $videoplayer->type === 'auto'
    ? drive::detect_type($videoplayer->videourl)
    : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);

// PDF resources always require this Moodle proxy because Drive Resource must not
// use the Google Drive PDF viewer. Other resource types still respect the admin
// protected mode setting while they are being migrated to native viewers.
if (!drive::is_pdf_type($type) && !get_config('mod_videoplayer', 'protectedmode')) {
    throw new moodle_exception('protectedmodedisabled', 'mod_videoplayer');
}

$url = drive::protected_content_url($videoplayer->videourl, $fileid, $type);
if (!$url) {
    throw new moodle_exception('unsupportedprotectedresource', 'mod_videoplayer');
}

$contenttype = drive::default_mimetype($type);
$filename = clean_filename(format_string($videoplayer->name, true, ['context' => $context]));
if ($filename === '') {
    $filename = 'drive-resource';
}

if (drive::is_pdf_type($type) && !preg_match('/\.pdf$/i', $filename)) {
    $filename .= '.pdf';
}

@set_time_limit(0);
while (ob_get_level()) {
    ob_end_clean();
}

$range = optional_param('range', '', PARAM_RAW);
if (empty($range) && !empty($_SERVER['HTTP_RANGE'])) {
    $range = clean_param($_SERVER['HTTP_RANGE'], PARAM_RAW);
}

$requestheaders = [];
if (!empty($range) && preg_match('/^bytes=\d*-\d*$/', $range)) {
    $requestheaders[] = 'Range: ' . $range;
}

$headerbuffer = [];
$contentlength = null;
$remotecontenttype = null;

$head = curl_init($url);
curl_setopt_array($head, [
    CURLOPT_NOBODY => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => $requestheaders,
    CURLOPT_HEADERFUNCTION => function($curl, string $header) use (&$headerbuffer, &$contentlength, &$remotecontenttype): int {
        $length = strlen($header);
        $trimmed = trim($header);

        if ($trimmed === '') {
            return $length;
        }

        if (preg_match('/^Content-Length:\s*(\d+)/i', $trimmed, $matches)) {
            $contentlength = (int) $matches[1];
        } else if (preg_match('/^Content-Type:\s*(.+)$/i', $trimmed, $matches)) {
            $remotecontenttype = trim($matches[1]);
        } else if (preg_match('/^Content-Range:\s*(.+)$/i', $trimmed, $matches)) {
            $headerbuffer['Content-Range'] = trim($matches[1]);
        }

        return $length;
    },
]);

curl_exec($head);
$headcode = (int) curl_getinfo($head, CURLINFO_HTTP_CODE);
curl_close($head);

if ($headcode >= 400) {
    throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
}

$httpcode = (!empty($range) || $headcode === 206) ? 206 : 200;

if (!headers_sent()) {
    http_response_code($httpcode);
    header('Content-Type: ' . ($remotecontenttype ?: $contenttype));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=300, no-transform');
    header('Pragma: private');

    if ($contentlength !== null) {
        header('Content-Length: ' . $contentlength);
    }

    if (!empty($headerbuffer['Content-Range'])) {
        header('Content-Range: ' . $headerbuffer['Content-Range']);
    }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => $requestheaders,
    CURLOPT_WRITEFUNCTION => function($curl, string $data): int {
        echo $data;
        flush();
        return strlen($data);
    },
]);

$result = curl_exec($ch);
$curlerror = curl_error($ch);
$curlcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result === false || $curlcode >= 400) {
    debugging('Drive Resource protected stream failed: ' . $curlerror, DEBUG_DEVELOPER);
}

die;
