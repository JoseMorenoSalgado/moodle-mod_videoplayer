# Drive Resource developer guide

## Component identity

Moodle component:

```text
mod_videoplayer
```

Commercial product name: **Drive Resource**.

Do not rename the Moodle component. Existing installations depend on it for database tables, capabilities, backup/restore mappings and upgrade continuity.

## Engineering standards

Production changes must follow:

- Moodle Coding Style.
- PHP syntax compatible with the declared Moodle/PHP support matrix.
- SOLID boundaries between authorization, storage, upstream HTTP, viewers and progress.
- Moodle File API.
- Moodle External API.
- Moodle Privacy API.
- Moodle Backup & Restore API.
- Moodle Events API.
- Moodle Completion API.
- locally bundled runtime third-party assets only.

## Current production compatibility declaration

`version.php` currently declares:

```text
$plugin->requires = 2025041400;
```

That means the production baseline is Moodle 5.0+. Moodle 4.x support must not be claimed until a dedicated compatibility matrix is completed and the requirement is deliberately lowered.

## Main folders

```text
amd/src/                         AMD JavaScript sources
amd/build/                       production AMD bundles
backup/moodle2/                  backup and restore
classes/event/                   Moodle events
classes/external/                AJAX/external functions
classes/local/                   internal services
classes/privacy/                 Privacy API
classes/task/                    scheduled/ad-hoc tasks
db/                              schema, services, access and tasks
docs/                            engineering/product documentation
lang/                            language packs
templates/                       Moodle-owned Mustache viewers
thirdpartylibs/                  locally bundled third-party libraries
```

## Authorization boundary

`protected.php` must remain thin. Required order:

```text
resolve cm/course/instance
↓
require_login()
↓
context_module
↓
require_capability(mod/videoplayer:view)
↓
close session write lock
↓
dispatch protected bytes
```

Never expose:

- Google Drive file IDs.
- direct Google download URLs.
- Google preview URLs.
- OAuth access tokens.
- open-in-Drive links.

## Viewer routing

`view.php` owns viewer selection.

Supported routes:

```text
video        → video.mustache → Plyr/native HTML5
pdf          → pdfjs.mustache or book.mustache
document     → server PDF export → PDF viewer
spreadsheet  → server PDF export → PDF viewer
presentation → server PDF export → PDF viewer
image        → image.mustache
file         → no active inline viewer
```

Do not reintroduce a generic Google preview iframe.

Generic `/file/d/...` URLs cannot be reliably auto-typed without metadata. When `drive::detect_type()` returns `file`, require an explicit supported type instead of guessing.

## Local/cache delivery service

`classes/local/protected_stream.php` owns:

- Moodle private PDF delivery.
- local/cache byte ranges.
- PDF cache paths/keys.
- PDF cache validation.
- complete PDF cache warming.
- cache cleanup.

Do not add browser-facing upstream proxy logic to this class.

## Upstream range proxy

`classes/local/http_range_proxy.php` owns:

- one canonical outgoing `Range` request.
- `HEAD` and `If-Range` semantics.
- streamed cURL output.
- redirect-cookie continuity.
- response MIME resolution.
- safe `Content-Length`, `Content-Range`, `Accept-Ranges`, `ETag`, `Last-Modified` relay.
- rejection of unexpected HTML/XHTML/JSON interstitials.

Do not forward arbitrary browser headers upstream.

The proxy URL must always be generated internally from a validated activity record.

## PDF viewer development

### Standard viewer

Files:

```text
amd/src/pdfviewer.js
amd/build/pdfviewer.min.js
templates/pdfjs.mustache
styles_pdf_overlay.css
styles_pdf_mobile.css
```

Production requirements:

- source and critical production bundle must remain synchronized;
- render only the visible page;
- bound output scale for high-DPI devices;
- do not eagerly rasterize the entire document;
- support previous/next page;
- support zoom and fit-to-screen;
- support fullscreen;
- support mobile swipe;
- support text search without a CDN;
- save cumulative active time;
- save actual last page;
- calculate completion from observed pages rather than page number alone.

The repository CI checks `amd/src/pdfviewer.js` against `amd/build/pdfviewer.min.js`.

### Mobile stabilizer

Files:

```text
amd/src/pdfmobile.js
amd/build/pdfmobile.min.js
```

The mobile module may correct scrolling/viewport issues, but it must not resize the PDF canvas or override zoom controlled by `pdfviewer.js`.

### Protected book viewer

Files:

```text
amd/src/bookviewer.js
amd/build/bookviewer.min.js
templates/book.mustache
```

Keep it isolated from the standard searchable viewer. Any future progress-model changes must preserve actual resume state and must not infer completion merely from reaching the last page.

## Fast-first-byte PDF cache

Cold-cache flow:

```text
PDF.js range request
↓
protected.php authorization
↓
queue precache_pdf with duplicate suppression
↓
proxy requested bytes immediately
↓
cron warms complete PDF
↓
subsequent requests use local cache
```

Do not recompress or rasterize PDFs in the cache pipeline.

## Progress service contract

`classes/local/progress/progress_service.php` stores monotonic values for:

- progress/active time.
- completion percentage.
- completion state.
- total pages.
- cumulative time spent.

`lastpage` is intentionally different: it represents the actual last reported page and may move backward.

The service must not calculate completion automatically from `lastpage / totalpages`.

## AMD rules

Moodle production loads from `amd/build/`. A correct `amd/src/` file with a stale or missing build file is a production defect.

When changing AMD:

1. change `amd/src/`;
2. rebuild with Moodle Grunt when available;
3. commit the production bundle;
4. run CI syntax checks;
5. verify the browser network/console on staging.

## Security rules for content types

Only supported viewer types may be rendered inline.

Do not embed unknown generic files in a same-origin iframe. An upstream HTML file served through Moodle's origin can become an active-content security risk.

Unexpected HTML/JSON returned by an upstream download flow must be rejected before response headers/body are sent to the media/PDF client.

## Google Drive enterprise roadmap

The current release uses shareable Drive/Docs resources. Private enterprise integration requires a future site-owned OAuth/service-account Drive API layer.

That integration should:

- obtain metadata server-side;
- resolve MIME type from Drive metadata;
- access content/export through authenticated Google APIs;
- keep credentials/tokens server-side;
- preserve Moodle authorization before every browser-visible delivery.

## Database changes

For schema changes:

1. update `db/install.xml`;
2. add `db/upgrade.php` migration;
3. update backup/restore;
4. update Privacy API when personal data changes;
5. bump `version.php`.

A code-only release may bump `version.php` without a DB migration.

## CI

`.github/workflows/quality.yml` checks:

- PHP syntax on PHP 8.2 and 8.3;
- JavaScript syntax;
- XML well-formedness;
- critical AMD consistency;
- presence of local PDF.js/Plyr assets;
- no runtime CDN regression;
- no Google preview/iframe path in protected learner-facing files.

CI does not replace Moodle integration tests or physical-device testing.

## Commercial release gate

Before production:

- CI green.
- Moodle upgrade completes on staging.
- Moodle developer debugging clean.
- browser console clean.
- cron/ad-hoc tasks healthy.
- protected range protocol verified.
- cold/warm PDF cache verified.
- PDF search/zoom/fullscreen verified.
- progress/resume verified across sessions.
- physical iPhone Safari video seek/fullscreen/rotation verified.
- Android Chrome verified.
- no raw Drive URL/file ID in learner HTML.
- backup/restore tested.
- Privacy API export/delete tested.
