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

## Viewers

- `bookviewer.js`: responsive two-page desktop / one-page mobile PDF reader.
- `pdfviewer.js`: standard one-page PDF.js rendering.
- `ebookviewer.js`: optional local StPageFlip with PDF.js fallback.
- `plyr.js`: local Plyr progressive enhancement over native HTML5 video.

## CSS isolation

Moodle compiles a module's root `styles.css` globally. Drive Resource keeps it free of presentation rules. `view.php` loads `styles_activity.css` only on the activity page, followed by specialised viewer styles.

This boundary prevents interference with Tiles/Mosaico, course cards, course indexes, Moodle modals and themes. Third-party course formats must never be patched to compensate for Drive Resource styling.

## Progress and completion

Viewer AMD modules call `mod_videoplayer_save_progress`, which validates context and capability before delegating to `progress_service` and optional `reward_service`.

Persisted state includes active time, progress, completion percentage, last page, total pages, completion state and optional points/rewards.

## Events

- `course_module_viewed`
- `progress_updated`
- `resource_completed`
- `reward_awarded`

## Backup, restore and privacy

Backup/Restore includes activity configuration, Moodle-local PDF files and optional user progress/reward data. Privacy API supports metadata declaration, context discovery, export and deletion by context/user list.

## Validation architecture

`.github/workflows/moodle-50-ci.yml` is the executable compatibility gate. It runs lint, Moodle coding style, PHPDoc, plugin validation, XMLDB savepoint checks, Mustache, AMD/JavaScript and PHPUnit against Moodle 5.0.
