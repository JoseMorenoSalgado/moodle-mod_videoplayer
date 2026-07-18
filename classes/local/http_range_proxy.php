<?php
// This file is part of Moodle - http://moodle.org/.
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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_videoplayer\local;


/**
 * Resilient HTTP byte-range proxy for protected Drive resources.
 *
 * Keeps Moodle as the only browser-visible endpoint, forwards one validated
 * byte range and streams the upstream body without buffering the full resource
 * in PHP memory.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http_range_proxy {
    /** @var int cURL streaming buffer size in bytes. */
    private const STREAM_BUFFER_SIZE = 262144;

    /** @var int Private browser cache lifetime for authorised resources. */
    private const PRIVATE_CACHE_SECONDS = 300;

    /** @var int Minimum bytes/second before a stalled upstream is considered too slow. */
    private const LOW_SPEED_LIMIT = 1024;

    /** @var int Seconds an upstream may remain below LOW_SPEED_LIMIT. */
    private const LOW_SPEED_TIME = 60;

    /** @var int Maximum accepted If-Range header length. */
    private const MAX_IF_RANGE_LENGTH = 512;

    /**
     * Stream an upstream resource through Moodle.
     *
     * @param string $url Server-side upstream URL.
     * @param string $filename Safe browser filename.
     * @param string $fallbacktype Fallback MIME type.
     * @param string $cachestatus Cache diagnostic status.
     * @return never
     */
    public static function proxy(
        string $url,
        string $filename,
        string $fallbacktype,
        string $cachestatus = 'BYPASS'
    ): never {
        $range = self::request_range_header();
        $requestheaders = [
            'Accept: */*',
            'Accept-Encoding: identity',
        ];

        $ifrange = self::request_if_range_header();
        if ($range !== '' && $ifrange !== '') {
            $requestheaders[] = 'If-Range: ' . $ifrange;
        }

        $responseheaders = [];
        $headerssent = false;
        $discardbody = false;
        $invalidcontent = false;
        $ishead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

        $headercallback = static function ($curl, string $header) use (&$responseheaders, &$discardbody): int {
            $length = strlen($header);
            $trimmed = trim($header);
            if ($trimmed === '') {
                return $length;
            }

            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $trimmed, $matches)) {
                $status = (int)$matches[1];
                $responseheaders = ['status' => $status];
                $discardbody = $status >= 400;
                return $length;
            }

            $patterns = [
                'content-length' => '/^Content-Length:\s*(\d+)/i',
                'content-type' => '/^Content-Type:\s*(.+)$/i',
                'content-range' => '/^Content-Range:\s*(.+)$/i',
                'content-disposition' => '/^Content-Disposition:\s*(.+)$/i',
                'accept-ranges' => '/^Accept-Ranges:\s*(.+)$/i',
                'etag' => '/^ETag:\s*(.+)$/i',
                'last-modified' => '/^Last-Modified:\s*(.+)$/i',
            ];

            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $trimmed, $matches)) {
                    $responseheaders[$key] = trim($matches[1]);
                    break;
                }
            }

            return $length;
        };

        $ch = curl_init($url);
        if ($ch === false) {
            debugging('Drive Resource proxy could not initialize cURL.', DEBUG_DEVELOPER);
            self::send_bad_gateway();
        }

        $options = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_LOW_SPEED_LIMIT => self::LOW_SPEED_LIMIT,
            CURLOPT_LOW_SPEED_TIME => self::LOW_SPEED_TIME,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BUFFERSIZE => self::STREAM_BUFFER_SIZE,
            CURLOPT_HTTPHEADER => $requestheaders,
            CURLOPT_HEADERFUNCTION => $headercallback,
            CURLOPT_USERAGENT => 'DriveResourceMoodleProxy/1.2',
            // Enable libcurl's in-memory cookie engine so cookies set during
            // Google redirects are retained for subsequent redirect hops.
            CURLOPT_COOKIEFILE => '',
            CURLOPT_WRITEFUNCTION => static function (
                $curl,
                string $data
            ) use (
                &$headerssent,
                &$responseheaders,
                &$discardbody,
                &$invalidcontent,
                $fallbacktype,
                $filename,
                $cachestatus
            ): int {
                if ($discardbody) {
                    return strlen($data);
                }

                $status = (int)($responseheaders['status'] ?? 0);
                if (!$headerssent && in_array($status, [200, 206], true)) {
                    if (self::is_unexpected_upstream_body($responseheaders, $fallbacktype, $data)) {
                        $invalidcontent = true;
                        $discardbody = true;
                        return strlen($data);
                    }

                    self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
                    $headerssent = true;
                }

                if ($headerssent) {
                    echo $data;
                    flush();
                }

                return strlen($data);
            },
        ];

        // CURLOPT_RANGE is the single source of the outgoing Range header.
        // Sending a manual Range header as well creates duplicate upstream
        // headers and can break Safari/iOS seek negotiation.
        if ($range !== '') {
            $options[CURLOPT_RANGE] = substr($range, 6);
        }
        if ($ishead) {
            $options[CURLOPT_NOBODY] = true;
        }

        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $curlerror = curl_error($ch);
        $curlcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($invalidcontent && !$headerssent) {
            debugging(
                'Drive Resource proxy rejected an unexpected upstream response body for protected media.',
                DEBUG_DEVELOPER
            );
            self::send_bad_gateway();
        }

        if ($ishead && $result !== false && in_array($curlcode, [200, 206], true)) {
            if (self::is_unexpected_content_type($responseheaders, $fallbacktype)) {
                debugging(
                    'Drive Resource proxy rejected an unexpected upstream Content-Type for a HEAD request.',
                    DEBUG_DEVELOPER
                );
                self::send_bad_gateway();
            }
            self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
            die;
        }

        if ($curlcode === 416 && !$headerssent) {
            self::send_range_not_satisfiable($responseheaders, $cachestatus);
        }

        if ($result === false || $curlcode >= 400 || !in_array($curlcode, [200, 206], true)) {
            debugging('Drive Resource proxy failed: HTTP ' . $curlcode . ' ' . $curlerror, DEBUG_DEVELOPER);
            if (!$headerssent) {
                self::send_bad_gateway();
            }
            die;
        }

        if (!$headerssent) {
            self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
        }

        die;
    }

    /**
     * Return a validated single Range request header.
     *
     * @return string
     */
    private static function request_range_header(): string {
        if (empty($_SERVER['HTTP_RANGE'])) {
            return '';
        }

        $candidate = trim((string)$_SERVER['HTTP_RANGE']);
        return preg_match('/^bytes=\d*-\d*$/', $candidate) ? $candidate : '';
    }

    /**
     * Return a safe If-Range validator when supplied by the browser.
     *
     * @return string
     */
    private static function request_if_range_header(): string {
        if (empty($_SERVER['HTTP_IF_RANGE'])) {
            return '';
        }

        $candidate = trim(str_replace(["\r", "\n"], '', (string)$_SERVER['HTTP_IF_RANGE']));
        if (strlen($candidate) > self::MAX_IF_RANGE_LENGTH) {
            return '';
        }

        return $candidate;
    }

    /**
     * Determine whether a successful upstream response is clearly not the
     * protected binary resource expected by the current viewer.
     *
     * Google Drive may return an HTML warning/login page with HTTP 200. Such a
     * body must never be forwarded to an HTML5 media element or PDF.js as if it
     * were valid protected content.
     *
     * @param array $headers Captured upstream response headers.
     * @param string $fallbacktype Expected plugin MIME type.
     * @param string $firstchunk First body chunk.
     * @return bool
     */
    private static function is_unexpected_upstream_body(
        array $headers,
        string $fallbacktype,
        string $firstchunk
    ): bool {
        if (self::is_unexpected_content_type($headers, $fallbacktype)) {
            return true;
        }

        if ($fallbacktype === 'application/octet-stream') {
            return false;
        }

        $probe = ltrim(substr($firstchunk, 0, 1024));
        return preg_match('/^(?:<!doctype\s+html|<html\b|<head\b|<body\b)/i', $probe) === 1;
    }

    /**
     * Determine whether an upstream MIME type is incompatible with a protected
     * media/PDF viewer response.
     *
     * @param array $headers Captured upstream response headers.
     * @param string $fallbacktype Expected plugin MIME type.
     * @return bool
     */
    private static function is_unexpected_content_type(array $headers, string $fallbacktype): bool {
        if ($fallbacktype === 'application/octet-stream') {
            return false;
        }

        $candidate = self::base_content_type((string)($headers['content-type'] ?? ''));
        return in_array($candidate, ['text/html', 'application/xhtml+xml', 'application/json'], true);
    }

    /**
     * Send browser-safe headers for a successful proxied response.
     *
     * @param array $headers Captured upstream headers.
     * @param string $fallbacktype Fallback MIME type.
     * @param string $filename Safe filename.
     * @param string $cachestatus Cache diagnostic status.
     * @return void
     */
    private static function send_response_headers(
        array $headers,
        string $fallbacktype,
        string $filename,
        string $cachestatus
    ): void {
        $status = (int)($headers['status'] ?? 200) === 206 ? 206 : 200;
        $contenttype = self::resolved_content_type($headers, $fallbacktype);
        $safefilename = str_replace(["\r", "\n", '"'], '', $filename);

        http_response_code($status);
        header('Content-Type: ' . $contenttype);
        header('Content-Disposition: inline; filename="' . $safefilename . '"; filename*=UTF-8\'\'' . rawurlencode($safefilename));
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: private, max-age=' . self::PRIVATE_CACHE_SECONDS . ', must-revalidate, no-transform');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::PRIVATE_CACHE_SECONDS) . ' GMT');
        header('Vary: Range, If-Range');
        header('X-Drive-Resource-Cache: ' . self::safe_cache_status($cachestatus));

        $acceptranges = strtolower((string)($headers['accept-ranges'] ?? ''));
        if ($status === 206 || $acceptranges === 'bytes') {
            header('Accept-Ranges: bytes');
        }

        if (!empty($headers['content-length'])) {
            header('Content-Length: ' . (int)$headers['content-length']);
        }
        if ($status === 206 && !empty($headers['content-range'])) {
            header('Content-Range: ' . self::safe_header_value((string)$headers['content-range']));
        }
        if (!empty($headers['etag'])) {
            header('ETag: ' . self::safe_header_value((string)$headers['etag']));
        }
        if (!empty($headers['last-modified'])) {
            header('Last-Modified: ' . self::safe_header_value((string)$headers['last-modified']));
        }
    }

    /**
     * Resolve the safest useful MIME type for the protected client response.
     *
     * When Google returns application/octet-stream, infer a more useful type
     * from its Content-Disposition filename when possible. Otherwise retain the
     * plugin fallback type.
     *
     * @param array $headers Captured upstream headers.
     * @param string $fallbacktype Fallback MIME type.
     * @return string
     */
    private static function resolved_content_type(array $headers, string $fallbacktype): string {
        $candidate = self::safe_content_type((string)($headers['content-type'] ?? ''));
        if ($candidate === '') {
            return $fallbacktype;
        }

        if (self::base_content_type($candidate) !== 'application/octet-stream') {
            return $candidate;
        }

        $inferred = self::mime_from_content_disposition((string)($headers['content-disposition'] ?? ''));
        return $inferred !== '' ? $inferred : $fallbacktype;
    }

    /**
     * Infer a supported MIME type from an upstream Content-Disposition header.
     *
     * @param string $contentdisposition Upstream Content-Disposition value.
     * @return string Empty string when no supported type can be inferred.
     */
    private static function mime_from_content_disposition(string $contentdisposition): string {
        $value = self::safe_header_value($contentdisposition);
        $filename = '';

        if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $value, $matches)) {
            $filename = rawurldecode(trim($matches[1], " \t\n\r\0\x0B\"'"));
        } else if (preg_match('/filename="([^"]+)"/i', $value, $matches)) {
            $filename = $matches[1];
        } else if (preg_match('/filename=([^;]+)/i', $value, $matches)) {
            $filename = trim($matches[1], " \t\n\r\0\x0B\"'");
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $types = [
            'mp4' => 'video/mp4',
            'm4v' => 'video/x-m4v',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];

        return $types[$extension] ?? '';
    }

    /**
     * Relay a valid 416 response without exposing upstream details.
     *
     * @param array $headers Captured upstream headers.
     * @param string $cachestatus Cache diagnostic status.
     * @return never
     */
    private static function send_range_not_satisfiable(array $headers, string $cachestatus): never {
        http_response_code(416);
        header('Cache-Control: no-store, no-cache, must-revalidate, no-transform');
        header('X-Content-Type-Options: nosniff');
        header('X-Drive-Resource-Cache: ' . self::safe_cache_status($cachestatus));
        if (!empty($headers['content-range'])) {
            header('Content-Range: ' . self::safe_header_value((string)$headers['content-range']));
        }
        die;
    }

    /**
     * Send a generic upstream failure response.
     *
     * @return never
     */
    private static function send_bad_gateway(): never {
        http_response_code(502);
        header('Cache-Control: no-store, no-cache, must-revalidate, no-transform');
        header('X-Content-Type-Options: nosniff');
        die;
    }

    /**
     * Return a safe MIME type, or an empty string when invalid.
     *
     * @param string $candidate Upstream Content-Type value.
     * @return string
     */
    private static function safe_content_type(string $candidate): string {
        $candidate = trim(str_replace(["\r", "\n"], '', $candidate));
        if ($candidate !== '' && preg_match('/^[a-zA-Z0-9.+-]+\/[a-zA-Z0-9.+-]+(?:\s*;\s*[a-zA-Z0-9._=-]+)*$/', $candidate)) {
            return $candidate;
        }

        return '';
    }

    /**
     * Return the lower-case base MIME type without parameters.
     *
     * @param string $contenttype Content-Type value.
     * @return string
     */
    private static function base_content_type(string $contenttype): string {
        $parts = explode(';', strtolower(trim($contenttype)), 2);
        return trim($parts[0]);
    }

    /**
     * Remove CR/LF from a relayed header value.
     *
     * @param string $value Header value.
     * @return string
     */
    private static function safe_header_value(string $value): string {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    /**
     * Normalize the diagnostic cache status.
     *
     * @param string $status Cache status.
     * @return string
     */
    private static function safe_cache_status(string $status): string {
        return preg_replace('/[^A-Z_-]/', '', strtoupper($status));
    }
}
