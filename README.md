# Drive Resource for Moodle

**Drive Resource** is a Moodle activity module for publishing protected learning resources from Google Drive and Moodle private storage.

The internal Moodle component remains `mod_videoplayer` for compatibility with existing installations. The user-facing commercial product name is **Drive Resource**.

## Features

- Google Drive resources delivered through Moodle-owned protected endpoints.
- Videos, PDFs, images, documents, spreadsheets and presentations.
- Local protected PDFs stored through Moodle File API.
- Local PDF.js rendering with no CDN dependency.
- HTML5 video playback progressively enhanced with local Plyr assets.
- iPhone/iPad-oriented video streaming with proper byte-range semantics.
- `Range`, `If-Range`, `HEAD`, `206 Partial Content` and `416` handling for protected upstream delivery.
- Fast-first-byte Google Drive PDF loading.
- Asynchronous, deduplicated PDF cache warming through Moodle ad-hoc tasks.
- Protected PDF cache outside the web root.
- Responsive protected book viewer.
- Fullscreen, page navigation and mobile swipe behavior.
- Reading/progress tracking and Moodle Completion API integration.
- Moodle Events API integration.
- Optional watermark and gamification features.
- Backup & Restore support.
- Privacy API support.
- Teacher progress reporting.
- Activity-only viewer CSS that cannot alter third-party course formats or site navigation.

## Requirements

- Moodle 4.x or Moodle 5.x target environment.
- PHP 8.2+ recommended.
- PHP cURL extension.
- HTTPS for production use.
- Moodle cron configured and running.
- Writable `$CFG->localcachedir`.
- Required third-party libraries bundled locally.

## Required local libraries

Drive Resource must not use CDN assets in production.

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
thirdpartylibs/pageflip/page-flip.browser.js   # optional
thirdpartylibs/pageflip/page-flip.css         # optional
```

PageFlip is optional. Protected PDF.js rendering remains available without it.

## Installation

Copy the plugin to:

```text
mod/videoplayer
```

Run Moodle upgrade:

```bash
php admin/cli/upgrade.php
```

Then purge Moodle caches and verify cron/ad-hoc task execution.

See `docs/installation.md` for production validation.

## CSS isolation

Moodle compiles a module's root `styles.css` into the site-wide theme bundle. Drive Resource therefore keeps `styles.css` intentionally free of viewer rules.

The complete PDF, ebook, video and fullscreen presentation is stored in:

```text
styles_activity.css
```

`view.php` requests that file only on `mod/videoplayer/view.php`. This prevents Drive Resource from changing or blocking navigation in third-party course formats such as Tiles/Mosaico, themes, cards, modals, course indexes or unrelated plugins.

After upgrading from `1.1.19-beta` or earlier, deploy the complete plugin directory and purge Moodle and browser caches so the previous global CSS bundle is discarded.

## Protected delivery architecture

```text
Google Drive link / Moodle private PDF
↓
Drive Resource activity
↓
protected.php
↓
require_login + course module + context_module + capability
↓
protected_stream OR http_range_proxy
↓
Moodle-owned viewer
↓
learner
```

### Local and cached files

`classes/local/protected_stream.php` handles trusted local/cache file delivery and byte ranges.

### Upstream resources

`classes/local/http_range_proxy.php` handles protected upstream HTTP streaming. It forwards a single validated byte range and safely preserves the metadata needed by Safari/iOS media playback and PDF.js range loading.

The plugin-owned protected video/PDF UI does not expose raw Google Drive file IDs, download URLs or preview URLs.

## Fast-first-byte PDF loading

When a Google Drive PDF is already cached:

```text
protected.php → local cache → Range response → PDF.js
```

When the PDF cache is cold:

```text
PDF.js range request
↓
protected.php validates Moodle access
↓
queue deduplicated precache_pdf task
↓
proxy requested bytes immediately
↓
Moodle cron warms complete PDF cache
↓
later requests use local cache
```

This avoids making the first page wait for a complete PDF download. The PDF is not recompressed, rasterized or transcoded, so document quality is preserved.

Cache files are stored under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Possible diagnostic headers include:

```text
X-Drive-Resource-Cache: LOCAL
X-Drive-Resource-Cache: HIT
X-Drive-Resource-Cache: MISS_QUEUED
X-Drive-Resource-Cache: MISS
X-Drive-Resource-Cache: BYPASS
```

## iPhone/iPad video playback

Protected video playback uses an HTML5 `<video>` element and local Plyr enhancement.

The current implementation:

- keeps `playsinline` and `webkit-playsinline`;
- uses `preload="metadata"` to reduce initial transfer pressure;
- does not force every source to `video/mp4`;
- preserves native HTML5 controls as a fallback;
- uses the protected range proxy for seeking;
- avoids duplicate upstream `Range` headers;
- safely relays valid partial-content metadata.

Production QA should include Safari on physical iPhone/iPad devices because simulator behavior does not cover every media-stack edge case.

## Security model

The enforceable controls are server-side:

```text
require_login()
context_module
mod/videoplayer:view
protected.php
protected_stream / http_range_proxy
```

Browser controls such as hiding download buttons, disabling right click and showing watermarks are deterrents, not DRM.

See `docs/security.md` for the complete threat model and release checklist.

## Progress tracking

Drive Resource tracks supported reading/viewing state including:

- active time;
- completion percentage;
- last page;
- total pages;
- completion state;
- points/rewards when enabled.

Moodle completion and events are updated through the plugin service layer.

## JavaScript AMD build

Development sources:

```text
amd/src/
```

Production bundles:

```text
amd/build/
```

Build with Moodle tooling:

```bash
npx grunt amd
```

or the equivalent `grunt amd` command in a configured Moodle development environment.

## Documentation

- `docs/architecture.md`
- `docs/database.md`
- `docs/developer-guide.md`
- `docs/installation.md`
- `docs/manual-test-checklist.md`
- `docs/security.md`

## Compatibility

Current development release:

- Release: `1.1.20-beta`
- Moodle component: `mod_videoplayer`
- Product name: Drive Resource
- Target Moodle versions: 4.x and 5.x
- Recommended PHP: 8.2+

## License

GNU GPL v3 or later.

Third-party libraries are documented in `thirdpartylibs.xml` and must remain locally bundled for production use.

## Maintainer

Elearning Cloud  
https://elearningcloud.io
