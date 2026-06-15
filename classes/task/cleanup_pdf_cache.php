<?php
namespace mod_videoplayer\task;

class cleanup_pdf_cache extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_cleanup_pdf_cache', 'mod_videoplayer');
    }

    public function execute(): void {
        global $CFG;

        $cachedir = $CFG->localcachedir . '/mod_videoplayer/pdf';
        if (!is_dir($cachedir)) {
            return;
        }

        $ttl = (int)get_config('mod_videoplayer', 'pdfcachettl');
        if ($ttl <= 0) {
            $ttl = 86400;
        }

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
            $isstaletmp = strpos($basename, '.tmp.') !== false && filemtime($file) + 3600 < $now;

            if ($isexpiredpdf || $isstaletmp) {
                @unlink($file);
            }
        }
    }
}
