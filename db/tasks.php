<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\mod_videoplayer\\task\\cleanup_pdf_cache',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '*/6',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
