<?php
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

use mod_videoplayer\local\drive;

/** Private browser cache lifetime for authorized protected streams. */
const MOD_VIDEOPLAYER_PRIVATE_CACHE_SECONDS = 300;

/**
 * Send private cache headers that preserve byte-range semantics.
 *
 * Protected resources may be cached only by the authenticated browser. The
 * `no-transform` directive is required because dynamic gzip/brotli would break
 * byte offsets used by PDF.js and media players.
 *
 * @param string $etag Stable entity tag without quotes.
 * @param int $lastmodified Unix timestamp.
 * @return void
 */
function mod_videoplayer_send_private_cache_headers(string $etag = '', int $lastmodified = 0): void {
    header('Cache-Control: private, max-age=' . MOD_VIDEOPLAYER_PRIVATE_CACHE_SECONDS . ', must-revalidate, no-transform');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + MOD_VIDEOPLAYER_PRIVATE_CACHE_SECONDS) . ' GMT');
    if ($etag !== '') {
        header('ETag: "' . preg_replace('/[^a-zA-Z0-9_\-.]/', '', $etag) . '"');
    }
    if ($lastmodified > 0) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
    }
}

/**
 * Send a safe cache diagnostic header.
 *
 * @param string $status Cache status.
 * @return void
 */
function mod_videoplayer_send_cache_status(string $status): void {
    header('X-Drive-Resource-Cache: ' . preg_replace('/[^A-Z_-]/', '', strtoupper($status)));
}

/**
 * Resolve the physical Moodle File API path for a stored file when available.
 *
 * @param stored_file $file Stored file.
 * @return string|null Absolute path or null when not readable.
 */
function mod_videoplayer_get_stored_file_path(stored_file $file): ?string {
    global $CFG;

    $hash = $file->get_contenthash();
    if ($hash === '' || strlen($hash) < 4) {
        return null;
    }

    $path = $CFG->dataroot . '/filedir/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
    return is_readable($path) ? $path : null;
}

/**
 * Send a local file with safe byte-range support.
 *
 * @param string $path Absolute local path.
 * @param string $filename Safe download filename.
 * @param string $contenttype MIME type.
 * @param string $etag Optional stable entity tag.
 * @param int $lastmodified Optional unix timestamp.
 * @param string $cachestatus Cache diagnostic status.
 * @return void
 */
function mod_videoplayer_send_file(
    string $path,
    string $filename,
    string $contenttype,
    string $etag = '',
    int $lastmodified = 0,
    string $cachestatus = 'LOCAL'
): void {
    if (!is_readable($path)) {
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }

    $size = filesize($path);
    if ($size === false || $size <= 0) {
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }

    $lastmodified = $lastmodified > 0 ? $lastmodified : (filemtime($path) ?: time());
    $etag = $etag !== '' ? $etag : sha1($size . ':' . $lastmodified);

    $range = '';
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'])) {
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
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            header('Cache-Control: no-store, no-cache, must-revalidate, no-transform');
            mod_videoplayer_send_cache_status('RANGE_INVALID');
            die;
        }
        $code = 206;
    }

    $length = $end - $start + 1;

    http_response_code($code);
    header('Content-Type: ' . $contenttype);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Accept-Ranges: bytes');
    mod_videoplayer_send_private_cache_headers($etag, $lastmodified);
    mod_videoplayer_send_cache_status($cachestatus);
    header('Content-Length: ' . $length);
    header('Vary: Range');
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
        $chunk = fread($fp, min(262144, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fp);
    die;
}

/**
 * Send a stored Moodle PDF through the Drive Resource streamer.
 *
 * @param stored_file $file Stored Moodle file.
 * @param string $filename Safe filename.
 * @return void
 */
function mod_videoplayer_send_stored_pdf(stored_file $file, string $filename): void {
    $path = mod_videoplayer_get_stored_file_path($file);
    if ($path === null) {
        $tmpdir = make_request_directory();
        $path = $tmpdir . '/' . sha1($file->get_contenthash() . ':' . $file->get_timemodified()) . '.pdf';
        $file->copy_content_to($path);
    }

    $fh = fopen($path, 'rb');
    $header = $fh === false ? '' : fread($fh, 1024);
    if ($fh !== false) {
        fclose($fh);
    }
    if (strpos($header, '%PDF-') === false) {
        debugging('Drive Resource local PDF did not contain a PDF header in the first 1024 bytes.', DEBUG_DEVELOPER);
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }

    mod_videoplayer_send_file($path, $filename, 'application/pdf', $file->get_contenthash(), (int)$file->get_timemodified(), 'LOCAL');
}

/**
 * Relay only safe proxy response headers from the final upstream response.
 *
 * @param array $headers Captured upstream headers.
 * @param string $fallbacktype Fallback MIME type.
 * @param string $filename Safe filename.
 * @param string $cachestatus Cache diagnostic status.
 * @return void
 */
function mod_videoplayer_send_proxy_headers(array $headers, string $fallbacktype, string $filename, string $cachestatus = 'BYPASS'): void {
    $ispartial = !empty($headers['content-range']) || ((int)($headers['status'] ?? 0) === 206);
    http_response_code($ispartial ? 206 : 200);
    header('Content-Type: ' . ($headers['content-type'] ?? $fallbacktype));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Accept-Ranges: bytes');
    mod_videoplayer_send_private_cache_headers();
    mod_videoplayer_send_cache_status($cachestatus);
    header('Vary: Range');

    if (!empty($headers['content-length'])) {
        header('Content-Length: ' . $headers['content-length']);
    }
    if (!empty($headers['content-range'])) {
        header('Content-Range: ' . $headers['content-range']);
    }
}

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

if (($videoplayer->source ?? 'googledrive') === 'localpdf') {
    $file = videoplayer_get_localpdf_file($context);
    if (!$file) {
        throw new moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
    }
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    \core\session\manager::write_close();
    @set_time_limit(0);
    while (ob_get_level()) {
        ob_end_clean();
    }

    mod_videoplayer_send_stored_pdf($file, $filename);
}

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
if (drive::is_pdf_type($type) && !preg_match('/\.pdf$/i', $filename)) {
    $filename .= '.pdf';
} else if ($type === 'video' && !preg_match('/\.(mp4|webm|m4v|mov)$/i', $filename)) {
    $filename .= '.mp4';
}

\core\session\manager::write_close();
@set_time_limit(0);
while (ob_get_level()) {
    ob_end_clean();
}

$range = '';
$curlrange = '';
if (!empty($_SERVER['HTTP_RANGE'])) {
    $candidate = clean_param($_SERVER['HTTP_RANGE'], PARAM_RAW);
    if (preg_match('/^bytes=\d*-\d*$/', $candidate)) {
        $range = $candidate;
        $curlrange = substr($candidate, 6);
    }
}

$pdfcache = drive::is_pdf_type($type) && get_config('mod_videoplayer', 'pdfcacheenabled');
$cachettl = (int)get_config('mod_videoplayer', 'pdfcachettl');
if ($cachettl <= 0) {
    $cachettl = 86400;
}
$cachefile = '';
$cachestatus = $pdfcache ? 'MISS' : 'BYPASS';
if ($pdfcache) {
    $cachedir = $CFG->localcachedir . '/mod_videoplayer/pdf';
    if (!is_dir($cachedir)) {
        make_writable_directory($cachedir);
    }
    $cachekey = sha1($fileid . ':' . $type);
    $cachefile = $cachedir . '/' . $cachekey . '.pdf';
    if (is_readable($cachefile) && filemtime($cachefile) + $cachettl > time()) {
        mod_videoplayer_send_file($cachefile, $filename, $contenttype, $cachekey, filemtime($cachefile) ?: time(), 'HIT');
    }
}

$requestheaders = [
    'Accept-Encoding: identity',
];
if ($range !== '') {
    $requestheaders[] = 'Range: ' . $range;
}

$responseheaders = [];
$headerssent = false;
$headercallback = function($curl, string $header) use (&$responseheaders): int {
    $length = strlen($header);
    $trimmed = trim($header);
    if ($trimmed === '') {
        return $length;
    }
    if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $trimmed, $m)) {
        $responseheaders = ['status' => (int)$m[1]];
        return $length;
    }
    if (preg_match('/^Content-Length:\s*(\d+)/i', $trimmed, $m)) {
        $responseheaders['content-length'] = (int)$m[1];
    } else if (preg_match('/^Content-Type:\s*(.+)$/i', $trimmed, $m)) {
        $responseheaders['content-type'] = trim($m[1]);
    } else if (preg_match('/^Content-Range:\s*(.+)$/i', $trimmed, $m)) {
        $responseheaders['content-range'] = trim($m[1]);
    } else if (preg_match('/^Accept-Ranges:\s*(.+)$/i', $trimmed, $m)) {
        $responseheaders['accept-ranges'] = trim($m[1]);
    }
    return $length;
};

$cachehandle = null;
$tmpcachefile = '';
if ($pdfcache && $range === '' && $cachefile !== '') {
    $tmpcachefile = $cachefile . '.tmp.' . getmypid();
    $cachehandle = fopen($tmpcachefile, 'wb');
}

$ch = curl_init($url);
$options = [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_BUFFERSIZE => 262144,
    CURLOPT_HTTPHEADER => $requestheaders,
    CURLOPT_HEADERFUNCTION => $headercallback,
    CURLOPT_WRITEFUNCTION => function($curl, string $data) use (&$cachehandle, &$headerssent, &$responseheaders, $contenttype, $filename, $cachestatus): int {
        if (!$headerssent) {
            mod_videoplayer_send_proxy_headers($responseheaders, $contenttype, $filename, $cachestatus);
            $headerssent = true;
        }
        if ($cachehandle) {
            fwrite($cachehandle, $data);
        }
        echo $data;
        flush();
        return strlen($data);
    },
];
if ($curlrange !== '') {
    $options[CURLOPT_RANGE] = $curlrange;
}
curl_setopt_array($ch, $options);
$result = curl_exec($ch);
$curlerror = curl_error($ch);
$curlcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$headerssent && $curlcode < 400) {
    mod_videoplayer_send_proxy_headers($responseheaders, $contenttype, $filename, $cachestatus);
}

if ($cachehandle) {
    fclose($cachehandle);
    if ($result && $curlcode >= 200 && $curlcode < 300 && is_file($tmpcachefile) && filesize($tmpcachefile) > 0) {
        rename($tmpcachefile, $cachefile);
    } else if (is_file($tmpcachefile)) {
        unlink($tmpcachefile);
    }
}

if ($result === false || $curlcode >= 400) {
    if (!$headerssent) {
        http_response_code(502);
    }
    debugging('Drive Resource proxy failed: HTTP ' . $curlcode . ' ' . $curlerror, DEBUG_DEVELOPER);
}

die;
