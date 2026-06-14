# Changelog

All notable changes to **Drive Resource** are documented in this file.

The internal Moodle component is `mod_videoplayer` for compatibility with previous installations.

## v1.0.0 - 2026-06-14

### Added

- New user-facing product identity: **Drive Resource**.
- Support for Google Drive videos, PDFs, images, documents, spreadsheets and presentations.
- Automatic Google Drive file ID extraction from common sharing URL formats.
- Automatic resource type detection.
- Activity form redesigned for multi-resource usage.
- Protected mode to hide plugin-owned direct Google Drive navigation links.
- Protected endpoint (`protected.php`) for authenticated Moodle-based resource access.
- Plugin-owned fullscreen viewer for mobile and desktop.
- Presence-based progress tracking using Moodle AJAX external services.
- Teacher progress report per activity.
- Moodle Completion API integration.
- Backup and Restore support.
- Privacy API support for user progress data.
- English and Spanish language packs.
- Admin settings for progress tracking, protected mode and defaults.
- Multipurpose SVG activity icon.
- Mustache template for resource rendering.
- Output renderer class.
- AMD modules for progress tracking and fullscreen behavior.

### Changed

- User-facing strings changed from video-only terminology to Drive Resource terminology.
- Database schema optimized with defaults, indexes and unique user-progress constraints.
- Upgrade path rebuilt to avoid obsolete legacy tables.
- View page refactored to use the Google Drive helper service and Mustache template.
- Backup and Restore aligned with the current database structure.

### Security

- Added iframe sandbox restrictions.
- Removed plugin-owned button that opened resources directly in Google Drive.
- Added `referrerpolicy="no-referrer"` to embedded resource iframes.
- Added protected endpoint authorization through Moodle course and capability checks.

### Known limitations

- Google Drive embedded viewers may still display internal controls controlled by Google.
- Exact playback time cannot be read from Google Drive iframes because of browser cross-origin restrictions.
- Progress tracking for Google Drive resources is presence-based, not exact playback-percentage-based.
