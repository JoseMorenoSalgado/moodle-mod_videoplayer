# Drive Resource Architecture

Drive Resource is a Moodle activity module for publishing protected Google Drive and Moodle-local resources inside courses.

The internal component name remains `mod_videoplayer` for compatibility. The product identity shown to users is **Drive Resource**.

## Target flow

```text
Resource source
↓
Drive Resource activity instance
↓
protected.php
↓
Moodle access checks
↓
Secure proxy or Moodle File API delivery
↓
Local viewer
↓
Progress, completion and gamification
```

## Resource sources

Drive Resource currently supports two source families:

1. **Google Drive URLs** parsed server-side and streamed through `protected.php` where supported.
2. **Local protected PDFs** stored in Moodle private file storage under the `mod_videoplayer/localpdf` file area.

Local PDFs are never placed in the web root. They are served only after Moodle validates course, module, context and capability access.

## Main components

### `mod_form.php`

Defines the teacher-facing activity form. It supports:

- Google Drive source.
- Local protected PDF source.
- resource type selection.
- PDF display mode: standard or ebook.
- download discouragement settings.
- right-click/copy discouragement.
- dynamic watermark.
- gamification settings.
- completion percentage.

### `lib.php`

Handles Moodle module lifecycle operations:

- supported features.
- add/update/delete instance.
- File API persistence for local PDFs.
- cleanup of local files, progress and rewards.

### `protected.php`

Authenticated resource endpoint. It validates:

- course module.
- course.
- activity instance.
- `require_login()`.
- `context_module`.
- `mod/videoplayer:view` capability.

For local PDFs, it uses Moodle File API delivery. For supported Google Drive resources, it streams through a protected proxy.

### `view.php`

Main activity page. It:

- validates access.
- triggers `course_module_viewed`.
- marks the module as viewed for completion.
- loads the correct AMD viewer.
- sends initial progress, page and gamification state to the template.

## Viewers

### Standard PDF viewer

`amd/src/pdfviewer.js` renders protected PDFs with local PDF.js.

### Ebook viewer

`amd/src/ebookviewer.js` renders protected PDFs with local PDF.js and optional local StPageFlip.

The ebook viewer is progressive:

```text
PDF.js local
↓
Render PDF pages as canvas
↓
If PageFlip is available, use flipbook
↓
If PageFlip is missing, fallback to protected PDF.js flow
```

Required optional PageFlip files:

```text
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

StPageFlip is documented upstream as MIT licensed and supports mobile devices, no dependencies, HTML pages and script-tag usage through `St.PageFlip`.

### Video viewer

HTML5 video playback uses local Plyr assets where available.

## Progress and gamification

Progress is saved through the AJAX function `mod_videoplayer_save_progress` and delegated to services:

```text
classes/local/progress/progress_service.php
classes/local/gamification/reward_service.php
```

Progress data is stored in `videoplayer_views`:

- last page.
- total pages.
- active time.
- completion percentage.
- completion state.
- points.

Rewards are stored in `videoplayer_rewards` and awarded without duplicates.

## Events

The module emits:

- `course_module_viewed`
- `progress_updated`
- `resource_completed`
- `reward_awarded`

These support Moodle logs, reporting, completion workflows and future analytics.

## Backup and restore

Backup and restore include:

- activity configuration.
- local PDF files.
- progress records when user data is included.
- gamification rewards when user data is included.

## Privacy API

Privacy API exports and deletes:

- progress records.
- reading state.
- completion data.
- reward data.

## Security boundary

Drive Resource prevents direct public file URLs for local PDFs and hides source URLs from the learner-facing UI. Browser-level restrictions such as disabling right click are only deterrents. The enforceable protection is server-side access control through Moodle and `protected.php`.
