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
- Resume from the actual last saved PDF page and saved video playback second.
- PDF completion based on unique pages actually observed across sessions.
- Video completion based on unique playback ranges actually watched.
- Cumulative active-time tracking across sessions.
- Moodle Completion API and Events API integration.
- Optional watermark and gamification features.
- Backup & Restore support.
- Privacy API support.
- Teacher progress reporting.

## Requirements and supported Moodle branches

The current plugin metadata declares:

```text
$plugin->requires = 2025041400
$plugin->supported = [500, 502]
```

The production compatibility range currently declared and tested by CI is **Moodle 5.0 through Moodle 5.2**. Moodle 4.x remains a project compatibility target, but it is not production-supported by this release candidate.

CI exercises:

- Moodle 5.0 with PHP 8.2.
- Moodle 5.2 with PHP 8.3.
- PostgreSQL 16 for formal Moodle plugin prechecks.
- Independent PHP 8.2/8.3 syntax, XML and protected-architecture checks.

Additional requirements:

- A PHP version supported by the installed Moodle branch.
- PHP cURL extension.
- HTTPS in production.
- Moodle cron configured and running frequently.
- Writable `$CFG->localcachedir`.
- Required third-party libraries bundled locally.

Moodle 5.2 requires PHP 8.3 or later; follow the requirements of the Moodle branch being deployed rather than relying only on the plugin's standalone PHP syntax compatibility.
