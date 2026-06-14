# Drive Resource Architecture

Drive Resource is a Moodle activity module that embeds and tracks Google Drive resources inside Moodle courses.

The internal component name is `mod_videoplayer` for compatibility. The product identity shown to users is **Drive Resource**.

## High-level flow

```text
Google Drive / Google Docs
          |
          v
+----------------------+        +----------------------+
|  Drive URL submitted | -----> |  mod_form.php        |
+----------------------+        +----------------------+
                                      |
                                      v
                              +----------------------+
                              |  videoplayer table   |
                              +----------------------+
                                      |
                                      v
+----------------------+        +----------------------+
|  Student opens CM    | -----> |  view.php            |
+----------------------+        +----------------------+
                                      |
                  +-------------------+-------------------+
                  |                   |                   |
                  v                   v                   v
        +------------------+ +------------------+ +------------------+
        | resource.mustache| | progress.js      | | fullscreen.js    |
        +------------------+ +------------------+ +------------------+
                  |                   |                   |
                  v                   v                   v
        Embedded resource     AJAX progress       Fullscreen overlay
```

## Main components

### `mod_form.php`

Defines the teacher-facing activity form. It validates supported Google Drive and Google Docs URLs and allows the teacher to configure:

- resource name,
- Drive URL,
- resource type,
- completion percentage,
- standard Moodle activity settings.

### `classes/local/drive.php`

Helper service for Google Drive URLs. It is responsible for:

- extracting file IDs,
- detecting resource types,
- validating supported URLs,
- generating preview URLs,
- supporting protected resource URL construction.

### `view.php`

Main student-facing activity page. It:

- validates course and module access,
- triggers the Moodle viewed event,
- marks the module as viewed for completion,
- prepares the template context,
- loads AMD modules for progress and fullscreen behavior,
- renders `templates/resource.mustache`.

### `templates/resource.mustache`

Presentation layer for the embedded resource. It renders:

- the resource toolbar,
- the protected resource notice,
- the embedded iframe,
- the fullscreen overlay markup.

### `classes/output/renderer.php`

Renderer class for centralizing output logic. It wraps rendering of the resource template and error output.

### `amd/src/progress.js`

Tracks active presence in the activity page. Google Drive iframes do not expose exact playback time, so this module sends periodic heartbeat updates based on active page presence.

### `classes/external/save_progress.php`

AJAX external API endpoint that receives progress data and stores it in `videoplayer_views`.

### `amd/src/fullscreen.js`

Controls the plugin-owned fullscreen viewer. It opens the resource in an overlay inside Moodle instead of redirecting users to Google Drive.

### `protected.php`

Authenticated endpoint intended to reduce direct exposure of the original Google Drive URL. It validates Moodle access before serving supported content through the plugin.

## Backup and Restore

Backup and Restore are implemented under:

```text
backup/moodle2/
```

They include the main activity record and user progress records when user data is included in the backup.

## Privacy API

User progress data is stored in `videoplayer_views`. The Privacy API declares this table and its user-related fields.

## Security boundaries

Drive Resource can restrict plugin-owned links and iframe permissions. However, Google controls its own embedded viewer. Controls rendered internally by Google cannot be removed by Moodle because of browser cross-origin security rules.
