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
 * Local PDF.js ESM loader for Drive Resource.
 *
 * Moodle compiles AMD source through Babel and transforms dynamic import()
 * expressions into RequireJS requests. PDF.js is distributed as an ES module,
 * not an AMD module, so it must be loaded through a module script. The local
 * PDF.js bundle registers its API as globalThis.pdfjsLib after evaluation.
 *
 * @module     mod_videoplayer/pdfjsloader
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot +
        '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
    const SCRIPT_ID = 'mod-videoplayer-pdfjs-module';

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
     * Configure the local PDF.js worker and return the validated API.
     *
     * @param {*} candidate
     * @returns {Object}
     * @throws {TypeError}
     */
    const configureLibrary = function(candidate) {
        if (!isLibrary(candidate)) {
            throw new TypeError('Drive Resource could not initialise the local PDF.js API.');
        }

        candidate.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
        return candidate;
    };

    /**
     * Load and configure the bundled PDF.js API once per page.
     *
     * @returns {Promise<Object>}
     */
    const load = function() {
        if (isLibrary(window.pdfjsLib)) {
            return Promise.resolve(configureLibrary(window.pdfjsLib));
        }

        if (!loadPromise) {
            loadPromise = new Promise(function(resolve, reject) {
                const previous = document.getElementById(SCRIPT_ID);
                if (previous) {
                    previous.remove();
                }

                const script = document.createElement('script');
                script.id = SCRIPT_ID;
                script.type = 'module';
                script.src = PDFJS_URL;
                script.async = true;
                script.onload = function() {
                    try {
                        resolve(configureLibrary(window.pdfjsLib));
                    } catch (error) {
                        loadPromise = null;
                        reject(error);
                    }
                };
                script.onerror = function() {
                    loadPromise = null;
                    reject(new TypeError('Drive Resource could not load the local PDF.js module.'));
                };
                document.head.appendChild(script);
            });
        }

        return loadPromise;
    };

    return {load: load};
});
