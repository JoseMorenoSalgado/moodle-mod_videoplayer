<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\local;

/**
 * Protected streaming and PDF cache service for Drive Resource.
 *
 * This service centralises byte-range streaming, Google Drive PDF cache warming,
 * cache cleanup and safe response headers. Keeping this logic out of
 * protected.php makes the public endpoint smaller and easier to review.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class protected_stream {

    /** @var int Private browser cache lifetime for authorised protected streams. */
    private const PRIVATE_CACHE_SECONDS = 300;

    /** @var int Stream chunk size in bytes. */
    private const STREAM_CHUNK_SIZE = 262144;

    /** @var int Default PDF cache lifetime: 30 days. */
    private const DEFAULT_PDF_CACHE_TTL = 2592000;

    /** @var int Temporary cache file stale lifetime. */
    private const STALE_TMP_TTL = 3600;

    /**
     * Return the configured PDF cache TTL.
     *
     * @return int Cache TTL in seconds.
     */
    public static function pdf_cache_ttl(): int {
        $ttl = (int)get_config('mod_videoplayer', 'pdfcachettl');
        return $ttl > 0 ? $ttl : self::DEFAULT_PDF_CACHE_TTL;
    }

    /**
     * Return the plugin PDF cache directory, creating it when possible.
     *
     * @return string Absolute cache directory.
     */
    public static function pdf_cache_dir(): string {
        global $CFG;

        $cachedir = $CFG->localcachedir . '/mod_videoplayer/pdf';
        if (!is_dir($cachedir)) {
            make_writable_directory($cachedir);
        }

        return $cachedir;
    }

    /**
     * Build the stable cache key for a protected Drive PDF.
     *
     * Keep this compatible with previous cache keys so existing warmed files
     * remain reusable after the refactor.
     *
     * @param string $fileid Google Drive file id.
     * @param string $type Resource type.
     * @return string Cache key.
     */
    public static function cache_key(string $fileid, string $type): string {
        return sha1($fileid . ':' . $type);
    }

    /**
     * Build the final PDF cache file path.
     *
     * @param string $fileid Google Drive file id.
     * @param string $type Resource type.
     * @return string Absolute cache file path.
     */
    public static function cache_file_for(string $fileid, string $type): string {
        return self::pdf_cache_dir() . '/' . self::cache_key($fileid, $type) . '.pdf';
    }

    /**
     * Check if a cache file is fresh and valid.
     *
     * @param string $path Absolute cache path.
     * @param int|null $ttl Optional TTL override.
     * @return bool
     */
    public static function is_fresh_pdf_cache(string $path, ?int $ttl = null): bool {
        $ttl = $ttl ?? self::pdf_cache_ttl();
        return is_readable($path)
            && filemtime($path) + $ttl > time()
            && self::is_pdf_file($path);
    }

    /**
     * Resolve the physical Moodle File API path for a stored file when available.
     *
     * @param \stored_file $file Stored file.
     * @return string|null Absolute path or null when not readable.
     */
    public static function stored_file_path(\stored_file $file): ?string {
        global $CFG;

        $hash = $file->get_contenthash();
        if ($hash === '' || strlen($hash) < 4) {
            return null;
        }

        $path = $CFG->dataroot . '/filedir/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        return is_readable($path) ? $path : null;
    }

    /**
     * Check whether a local file starts like a PDF.
     *
     * @param string $path Absolute path.
     * @return bool
     */
    public static function is_pdf_file(string $path): bool {
        if (!is_readable($path) || filesize($path) <= 0) {
            return false;
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $header = fread($fh, 1024);
        fclose($fh);

        return is_string($header) && strpos($header, '%PDF-') !== false;
    }

    /**
     * Send a stored Moodle PDF through the protected streamer.
     *
     * @param \stored_file $file Stored Moodle file.
     * @param string $filename Safe filename.
     * @return void
     */
    public static function send_stored_pdf(\stored_file $file, string $filename): void {
        $path = self::stored_file_path($file);
        if ($path === null) {
            $tmpdir = make_request_directory();
            $path = $tmpdir . '/' . sha1($file->get_contenthash() . ':' . $file->get_timemodified()) . '.pdf';
            $file->copy_content_to($path);
        }

        if (!self::is_pdf_file($path)) {
            debugging('Drive Resource local PDF did not contain a PDF header in the first 1024 bytes.', DEBUG_DEVELOPER);
            throw new \moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
        }

        self::send_file($path, $filename, 'application/pdf', $file->get_contenthash(), (int)$file->get_timemodified(), 'LOCAL');
    }

    /**
     * Send a local file with byte-range support.
     *
     * @param string $path Absolute local path.
     * @param string $filename Safe filename.
     * @param string $contenttype MIME type.
     * @param string $etag Optional stable entity tag.
     * @param int $lastmodified Optional unix timestamp.
     * @param string $cachestatus Cache diagnostic status.
     * @return void
     */
    public static function send_file(
        string $path,
        string $filename,
        string $contenttype,
        string $etag = '',
        int $lastmodified = 0,
        string $cachestatus = 'LOCAL'
    ): void {
        if (!is_readable($path)) {
            throw new \moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            throw new \moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
        }

        $lastmodified = $lastmodified > 0 ? $lastmodified : (filemtime($path) ?: time());
        $etag = $etag !== '' ? $etag : sha1($size . ':' . $lastmodified);
        [$start, $end, $code] = self::resolve_range($size);
        $length = $end - $start + 1;

        http_response_code($code);
        header('Content-Type: ' . $contenttype);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Accept-Ranges: bytes');
        self::send_private_cache_headers($etag, $lastmodified);
        self::send_cache_status($cachestatus);
        header('Content-Length: ' . $length);
        header('Vary: Range');
        if ($code === 206) {
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        self::stream_file_segment($path, $start, $length);
        die;
    }

    /**
     * Warm a Google Drive PDF into local cache.
     *
     * @param string $url Resolved upstream download URL.
     * @param string $cachefile Final cache file path.
     * @return bool Whether a valid PDF was cached.
     */
    public static function warm_drive_pdf_cache(string $url, string $cachefile): bool {
        $cachedir = dirname($cachefile);
        if (!is_dir($cachedir)) {
            make_writable_directory($cachedir);
        }
        if (!is_writable($cachedir)) {
            debugging('Drive Resource PDF cache warm failed: cache directory is not writable.', DEBUG_DEVELOPER);
            return false;
        }

        $lockfile = $cachefile . '.lock';
        $lockhandle = fopen($lockfile, 'c');
        if ($lockhandle === false) {
            debugging('Drive Resource PDF cache warm failed: lock file is not writable.', DEBUG_DEVELOPER);
            return false;
        }

        if (!flock($lockhandle, LOCK_EX)) {
            fclose($lockhandle);
            debugging('Drive Resource PDF cache warm failed: lock could not be acquired.', DEBUG_DEVELOPER);
            return false;
        }

        clearstatcache(true, $cachefile);
        if (self::is_fresh_pdf_cache($cachefile)) {
            flock($lockhandle, LOCK_UN);
            fclose($lockhandle);
            return true;
        }

        $tmpfile = $cachefile . '.tmp.' . getmypid();
        $cookiejar = $cachefile . '.cookies.' . getmypid();
        foreach ([$tmpfile, $cookiejar] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $download = self::download_to_file($url, $tmpfile, $cookiejar);
        $valid = $download['ok'] && self::is_pdf_file($tmpfile);

        if (!$valid && is_file($tmpfile)) {
            $confirmtoken = self::extract_drive_confirm_token($tmpfile);
            if ($confirmtoken !== null) {
                unlink($tmpfile);
                $download = self::download_to_file(self::add_drive_confirm_token($url, $confirmtoken), $tmpfile, $cookiejar);
                $valid = $download['ok'] && self::is_pdf_file($tmpfile);
            }
        }

        if ($valid) {
            rename($tmpfile, $cachefile);
        } else {
            if (is_file($tmpfile)) {
                unlink($tmpfile);
            }
            debugging(
                'Drive Resource PDF cache warm failed: HTTP ' . ($download['httpcode'] ?? 0) . ' ' .
                ($download['error'] ?? '') . ' content-type=' . ($download['contenttype'] ?? ''),
                DEBUG_DEVELOPER
            );
        }

        if (is_file($cookiejar)) {
            unlink($cookiejar);
        }

        flock($lockhandle, LOCK_UN);
        fclose($lockhandle);

        return $valid;
    }

    /**
     * Proxy an upstream protected resource with safe headers.
     *
     * @param string $url Upstream URL.
     * @param string $filename Safe filename.
     * @param string $contenttype Fallback MIME type.
     * @param string $cachestatus Cache diagnostic status.
     * @return void
     */
    public static function proxy_upstream(string $url, string $filename, string $contenttype, string $cachestatus = 'BYPASS'): void {
        $range = self::request_range_header();
        $requestheaders = ['Accept-Encoding: identity'];
        if ($range !== '') {
            $requestheaders[] = 'Range: ' . $range;
        }

        $responseheaders = [];
        $headerssent = false;
        $headercallback = static function($curl, string $header) use (&$responseheaders): int {
            $length = strlen($header);
            $trimmed = trim($header);
            if ($trimmed === '') {
                return $length;
            }
            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $trimmed, $matches)) {
                $responseheaders = ['status' => (int)$matches[1]];
                return $length;
            }
            if (preg_match('/^Content-Length:\s*(\d+)/i', $trimmed, $matches)) {
                $responseheaders['content-length'] = (int)$matches[1];
            } else if (preg_match('/^Content-Type:\s*(.+)$/i', $trimmed, $matches)) {
                $responseheaders['content-type'] = trim($matches[1]);
            } else if (preg_match('/^Content-Range:\s*(.+)$/i', $trimmed, $matches)) {
                $responseheaders['content-range'] = trim($matches[1]);
            }
            return $length;
        };

        $ch = curl_init($url);
        $options = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BUFFERSIZE => self::STREAM_CHUNK_SIZE,
            CURLOPT_HTTPHEADER => $requestheaders,
            CURLOPT_HEADERFUNCTION => $headercallback,
            CURLOPT_WRITEFUNCTION => static function($curl, string $data) use (&$headerssent, &$responseheaders, $contenttype, $filename, $cachestatus): int {
                if (!$headerssent) {
                    self::send_proxy_headers($responseheaders, $contenttype, $filename, $cachestatus);
                    $headerssent = true;
                }
                echo $data;
                flush();
                return strlen($data);
            },
        ];
        if ($range !== '') {
            $options[CURLOPT_RANGE] = substr($range, 6);
        }
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $curlerror = curl_error($ch);
        $curlcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$headerssent && $curlcode < 400) {
            self::send_proxy_headers($responseheaders, $contenttype, $filename, $cachestatus);
        }

        if ($result === false || $curlcode >= 400) {
            if (!$headerssent) {
                http_response_code(502);
            }
            debugging('Drive Resource proxy failed: HTTP ' . $curlcode . ' ' . $curlerror, DEBUG_DEVELOPER);
        }

        die;
    }

    /**
     * Remove expired PDF cache, temporary and cookie files.
     *
     * @return void
     */
    public static function cleanup_pdf_cache(): void {
        $cachedir = self::pdf_cache_dir();
        if (!is_dir($cachedir)) {
            return;
        }

        $ttl = self::pdf_cache_ttl();
        $now = time();
        $files = glob($cachedir . '/*');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $basename = basename($file);
            $isexpiredpdf = preg_match('/\.pdf$/', $basename) && filemtime($file) + $ttl < $now;
            $isstaletmp = strpos($basename, '.tmp.') !== false && filemtime($file) + self::STALE_TMP_TTL < $now;
            $isstalecookie = strpos($basename, '.cookies.') !== false && filemtime($file) + self::STALE_TMP_TTL < $now;

            if ($isexpiredpdf || $isstaletmp || $isstalecookie) {
                @unlink($file);
            }
        }
    }

    /**
     * Resolve the current request Range header.
     *
     * @return string Safe Range header or empty string.
     */
    private static function request_range_header(): string {
        if (empty($_SERVER['HTTP_RANGE'])) {
            return '';
        }

        $candidate = clean_param($_SERVER['HTTP_RANGE'], PARAM_RAW);
        return preg_match('/^bytes=\d*-\d*$/', $candidate) ? $candidate : '';
    }

    /**
     * Resolve byte-range start, end and HTTP code.
     *
     * @param int $size File size.
     * @return array{0:int,1:int,2:int}
     */
    private static function resolve_range(int $size): array {
        $range = self::request_range_header();
        $start = 0;
        $end = $size - 1;
        $code = 200;

        if ($range === '') {
            return [$start, $end, $code];
        }

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
            self::send_cache_status('RANGE_INVALID');
            die;
        }

        return [$start, $end, 206];
    }

    /**
     * Stream part of a file without loading it into memory.
     *
     * @param string $path Absolute file path.
     * @param int $start Start byte.
     * @param int $length Number of bytes to stream.
     * @return void
     */
    private static function stream_file_segment(string $path, int $start, int $length): void {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new \moodle_exception('protectedresourceunavailable', 'mod_videoplayer');
        }

        fseek($fp, $start);
        $left = $length;
        while ($left > 0 && !feof($fp)) {
            $chunk = fread($fp, min(self::STREAM_CHUNK_SIZE, $left));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $left -= strlen($chunk);
            flush();
        }
        fclose($fp);
    }

    /**
     * Send private cache headers.
     *
     * @param string $etag Stable entity tag without quotes.
     * @param int $lastmodified Unix timestamp.
     * @return void
     */
    private static function send_private_cache_headers(string $etag = '', int $lastmodified = 0): void {
        header('Cache-Control: private, max-age=' . self::PRIVATE_CACHE_SECONDS . ', must-revalidate, no-transform');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::PRIVATE_CACHE_SECONDS) . ' GMT');
        if ($etag !== '') {
            header('ETag: "' . preg_replace('/[^a-zA-Z0-9_\-.]/', '', $etag) . '"');
        }
        if ($lastmodified > 0) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
        }
    }

    /**
     * Send cache diagnostic header.
     *
     * @param string $status Cache status.
     * @return void
     */
    private static function send_cache_status(string $status): void {
        header('X-Drive-Resource-Cache: ' . preg_replace('/[^A-Z_-]/', '', strtoupper($status)));
    }

    /**
     * Send safe upstream proxy headers.
     *
     * @param array $headers Captured upstream headers.
     * @param string $fallbacktype Fallback MIME type.
     * @param string $filename Safe filename.
     * @param string $cachestatus Cache diagnostic status.
     * @return void
     */
    private static function send_proxy_headers(array $headers, string $fallbacktype, string $filename, string $cachestatus): void {
        $ispartial = !empty($headers['content-range']) || ((int)($headers['status'] ?? 0) === 206);
        http_response_code($ispartial ? 206 : 200);
        header('Content-Type: ' . ($headers['content-type'] ?? $fallbacktype));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Accept-Ranges: bytes');
        self::send_private_cache_headers();
        self::send_cache_status($cachestatus);
        header('Vary: Range');

        if (!empty($headers['content-length'])) {
            header('Content-Length: ' . $headers['content-length']);
        }
        if (!empty($headers['content-range'])) {
            header('Content-Range: ' . $headers['content-range']);
        }
    }

    /**
     * Download an upstream URL to a file using a cookie jar.
     *
     * @param string $url Download URL.
     * @param string $targetpath Target file path.
     * @param string $cookiejar Cookie jar path.
     * @return array Download result.
     */
    private static function download_to_file(string $url, string $targetpath, string $cookiejar): array {
        $handle = fopen($targetpath, 'wb');
        if ($handle === false) {
            return ['ok' => false, 'httpcode' => 0, 'error' => 'target_not_writable', 'contenttype' => ''];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BUFFERSIZE => self::STREAM_CHUNK_SIZE,
            CURLOPT_HTTPHEADER => ['Accept-Encoding: identity'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 DriveResourceMoodleProxy/1.0',
            CURLOPT_COOKIEJAR => $cookiejar,
            CURLOPT_COOKIEFILE => $cookiejar,
            CURLOPT_FILE => $handle,
        ]);

        $result = curl_exec($ch);
        $curlerror = curl_error($ch);
        $curlcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contenttype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        fclose($handle);

        return [
            'ok' => $result !== false && $curlcode >= 200 && $curlcode < 300,
            'httpcode' => $curlcode,
            'error' => $curlerror,
            'contenttype' => $contenttype,
        ];
    }

    /**
     * Extract the Google Drive download confirmation token from a warning page.
     *
     * @param string $path HTML response path.
     * @return string|null Confirmation token.
     */
    private static function extract_drive_confirm_token(string $path): ?string {
        if (!is_readable($path)) {
            return null;
        }

        $html = file_get_contents($path, false, null, 0, 1048576);
        if (!is_string($html) || $html === '') {
            return null;
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $patterns = [
            '/[?&]confirm=([0-9A-Za-z_\-]+)/',
            '/name=["\']confirm["\'][^>]*value=["\']([^"\']+)["\']/i',
            '/confirm=([0-9A-Za-z_\-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return clean_param($matches[1], PARAM_ALPHANUMEXT);
            }
        }

        return null;
    }

    /**
     * Append a Google Drive confirmation token to a download URL.
     *
     * @param string $url Download URL.
     * @param string $token Confirmation token.
     * @return string URL with confirmation token.
     */
    private static function add_drive_confirm_token(string $url, string $token): string {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . 'confirm=' . rawurlencode($token);
    }
}
