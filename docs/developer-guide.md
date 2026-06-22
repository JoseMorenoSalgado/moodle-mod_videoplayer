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
ebookviewer.js / pdfviewer.js
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

### Standard PDF viewer

Use `amd/src/pdfviewer.js` for one-page protected PDF rendering.

### Ebook viewer

Use `amd/src/ebookviewer.js` for ebook rendering.

Rules:

- Load PDF.js locally.
- Load PageFlip locally from `thirdpartylibs/pageflip`.
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
