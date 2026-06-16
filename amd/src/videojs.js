define(['core/notification'], function(Notification) {
    var PLYR_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/plyr/plyr.min.js';
    var PLYR_CSS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/plyr/plyr.css';
    var plyrPromise = null;

    var ensureCss = function() {
        if (document.querySelector('link[data-mod-videoplayer-plyr="1"]')) {
            return;
        }

        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = PLYR_CSS_URL;
        link.dataset.modVideoplayerPlyr = '1';
        document.head.appendChild(link);
    };

    var loadPlyr = function() {
        ensureCss();

        if (window.Plyr) {
            return Promise.resolve(window.Plyr);
        }

        if (!plyrPromise) {
            plyrPromise = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = PLYR_URL;
                script.async = true;
                script.onload = function() {
                    if (window.Plyr) {
                        resolve(window.Plyr);
                    } else {
                        reject(new Error('Plyr did not expose window.Plyr'));
                    }
                };
                script.onerror = function() {
                    reject(new Error('Local Plyr library could not be loaded'));
                };
                document.head.appendChild(script);
            });
        }

        return plyrPromise;
    };

    var init = function() {
        var players = Array.prototype.slice.call(document.querySelectorAll('.js-drive-resource-video, .mod-videoplayer-native-video'));
        if (!players.length) {
            return;
        }

        loadPlyr().then(function(Plyr) {
            players.forEach(function(node) {
                if (node.dataset.plyrReady === '1') {
                    return;
                }

                node.dataset.plyrReady = '1';
                new Plyr(node, {
                    controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen'],
                    settings: ['speed'],
                    speed: {
                        selected: 1,
                        options: [0.5, 0.75, 1, 1.25, 1.5, 2]
                    },
                    hideControls: true,
                    keyboard: {
                        focused: true,
                        global: false
                    }
                });
            });
        }).catch(function(error) {
            if (window.console) {
                window.console.warn(error.message || error);
            }
            Notification.addNotification({
                message: M.util.get_string('videojsmissing', 'mod_videoplayer'),
                type: 'warning'
            });
        });
    };

    return {
        init: init
    };
});
