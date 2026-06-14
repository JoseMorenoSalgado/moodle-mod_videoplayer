// This file is part of Moodle - http://moodle.org/

/**
 * Progress tracking for mod_videoplayer.
 *
 * Google Drive iframes do not expose playback time because of browser
 * cross-origin restrictions. This tracker records meaningful activity
 * using page presence and periodic heartbeat updates.
 *
 * @module     mod_videoplayer/progress
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const DEFAULT_INTERVAL = 30000;
    const DEFAULT_REQUIRED_SECONDS = 300;

    let state = {
        cmid: 0,
        requiredSeconds: DEFAULT_REQUIRED_SECONDS,
        interval: DEFAULT_INTERVAL,
        elapsed: 0,
        completed: false,
        timer: null,
        lastTick: null,
        visible: true,
        sentFinal: false
    };

    /**
     * Clamp a numeric value.
     *
     * @param {number} value
     * @param {number} min
     * @param {number} max
     * @returns {number}
     */
    const clamp = function(value, min, max) {
        return Math.max(min, Math.min(max, value));
    };

    /**
     * Calculate current completion percentage.
     *
     * @returns {number}
     */
    const getCompletionPercentage = function() {
        if (state.requiredSeconds <= 0) {
            return 100;
        }
        return clamp((state.elapsed / state.requiredSeconds) * 100, 0, 100);
    };

    /**
     * Send progress to Moodle.
     *
     * @param {boolean} force
     * @returns {Promise}
     */
    const sendProgress = function(force) {
        const percentage = getCompletionPercentage();
        const completed = state.completed || percentage >= 100;
        state.completed = completed;

        if (!force && state.sentFinal && completed) {
            return Promise.resolve();
        }

        const request = {
            methodname: 'mod_videoplayer_save_progress',
            args: {
                cmid: state.cmid,
                progress: Math.round(state.elapsed),
                completed: completed,
                completionpercentage: Math.round(percentage * 100) / 100
            }
        };

        return Ajax.call([request])[0]
            .then(function(response) {
                if (response && response.completed) {
                    state.completed = true;
                    state.sentFinal = true;
                }
                return response;
            })
            .catch(Notification.exception);
    };

    /**
     * Update elapsed active viewing time.
     */
    const tick = function() {
        const now = Date.now();

        if (state.lastTick === null) {
            state.lastTick = now;
            return;
        }

        if (state.visible) {
            const delta = Math.max(0, (now - state.lastTick) / 1000);
            state.elapsed += delta;
        }

        state.lastTick = now;
        sendProgress(false);
    };

    /**
     * Register visibility tracking.
     */
    const registerVisibilityEvents = function() {
        document.addEventListener('visibilitychange', function() {
            state.visible = !document.hidden;
            state.lastTick = Date.now();

            if (document.hidden) {
                sendProgress(true);
            }
        });

        window.addEventListener('beforeunload', function() {
            sendProgress(true);
        });
    };

    /**
     * Init tracker.
     *
     * @param {Object} options
     */
    const init = function(options) {
        options = options || {};

        state.cmid = parseInt(options.cmid, 10) || 0;
        state.requiredSeconds = parseInt(options.requiredSeconds, 10) || DEFAULT_REQUIRED_SECONDS;
        state.interval = parseInt(options.interval, 10) || DEFAULT_INTERVAL;
        state.elapsed = parseFloat(options.initialProgress) || 0;
        state.completed = Boolean(options.completed);
        state.visible = !document.hidden;
        state.lastTick = Date.now();

        if (!state.cmid) {
            return;
        }

        registerVisibilityEvents();
        sendProgress(false);

        state.timer = window.setInterval(tick, state.interval);
    };

    return {
        init: init
    };
});
