# Drive Resource Architecture

Drive Resource is a Moodle activity module for publishing protected Google Drive and Moodle-local resources. The Moodle component remains `mod_videoplayer`; the commercial product name is **Drive Resource**.

## Production flow

```text
Google Drive share link or Moodle private PDF
↓
Drive Resource activity
↓
view.php selects a supported Moodle-owned viewer
↓
protected.php
↓
course module + course + activity instance
↓
require_login()
↓
context_module + mod/videoplayer:view
↓
protected_stream OR http_range_proxy
↓
PDF.js / HTML5 video + Plyr / protected image
↓
learner
```

The protected learner-facing architecture does not use a Google Drive preview iframe.

## Supported viewer routes

### Video

```text
video.mustache
↓
HTML5 <video>
↓
local Plyr progressive enhancement
↓
protected.php
↓
http_range_proxy
↓
Google Drive server-side content URL
```

The browser only receives the Moodle `protected.php` URL.

### PDF and Google Workspace documents

The following resource types use local PDF.js:

- PDF.
- Google Docs exported server-side to PDF.
- Google Sheets exported server-side to PDF.
- Google Slides exported server-side to PDF.
- Moodle-local protected PDF.

Standard mode provides page navigation, zoom, fit-to-screen, fullscreen, mobile swipe and text search.

### Images

Images use a protected `<img>` viewer whose `src` is the Moodle `protected.php` endpoint.

### Unknown generic files

Unknown generic file types are not embedded as active same-origin HTML. Generic `drive.google.com/file/d/...` URLs do not contain MIME metadata; when automatic detection cannot determine the type, the teacher must explicitly choose a supported viewer type.

## Public protected endpoint

`protected.php` is an authorization/orchestration endpoint. Required sequence:

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
dispatch protected delivery
```

It must never render raw Google Drive file IDs, preview URLs or direct upstream URLs.

## Delivery services

### `classes/local/protected_stream.php`

Owns trusted local/cache delivery:

- Moodle private File API PDF access.
- single-range local file delivery.
- `200`, `206` and `416` behavior.
- PDF cache paths and cache keys.
- PDF signature validation.
- cache warming and cleanup.

### `classes/local/http_range_proxy.php`

Owns upstream browser-facing delivery:

- one validated outgoing byte range.
- `If-Range` only with a valid range request.
- `HEAD` support.
- streamed cURL output without full PHP buffering.
- redirect cookie continuity.
- bounded low-speed handling.
- safe response-header relay.
- MIME resolution from trusted upstream metadata.
- rejection of unexpected HTML/XHTML/JSON interstitials.

The proxy destination is derived server-side from a validated activity record. It is not a generic user-supplied URL proxy.

## PDF fast-first-byte flow

```text
PDF.js requests protected.php
↓
Moodle authorization
↓
Fresh local PDF cache?
├─ yes → protected_stream::send_file() → HIT
└─ no  → queue duplicate-suppressed precache_pdf task
          ↓
          immediately proxy requested bytes → MISS_QUEUED
          ↓
          Moodle cron warms complete PDF cache
          ↓
          later requests → HIT
```

The PDF is not recompressed, rasterized or transcoded.

Cache location:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

## PDF rendering and memory strategy

The standard viewer:

- renders only the visible page;
- prefetches neighboring PDF page objects only;
- bounds device-pixel-ratio rendering;
- caches a limited number of extracted page-text entries for search;
- does not eagerly rasterize the complete document;
- restores the actual last saved page.

The mobile stabilizer does not resize the canvas and does not override user zoom.

## Progress model

`videoplayer_views` stores:

- cumulative progress/active time;
- completion percentage;
- actual last page;
- total pages;
- completion state;
- cumulative time spent;
- points.

For the standard PDF viewer, completion percentage is based on pages observed by the viewer and persisted monotonically. `lastpage` is not monotonic: it represents the actual last reported page so resume behavior is accurate.

The server does not infer PDF completion merely from `lastpage / totalpages`.

## Events and completion

The module emits:

- `course_module_viewed`.
- `progress_updated`.
- `resource_completed`.
- `reward_awarded`.

Completion transitions are integrated with Moodle Completion API.

## Backup, restore and privacy

Backup/restore covers activity configuration, local protected PDFs and user data when included.

Privacy API covers progress, reading state, completion data and rewards.

## Google Drive access model

Release `1.1.18-beta` uses server-side access to shareable Google Drive/Google Docs resources. It does not yet provide site-owned OAuth/service-account Google Drive API access for private enterprise files.

The enterprise target is:

```text
Moodle-authorized request
↓
server-owned Google OAuth credentials
↓
Google Drive API metadata/content access
↓
protected_stream/http_range_proxy equivalent delivery layer
↓
Moodle-owned viewer
```

That future integration must preserve the existing Moodle authorization boundary and must not expose Google access tokens or source URLs to learners.
