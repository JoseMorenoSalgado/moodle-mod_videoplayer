// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Normalised local PDF.js loader for Drive Resource.
 *
 * Dynamic import implementations can expose PDF.js as a direct ESM
 * namespace, a default wrapper, or the global namespace installed by
 * the bundle. This adapter validates those shapes before configuring
 * the locally bundled worker.
 *
 * @module     mod_videoplayer/pdfjsloader
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot +
        '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';

    let loadPromise = null;

    /**
     * Determine whether a value exposes the PDF.js viewer contract.
     *
     * @param {*} candidate
     * @returns {boolean}
     */
    const isLibrary = function(candidate) {
        return Boolean(
            candidate &&
            typeof candidate.getDocument === 'function' &&
            candidate.GlobalWorkerOptions
        );
    };

    /**
     * Resolve PDF.js from known browser module namespace shapes.
     *
     * @param {Object} moduleNamespace
     * @returns {Object}
     * @throws {TypeError}
     */
    const resolveLibrary = function(moduleNamespace) {
        const candidates = [
            moduleNamespace,
            moduleNamespace && moduleNamespace.default,
            moduleNamespace && moduleNamespace.pdfjsLib,
            window.pdfjsLib || null
        ];

        for (let index = 0; index < candidates.length; index++) {
            if (isLibrary(candidates[index])) {
                return candidates[index];
            }
        }

        throw new TypeError('Drive Resource could not initialise the local PDF.js API.');
    };

    /**
     * Load and configure the bundled PDF.js API once per page.
     *
     * @returns {Promise<Object>}
     */
    const load = function() {
        if (!loadPromise) {
            loadPromise = import(PDFJS_URL).then(function(moduleNamespace) {
                const pdfjsLib = resolveLibrary(moduleNamespace);
                pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
                return pdfjsLib;
            });
        }
        return loadPromise;
    };

    return {load: load};
});
