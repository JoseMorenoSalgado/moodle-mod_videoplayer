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
 * View page for the Video Player activity.
 *
 * @package    mod_videoplayer
 * @copyright  2025 Jose Erasmo Moreno Salgado - Elearning Cloud  <jose@elearningcloud.org>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_videoplayer';        // Full name of the plugin (used for diagnostics).
$plugin->release   = '1.0.0';                  // Human-readable version name.
$plugin->version   = 2025071900;               // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022041900;               // Moodle version required (e.g., 4.0 = 2022041900).
$plugin->supported = [40100, 50000];           // Supported Moodle versions (e.g., 4.1 to 5.0).
$plugin->maturity  = MATURITY_STABLE;          // Maturity level of the plugin (e.g., ALPHA, BETA, RC, STABLE).
