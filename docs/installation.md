# Drive Resource installation

## Requirements

- Moodle 4.x or Moodle 5.x target environment.
- PHP 8.2 or newer recommended.
- PHP cURL extension enabled for protected Google Drive proxy delivery.
- Moodle cron configured and running reliably.
- Writable `$CFG->localcachedir`.
- HTTPS enabled for production use.
- Required third-party libraries installed locally under `thirdpartylibs/`.

## Installation

Place the plugin in:

```text
mod/videoplayer
```

Run Moodle upgrade:

```bash
php admin/cli/upgrade.php
```

Then purge Moodle caches.

## Required local libraries

Drive Resource must not load viewer libraries from a CDN in production.

### PDF.js

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

### Plyr

The current AMD loader expects:

```text
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
```

Native HTML5 controls remain the fallback if Plyr enhancement cannot initialize.

### StPageFlip

Optional for legacy/optional ebook behavior:

```text
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

PDF.js remains the protected fallback when PageFlip is unavailable.

## Moodle cron

Cron is required for the fast-first-byte Google Drive PDF strategy. On a cold PDF request, Drive Resource immediately proxies the requested bytes and queues a deduplicated ad-hoc task to warm the complete PDF cache.

Recommended production cron frequency is the normal Moodle recommendation for frequent cron execution. Verify that ad-hoc tasks are being processed and that the web server/PHP user can write to `$CFG->localcachedir`.

## Creating a local protected PDF activity

1. Turn editing on in a course.
2. Add a **Drive Resource** activity.
3. Select **Local protected PDF**.
4. Upload one PDF file.
5. Choose the available PDF display mode.
6. Configure completion percentage.
7. Optionally enable watermark and gamification.
8. Save and display.

## Creating a Google Drive activity

1. Add a **Drive Resource** activity.
2. Select **Google Drive**.
3. Paste a supported Google Drive/Docs share URL.
4. Select the resource type or use automatic detection where appropriate.
5. Save and display.

For protected video playback, the Google Drive file must be accessible to the server-side download flow configured by the plugin.

## Protected PDF cache

Cache location:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Typical response diagnostics:

```text
X-Drive-Resource-Cache: LOCAL
X-Drive-Resource-Cache: HIT
X-Drive-Resource-Cache: MISS_QUEUED
X-Drive-Resource-Cache: MISS
X-Drive-Resource-Cache: BYPASS
```

Expected cold-cache flow:

```text
first PDF.js range request
↓
MISS_QUEUED
↓
requested bytes stream immediately
↓
Moodle cron executes precache_pdf
↓
subsequent request
↓
HIT
```

No PDF recompression or rasterization is performed by this cache flow.

## Video streaming validation

Use browser developer tools or an HTTP client while authenticated to verify the protected endpoint. A video should support seeking through valid byte-range responses.

Validate at minimum:

```text
Range: bytes=0-1
Range: bytes=<middle>-<middle+chunk>
```

Expected behavior for a range-capable upstream resource:

```text
HTTP 206
Accept-Ranges: bytes
Content-Range: bytes start-end/total
Content-Length: requested-range-length
```

Do not test by exposing or copying the upstream Google Drive download URL. Test only the Moodle `protected.php` endpoint.

## iPhone/iPad validation

On Safari/iOS test:

- initial play after a user gesture;
- inline playback;
- pause/resume;
- seek forward and backward;
- playback speed control where supported by the browser;
- fullscreen/native iOS presentation;
- rotation between portrait and landscape;
- recovery after locking/unlocking the device;
- a cold start and a repeated start of the same video.

The HTML5 source intentionally does not force `video/mp4`; the protected endpoint response MIME type is used for browser negotiation.

## Upgrade notes

Release `1.1.19-beta` scopes fallback fullscreen CSS to Drive Resource containers. This prevents a generic fullscreen class from interfering with course cards, activity links, breadcrumbs or theme navigation controls.

Release `1.1.18-beta` fixes the legacy XMLDB index dependency upgrade failure for `source`, `type` and progress fields.

Release `1.1.17-beta` introduces a dedicated `http_range_proxy` service and changes cold Google Drive PDF loading to immediate range proxying plus asynchronous cache warming.

After upgrade:

1. run Moodle upgrade so the new plugin version is registered;
2. purge all Moodle caches;
3. perform a browser hard refresh or clear the browser cache;
4. confirm cron is running;
5. confirm PHP cURL is available;
6. run the streaming and PDF cache validation below.

Always validate upgrades on a staging Moodle before commercial production deployment.

## Recovering unresponsive course links after an upgrade

If course cards or activity links do not respond after deploying Drive Resource, first deploy `1.1.19-beta` or newer and run:

```bash
php admin/cli/maintenance.php --disable
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Then force-refresh the browser with `Ctrl+F5` or open the site in a private window. Do not delete courses, activities or database tables.

To distinguish a theme/JavaScript issue from a server-side failure, open a course directly using:

```text
/course/view.php?id=<courseid>
```

- If the direct URL opens, inspect the browser console and theme JavaScript because navigation interception is client-side.
- If the direct URL returns an error, inspect the PHP/web-server log and complete the Moodle upgrade before further changes.

## Post-installation checklist

- confirm `thirdpartylibs.xml` is valid;
- confirm local PDF.js files are present;
- confirm `plyr.min.js` and `plyr.css` are present;
- confirm PageFlip files if that optional mode is used;
- confirm `$CFG->localcachedir` is writable;
- confirm Moodle cron/ad-hoc tasks execute;
- create and open a local protected PDF as a learner;
- create and open a Google Drive PDF with cold cache;
- verify first request is not blocked on complete cache warming;
- verify a later PDF request reports `HIT` after cron warming;
- test a large PDF on iPhone/iPad and Android;
- test protected video play and seek on iPhone Safari;
- inspect that no Google Drive source URL/file ID appears in protected viewer HTML;
- confirm progress and completion behavior;
- test backup and restore;
- test Privacy API export/delete.
