# Drive Resource developer guide

## Component identity

The Moodle component is:

```text
mod_videoplayer
```

The commercial product name is:

```text
Drive Resource
```

Keep the component name stable for Moodle upgrade compatibility. Use Drive Resource in language strings, UI and documentation.

## Coding standards

Follow:

- Moodle Coding Style.
- PHP 8.2 compatible syntax.
- small classes and methods.
- Moodle File API.
- Moodle External API.
- Moodle Privacy API.
- Moodle Backup & Restore API.
- Moodle Events API.

## Main folders

```text
amd/src/                         AMD JavaScript source files
backup/moodle2/                  Backup and restore logic
classes/event/                   Moodle event classes
classes/external/                AJAX/external API endpoints
classes/local/                   Internal services
classes/privacy/                 Privacy API provider
db/                              install.xml, upgrade.php and services.php
lang/en/                         Language strings
templates/                       Mustache templates
thirdpartylibs/                  Local third-party libraries
```

## Service layer

Business logic should not be embedded directly in external API classes.

Use:

```text
classes/local/progress/progress_service.php
classes/local/gamification/reward_service.php
```

The external API should validate parameters, context, login and capability, then delegate to services.

## Progress save flow

```text
bookviewer.js / ebookviewer.js / pdfviewer.js
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
Moodle events + Completion API
```

## Viewer development

### Protected book viewer

Use `amd/src/bookviewer.js` with `templates/book.mustache` for the default protected PDF book experience.

Expected behavior:

- Desktop renders a two-page spread to behave like a real book.
- Mobile renders one page at a time, similar to FlipHTML5-style reading.
- Fullscreen keeps previous/next buttons inside the PDF stage.
- Navigation works through buttons and mobile swipe gestures.
- Progress is saved by last visible page.
- PDF content is still delivered only through `protected.php`.

Do not expose raw Google Drive URLs, file IDs, preview URLs or direct download URLs in this viewer.

### Standard PDF viewer

Use `amd/src/pdfviewer.js` for one-page protected PDF rendering when a non-book fallback is required.

### Mobile PDF stabilizer

Use `amd/src/pdfmobile.js` for mobile viewport corrections that should remain independent from the PDF.js rendering pipeline.

This module is intentionally small and should only handle mobile layout stabilization, including:

- iOS/Safari canvas overflow correction.
- initial horizontal scroll correction.
- viewport re-stabilization after orientation changes.
- late PDF.js canvas rendering edge cases.

Do not move authorization, file access, progress calculation or PDF.js document loading into this module.

### Visual stylesheets

Presentation CSS is split by responsibility:

```text
styles.css                         Base plugin styles loaded by Moodle.
styles_bookviewer.css              Protected book viewer layout and fullscreen navigation.
styles_pdf_overlay.css             PDF.js overlay and canvas behavior.
styles_pdf_mobile.css              Mobile PDF-specific viewport rules.
styles_visual_refinements.css      Product-level visual polish for Drive Resource.
```

Keep mobile fixes isolated from the generic styles whenever possible. This reduces regression risk for desktop Moodle themes.

### Ebook viewer

Use `amd/src/ebookviewer.js` for optional legacy ebook rendering.

Rules:

- Load PDF.js locally.
- Load PageFlip locally from `thirdpartylibs/pageflip` when using PageFlip-specific behavior.
- Keep fallback to `pdfviewer.js` if PageFlip is missing.
- Do not use CDN.
- Do not expose raw source URLs.

## Database changes

For new installs, update:

```text
db/install.xml
```

For existing installs, always add an upgrade step in:

```text
db/upgrade.php
```

Then bump:

```text
version.php
```

## Backup and restore

When adding new activity fields, update both:

```text
backup/moodle2/backup_videoplayer_stepslib.php
backup/moodle2/restore_videoplayer_stepslib.php
```

When adding user data, check whether it should be backed up only when `userinfo` is enabled.

## Privacy API

When adding personal data, update:

```text
classes/privacy/provider.php
lang/en/videoplayer.php
```

Personal data currently includes:

- reading progress.
- completion percentage.
- last page.
- total pages.
- active time.
- points.
- rewards.

## Event rules

Use Moodle events for meaningful user actions:

- `progress_updated`
- `resource_completed`
- `reward_awarded`

Events must include restore mappings for object IDs and `other` data where needed.

## Commercial release requirements

Before release:

- no CDN references.
- third-party libraries registered in `thirdpartylibs.xml`.
- license notices documented.
- upgrade path tested.
- backup/restore tested.
- privacy export/delete tested.
- Moodle debug developer mode clean.
- PHP warnings clean.
- JavaScript console clean.
- mobile PDF viewer tested on iOS Safari, Android Chrome and Moodle app WebView.
- desktop book viewer tested with portrait PDFs and landscape PDFs.
