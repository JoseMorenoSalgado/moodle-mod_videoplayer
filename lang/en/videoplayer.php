<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Language strings for mod_videoplayer.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Google Drive resource';
$string['modulename'] = 'Google Drive resource';
$string['modulenameplural'] = 'Google Drive resources';
$string['modulename_help'] = 'Use this activity to embed videos, PDFs, images, documents, spreadsheets and presentations from Google Drive.';
$string['pluginadministration'] = 'Google Drive resource administration';

$string['resourcename'] = 'Resource name';
$string['videoname'] = 'Resource name';
$string['driveurl'] = 'Google Drive URL';
$string['driveurl_help'] = 'Paste a shareable Google Drive or Google Docs URL. Supported resources include videos, PDFs, images, documents, spreadsheets and presentations.';
$string['videourl'] = 'Google Drive URL';
$string['videourl_help'] = 'Paste a shareable Google Drive or Google Docs URL.';
$string['resourcetype'] = 'Resource type';
$string['completionpercentage'] = 'Required completion percentage';
$string['completionpercentage_help'] = 'Percentage required to consider this resource completed when progress tracking is available. For non-video resources, Moodle view completion may be used.';
$string['openindrive'] = 'Open in Google Drive';

$string['typeauto'] = 'Automatic';
$string['typevideo'] = 'Video';
$string['typepdf'] = 'PDF';
$string['typeimage'] = 'Image';
$string['typedocument'] = 'Document';
$string['typespreadsheet'] = 'Spreadsheet';
$string['typepresentation'] = 'Presentation';
$string['typefile'] = 'File';

$string['invalidurl'] = 'The provided URL is not valid. Please use a proper Google Drive shareable link.';
$string['invaliddriveurl'] = 'Enter a valid Google Drive or Google Docs shareable URL.';
$string['invalidcompletionpercentage'] = 'The completion percentage must be a number between 0 and 100.';

$string['mod_videoplayer:addinstance'] = 'Add a new Google Drive resource';
$string['mod_videoplayer:addinstance_help'] = 'Allows users to add a new Google Drive resource activity to a course.';
$string['mod_videoplayer:view'] = 'View Google Drive resource';
$string['mod_videoplayer:view_help'] = 'Allows users to view the Google Drive resource activity content.';
$string['mod_videoplayer:edit'] = 'Edit Google Drive resource';
$string['mod_videoplayer:edit_help'] = 'Allows users to edit the Google Drive resource settings.';
$string['mod_videoplayer:manage'] = 'Manage Google Drive resource';
$string['mod_videoplayer:manage_help'] = 'Allows users to manage Google Drive resource configuration.';
$string['mod_videoplayer:viewreport'] = 'View Google Drive resource reports';
$string['mod_videoplayer:viewreport_help'] = 'Allows users to view reports related to Google Drive resources.';
$string['mod_videoplayer:editreport'] = 'Edit Google Drive resource reports';
$string['mod_videoplayer:editreport_help'] = 'Allows users to edit reports and user progress in Google Drive resources.';

$string['privacy:metadata:videoplayer_views'] = 'Stores user progress and completion data for Google Drive resources.';
$string['privacy:metadata:videoplayer_views:videoplayerid'] = 'The Google Drive resource activity instance ID.';
$string['privacy:metadata:videoplayer_views:userid'] = 'The ID of the user who viewed the resource.';
$string['privacy:metadata:videoplayer_views:progress'] = 'The last saved progress value.';
$string['privacy:metadata:videoplayer_views:completed'] = 'Whether the resource has been marked as completed.';
$string['privacy:metadata:videoplayer_views:completionpercentage'] = 'The saved completion percentage.';
$string['privacy:metadata:videoplayer_views:timecreated'] = 'The time when the first progress record was created.';
$string['privacy:metadata:videoplayer_views:timemodified'] = 'The time when the progress record was last updated.';
