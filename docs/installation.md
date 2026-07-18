# Drive Resource installation

## Supported production baseline

Release candidate `1.1.18-beta` declares:

```text
$plugin->requires = 2025041400
```

This means the current production baseline is **Moodle 5.0 or newer**.

Moodle 4.x is a future compatibility target and must not be treated as production-supported until runtime testing is completed and `version.php` is intentionally changed.

Use a PHP version supported by the Moodle branch being deployed. Do not infer Moodle 5.2 PHP compatibility only from the plugin's PHP syntax checks.

## Server requirements

- PHP cURL extension.
- HTTPS for production.
- Moodle cron running frequently.
- Ad-hoc task processing enabled.
- Writable `$CFG->localcachedir`.
- Local PDF.js and Plyr assets installed.

## Installation

Place the plugin in:

```text
mod/videoplayer
```

Run Moodle upgrade:

```bash
php admin/cli/upgrade.php
```

Then purge Moodle caches.

## Required local libraries

No runtime CDN assets are allowed.

### PDF.js

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

### Plyr

```text
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
```

### Optional PageFlip assets

```text
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

## AMD production files

Moodle production loads AMD modules from `amd/build/`.

Required production modules include:

```text
amd/build/pdfviewer.min.js
amd/build/pdfmobile.min.js
amd/build/plyr.min.js
amd/build/bookviewer.min.js
```

A source file present only in `amd/src/` is not sufficient for production.

The repository CI validates JavaScript syntax and critical PDF viewer bundle consistency.

## Creating a Google Drive resource

1. Add **Drive Resource** to a course.
2. Select **Google Drive**.
3. Paste a supported shareable Drive/Docs URL.
4. Select the resource type.
5. For Google Docs/Sheets/Slides URLs, automatic detection is supported.
6. For generic `drive.google.com/file/d/...` URLs with no MIME information in the URL, explicitly choose Video, PDF or Image.
7. For PDF-compatible resources, choose the desired PDF display mode.
8. Save and display.

Do not expose/copy the internal server-side download URL into course content.

## Creating a local protected PDF

1. Add **Drive Resource**.
2. Select **Local protected PDF**.
3. Upload one PDF.
4. Select PDF display mode.
5. Configure completion.
6. Optionally enable watermark/gamification.
7. Save and display.

The PDF is stored through Moodle File API outside the web root.

## Protected PDF cache

Cache location:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Expected diagnostics:

```text
X-Drive-Resource-Cache: LOCAL
X-Drive-Resource-Cache: HIT
X-Drive-Resource-Cache: MISS_QUEUED
X-Drive-Resource-Cache: MISS
X-Drive-Resource-Cache: BYPASS
```

Cold-cache flow:

```text
first PDF.js range request
↓
MISS_QUEUED
↓
requested bytes stream immediately
↓
Moodle cron executes precache_pdf
↓
subsequent request
↓
HIT
```

The cache flow does not recompress the PDF.

## Video validation

Test only the Moodle protected endpoint while authenticated.

Verify a range-capable video can return:

```text
HTTP 206
Accept-Ranges: bytes
Content-Range: bytes start-end/total
Content-Length: requested-range-length
```

Test:

- first play;
- pause/resume;
- seek forward/backward;
- repeated seeking;
- playback rate;
- fullscreen;
- device rotation.

The proxy rejects unexpected HTML/JSON responses instead of passing them to the media element as successful video content.

## iPhone/iPad release validation

Use at least one physical iPhone/iPad Safari device before production.

Test:

- user-gesture initial play;
- inline playback;
- seek after initial metadata load;
- repeated seek after several minutes;
- native/Plyr fullscreen behavior;
- portrait/landscape rotation;
- lock/unlock recovery;
- Wi-Fi/mobile-network transition when practical;
- replay of the same protected video;
- native controls when Plyr is intentionally unavailable.

Inspect Safari Web Inspector for failed ranges and unexpected HTML responses.

## PDF release validation

Test:

- cold cache and warm cache;
- small, medium and 100+ page PDFs;
- previous/next page;
- zoom in/out;
- fit-to-screen;
- fullscreen;
- mobile swipe;
- text search;
- orientation change;
- actual last-page resume after leaving on an earlier page;
- completion percentage after viewing non-consecutive pages;
- cumulative active time across two separate sessions.

## Google Workspace documents

Verify Google Docs, Sheets and Slides render through the local PDF.js viewer and not through a Google preview iframe.

## Images

Verify images load through `protected.php` and no direct Drive URL appears in learner-facing HTML.

## CI

Pull requests run `.github/workflows/quality.yml`.

Required before release:

- PHP 8.2 syntax check green.
- PHP 8.3 syntax check green.
- JavaScript syntax check green.
- XML validation green.
- protected architecture guardrails green.
- critical AMD consistency green.

CI is not a substitute for Moodle staging/mobile testing.

## Current Google Drive access limitation

The release supports shareable Google Drive/Docs resources. Site-owned OAuth/service-account Google Drive API access for private enterprise files is not implemented yet.

Do not advertise private enterprise Drive integration until that milestone is completed and tested.

## Post-installation production checklist

- Moodle upgrade completed.
- Moodle caches purged.
- cron/ad-hoc tasks healthy.
- `$CFG->localcachedir` writable.
- required local libraries present.
- CI green for the release commit.
- logged-out/guest/unenrolled access tests passed.
- no Google preview iframe in protected viewer paths.
- no raw Drive file ID/URL in learner HTML.
- iPhone Safari video tests passed.
- Android Chrome tests passed.
- cold/warm PDF tests passed.
- PDF search/zoom/fullscreen tests passed.
- progress/resume/completion tests passed.
- backup/restore tested.
- Privacy API export/delete tested.
- Moodle developer debugging clean.
