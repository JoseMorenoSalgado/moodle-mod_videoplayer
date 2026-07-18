# Drive Resource for Moodle

**Drive Resource** is a Moodle activity module for publishing protected learning resources from Google Drive and Moodle private storage.

The internal Moodle component remains `mod_videoplayer` for upgrade compatibility. The commercial product name is **Drive Resource**.

## Current production architecture

```text
Google Drive share link / Moodle private PDF
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

Supported protected viewer paths:

- Video: HTML5 + locally bundled Plyr.
- PDF: locally bundled PDF.js.
- Google Docs: server-side PDF export + local PDF.js.
- Google Sheets: server-side PDF export + local PDF.js.
- Google Slides: server-side PDF export + local PDF.js.
- Images: protected Moodle endpoint + native responsive image viewer.
- Local PDFs: Moodle File API + protected byte-range delivery + local PDF.js.

The learner-facing protected paths do not use the Google Drive preview iframe and do not render raw Drive file IDs, preview URLs or direct download URLs in the plugin-owned viewer HTML.

## Features

- Server-side Moodle authorization before protected bytes are delivered.
- `Range`, `If-Range`, `HEAD`, `206 Partial Content` and `416` handling.
- Dedicated upstream `http_range_proxy` service.
- Rejection of unexpected HTML/JSON interstitial responses before they reach media/PDF viewers.
- Redirect cookie continuity for Google download flows.
- HTML5 video with local Plyr enhancement and native fallback.
- iPhone/iPad inline playback attributes and protected seeking.
- Local PDF.js with page navigation, zoom, fit-to-screen, fullscreen and text search.
- Responsive mobile PDF layout with bounded device-pixel-ratio rendering.
- Fast-first-byte Google Drive PDF loading.
- Deduplicated asynchronous PDF cache warming through Moodle ad-hoc tasks.
- Protected PDF cache outside the web root.
- Resume from the actual last saved page.
- PDF completion based on pages observed by the standard viewer rather than simply reaching the last page.
- Cumulative active-time tracking across PDF sessions.
- Moodle Completion API and Events API integration.
- Optional watermark and gamification features.
- Backup & Restore support.
- Privacy API support.
- Teacher progress reporting.

## Requirements

The current plugin metadata declares:

```text
$plugin->requires = 2025041400
```

Therefore the current production baseline is **Moodle 5.0 or newer**. Moodle 4.x remains a project compatibility target, but it must not be advertised as production-supported until a dedicated 4.x compatibility test matrix is completed and the minimum Moodle requirement is intentionally lowered.

Additional requirements:

- PHP version supported by the installed Moodle branch.
- PHP cURL extension.
- HTTPS in production.
- Moodle cron configured and running frequently.
- Writable `$CFG->localcachedir`.
- Required third-party libraries bundled locally.

For Moodle 5.2 deployments, follow Moodle's PHP requirements for that branch rather than relying only on the plugin's PHP syntax compatibility.

## Required local libraries

Runtime CDN dependencies are not permitted.

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
thirdpartylibs/pageflip/page-flip.browser.js   # optional book mode
thirdpartylibs/pageflip/page-flip.css         # optional book mode
```

## Installation

Copy the plugin to:

```text
mod/videoplayer
```

Run Moodle upgrade:

```bash
php admin/cli/upgrade.php
```

Then:

1. Purge Moodle caches.
2. Confirm cron and ad-hoc tasks run successfully.
3. Confirm `$CFG->localcachedir` is writable.
4. Run the staging checklist in `docs/manual-test-checklist.md`.
5. Validate physical iPhone/iPad playback before production deployment.

## Fast-first-byte PDF loading

Warm cache:

```text
protected.php → local cache → Range response → PDF.js
```

Cold cache:

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

The cache flow does not recompress, rasterize or transcode the PDF, preserving original document quality.

Cache location:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Diagnostic response states include:

```text
X-Drive-Resource-Cache: LOCAL
X-Drive-Resource-Cache: HIT
X-Drive-Resource-Cache: MISS_QUEUED
X-Drive-Resource-Cache: MISS
X-Drive-Resource-Cache: BYPASS
```

## iPhone/iPad video playback

Protected video playback:

- keeps `playsinline` and `webkit-playsinline`;
- uses `preload="metadata"`;
- keeps native HTML5 controls as a fallback;
- uses a single canonical upstream byte range;
- safely relays valid partial-content metadata;
- preserves redirect cookies in the cURL flow;
- rejects HTML login/warning pages instead of returning them as successful media.

Production QA must still include physical Safari/iOS testing because server-side protocol correctness cannot replace validation against the Apple media stack and the actual Google Drive file being used.

## Google Drive access model and enterprise roadmap

The current release supports server-side delivery of **shareable Google Drive/Google Docs resources**. Generic `drive.google.com/file/d/...` links do not contain MIME metadata, so teachers must explicitly choose Video, PDF or Image when automatic detection cannot determine the resource type.

The current release does **not yet implement a site-owned OAuth/service-account Google Drive API integration for private enterprise files**. That is a separate commercial-hardening milestone. A future Drive API integration should use authenticated server-side metadata/content access while preserving the same Moodle authorization boundary and protected viewer URLs.

## Security model

The enforceable controls are server-side:

```text
require_login()
context_module
mod/videoplayer:view
protected.php
protected_stream / http_range_proxy
```

Browser restrictions such as hiding download controls, disabling right click and displaying watermarks are deterrents, not DRM.

Unknown generic file types are not embedded as active same-origin HTML. Only explicitly supported protected viewer types are rendered.

See `docs/security.md` for the threat model.

## Progress tracking

Drive Resource stores supported viewing state including:

- cumulative active time;
- completion percentage;
- actual last page;
- total pages;
- completion state;
- optional points/rewards.

The standard PDF viewer maintains monotonic completion progress while keeping `lastpage` as the actual page where the learner stopped, allowing correct resume behavior.

## JavaScript AMD build

Development sources:

```text
amd/src/
```

Production bundles:

```text
amd/build/
```

Critical production AMD modules must exist in `amd/build/`. The repository CI checks JavaScript syntax and verifies that the production PDF viewer bundle matches its source implementation.

For normal development, rebuild AMD with Moodle tooling:

```bash
npx grunt amd
```

## Quality gates

The repository contains `.github/workflows/quality.yml` with checks for:

- PHP syntax on PHP 8.2 and 8.3;
- JavaScript syntax;
- XML well-formedness;
- critical AMD bundle consistency;
- required local third-party assets;
- runtime CDN regressions;
- reintroduction of Google preview/iframe paths into the protected learner-facing architecture.

CI is necessary but not sufficient. Staging Moodle, cron, real Drive files and physical mobile devices must be tested before release.

## Documentation

- `docs/architecture.md`
- `docs/database.md`
- `docs/developer-guide.md`
- `docs/installation.md`
- `docs/manual-test-checklist.md`
- `docs/security.md`

## Compatibility

Current release candidate:

- Release: `1.1.18-beta`
- Moodle component: `mod_videoplayer`
- Product name: Drive Resource
- Declared minimum Moodle build: `2025041400` (Moodle 5.0)
- Moodle 4.x: compatibility target, not yet production-declared
- PHP: use a version supported by the selected Moodle release

## License

GNU GPL v3 or later.

Third-party libraries are documented in `thirdpartylibs.xml` and must remain locally bundled for production use.

## Maintainer

Elearning Cloud  
https://elearningcloud.io
