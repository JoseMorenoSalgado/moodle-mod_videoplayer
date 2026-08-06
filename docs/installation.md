# Drive Resource installation

## Supported platform

- Moodle 5.0, 5.1 or 5.2.
- Minimum core version: `2025041400`.
- PHP 8.2 or 8.3.
- PHP cURL and the standard extensions required by Moodle 5.0.
- HTTPS, working Moodle cron and writable `$CFG->localcachedir`.

Moodle 4.x is not supported by release `1.1.26-beta`.

## Install or upgrade

Copy the complete plugin directory to:

```text
mod/videoplayer
```

Verify these required files exist:

```text
mod/videoplayer/version.php
mod/videoplayer/styles_activity.css
mod/videoplayer/styles_pageflip_fix.css
mod/videoplayer/templates/pdfjs.mustache
mod/videoplayer/amd/build/pdfjsloader.min.js
mod/videoplayer/amd/build/ebookviewer.min.js
mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs
mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs
mod/videoplayer/thirdpartylibs/pageflip/page-flip.browser.js
mod/videoplayer/thirdpartylibs/pageflip/page-flip.css
mod/videoplayer/thirdpartylibs/plyr/plyr.css
mod/videoplayer/thirdpartylibs/plyr/plyr.min.js
```

From the Moodle application root run:

```bash
php admin/cli/maintenance.php --enable
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

For cPanel PHP 8.3:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/maintenance.php --enable
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/upgrade.php --non-interactive
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/purge_caches.php
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/maintenance.php --disable
```

Release `1.1.26-beta` enforces one-page protected Ebook reading on phones and two facing pages on desktop-sized viewers. It renders the visible page or spread lazily and prefetches only adjacent pages during idle time.

Release `1.1.25-beta` fixes mobile PDF.js initialization. All PDF viewers use `mod_videoplayer/pdfjsloader`, which loads the local ES module using a native `<script type="module">` element and configures the local worker only after validating the PDF.js API.

Release `1.1.24-beta` restored the protected Ebook/PageFlip viewer and exposed a real PDF display-mode selector. Historical activities that stored the hidden value `standard` are interpreted as Ebook activities. The explicit standard PDF.js mode stores `pdfjs`.

Release `1.1.23-beta` updated protected Google Drive blob delivery, preserved optional sharing `resourcekey` values and rejected HTML/error responses before they reached a media viewer.

Release `1.1.22-beta` changed `videoplayer.videourl` to a nullable XMLDB field. Local protected PDFs do not have a Google Drive URL. Existing URLs are preserved.

Clear Moodle caches and the browser/site cache after every upgrade involving AMD, Mustache, CSS or protected media delivery.

## Moodle 5.0 metadata

`version.php` declares:

```php
$plugin->requires = 2025041400;
$plugin->supported = [500, 502];
```

Moodle accepts the plugin on branches 5.0 through 5.2 and rejects older branches outside the product contract.

## Google Drive access requirements

For link-based protected video/PDF delivery, the file must:

- be accessible to anyone with the sharing link, unless an authenticated Drive API integration is configured;
- allow downloading by viewers;
- remain a normal Drive blob file for video/audio/image delivery;
- retain the complete original sharing URL when it contains `resourcekey`.

Drive Resource cannot bypass an owner or organisation policy that disables downloads. A file requiring Google account login returns an access page rather than media bytes and is rejected by the protected proxy.

Do not remove query parameters from the sharing URL before saving the activity. The plugin keeps `resourcekey` server-side and never renders it to the learner.

## Local libraries

All viewer dependencies are local. No runtime CDN is allowed.

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
```

PDF.js remains available when PageFlip cannot initialise. Native HTML5 controls remain available when Plyr enhancement cannot initialise.

## PDF.js ESM loading

PDF.js is an ES module. It must not be requested as a RequireJS/AMD module.

```text
ebookviewer / bookviewer / pdfviewer
↓
mod_videoplayer/pdfjsloader
↓
<script type="module" src="pdf.min.mjs">
↓
window.pdfjsLib validation
↓
pdf.worker.min.mjs
```

The production bundle must exist at:

```text
amd/build/pdfjsloader.min.js
```

A valid production bundle contains native module-script creation and does not contain `_systemImportTransformerGlobalIdentifier`.

## PDF display modes

### Protected Ebook

This is the default mode.

- Phones show exactly one page at a time in portrait and landscape.
- Desktop-sized viewer containers show two facing pages.
- PDF.js renders the active page or spread first.
- Only adjacent pages are prefetched during browser idle time.
- PageFlip provides swipe, corner, shadow, paper, centre-gutter and page-turn effects.
- Resize or orientation changes preserve the current page.
- Reading progress, completion, optional watermark and gamification remain available.

### Standard PDF.js

This mode uses a single PDF.js canvas and provides zoom, fit-to-screen, previous/next page and fullscreen controls.

The teacher selects the mode in the activity settings. Existing activities created while the field was hidden automatically use Protected Ebook.

## Cron and cache

Cold Google Drive PDFs proxy requested ranges immediately and queue a deduplicated ad-hoc task to warm the complete cache.

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Cron must process ad-hoc tasks frequently. The PHP/web user must be able to create directories and files under `$CFG->localcachedir`.

## Functional validation on Moodle 5.0

After installation, verify:

1. Administration reports Drive Resource `1.1.26-beta` and version `2026080505`.
2. A teacher can create and edit an activity.
3. The PDF display-mode selector offers Protected Ebook and Standard PDF.js.
4. An enrolled learner can open the activity.
5. Guest and unauthorised users are denied.
6. A local protected PDF opens with the Ebook page-turn effect.
7. A physical phone shows one page in portrait.
8. Rotating the same phone to landscape keeps one page.
9. A desktop-width browser shows two facing pages.
10. The first Ebook paint does not render the complete PDF; visible and adjacent pages render progressively.
11. Swipe, previous/next, page corners and fullscreen work.
12. A Google Drive PDF opens with cold cache and later reports a cache hit.
13. Standard PDF.js mode provides working zoom and fit controls.
14. The affected Android/iOS browser no longer displays a `workerSrc` TypeError.
15. The Network panel loads `pdf.min.mjs` as a JavaScript module and `pdf.worker.min.mjs` from the plugin.
16. A protected video starts, exposes a non-zero duration, pauses and seeks.
17. Progress and completion persist.
18. Backup and Restore preserve configuration and local files.
19. Privacy export/delete completes.
20. Tiles/Mosaico and a standard course format navigate normally before and after opening Drive Resource.

## Mobile PDF.js troubleshooting

The error below indicates an obsolete AMD bundle or browser cache:

```text
Cannot set properties of undefined (setting 'workerSrc')
```

Confirm the installed release is `1.1.26-beta`, confirm `amd/build/pdfjsloader.min.js` exists, purge Moodle caches and clear the site's stored data in the mobile browser.

The browser Network panel should load:

```text
mod/videoplayer/amd/build/pdfjsloader.min.js
mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs
mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

`pdf.min.mjs` must not be requested through RequireJS. A failure to load the local module should produce the controlled PDF.js viewer error, not an unhandled `workerSrc` exception.

## Ebook troubleshooting

When a PDF opens without the page-turn effect or uses the wrong number of pages, verify:

```text
styles_pageflip_fix.css
amd/build/ebookviewer.min.js
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

Confirm the activity display mode is **Protected Ebook**. Purge Moodle caches and clear the browser site cache after replacing the plugin.

The Ebook page should contain:

```text
data-display-mode="ebook"
data-region="ebook-stage"
```

At runtime the Ebook root/stage receives one of these layout classes:

```text
is-phone-single-page
is-desktop-double-page
```

A phone must retain `is-phone-single-page` after rotation. A desktop-width container should receive `is-desktop-double-page`.

## Protected video troubleshooting

A player showing `00:00 / 00:00` means the browser did not receive usable media metadata. Inspect:

```text
mod/videoplayer/protected.php?id=<cmid>
```

Expected response:

```text
HTTP 200 or 206
Content-Type: video/*
X-Drive-Resource-Status: MEDIA
```

Possible failure diagnostics:

```text
HTTP 502
X-Drive-Resource-Status: UPSTREAM_CONTENT_REJECTED
X-Drive-Resource-Status: UPSTREAM_REQUEST_FAILED
```

## Range validation

Authenticated requests to Moodle `protected.php` should support:

```text
Range: bytes=0-1
Range: bytes=<middle>-<middle+chunk>
```

Expected valid response:

```text
HTTP 206
Accept-Ranges: bytes
Content-Range: bytes start-end/total
Content-Length: requested-length
X-Drive-Resource-Status: MEDIA
```

Invalid ranges should return `416`. Never test by exposing the upstream Google Drive URL.

## Automated validation

The repository workflow `.github/workflows/moodle-50-ci.yml` tests Moodle `MOODLE_500_STABLE` with:

- PHP 8.2 and 8.3;
- MariaDB 10.11 and PostgreSQL 15;
- PHP lint, PHPCS, PHPDoc, plugin validation, savepoints, Mustache, Grunt and PHPUnit;
- a permanent PDF.js native-ESM bundle contract;
- a permanent responsive Ebook/lazy-rendering contract.

A green workflow is required before commercial release, but it does not replace staging tests on the actual Moodle server, theme, course format and physical mobile/desktop devices.

## Recovery after a failed upgrade

Do not delete courses, activities, tables or physical database indexes. Keep maintenance mode enabled, restore the previous complete `mod/videoplayer` directory if necessary, purge caches and inspect the first PHP/XMLDB error.

Drive Resource does not require editing `format_tiles` or any third-party course-format plugin.

## Upgrade to 1.1.27-beta

1. Replace the plugin code and visit Site administration > Notifications, or run the Moodle CLI upgrade.
2. Confirm the `videoplayer.displaymode` default is `pdfjs`; the upgrade converts legacy modes automatically.
3. Purge all Moodle caches and reset PHP OPcache.
4. Purge Cloudflare, NGINX or other reverse-proxy caches for `/mod/videoplayer/`.
5. Test one protected PDF on Android, iPhone, tablet and desktop. The response from `protected.php` must start with `%PDF-` and use `Content-Type: application/pdf`.
