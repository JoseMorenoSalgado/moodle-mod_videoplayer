// This file is part of Moodle - http://moodle.org/

/**
 * Mobile viewport stabilizer for the protected PDF.js viewer.
 *
 * This module fixes iOS/Safari layout edge cases where the PDF canvas can
 * render wider than the visible viewport or start with a horizontal offset.
 *
 * @module     mod_videoplayer/pdfmobile
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const MOBILE_QUERY = '(max-width: 767.98px)';
    const VIEWER_SELECTOR = '.mod-videoplayer-pdfjs-viewer';
    const WRAP_SELECTOR = '.mod-videoplayer-pdfjs-canvas-wrap';
    const CANVAS_SELECTOR = '.mod-videoplayer-pdfjs-canvas';
    const STABILIZE_DELAY = 120;
    const MAX_ATTEMPTS = 20;

    /**
     * Whether current viewport should use the mobile stabilizer.
     *
     * @returns {boolean}
     */
    const isMobile = function() {
        return window.matchMedia(MOBILE_QUERY).matches;
    };

    /**
     * Apply safe display constraints to one viewer.
     *
     * @param {HTMLElement} viewer Viewer node.
     * @returns {void}
     */
    const stabilizeViewer = function(viewer) {
        if (!isMobile() || !viewer) {
            return;
        }

        const wrap = viewer.querySelector(WRAP_SELECTOR);
        const canvas = viewer.querySelector(CANVAS_SELECTOR);
        if (!wrap || !canvas || !canvas.width || !canvas.height) {
            return;
        }

        const availableWidth = Math.max(wrap.clientWidth - 8, 280);
        const inlineWidth = parseFloat(canvas.style.width || '0');
        const inlineHeight = parseFloat(canvas.style.height || '0');

        if (!inlineWidth || !inlineHeight) {
            return;
        }

        if (inlineWidth > availableWidth) {
            const ratio = availableWidth / inlineWidth;
            canvas.style.width = Math.floor(availableWidth) + 'px';
            canvas.style.height = Math.floor(inlineHeight * ratio) + 'px';
        }

        canvas.style.maxWidth = '100%';
        canvas.style.marginLeft = 'auto';
        canvas.style.marginRight = 'auto';
        canvas.style.display = 'block';

        if (wrap.scrollLeft !== 0) {
            wrap.scrollLeft = 0;
        }
    };

    /**
     * Stabilize all PDF viewers after PDF.js has rendered.
     *
     * @param {number} attempt Current retry attempt.
     * @returns {void}
     */
    const stabilizeAll = function(attempt) {
        if (!isMobile()) {
            return;
        }

        const viewers = Array.prototype.slice.call(document.querySelectorAll(VIEWER_SELECTOR));
        viewers.forEach(stabilizeViewer);

        if (attempt < MAX_ATTEMPTS) {
            window.setTimeout(function() {
                stabilizeAll(attempt + 1);
            }, STABILIZE_DELAY);
        }
    };

    /**
     * Initialise mobile PDF viewport stabilisation.
     *
     * @returns {void}
     */
    const init = function() {
        stabilizeAll(0);

        window.addEventListener('resize', function() {
            stabilizeAll(0);
        });

        window.addEventListener('orientationchange', function() {
            window.setTimeout(function() {
                stabilizeAll(0);
            }, 250);
        });
    };

    return {
        init: init
    };
});
