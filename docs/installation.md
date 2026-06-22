# Drive Resource installation

## Requirements

- Moodle 4.x or Moodle 5.x.
- PHP 8.2 or newer recommended.
- Local third-party libraries installed under `thirdpartylibs/`.
- Moodle cron configured.

## Installation

Place the plugin in:

```text
mod/videoplayer
```

Then run Moodle upgrade:

```bash
php admin/cli/upgrade.php
```

Or complete the upgrade from Site administration in the browser.

## Required local libraries

Drive Resource must not load viewer libraries from a CDN in production.

### PDF.js

Required for PDF and ebook rendering:

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

### Plyr

Required for enhanced HTML5 video playback:

```text
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.polyfilled.js
```

The exact Plyr JavaScript filename must match the AMD loader implementation in the plugin.

### StPageFlip

Optional but recommended for ebook mode:

```text
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

If PageFlip is missing, ebook mode falls back to the protected PDF.js canvas viewer.

## Creating a local protected PDF activity

1. Turn editing on in a course.
2. Add a Drive Resource activity.
3. Select **Local protected PDF** as source.
4. Upload one PDF file.
5. Choose **Protected ebook viewer** or **Standard PDF viewer**.
6. Configure completion percentage.
7. Optionally enable watermark and gamification.
8. Save and display.

## Creating a Google Drive activity

1. Add a Drive Resource activity.
2. Select **Google Drive** as source.
3. Paste a supported shareable Google Drive URL.
4. Select or auto-detect the resource type.
5. Save and display.

## Upgrade notes

The upgrade step `2026062100` adds:

- PDF display mode settings.
- local PDF protection settings.
- gamification settings.
- reading state fields in `videoplayer_views`.
- `videoplayer_rewards` table.

Always test upgrade on a staging Moodle before deploying to production.

## Post-installation checklist

- Purge Moodle caches.
- Confirm `thirdpartylibs.xml` is valid.
- Confirm PDF.js files are present.
- Confirm PageFlip files are present if ebook mode is required.
- Create a local PDF activity.
- Open as student.
- Navigate pages.
- Confirm progress is saved.
- Confirm completion is marked after the configured percentage.
- Test backup and restore.
- Test privacy export/delete.
