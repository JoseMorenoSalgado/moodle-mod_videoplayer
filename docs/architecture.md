# Drive Resource Architecture

Drive Resource is a Moodle activity module for publishing protected Google Drive and Moodle-local resources inside courses. The internal component remains `mod_videoplayer`; the commercial product name is **Drive Resource**.

## Target flow

```text
Google Drive link or Moodle private file
↓
Drive Resource activity
↓
protected.php
↓
require_login + course module + context_module + capability
↓
Local protected file OR protected cache OR http_range_proxy
↓
Local PDF.js / HTML5 video / supported resource viewer
↓
Progress + completion + events
```

The learner-facing browser never receives a raw Google Drive source URL from the plugin-owned video or PDF viewers.

## Resource sources

1. **Google Drive** resources are resolved server-side and delivered through `protected.php` where protected delivery is supported.
2. **Local protected PDFs** are stored in Moodle private File API storage under the `mod_videoplayer/localpdf` file area.

Local PDFs are never placed in the web root.

## Public protected endpoint

`protected.php` is intentionally small. It must always validate:

- course module;
- course;
- activity instance;
- `require_login()`;
- `context_module`;
- `mod/videoplayer:view`.

After authorization it delegates delivery to internal services.

## Protected delivery services

### `classes/local/protected_stream.php`

Owns local and cached-file delivery primitives:

- Moodle File API PDF streaming;
- local byte-range support;
- PDF cache key generation;
- PDF cache freshness checks;
- full Google Drive PDF cache warming;
- Google Drive confirmation-token retry used by cache warming;
- stale cache cleanup;
- `X-Drive-Resource-Cache` diagnostics.

### `classes/local/http_range_proxy.php`

Owns upstream browser-facing streaming:

- forwards one validated byte range through cURL;
- uses `CURLOPT_RANGE` as the single outgoing Range source;
- supports `HEAD`, `Range` and `If-Range` semantics;
- streams chunks without buffering complete media in PHP memory;
- safely relays `206`, `Content-Range`, `Content-Length`, `Accept-Ranges`, `ETag` and `Last-Modified` where applicable;
- prevents upstream error bodies from being returned as successful protected media;
- sanitizes relayed response header values.

Separating local-file streaming from upstream HTTP range proxying keeps each service focused and avoids mixing browser protocol behavior with Moodle File API storage behavior.

## Google Drive PDF fast-first-byte cache flow

```text
PDF.js requests protected.php
↓
Moodle authorization
↓
Fresh local cache exists?
├─ yes → protected_stream::send_file() with Range support (HIT)
└─ no  → queue deduplicated precache_pdf ad-hoc task (MISS_QUEUED)
          ↓
          proxy requested range immediately through http_range_proxy
          ↓
          Moodle cron warms complete PDF cache in background
          ↓
          later requests use local cached PDF
```

This design avoids blocking the first PDF open on a complete Google Drive download. The original PDF bytes are preserved; no image recompression or PDF transcoding is performed.

Cache files remain outside the web root under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

## Video delivery flow

```text
HTML5 <video> / Plyr
↓
protected.php?id=<cmid>
↓
http_range_proxy
↓
Google Drive download endpoint
```

The proxy preserves valid partial-content metadata required for seeking and mobile media playback. The HTML5 source does not force a `video/mp4` type, allowing the browser to negotiate the MIME type returned by the protected endpoint.

On Apple mobile devices the player keeps inline playback attributes and allows the native media layer to handle iOS-specific presentation behavior while Plyr provides the enhanced UI when available.

## Viewers

### Protected PDF book viewer

`amd/src/bookviewer.js` uses locally bundled PDF.js. Desktop renders a two-page spread; mobile renders one page at a time.

### Standard PDF viewer

`amd/src/pdfviewer.js` provides one-page PDF.js rendering when required.

### Ebook viewer

`amd/src/ebookviewer.js` uses local PDF.js with optional local StPageFlip assets. No CDN is permitted.

### Video viewer

`templates/video.mustache` renders the protected HTML5 media element and `amd/src/plyr.js` progressively enhances it with locally bundled Plyr. Native HTML5 controls remain the fallback.

## Presentation and CSS isolation

Moodle compiles `mod/videoplayer/styles.css` into the site-wide theme CSS. For that reason, the root file must remain free of viewer presentation rules.

The presentation boundary is:

```text
course page / third-party format
↓
no Drive Resource viewer CSS

mod/videoplayer/view.php
↓
styles_activity.css
↓
viewer-specific CSS files
↓
Drive Resource template root
```

`view.php` explicitly loads `styles_activity.css` before the more specialized PDF, book and visual refinement stylesheets. This guarantees that viewer rules are present only while rendering a Drive Resource activity and cannot modify Tiles/Mosaico navigation, course cards, Moodle modals, course indexes, theme components or unrelated plugins.

New root-level CSS must not be added to `styles.css`. New presentation code belongs in an activity-only stylesheet and all selectors must remain prefixed with `mod-videoplayer`.

## Progress and completion

Progress is saved through `mod_videoplayer_save_progress` and delegated to internal services:

```text
viewer AMD module
↓
core/ajax
↓
classes/external/save_progress.php
↓
classes/local/progress/progress_service.php
↓
classes/local/gamification/reward_service.php
↓
videoplayer_views / videoplayer_rewards
↓
Moodle Events API + Completion API
```

Tracked PDF state includes active time, last page, total pages, completion percentage, completion state and optional points/rewards.

## Events

The module emits:

- `course_module_viewed`;
- `progress_updated`;
- `resource_completed`;
- `reward_awarded`.

## Backup and restore

Backup and restore include activity configuration, local PDF files, and user progress/rewards when user data is included.

## Privacy API

Privacy API export/delete covers progress, reading state, completion data and reward data.

## Security boundary

Browser restrictions such as hiding download controls or blocking right click are deterrents only. The enforceable boundary is Moodle server-side authorization in `protected.php`. Raw `fileId`, direct Google Drive download URLs and preview URLs must not be exposed by plugin-owned protected viewers.
