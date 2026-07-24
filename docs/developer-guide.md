# Drive Resource developer guide

## Component identity

The Moodle component remains:

```text
mod_videoplayer
```

The commercial product name is **Drive Resource**. Do not rename the Moodle component because existing installations depend on it for upgrades, capabilities, database tables and backup/restore mappings.

## Engineering standards

Production changes must follow:

- Moodle Coding Style and Moodle APIs.
- PHP 8.2+ compatible code.
- PSR-12 principles where they do not conflict with Moodle Coding Style.
- small, cohesive classes and methods.
- SOLID boundaries between HTTP delivery, storage, viewers and progress logic.
- local third-party assets only; no CDN runtime dependencies.
- Moodle File API, Privacy API, Backup & Restore API, Events API and Completion API requirements.

## Main folders

```text
amd/src/                         AMD JavaScript source files
amd/build/                       production AMD bundles
backup/moodle2/                  backup and restore logic
classes/event/                   Moodle event classes
classes/external/                AJAX/external API endpoints
classes/local/                   internal application/domain services
classes/privacy/                 Privacy API provider
classes/task/                    scheduled and ad-hoc tasks
db/                              schema, upgrade, services and tasks
docs/                            product and engineering documentation
lang/                            language packs
templates/                       Mustache templates
thirdpartylibs/                  locally bundled third-party libraries
styles.css                       intentionally empty global stylesheet
styles_activity.css              base viewer styles loaded only by view.php
```

## Service boundaries

Business logic must not be embedded in public scripts or external API classes.

### Progress and gamification

```text
classes/local/progress/progress_service.php
classes/local/gamification/reward_service.php
```

External APIs validate parameters, login, context and capability, then delegate to these services.

### Local and cached file delivery

```text
classes/local/protected_stream.php
```

This service owns:

- Moodle private File API PDF streaming.
- byte-range responses for readable local/cache files.
- PDF cache paths and cache keys.
- PDF cache freshness checks.
- complete Google Drive PDF cache warming.
- Google Drive confirmation-token handling used during cache warming.
- cache cleanup.

Do not add new upstream browser proxy behavior to this class.

### Upstream HTTP range delivery

```text
classes/local/http_range_proxy.php
```

This service owns protected HTTP proxy behavior for resources that remain upstream:

- one validated `Range` request.
- `If-Range` forwarding.
- `HEAD` support.
- `200`, `206` and `416` semantics.
- safe relay of `Content-Length`, `Content-Range`, `Accept-Ranges`, `ETag` and `Last-Modified`.
- streamed cURL output without loading the complete resource into PHP memory.
- generic upstream failures that do not expose Google response bodies or source URLs.

**Never send a manual `Range` header and `CURLOPT_RANGE` for the same request.** `CURLOPT_RANGE` is the canonical outgoing range mechanism.

## `protected.php` rules

`protected.php` must remain a thin authorization and orchestration endpoint.

Required sequence:

```text
required_param(id)
↓
get course module
↓
get course
↓
get activity instance
↓
require_login(course, true, cm)
↓
context_module::instance(cmid)
↓
require_capability(mod/videoplayer:view)
↓
close Moodle session write lock
↓
dispatch to protected_stream or http_range_proxy
```

Never render or return:

- Google Drive `fileId`.
- direct Google download URL.
- Google preview URL.
- open-in-Drive URL.

## Fast-first-byte PDF algorithm

Cold Google Drive PDFs must not block the first viewer request while the complete document is downloaded into cache.

```text
PDF.js requests protected.php
↓
Fresh local cache?
├─ yes → protected_stream::send_file() → HIT
└─ no  → queue precache_pdf with duplicate suppression
          ↓
          immediately proxy requested range → MISS_QUEUED
          ↓
          Moodle cron warms full PDF cache
          ↓
          later requests → HIT
```

The algorithm does not recompress or transcode PDF content, so document quality remains unchanged.

Expected cache diagnostics:

```text
LOCAL         Moodle private PDF served directly.
HIT           Fresh server-side PDF cache served.
MISS_QUEUED   Cold PDF proxied immediately and cache warm task queued.
MISS          Cold PDF proxied; cache task could not be queued.
BYPASS        Cache is disabled or not applicable.
RANGE_INVALID Invalid range for a local/cached file.
```

`WARMED` and `WARM_FAILED` may still appear from legacy/internal cache-warming flows and should not be used as the primary first-request path.

## Video development

The protected video path is:

```text
templates/video.mustache
↓
HTML5 <video>
↓
amd/src/plyr.js progressive enhancement
↓
protected.php
↓
http_range_proxy
```

Rules:

- keep `playsinline` and `webkit-playsinline` for iOS.
- use `preload="metadata"` unless a measured requirement justifies otherwise.
- do not hardcode `type="video/mp4"` when the protected endpoint can return different MIME types.
- keep native HTML5 controls as the failure fallback.
- do not disable playback-rate support while exposing a speed selector.
- treat browser UI restrictions as deterrents, not as the security boundary.
- preserve valid `206 Partial Content` behavior for seeking and Safari/iOS playback.

When changing `amd/src/plyr.js`, rebuild and commit the corresponding production file under `amd/build/`.

## PDF viewer development

### Protected book viewer

Use:

```text
amd/src/bookviewer.js
templates/book.mustache
```

Desktop displays a two-page spread. Mobile displays one page at a time. PDF data is always loaded through `protected.php`.

Performance rules:

- render only visible pages at high quality.
- prefetch only a small neighboring working set.
- cap canvas cache size.
- avoid rendering the complete PDF eagerly.
- preserve device-pixel-ratio quality within a bounded output scale.
- cancel or ignore stale renders after resize/orientation changes.

### Standard PDF viewer

Use `amd/src/pdfviewer.js` for one-page rendering with zoom and fit controls.

### Mobile PDF stabilizer

Use `amd/src/pdfmobile.js` only for viewport/layout corrections. Authorization, file access and PDF loading do not belong in this module.

### Ebook viewer

`amd/src/ebookviewer.js` may use locally bundled StPageFlip. It must keep a PDF.js fallback and must never load PageFlip or PDF.js from a CDN.

## CSS and theme isolation

Moodle automatically compiles a module's root `styles.css` into the global theme bundle. Any rule placed there can affect every course format and page, including pages where Drive Resource is not open.

Production rules:

1. Keep `styles.css` free of presentation rules.
2. Put shared viewer presentation in `styles_activity.css`.
3. Load `styles_activity.css` explicitly from `view.php` before specialized stylesheets.
4. Prefix every selector, animation and custom property with `mod-videoplayer` or `drive-resource`.
5. Never use generic state classes such as `.fullscreen`, `.is-active`, `.overlay`, `.loading` or `.is-fallback-fullscreen` without a Drive Resource root selector.
6. Verify that course formats such as Tiles/Mosaico still animate and navigate normally after every CSS change.

Do not modify third-party course formats to compensate for Drive Resource CSS. The module must remain self-contained and compatible through strict presentation boundaries.

## Database changes

For schema changes:

1. update `db/install.xml` for new installs;
2. add an upgrade step in `db/upgrade.php` for existing installs;
3. update backup and restore when activity/user fields change;
4. update Privacy API when personal data changes;
5. bump `version.php`.

A code-only release may bump `version.php` without a database upgrade step.

## Progress save flow

```text
viewer AMD module
↓
core/ajax
↓
mod_videoplayer_save_progress
↓
classes/external/save_progress.php
↓
progress_service
↓
reward_service
↓
videoplayer_views / videoplayer_rewards
↓
Moodle Events API + Completion API
```

## Backup, restore and privacy

New activity configuration fields must be reviewed in both backup and restore code.

New personal data must be reviewed in:

```text
classes/privacy/provider.php
```

Current personal data includes reading/progress state, completion percentage, last page, total pages, active time, points and rewards.

## Events

Meaningful state transitions use Moodle events:

- `course_module_viewed`.
- `progress_updated`.
- `resource_completed`.
- `reward_awarded`.

Avoid emitting high-frequency events on every media `timeupdate` event.

## Commercial release checklist

Before release:

- run Moodle PHP code checks and PHP syntax validation.
- rebuild AMD production bundles.
- run JavaScript lint/build checks.
- verify no CDN references.
- verify third-party libraries in `thirdpartylibs.xml`.
- verify `styles.css` contains no viewer presentation rules.
- verify all activity styles are loaded only from `view.php`.
- test Tiles/Mosaico and another course format with animated navigation enabled.
- test fresh install and upgrade.
- test backup/restore.
- test Privacy API export/delete.
- test guest and unenrolled access denial.
- test Google Drive video start, pause, seek and resume on iPhone Safari.
- test Android Chrome and Moodle app WebView.
- test video `Range: bytes=0-1`, mid-file ranges and invalid ranges.
- test PDF cold-cache first open and later `HIT` behavior.
- test large PDFs on low-memory mobile devices.
- test PDF zoom/fullscreen/orientation changes.
- test Moodle debug developer mode with no new warnings.
- inspect browser console and network panel for failed ranges or leaked Drive URLs.
