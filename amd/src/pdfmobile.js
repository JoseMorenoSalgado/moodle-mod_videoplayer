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
    const VIEWPORT_GUTTER = 10;

    /**
     * Whether current viewport should use the mobile stabilizer.
     *
     * @returns {boolean}
     */
    const isMobile = function() {
        return window.matchMedia(MOBILE_QUERY).matches;
    };

    /**
     * Return a reliable canvas aspect ratio based on the rendered bitmap.
     *
     * @param {HTMLCanvasElement} canvas Rendered PDF canvas.
     * @returns {number}
     */
    const getCanvasRatio = function(canvas) {
        if (!canvas || !canvas.width || !canvas.height) {
            return 1;
        }

        return canvas.width / canvas.height;
    };

    /**
     * Force mobile PDF pages to use safe fit-width mode.
     *
     * The page uses the full available width, keeps the PDF aspect ratio and
     * avoids the previous fit-page behavior that left excessive blank space
     * below short/cover pages.
     *
     * @param {HTMLElement} wrap Canvas wrapper.
     * @param {HTMLCanvasElement} canvas Rendered PDF canvas.
     * @returns {void}
     */
    const applySafeFitWidth = function(wrap, canvas) {
        const ratio = getCanvasRatio(canvas);
        const availableWidth = Math.max(wrap.clientWidth - VIEWPORT_GUTTER, 260);
        const targetWidth = availableWidth;
        const targetHeight = targetWidth / ratio;

        canvas.style.width = Math.floor(targetWidth) + 'px';
        canvas.style.height = Math.floor(targetHeight) + 'px';
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = 'none';
        canvas.style.marginLeft = 'auto';
        canvas.style.marginRight = 'auto';
        canvas.style.display = 'block';

        wrap.style.height = Math.floor(targetHeight) + 'px';
        wrap.style.minHeight = Math.floor(targetHeight) + 'px';
        wrap.style.maxHeight = 'none';
        wrap.style.overflowX = 'hidden';
        wrap.style.overflowY = 'hidden';
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

        applySafeFitWidth(wrap, canvas);

        wrap.scrollLeft = 0;
        wrap.scrollTop = 0;
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
