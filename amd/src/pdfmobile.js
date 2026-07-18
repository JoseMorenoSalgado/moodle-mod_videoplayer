// This file is part of Moodle - http://moodle.org/

/**
 * Mobile viewport stabilizer for the protected PDF.js viewer.
 *
 * Keeps iOS/Safari scrolling stable without resizing the PDF canvas or
 * overriding the zoom level selected by the main viewer.
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
    const MAX_ATTEMPTS = 12;

    const isMobile = function() {
        return window.matchMedia(MOBILE_QUERY).matches;
    };

    const stabilizeViewer = function(viewer) {
        if (!isMobile() || !viewer) {
            return;
        }

        const wrap = viewer.querySelector(WRAP_SELECTOR);
        const canvas = viewer.querySelector(CANVAS_SELECTOR);
        if (!wrap || !canvas || !canvas.width || !canvas.height) {
            return;
        }

        // The main PDF viewer owns canvas dimensions and zoom. The stabilizer
        // only ensures touch scrolling remains enabled and removes accidental
        // initial horizontal offsets when the rendered page already fits.
        wrap.style.webkitOverflowScrolling = 'touch';
        wrap.style.overflow = 'auto';
        wrap.style.overscrollBehavior = 'contain';

        if (canvas.getBoundingClientRect().width <= wrap.clientWidth + 2) {
            wrap.scrollLeft = 0;
        }
    };

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
