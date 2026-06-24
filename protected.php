<?php
// This file is part of Moodle - http://moodle.org/

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

use mod_videoplayer\local\drive;
use mod_videoplayer\local\protected_stream;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('videoplayer', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videoplayer = $DB->get_record('videoplayer', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videoplayer:view', $context);

$filename = clean_filename(format_string($videoplayer->name, true, ['context' => $context]));
if ($filename === '') {
    $filename = 'drive-resource';
}

\core\session\manager::write_close();
@set_time_limit(0);
while (ob_get_level()) {
    ob_end_clean();
}

if (($videoplayer->source ?? 'googledrive') === 'localpdf') {
    $file = videoplayer_get_localpdf_file($context);
    if (!$file) {
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    protected_stream::send_stored_pdf($file, $filename);
}

$fileid = drive::extract_file_id($videoplayer->videourl ?? '');
if (!$fileid) {
    throw new moodle_exception('invaliddriveurl', 'mod_videoplayer');
}

$type = empty($videoplayer->type) || $videoplayer->type === 'auto'
    ? drive::detect_type($videoplayer->videourl)
    : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);

if (!drive::is_pdf_type($type) && !get_config('mod_videoplayer', 'protectedmode')) {
    throw new moodle_exception('protectedmodedisabled', 'mod_videoplayer');
}

$url = drive::protected_content_url($videoplayer->videourl, $fileid, $type);
if (!$url) {
    throw new moodle_exception('unsupportedprotectedresource', 'mod_videoplayer');
}

$contenttype = drive::default_mimetype($type);
if (drive::is_pdf_type($type) && !preg_match('/\.pdf$/i', $filename)) {
    $filename .= '.pdf';
} else if ($type === 'video' && !preg_match('/\.(mp4|webm|m4v|mov)$/i', $filename)) {
    $filename .= '.mp4';
}

$cachestatus = 'BYPASS';
$pdfcache = drive::is_pdf_type($type) && (string)get_config('mod_videoplayer', 'pdfcacheenabled') !== '0';
if ($pdfcache) {
    $cachekey = protected_stream::cache_key($fileid, $type);
    $cachefile = protected_stream::cache_file_for($fileid, $type);

    if (protected_stream::is_fresh_pdf_cache($cachefile)) {
        protected_stream::send_file(
            $cachefile,
            $filename,
            $contenttype,
            $cachekey,
            filemtime($cachefile) ?: time(),
            'HIT'
        );
    }

    if (protected_stream::warm_drive_pdf_cache($url, $cachefile)) {
        protected_stream::send_file(
            $cachefile,
            $filename,
            $contenttype,
            $cachekey,
            filemtime($cachefile) ?: time(),
            'WARMED'
        );
    }

    $cachestatus = 'WARM_FAILED';
}

protected_stream::proxy_upstream($url, $filename, $contenttype, $cachestatus);
