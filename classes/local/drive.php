<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_videoplayer\local;

/**
 * Google Drive URL helper.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drive {

    /** @var string Google Drive file source. */
    public const SOURCE_GOOGLEDRIVE = 'googledrive';

    /** @var string Generic media type. */
    public const TYPE_AUTO = 'auto';

    /**
     * Extract a Google Drive file ID from supported sharing URLs.
     *
     * @param string $url
     * @return string|null
     */
    public static function extract_file_id(string $url): ?string {
        $url = trim($url);

        $patterns = [
            '~drive\.google\.com/file/d/([a-zA-Z0-9_-]+)~',
            '~drive\.google\.com/open\?id=([a-zA-Z0-9_-]+)~',
            '~drive\.google\.com/uc\?id=([a-zA-Z0-9_-]+)~',
            '~docs\.google\.com/(document|spreadsheets|presentation)/d/([a-zA-Z0-9_-]+)~',
            '~[?&]id=([a-zA-Z0-9_-]+)~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $id = end($matches);
                return clean_param($id, PARAM_ALPHANUMEXT);
            }
        }

        return null;
    }

    /**
     * Detect resource type from a Google Drive URL.
     *
     * @param string $url
     * @return string
     */
    public static function detect_type(string $url): string {
        if (strpos($url, 'docs.google.com/document') !== false) {
            return 'document';
        }
        if (strpos($url, 'docs.google.com/spreadsheets') !== false) {
            return 'spreadsheet';
        }
        if (strpos($url, 'docs.google.com/presentation') !== false) {
            return 'presentation';
        }
        return 'file';
    }

    /**
     * Build a secure preview URL.
     *
     * @param string $fileid
     * @return \moodle_url
     */
    public static function preview_url(string $fileid): \moodle_url {
        $fileid = clean_param($fileid, PARAM_ALPHANUMEXT);
        return new \moodle_url('https://drive.google.com/file/d/' . rawurlencode($fileid) . '/preview');
    }

    /**
     * Validate whether the URL is a supported Google Drive URL.
     *
     * @param string $url
     * @return bool
     */
    public static function is_supported_url(string $url): bool {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $host = strtolower($host);
        if (!in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            return false;
        }

        return self::extract_file_id($url) !== null;
    }
}
