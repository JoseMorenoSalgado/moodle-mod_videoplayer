# Video.js local library

Drive Resource does not load Video.js from a CDN.

Place the production Video.js files in this directory:

- `video.min.js`
- `video-js.min.css`

The Moodle view page loads these files locally from:

- `/mod/videoplayer/thirdpartylibs/videojs/video.min.js`
- `/mod/videoplayer/thirdpartylibs/videojs/video-js.min.css`

Do not commit CDN URLs or remote script tags.
