// This file is part of Moodle - http://moodle.org/

/**
 * Progressive enhancement for protected Drive Resource videos using local Plyr.
 *
 * @module     mod_videoplayer/plyr
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* Promise failures remain handled by the terminal catch. */
/* eslint-disable promise/always-return */
define(['core/notification'], function(Notification) {
    var PLYR_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/plyr/plyr.min.js';
    var SEEK_RECOVERY_DELAY = 180;
    var SEEK_TOLERANCE = 1.25;
    var MAX_SEEK_RECOVERY_ATTEMPTS = 2;
    var plyrPromise = null;

    var blockBrowserMediaActions = function(event) {
        event.preventDefault();
        event.stopPropagation();
        return false;
    };

    var loadAmdPlyr = function(resolve, reject) {
        if (window.require) {
            window.require(['Plyr'], function(Plyr) {
                resolve(Plyr);
            }, function() {
                reject(new Error('Plyr AMD module could not be resolved'));
            });
            return;
        }

        reject(new Error('Plyr did not expose window.Plyr'));
    };

    var loadPlyr = function() {
        if (window.Plyr) {
            return Promise.resolve(window.Plyr);
        }

        if (!plyrPromise) {
            plyrPromise = new Promise(function(resolve, reject) {
                var existing = document.querySelector('script[data-mod-videoplayer-plyr="1"]');
                var script = existing || document.createElement('script');

                script.onload = function() {
                    if (window.Plyr) {
                        resolve(window.Plyr);
                        return;
                    }

                    loadAmdPlyr(resolve, reject);
                };
                script.onerror = function() {
                    reject(new Error('Local Plyr library could not be loaded'));
                };

                if (existing) {
                    loadAmdPlyr(resolve, reject);
                    return;
                }

                script.src = PLYR_URL;
                script.async = true;
                script.setAttribute('data-mod-videoplayer-plyr', '1');
                document.head.appendChild(script);
            });
        }

        return plyrPromise;
    };

    var isAppleMobile = function() {
        var platform = navigator.platform || '';
        var userAgent = navigator.userAgent || '';
        var touchMac = platform === 'MacIntel' && navigator.maxTouchPoints > 1;
        return /iPad|iPhone|iPod/.test(userAgent) || touchMac;
    };

    var shouldPreloadVideo = function() {
        var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!connection) {
            return true;
        }
        if (connection.saveData) {
            return false;
        }

        return ['slow-2g', '2g'].indexOf(connection.effectiveType || '') === -1;
    };

    var markOrientation = function(node) {
        var wrapper = node.closest('.mod-videoplayer-native-frame');
        if (!wrapper || !node.videoWidth || !node.videoHeight) {
            return;
        }

        wrapper.classList.remove('is-portrait-video', 'is-landscape-video', 'is-square-video');
        if (node.videoHeight > node.videoWidth) {
            wrapper.classList.add('is-portrait-video');
        } else if (node.videoWidth > node.videoHeight) {
            wrapper.classList.add('is-landscape-video');
        } else {
            wrapper.classList.add('is-square-video');
        }
    };

    var registerSeekRecovery = function(node) {
        var state = {
            target: 0,
            attempts: 0,
            restoring: false,
            timer: null
        };

        var clearTimer = function() {
            if (state.timer !== null) {
                window.clearTimeout(state.timer);
                state.timer = null;
            }
        };

        var reset = function() {
            clearTimer();
            state.target = 0;
            state.attempts = 0;
            state.restoring = false;
        };

        var targetReached = function() {
            return state.target <= 0 || Math.abs(node.currentTime - state.target) <= SEEK_TOLERANCE;
        };

        var restore = function() {
            clearTimer();
            if (
                state.target <= 1.5 ||
                targetReached() ||
                state.attempts >= MAX_SEEK_RECOVERY_ATTEMPTS ||
                node.readyState < 1
            ) {
                return;
            }

            var duration = Number.isFinite(node.duration) ? node.duration : 0;
            var target = duration > 0 ? Math.min(state.target, Math.max(0, duration - 0.25)) : state.target;
            state.restoring = true;
            state.attempts += 1;

            try {
                node.currentTime = target;
            } catch (error) {
                state.restoring = false;
                return;
            }

            window.setTimeout(function() {
                state.restoring = false;
                if (targetReached()) {
                    reset();
                }
            }, SEEK_RECOVERY_DELAY);
        };

        var scheduleRestore = function() {
            if (state.target <= 1.5 || targetReached() || state.attempts >= MAX_SEEK_RECOVERY_ATTEMPTS) {
                return;
            }
            clearTimer();
            state.timer = window.setTimeout(restore, SEEK_RECOVERY_DELAY);
        };

        node.addEventListener('seeking', function() {
            if (state.restoring) {
                return;
            }

            var target = Number(node.currentTime) || 0;
            if (target <= 1.5) {
                reset();
                return;
            }

            state.target = target;
            state.attempts = 0;
        });
        node.addEventListener('seeked', scheduleRestore);
        node.addEventListener('loadedmetadata', scheduleRestore);
        node.addEventListener('canplay', scheduleRestore);
        node.addEventListener('timeupdate', function() {
            if (state.target > 0 && targetReached()) {
                reset();
            }
        });
        node.addEventListener('emptied', scheduleRestore);
    };

    var prepareVideo = function(node) {
        // Keep native seek/playback-rate capabilities available. The server-side
        // protected endpoint is the security boundary; controlsList is only UX.
        node.setAttribute('controlsList', 'nodownload');
        node.setAttribute('draggable', 'false');
        node.setAttribute('playsinline', '');
        node.setAttribute('webkit-playsinline', '');
        node.setAttribute('x-webkit-airplay', 'allow');
        node.preload = shouldPreloadVideo() ? 'auto' : 'metadata';

        // Apple mobile browsers are most reliable when the native media layer can negotiate
        // presentation features itself. Plyr still supplies the surrounding UI.
        if (isAppleMobile()) {
            node.removeAttribute('disablepictureinpicture');
            try {
                node.disablePictureInPicture = false;
            } catch (error) {
                // Older Safari versions expose a read-only implementation.
            }
        } else {
            node.disablePictureInPicture = true;
        }

        node.addEventListener('contextmenu', blockBrowserMediaActions, true);
        node.addEventListener('dragstart', blockBrowserMediaActions, true);
        node.addEventListener('selectstart', blockBrowserMediaActions, true);
        node.addEventListener('loadedmetadata', function() {
            markOrientation(node);
        });
        registerSeekRecovery(node);
    };

    var init = function() {
        var nodes = Array.prototype.slice.call(document.querySelectorAll('.js-drive-resource-video'));
        if (!nodes.length) {
            return;
        }

        nodes.forEach(prepareVideo);

        loadPlyr().then(function(Plyr) {
            nodes.forEach(function(node) {
                if (node.dataset.plyrReady === '1') {
                    return;
                }

                node.dataset.plyrReady = '1';
                new Plyr(node, {
                    controls: [
                        'play-large', 'play', 'rewind', 'fast-forward', 'progress',
                        'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen'
                    ],
                    settings: ['speed'],
                    seekTime: 10,
                    speed: {
                        selected: 1,
                        options: [0.5, 0.75, 1, 1.25, 1.5, 2]
                    },
                    hideControls: true,
                    keyboard: {
                        focused: true,
                        global: false
                    },
                    tooltips: {
                        controls: true,
                        seek: true
                    },
                    storage: {
                        enabled: false
                    },
                    fullscreen: {
                        enabled: true,
                        fallback: true,
                        iosNative: true
                    }
                });
                markOrientation(node);
            });
        }).catch(function(error) {
            // Native HTML5 controls remain usable when Plyr cannot initialize.
            nodes.forEach(function(node) {
                node.controls = true;
            });
            if (window.console) {
                window.console.warn(error.message || error);
            }
            Notification.addNotification({
                message: M.util.get_string('plyrmissing', 'mod_videoplayer'),
                type: 'warning'
            });
        });
    };

    return {
        init: init
    };
});
