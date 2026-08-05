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
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

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
        if ($ifrange !== '') {
            $requestheaders[] = 'If-Range: ' . $ifrange;
        }

        $responseheaders = [];
        $headerssent = false;
        $discardbody = false;
        $invalidcontent = false;
        $ishead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

        $headercallback = static function (
            $curl,
            string $header
        ) use (
            &$responseheaders,
            &$discardbody,
            &$invalidcontent,
            $fallbacktype
        ): int {
            $length = strlen($header);
            $trimmed = trim($header);
            if ($trimmed === '') {
                $status = (int)($responseheaders['status'] ?? 0);
                if (in_array($status, [200, 206], true)) {
                    $candidate = (string)($responseheaders['content-type'] ?? '');
                    if (!self::is_compatible_content_type($candidate, $fallbacktype)) {
                        $invalidcontent = true;
                        $discardbody = true;
                    }
                }
                return $length;
            }

            if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $trimmed, $matches)) {
                $status = (int)$matches[1];
                $responseheaders = ['status' => $status];
                $discardbody = $status >= 400;
                $invalidcontent = false;
                return $length;
            }

            $patterns = [
                'content-length' => '/^Content-Length:\s*(\d+)/i',
                'content-type' => '/^Content-Type:\s*(.+)$/i',
                'content-range' => '/^Content-Range:\s*(.+)$/i',
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
            self::send_bad_gateway('CURL_INIT_FAILED');
        }

        $options = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BUFFERSIZE => self::STREAM_BUFFER_SIZE,
            CURLOPT_HTTPHEADER => $requestheaders,
            CURLOPT_HEADERFUNCTION => $headercallback,
            CURLOPT_USERAGENT => 'DriveResourceMoodleProxy/1.1',
            CURLOPT_WRITEFUNCTION => static function (
                $curl,
                string $data
            ) use (
                &$headerssent,
                &$responseheaders,
                &$discardbody,
                $fallbacktype,
                $filename,
                $cachestatus
            ): int {
                if ($discardbody) {
                    return strlen($data);
                }

                $status = (int)($responseheaders['status'] ?? 0);
                if (!$headerssent && in_array($status, [200, 206], true)) {
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
                'Drive Resource proxy rejected an incompatible upstream content type for ' . $fallbacktype . '.',
                DEBUG_DEVELOPER
            );
            self::send_bad_gateway('UPSTREAM_CONTENT_REJECTED');
        }

        if ($ishead && $result !== false && in_array($curlcode, [200, 206], true)) {
            self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
            die;
        }

        if ($curlcode === 416 && !$headerssent) {
            self::send_range_not_satisfiable($responseheaders, $cachestatus);
        }

        if ($result === false || $curlcode >= 400 || !in_array($curlcode, [200, 206], true)) {
            debugging('Drive Resource proxy failed: HTTP ' . $curlcode . ' ' . $curlerror, DEBUG_DEVELOPER);
            if (!$headerssent) {
                self::send_bad_gateway('UPSTREAM_REQUEST_FAILED');
            }
            die;
        }

        if (!$headerssent) {
            self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
        }

        die;
    }

    /**
     * Determine whether an upstream MIME type is compatible with the viewer.
     *
     * Empty and generic binary responses are accepted because Google may omit
     * a specific media MIME type. HTML, JSON and unrelated text responses are
     * rejected so login, permission and download-warning pages never reach a
     * video, audio, image or PDF element.
     *
     * @param string $candidate Upstream Content-Type value.
     * @param string $fallback Expected viewer MIME type.
     * @return bool
     */
    public static function is_compatible_content_type(string $candidate, string $fallback): bool {
        $candidate = strtolower(trim(explode(';', $candidate, 2)[0]));
        $fallback = strtolower(trim(explode(';', $fallback, 2)[0]));

        if ($candidate === '' || in_array($candidate, ['application/octet-stream', 'binary/octet-stream'], true)) {
            return true;
        }

        if ($candidate === 'text/html' || $candidate === 'application/json' || strpos($candidate, 'text/') === 0) {
            return false;
        }

        if (strpos($fallback, 'video/') === 0) {
            return strpos($candidate, 'video/') === 0;
        }
        if (strpos($fallback, 'audio/') === 0) {
            return strpos($candidate, 'audio/') === 0;
        }
        if (strpos($fallback, 'image/') === 0) {
            return strpos($candidate, 'image/') === 0;
        }
        if ($fallback === 'application/pdf') {
            return $candidate === 'application/pdf';
        }

        return true;
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

        return trim(str_replace(["\r", "\n"], '', (string)$_SERVER['HTTP_IF_RANGE']));
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
        $contenttype = self::safe_content_type((string)($headers['content-type'] ?? ''), $fallbacktype);
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
        header('X-Drive-Resource-Status: MEDIA');

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
        header('X-Drive-Resource-Status: RANGE_INVALID');
        if (!empty($headers['content-range'])) {
            header('Content-Range: ' . self::safe_header_value((string)$headers['content-range']));
        }
        die;
    }

    /**
     * Send a generic upstream failure response.
     *
     * @param string $status Safe diagnostic status.
     * @return never
     */
    private static function send_bad_gateway(string $status): never {
        http_response_code(502);
        header('Cache-Control: no-store, no-cache, must-revalidate, no-transform');
        header('X-Content-Type-Options: nosniff');
        header('X-Drive-Resource-Status: ' . self::safe_cache_status($status));
        die;
    }

    /**
     * Return a safe MIME type.
     *
     * @param string $candidate Upstream Content-Type value.
     * @param string $fallback Fallback MIME type.
     * @return string
     */
    private static function safe_content_type(string $candidate, string $fallback): string {
        $candidate = trim(str_replace(["\r", "\n"], '', $candidate));
        $basetype = strtolower(trim(explode(';', $candidate, 2)[0]));
        if (in_array($basetype, ['', 'application/octet-stream', 'binary/octet-stream'], true)) {
            return $fallback;
        }

        if (
            preg_match(
                '/^[a-zA-Z0-9.+-]+\/[a-zA-Z0-9.+-]+(?:\s*;\s*[a-zA-Z0-9._=-]+)*$/',
                $candidate
            )
        ) {
            return $candidate;
        }

        return $fallback;
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
