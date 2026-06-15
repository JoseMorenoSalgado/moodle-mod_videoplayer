<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

use mod_videoplayer\local\drive;

function mod_videoplayer_send_file(string $path, string $filename, string $contenttype): void {
    $size = filesize($path);
    $range = '';
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m)) {
        $range = $_SERVER['HTTP_RANGE'];
    }
    $start = 0;
    $end = $size - 1;
    $code = 200;
    if ($range !== '') {
        $parts = explode('-', substr($range, 6), 2);
        if ($parts[0] !== '') {
            $start = max(0, (int)$parts[0]);
        }
        if ($parts[1] !== '') {
            $end = min($end, (int)$parts[1]);
        }
        if ($start <= $end) {
            $code = 206;
        }
    }
    $length = $end - $start + 1;
    http_response_code($code);
    header('Content-Type: ' . $contenttype);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=900, no-transform');
    header('Pragma: private');
    header('Content-Length: ' . $length);
    if ($code === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    $fp = fopen($path, 'rb');
    if ($fp === false) {
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }
    fseek($fp, $start);
    $left = $length;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(131072, $left));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    die;
}

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
$type = empty($videoplayer->type) || $videoplayer->type === 'auto' ? drive::detect_type($videoplayer->videourl) : clean_param($videoplayer->type, PARAM_ALPHANUMEXT);
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

\core\session\manager::write_close();
@set_time_limit(0);
while (ob_get_level()) {
    ob_end_clean();
}

$range = optional_param('range', '', PARAM_RAW);
if (empty($range) && !empty($_SERVER['HTTP_RANGE'])) {
    $range = clean_param($_SERVER['HTTP_RANGE'], PARAM_RAW);
}

$pdfcache = drive::is_pdf_type($type) && get_config('mod_videoplayer', 'pdfcacheenabled');
$cachettl = (int)get_config('mod_videoplayer', 'pdfcachettl');
if ($cachettl <= 0) {
    $cachettl = 86400;
}
$cachefile = '';
if ($pdfcache) {
    $cachedir = $CFG->localcachedir . '/mod_videoplayer/pdf';
    if (!is_dir($cachedir)) {
        make_writable_directory($cachedir);
    }
    $cachekey = sha1($fileid . ':' . $type);
    $cachefile = $cachedir . '/' . $cachekey . '.pdf';
    if (is_readable($cachefile) && filemtime($cachefile) + $cachettl > time()) {
        mod_videoplayer_send_file($cachefile, $filename, $contenttype);
    }
}

$requestheaders = [];
if (!empty($range) && preg_match('/^bytes=\d*-\d*$/', $range)) {
    $requestheaders[] = 'Range: ' . $range;
}
$headerbuffer = [];
$contentlength = null;
$remotecontenttype = null;
$headercallback = function($curl, string $header) use (&$headerbuffer, &$contentlength, &$remotecontenttype): int {
    $length = strlen($header);
    $trimmed = trim($header);
    if ($trimmed === '') {
        return $length;
    }
    if (preg_match('/^Content-Length:\s*(\d+)/i', $trimmed, $m)) {
        $contentlength = (int)$m[1];
    } else if (preg_match('/^Content-Type:\s*(.+)$/i', $trimmed, $m)) {
        $remotecontenttype = trim($m[1]);
    } else if (preg_match('/^Content-Range:\s*(.+)$/i', $trimmed, $m)) {
        $headerbuffer['Content-Range'] = trim($m[1]);
    }
    return $length;
};
$head = curl_init($url);
curl_setopt_array($head, [CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $requestheaders, CURLOPT_HEADERFUNCTION => $headercallback]);
curl_exec($head);
$headcode = (int)curl_getinfo($head, CURLINFO_HTTP_CODE);
curl_close($head);
if ($headcode >= 400) {
    throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
}
$httpcode = (!empty($range) || $headcode === 206) ? 206 : 200;
http_response_code($httpcode);
header('Content-Type: ' . ($remotecontenttype ?: $contenttype));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=900, no-transform');
header('Pragma: private');
header('Vary: Range');
if ($contentlength !== null) {
    header('Content-Length: ' . $contentlength);
}
if (!empty($headerbuffer['Content-Range'])) {
    header('Content-Range: ' . $headerbuffer['Content-Range']);
}

$cachehandle = null;
$tmpcachefile = '';
if ($pdfcache && empty($range) && $cachefile !== '') {
    $tmpcachefile = $cachefile . '.tmp.' . getmypid();
    $cachehandle = fopen($tmpcachefile, 'wb');
}
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 0, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_BUFFERSIZE => 131072, CURLOPT_HTTPHEADER => $requestheaders, CURLOPT_WRITEFUNCTION => function($curl, string $data) use (&$cachehandle): int {
    if ($cachehandle) {
        fwrite($cachehandle, $data);
    }
    echo $data;
    flush();
    return strlen($data);
}]);
$result = curl_exec($ch);
$curlerror = curl_error($ch);
$curlcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($cachehandle) {
    fclose($cachehandle);
    if ($result !== false && $curlcode < 400 && is_readable($tmpcachefile)) {
        rename($tmpcachefile, $cachefile);
    } else if (file_exists($tmpcachefile)) {
        unlink($tmpcachefile);
    }
}
if ($result === false || $curlcode >= 400) {
    debugging('Drive Resource protected stream failed: ' . $curlerror, DEBUG_DEVELOPER);
}
die;
