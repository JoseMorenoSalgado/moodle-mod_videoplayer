# Drive Resource for Moodle

**Drive Resource** is a Moodle activity module for publishing protected learning resources from Google Drive and Moodle local private storage.

The internal Moodle component remains `mod_videoplayer` for compatibility with existing installations, but the user-facing product name is **Drive Resource**.

## Features

- Publish Google Drive resources inside Moodle courses.
- Publish local protected PDFs stored in Moodle private file storage.
- Support for videos, PDFs, images, documents, spreadsheets and presentations.
- Automatic resource type detection for supported Google Drive URLs.
- Protected `protected.php` delivery endpoint backed by a reusable protected stream service.
- Local PDF.js viewer without CDN.
- Protected ebook/book mode with desktop two-page spread and mobile one-page reading.
- Google Drive PDF cache warming under Moodle local cache.
- HTML5 video playback with local Plyr assets.
- Plugin-owned fullscreen viewer for mobile and desktop.
- Reading progress by page, percentage and active time.
- Optional watermark deterrent.
- Optional personal gamification milestones and points.
- Moodle Completion API integration.
- Moodle Events API integration.
- Backup and Restore support, including local PDF files.
- Privacy API support for progress and rewards.
- Teacher progress report per activity.
- Admin settings for tracking, protected mode and default completion behavior.

## Requirements

- Moodle 4.x or Moodle 5.x.
- PHP 8.2 or newer recommended.
- HTTPS-enabled Moodle site.
- Local PDF.js files installed under `thirdpartylibs/pdfjs/`.
- Local Plyr files installed under `thirdpartylibs/plyr/` when enhanced video playback is required.
- Local PageFlip files installed under `thirdpartylibs/pageflip/` when ebook flipbook mode is required.

## Required local libraries

Drive Resource must not use CDN assets in production.

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/plyr/plyr.css
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

PageFlip is optional. If it is missing, ebook mode falls back to the protected PDF.js viewer.

## Installation

1. Copy the plugin folder to:

   ```bash
   mod/videoplayer
   ```

2. Run Moodle upgrade:

   ```bash
   php admin/cli/upgrade.php
   ```

3. Purge Moodle caches.

4. Go to:

   ```text
   Site administration > Plugins > Activity modules > Drive Resource
   ```

5. Configure the default tracking, protected mode and PDF cache settings.

## Usage: local protected PDF

1. Enter a Moodle course.
2. Turn editing on.
3. Add a new **Drive Resource** activity.
4. Select **Local protected PDF**.
5. Upload one PDF file.
6. Choose **Standard PDF viewer** or **Protected ebook viewer**.
7. Optionally enable watermark and gamification.
8. Configure the required completion percentage.
9. Save and display.

## Usage: Google Drive resource

1. Enter a Moodle course.
2. Turn editing on.
3. Add a new **Drive Resource** activity.
4. Select **Google Drive**.
5. Paste a supported Google Drive or Google Docs URL.
6. Select the resource type or leave it as **Automatic**.
7. Configure completion if needed.
8. Save and display.

## Supported resources

Drive Resource supports common Google Drive and Google Docs URLs, including:

- videos from Google Drive.
- PDF files.
- images.
- Google Docs documents.
- Google Sheets spreadsheets.
- Google Slides presentations.
- generic Drive files supported by the configured delivery flow.

## Security model

The enforceable protection is server-side Moodle access control:

```text
require_login()
context_module
mod/videoplayer:view
protected.php
protected_stream service
Moodle File API, warmed PDF cache or secure proxy streaming
```

Browser controls such as disabling right click, hiding download buttons and showing watermarks are deterrents, not DRM.

## Protected PDF cache diagnostics

Protected Google Drive PDFs can be cached under Moodle local cache after Moodle access validation. The response header shows the delivery path:

```text
X-Drive-Resource-Cache: LOCAL
X-Drive-Resource-Cache: HIT
X-Drive-Resource-Cache: WARMED
X-Drive-Resource-Cache: WARM_FAILED
X-Drive-Resource-Cache: BYPASS
```

Cache files are stored outside the web root under:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

## Progress tracking

Drive Resource tracks:

- active time.
- completion percentage.
- last page.
- total pages.
- completion state.
- points and rewards when gamification is enabled.

For Google Drive iframes that do not expose playback APIs, presence-based tracking is used.

## Teacher reports

Teachers with the `mod/videoplayer:viewreport` capability can access the progress report for each activity.

## JavaScript AMD build

Development files live in:

```text
amd/src/
```

Compiled production files live in:

```text
amd/build/
```

To compile AMD files using Moodle tooling:

```bash
npm install
npx grunt amd
```

or, inside a Moodle development environment:

```bash
grunt amd
```

## Documentation

Additional technical documentation is available in the `docs/` directory:

- `docs/architecture.md`
- `docs/database.md`
- `docs/developer-guide.md`
- `docs/installation.md`
- `docs/manual-test-checklist.md`
- `docs/security.md`

## Compatibility

Current development branch:

- Release: `1.1.15-beta`
- Component: `mod_videoplayer`
- Product name: Drive Resource
- Supported Moodle versions: 4.x and 5.x target

## License

GNU GPL v3 or later.

Third-party libraries are documented in `thirdpartylibs.xml` and must remain locally bundled for production use.

## Maintainer

Elearning Cloud  
https://elearningcloud.io
