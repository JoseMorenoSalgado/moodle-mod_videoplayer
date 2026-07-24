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

Place the complete plugin directory in:

```text
mod/videoplayer
```

Do not copy only the modified PHP files. Release `1.1.20-beta` requires the new activity-only stylesheet:

```text
mod/videoplayer/styles_activity.css
```

Run Moodle upgrade:

```bash
php admin/cli/upgrade.php --non-interactive
```

Then purge Moodle caches:

```bash
php admin/cli/purge_caches.php
```

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

Release `1.1.20-beta` removes all viewer presentation rules from Moodle's globally compiled module stylesheet. The same viewer rules now live in `styles_activity.css` and are loaded only when a Drive Resource activity is opened.

This release is self-contained. **Do not modify `format_tiles`, the active Moodle theme or any other third-party plugin.** Compatibility is achieved by preventing Drive Resource CSS from entering third-party course-format pages.

Release `1.1.19-beta` scoped the fallback fullscreen selector but still left the activity rules inside the global module stylesheet. Release `1.1.20-beta` completes the isolation.

Release `1.1.18-beta` fixes the legacy XMLDB index dependency upgrade failure for `source`, `type` and progress fields.

Release `1.1.17-beta` introduces a dedicated `http_range_proxy` service and changes cold Google Drive PDF loading to immediate range proxying plus asynchronous cache warming.

After upgrade:

1. confirm the complete `mod/videoplayer` directory was deployed;
2. confirm `styles_activity.css` exists;
3. run Moodle upgrade;
4. purge all Moodle caches;
5. perform a browser hard refresh or clear the browser cache;
6. confirm cron is running;
7. confirm PHP cURL is available;
8. validate both Drive Resource and the installed course format.

Always validate upgrades on a staging Moodle before commercial production deployment.

## Recovering Tiles/Mosaico animated navigation

When animated course navigation stopped immediately after installing an earlier Drive Resource update, deploy `1.1.20-beta` or newer. No edit to Tiles/Mosaico is required.

From the Moodle application root run:

```bash
php admin/cli/maintenance.php --enable
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

Then clear the browser cache or test in a private window. Moodle must regenerate the theme bundle without the previous Drive Resource viewer rules.

Validate:

1. open a Tiles/Mosaico course;
2. open and close several animated mosaics;
3. navigate through the course index;
4. open a Drive Resource activity;
5. return to the course and repeat animated navigation;
6. confirm the browser console has no click, overlay or JavaScript-navigation errors.

Do not delete courses, activities or database tables.

## Post-installation checklist

- confirm `thirdpartylibs.xml` is valid;
- confirm `styles.css` contains no viewer rules;
- confirm `styles_activity.css` is present;
- confirm local PDF.js files are present;
- confirm `plyr.min.js` and `plyr.css` are present;
- confirm PageFlip files if that optional mode is used;
- confirm `$CFG->localcachedir` is writable;
- confirm Moodle cron/ad-hoc tasks execute;
- test the installed third-party course format with its animated navigation enabled;
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
