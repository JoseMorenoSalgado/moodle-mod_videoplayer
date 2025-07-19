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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for the Video Player activity module.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud <jose@elearningcloud.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General strings.
$string['modulename'] = 'Video player';
$string['modulenameplural'] = 'Video players';
$string['modulename_help'] = 'Use this module to embed and display videos from Google Drive or other sources.';
$string['pluginname'] = 'Video player';

// Form strings.
$string['videoname'] = 'Video title';
$string['videourl'] = 'Google Drive video URL';
$string['videourl_help'] = 'Paste the shareable link from Google Drive. The video will be embedded in the activity.';

// Capability strings.
$string['mod_videoplayer:addinstance'] = 'Add a new Video Player instance';
$string['mod_videoplayer:addinstance_help'] = 'Allows users to add a new instance of the Video Player activity to a course.';

$string['mod_videoplayer:view'] = 'View Video Player activity';
$string['mod_videoplayer:view_help'] = 'Allows users to view the Video Player activity content.';

$string['mod_videoplayer:edit'] = 'Edit Video Player activity';
$string['mod_videoplayer:edit_help'] = 'Allows users to edit the settings or content of the Video Player activity.';

$string['mod_videoplayer:manage'] = 'Manage Video Player activity';
$string['mod_videoplayer:manage_help'] = 'Allows users to manage general module configuration for the Video Player activity.';

$string['mod_videoplayer:viewreport'] = 'View Video Player reports';
$string['mod_videoplayer:viewreport_help'] = 'Allows users to view reports related to the Video Player activity.';

$string['mod_videoplayer:editreport'] = 'Edit Video Player reports';
$string['mod_videoplayer:editreport_help'] = 'Allows users to edit reports, such as user progress, in the Video Player activity.';

// Error messages.
$string['invalidurl'] = 'The provided video URL is not valid. Please use a proper Google Drive shareable link.';