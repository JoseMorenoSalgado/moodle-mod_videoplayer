define(['core/notification'], function(Notification) {
    var VIDEOJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/videojs/video.min.js';
    var videojsPromise = null;

    var loadVideoJs = function() {
        if (window.videojs) {
            return Promise.resolve(window.videojs);
        }

        if (!videojsPromise) {
            videojsPromise = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = VIDEOJS_URL;
                script.async = true;
                script.onload = function() {
                    if (window.videojs) {
                        resolve(window.videojs);
                    } else {
                        reject(new Error('Video.js did not expose window.videojs'));
                    }
                };
                script.onerror = function() {
                    reject(new Error('Local Video.js library could not be loaded'));
                };
                document.head.appendChild(script);
            });
        }

        return videojsPromise;
    };

    var initPlayer = function(videojs, node) {
        if (node.dataset.videojsReady === '1') {
            return;
        }

        node.dataset.videojsReady = '1';
        videojs(node, {
            controls: true,
            fluid: true,
            responsive: true,
            preload: 'metadata',
            playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
            controlBar: {
                pictureInPictureToggle: false
            },
            html5: {
                nativeAudioTracks: false,
                nativeVideoTracks: false
            }
        });
    };

    var init = function() {
        var players = Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-native-video'));
        if (!players.length) {
            return;
        }

        loadVideoJs().then(function(videojs) {
            players.forEach(function(node) {
                initPlayer(videojs, node);
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
