<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Local library placeholder for Drive Resource.
 *
 * @package    mod_videoplayer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return the production-safe PDF display mode.
 *
 * Legacy PageFlip and book modes are normalised to the locally bundled
 * PDF.js viewer. This prevents JavaScript assets from being exposed as
 * document content and keeps mobile and desktop rendering deterministic.
 *
 * @param string|null $requestedmode Requested or legacy database value.
 * @return string Always returns the supported PDF.js mode.
 */
function videoplayer_get_safe_pdf_displaymode(?string $requestedmode): string {
    $requestedmode = clean_param((string) $requestedmode, PARAM_ALPHANUMEXT);
    $supportedmodes = ['pdfjs'];

    return in_array($requestedmode, $supportedmodes, true) ? $requestedmode : 'pdfjs';
}
