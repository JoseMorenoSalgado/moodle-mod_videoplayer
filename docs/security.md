# Drive Resource security model

Drive Resource must be treated as a protected delivery layer, not as a browser-only restriction mechanism.

## Enforced protection

The following protections are enforced server-side:

- `require_login()` is required before content delivery.
- The activity uses `context_module` for permission checks.
- Users must have `mod/videoplayer:view`.
- Local PDFs are stored in Moodle private file storage, not in a public web directory.
- Local PDFs are served through `protected.php` after access checks.
- Google Drive direct URLs and file IDs should not be rendered in learner-facing templates.

## Deterrent controls

The following controls discourage casual copying but are not security boundaries:

- disabling right click.
- blocking basic copy, drag and select events.
- hiding download buttons in the custom viewer.
- watermark overlays.
- fullscreen-controlled viewer.

A user with sufficient browser access can still take screenshots or inspect network requests. These controls must be presented as deterrents, not absolute DRM.

## Local PDF delivery

Local PDFs are stored under the `mod_videoplayer/localpdf` file area and served through Moodle File API delivery. The browser receives bytes only after Moodle validates the current user session and capability.

## Google Drive delivery

Google Drive resources should be proxied where supported. The plugin must avoid exposing:

- raw file IDs.
- direct download URLs.
- preview URLs.
- open-in-Drive links.

Some Google-controlled embedded viewer elements cannot be removed because of browser cross-origin isolation. The long-term commercial target is to avoid Google Drive viewer UI completely by using Moodle-owned viewers and secure proxy delivery.

## Protected stream service

`protected.php` is intentionally kept as a thin authorised endpoint. Low-level delivery is delegated to `classes/local/protected_stream.php` so the same security behavior is reused by local files, cached PDFs, proxy fallback and cache tasks.

The protected stream service is responsible for:

- safe byte-range delivery from local files.
- private browser cache headers with `no-transform`.
- cache diagnostic header sanitisation.
- Google Drive PDF cache warming with PDF header validation.
- fallback to upstream proxy when warming fails.
- cleanup of stale temporary and cookie files.

Cached Google Drive PDFs are stored under `$CFG->localcachedir/mod_videoplayer/pdf/`. They are not public files; every request still passes through Moodle login, module context and capability validation before bytes are sent.

## Headers and streaming

`protected.php` should keep memory usage low and preserve streaming behavior. The target requirements are:

- `Range` support.
- `Accept-Ranges`.
- `Partial Content`.
- correct `Content-Type`.
- correct `Content-Length`.
- no full-file buffering for large files.

Local Moodle stored files should use Moodle private file storage and byte-range delivery through the protected stream service. External resources should stream through cURL or equivalent chunked delivery.

## Privacy

Progress and reward data are personal data. The Privacy API must export and delete:

- progress.
- completion percentage.
- last page.
- total pages.
- active time.
- points.
- earned rewards.

## Commercial hardening checklist

Before release:

- test guest access denial.
- test unenrolled user denial.
- test enrolled student access.
- test teacher access.
- verify direct `protected.php?id=<cmid>` access requires login.
- verify local PDF is not accessible from web root.
- verify cached Google Drive PDFs are not accessible directly from the web.
- verify no Drive URLs are rendered in HTML templates for protected local mode.
- verify backup/restore does not leak files across courses.
- verify privacy export/delete removes reward and progress records.
