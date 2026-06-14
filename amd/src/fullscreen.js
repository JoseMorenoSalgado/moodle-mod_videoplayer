// This file is part of Moodle - http://moodle.org/

/**
 * Fullscreen viewer for mod_videoplayer.
 *
 * Opens the embedded Drive Resource in a plugin-owned fullscreen overlay
 * without sending the user to Google Drive.
 *
 * @module     mod_videoplayer/fullscreen
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    /**
     * Open a fullscreen overlay.
     *
     * @param {HTMLElement} overlay
     */
    const openOverlay = function(overlay) {
        if (!overlay) {
            return;
        }

        const iframe = overlay.querySelector('iframe[data-src]');
        if (iframe && !iframe.getAttribute('src')) {
            iframe.setAttribute('src', iframe.getAttribute('data-src'));
        }

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('mod-videoplayer-fullscreen-open');

        if (overlay.requestFullscreen) {
            overlay.requestFullscreen().catch(function() {
                // Some mobile browsers deny fullscreen unless user gesture rules are met.
                // The overlay still works as a fixed full viewport layer.
            });
        }
    };

    /**
     * Close a fullscreen overlay.
     *
     * @param {HTMLElement} overlay
     */
    const closeOverlay = function(overlay) {
        if (!overlay) {
            return;
        }

        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('mod-videoplayer-fullscreen-open');

        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(function() {
                // Ignore exit errors.
            });
        }
    };

    /**
     * Initialise fullscreen controls.
     */
    const init = function() {
        document.addEventListener('click', function(event) {
            const openButton = event.target.closest('[data-action="mod-videoplayer-fullscreen"]');
            if (openButton) {
                event.preventDefault();
                const targetId = openButton.getAttribute('data-target');
                openOverlay(document.getElementById(targetId));
                return;
            }

            const closeButton = event.target.closest('[data-action="mod-videoplayer-close-fullscreen"]');
            if (closeButton) {
                event.preventDefault();
                closeOverlay(closeButton.closest('.mod-videoplayer-fullscreen'));
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const overlay = document.querySelector('.mod-videoplayer-fullscreen:not([hidden])');
                closeOverlay(overlay);
            }
        });
    };

    return {
        init: init
    };
});
