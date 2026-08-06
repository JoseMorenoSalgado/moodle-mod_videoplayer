# Drive Resource Architecture

Drive Resource is a Moodle activity module. Its stable internal component is `mod_videoplayer`; its commercial product name is **Drive Resource**.

## Compatibility boundary

The current release supports Moodle 5.0–5.2 and requires core version `2025041400` or newer. Moodle 5.0 requires PHP 8.2+, which permits the typed PHP implementation used by the streaming services.

The compatibility contract is continuously checked against `MOODLE_500_STABLE` using PHP 8.2/8.3, MariaDB and PostgreSQL.

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
protected_stream OR http_range_proxy
↓
local PDF.js / HTML5 video / supported resource viewer
↓
progress + completion + events
```

The browser must not receive raw Google Drive file IDs, direct download URLs or preview URLs from plugin-owned PDF and video viewers.

## Moodle 5.0 APIs used

Drive Resource depends only on APIs present in Moodle 5.0:

- Activity module callbacks and `moodleform_mod`.
- File API and `stored_file`.
- Completion API.
- Events API.
- External API under `core_external`.
- Scheduled and ad-hoc Task API.
- Privacy API metadata, export and deletion interfaces.
- Backup and Restore API.
- XMLDB and `database_manager`.
- AMD modules `core/ajax` and `core/notification`.

No Moodle 5.1- or 5.2-only API is required by the current implementation.

## Resource sources

- Google Drive resources are resolved server-side and delivered through Moodle-owned endpoints.
- Local protected PDFs are stored in Moodle File API under `mod_videoplayer/localpdf`.
- Cached PDFs remain under `$CFG->localcachedir/mod_videoplayer/pdf/`, outside the web root.

## Protected endpoint

`protected.php` remains a thin orchestration endpoint. It validates the activity, course, login, module context and `mod/videoplayer:view`, closes the session write lock, then delegates delivery.

## Streaming services

### `classes/local/protected_stream.php`

Handles trusted local/cache files, PDF signature checks, byte ranges, cache freshness, cache warming and cleanup.

### `classes/local/http_range_proxy.php`

Handles upstream browser-facing streaming, one validated range, `HEAD`, `If-Range`, `200/206/416`, safe metadata relay and chunked cURL output without buffering the complete resource.

## PDF fast-first-byte flow

```text
PDF.js range request
↓
Moodle authorisation
↓
fresh cache?
├─ yes → protected local range response
└─ no  → queue deduplicated precache task
          ↓
          proxy requested range immediately
          ↓
          cron warms complete cache
```

No recompression, rasterisation or transcoding is applied.

## PDF.js runtime loading

PDF.js is bundled as an ES module. Moodle's AMD/Grunt pipeline transforms a direct dynamic `import()` inside an AMD module into a RequireJS request, which is incompatible with `pdf.min.mjs` on affected mobile browsers.

The runtime boundary is therefore:

```text
ebookviewer.js / bookviewer.js / pdfviewer.js
↓
mod_videoplayer/pdfjsloader
↓
create local <script type="module">
↓
thirdpartylibs/pdfjs/pdf.min.mjs
↓
validate window.pdfjsLib
↓
configure thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

`pdfjsloader` owns a single cached loading promise. It validates `getDocument` and `GlobalWorkerOptions` before assigning `workerSrc`. The viewers never configure the worker independently.

This design preserves:

- local assets only;
- no CDN dependency;
- no `eval` or arbitrary URL execution;
- one PDF.js instance per page;
- controlled rejection when the local module cannot load;
- consistent behavior across Ebook, responsive book and standard PDF modes.

## Responsive Ebook pipeline

```text
protected PDF metadata
↓
lightweight PageFlip placeholders
↓
phone: current page | desktop: current two-page spread
↓
render visible page or spread
↓
idle prefetch of adjacent pages only
↓
local PageFlip transition
↓
protected progress and completion save
```

The viewer does not render the complete document before first paint. Phone detection combines the available viewer width with coarse-pointer and physical short-screen-side checks, so rotating a phone does not unexpectedly switch it to a desktop spread. Desktop mode is selected only when the available container can support two readable facing pages.

PageFlip receives lightweight page containers first. PDF.js renders the active page on phones or the active spread on desktop. Immediate neighbours are scheduled with `requestIdleCallback` when available and a bounded timer fallback otherwise.

The responsive rebuild preserves the current page when the viewer crosses its layout boundary. Completion uses the furthest visible page and therefore does not decrease when a learner turns backwards.

## Viewers

- `bookviewer.js`: alternative responsive two-page desktop / one-page mobile PDF reader.
- `pdfviewer.js`: standard one-page PDF.js rendering.
- `ebookviewer.js`: lazy PDF.js rendering with forced phone single-page and desktop two-page StPageFlip layouts.
- `pdfjsloader.js`: shared local PDF.js ES-module loader and worker configurator.
- `plyr.js`: local Plyr progressive enhancement over native HTML5 video.

## CSS isolation

Moodle compiles a module's root `styles.css` globally. Drive Resource keeps it free of presentation rules. `view.php` loads `styles_activity.css` only on the activity page and loads `styles_pageflip_fix.css` only for protected Ebook mode.

The Ebook stylesheet scopes paper texture, gutter, shadow, corner, fullscreen and reduced-motion rules below Drive Resource roots. This boundary prevents interference with Tiles/Mosaico, course cards, course indexes, Moodle modals and themes. Third-party course formats must never be patched to compensate for Drive Resource styling.

## Progress and completion

Viewer AMD modules call `mod_videoplayer_save_progress`, which validates context and capability before delegating to `progress_service` and optional `reward_service`.

Persisted state includes active time, progress, completion percentage, last page, total pages, completion state and optional points/rewards.

For Ebook mode, progress is calculated from the furthest page made visible. In desktop spread mode the second visible page contributes to progress. Returning to an earlier page does not reduce saved completion.

## Events

- `course_module_viewed`
- `progress_updated`
- `resource_completed`
- `reward_awarded`

## Backup, restore and privacy

Backup/Restore includes activity configuration, Moodle-local PDF files and optional user progress/reward data. Privacy API supports metadata declaration, context discovery, export and deletion by context/user list.

## Validation architecture

`.github/workflows/moodle-50-ci.yml` is the executable compatibility gate. It runs lint, Moodle coding style, PHPDoc, plugin validation, XMLDB savepoint checks, Mustache, AMD/JavaScript and PHPUnit against Moodle 5.0.

The workflow enforces two viewer contracts:

- `pdfjsloader.min.js` must create a native module script, reference the local worker and contain no dynamic-import transformer or RequireJS conversion for `pdf.min.mjs`;
- `ebookviewer.js` must preserve phone single-page mode, desktop two-page mode, idle adjacent-page prefetch, the dedicated PageFlip stylesheet and the absence of the former eager multi-page rendering constant.
