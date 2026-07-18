<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Resilient HTTP byte-range proxy for protected Drive resources.
 *
 * The proxy deliberately keeps Moodle as the only browser-visible endpoint and
 * forwards a single byte range without buffering the protected resource in PHP
 * memory. It also preserves the response metadata required by Safari/iOS media
 * playback and PDF.js range loading.
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
        $ishead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

        $headercallback = static function($curl, string $header) use (&$responseheaders, &$discardbody): int {
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

            $headers = [
                'content-length' => '/^Content-Length:\s*(\d+)/i',
                'content-type' => '/^Content-Type:\s*(.+)$/i',
                'content-range' => '/^Content-Range:\s*(.+)$/i',
                'accept-ranges' => '/^Accept-Ranges:\s*(.+)$/i',
                'etag' => '/^ETag:\s*(.+)$/i',
                'last-modified' => '/^Last-Modified:\s*(.+)$/i',
            ];

            foreach ($headers as $key => $pattern) {
                if (preg_match($pattern, $trimmed, $matches)) {
                    $responseheaders[$key] = trim($matches[1]);
                    break;
                }
            }

            return $length;
        };

        $ch = curl_init($url);
        if ($ch === false) {
            self::send_bad_gateway();
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
            CURLOPT_WRITEFUNCTION => static function($curl, string $data) use (
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
                    if (function_exists('fastcgi_finish_request')) {
                        // Do not finish the request here; keep streaming subsequent chunks.
                    }
                    flush();
                }

                return strlen($data);
            },
        ];

        // CURLOPT_RANGE is the single source of the outgoing Range header. Do
        // not also add a manual Range header because duplicate Range headers
        // are handled inconsistently by upstream servers and mobile browsers.
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

        if ($ishead && $result !== false && in_array($curlcode, [200, 206], true)) {
            self::send_response_headers($responseheaders, $fallbacktype, $filename, $cachestatus);
            die;
        }

        if ($curlcode === 416 && !$headerssent) {
            self::send_range_not_satisfiable($responseheaders, $cachestatus);
        }

        if ($result === false || $curlcode >= 400 || !in_array($curlcode, [200, 206], true)) {
            if (!$headerssent) {
                self::send_bad_gateway();
            }
            debugging('Drive Resource proxy failed: HTTP ' . $curlcode . ' ' . $curlerror, DEBUG_DEVELOPER);
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
        $status = (int)($headers['status'] ?? 200);
        $status = $status === 206 ? 206 : 200;
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
     * Return a safe MIME type.
     *
     * @param string $candidate Upstream Content-Type value.
     * @param string $fallback Fallback MIME type.
     * @return string
     */
    private static function safe_content_type(string $candidate, string $fallback): string {
        $candidate = trim(str_replace(["\r", "\n"], '', $candidate));
        if ($candidate !== '' && preg_match('/^[a-zA-Z0-9.+-]+\/[a-zA-Z0-9.+-]+(?:\s*;\s*[a-zA-Z0-9._=-]+)*$/', $candidate)) {
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
