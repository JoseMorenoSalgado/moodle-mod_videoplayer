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

defined('MOODLE_INTERNAL') || die();

/**
 * Version information for the Video Player activity module.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud <jose@elearningcloud.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$plugin->component = 'mod_videoplayer';       // Full name of the plugin (used for diagnostics).
$plugin->version   = 2025071900;              // Plugin version (YYYYMMDDXX).
$plugin->release   = '1.0.0';                 // Human-readable version.
$plugin->requires  = 2022041900;              // Requires at least this Moodle version (4.0).
$plugin->supported = [40100, 50000];          // Supported Moodle versions (4.1 to 5.0).
$plugin->maturity  = MATURITY_STABLE;         // Maturity level.
