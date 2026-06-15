<?php
namespace mod_videoplayer\task;

use mod_videoplayer\local\drive;

class precache_pdf extends \core\task\adhoc_task {
    public function execute(): void {
        global $CFG, $DB;

        $data = $this->get_custom_data();
        if (empty($data->instanceid)) {
            return;
        }

        $record = $DB->get_record('videoplayer', ['id' => (int)$data->instanceid]);
        if (!$record) {
            return;
        }

        $type = empty($record->type) || $record->type === 'auto'
            ? drive::detect_type($record->videourl)
            : clean_param($record->type, PARAM_ALPHANUMEXT);

        if (!drive::is_pdf_type($type) || !get_config('mod_videoplayer', 'pdfcacheenabled')) {
            return;
        }

        $fileid = drive::extract_file_id($record->videourl);
        if (!$fileid) {
            return;
        }

        $url = drive::protected_content_url($record->videourl, $fileid, $type);
        if (!$url) {
            return;
        }

        $cachedir = $CFG->localcachedir . '/mod_videoplayer/pdf';
        if (!is_dir($cachedir)) {
            make_writable_directory($cachedir);
        }

        $cm = get_coursemodule_from_instance('videoplayer', $record->id, $record->course, false, IGNORE_MISSING);
        $cmid = $cm ? $cm->id : 0;
        $cachekey = sha1($cmid . ':' . $fileid . ':' . $type . ':' . $record->timemodified);
        $cachefile = $cachedir . '/' . $cachekey . '.pdf';
        $tmpfile = $cachefile . '.tmp.' . getmypid();

        if (is_readable($cachefile)) {
            return;
        }

        $handle = fopen($tmpfile, 'wb');
        if ($handle === false) {
            return;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_BUFFERSIZE => 131072,
            CURLOPT_WRITEFUNCTION => function($curl, string $data) use ($handle): int {
                fwrite($handle, $data);
                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($handle);

        if ($result !== false && $httpcode < 400 && filesize($tmpfile) > 0) {
            rename($tmpfile, $cachefile);
            return;
        }

        if (file_exists($tmpfile)) {
            unlink($tmpfile);
        }
    }
}
