# Changelog

All notable changes to **Drive Resource** are documented in this file.

The internal Moodle component is `mod_videoplayer` for compatibility with previous installations.

## v1.1.0-beta - 2026-06-22

### Added

- Local protected PDF source stored through Moodle File API.
- Protected PDF delivery through `protected.php` for Moodle-local PDF files.
- Standard PDF.js viewer using local PDF.js assets.
- Protected ebook display mode.
- Optional local StPageFlip integration for realistic page turning.
- Fallback from ebook mode to protected PDF.js when PageFlip is unavailable.
- Reading resume support using last saved page.
- Reading progress by page, total pages, percentage and active time.
- Optional dynamic watermark deterrent.
- Optional gamification with personal milestones and points.
- `videoplayer_rewards` table for earned rewards.
- Progress service layer.
- Reward service layer.
- Moodle events: `progress_updated`, `resource_completed`, `reward_awarded`.
- Backup and Restore support for local PDF files, progress and rewards.
- Privacy API support for reading state and reward records.
- `thirdpartylibs.xml` entries for PDF.js, Plyr and StPageFlip.
- Documentation for architecture, installation, security, development and manual testing.
- Mobile PDF viewport stabilizer for iOS/Safari rendering edge cases.
- Dedicated visual refinement stylesheet for Drive Resource activity presentation.
- Protected responsive book viewer with desktop two-page spread and mobile one-page reading mode.

### Changed

- `save_progress.php` now delegates business logic to internal services.
- PDF rendering context now supports both standard and ebook modes.
- Activity form now supports Google Drive and local protected PDF sources.
- Completion can be calculated from PDF page progress.
- README updated for protected local PDF and ebook workflows.
- PDF viewer mobile layout now uses larger touch targets, safer viewport units and reduced chrome spacing.
- PDF visual presentation was refactored into focused stylesheets for maintainability.
- PDF resources now render through the protected book viewer by default.

### Security

- Local PDFs are stored outside the web root in Moodle private file storage.
- Local PDF access requires Moodle login, module context and `mod/videoplayer:view` capability.
- Direct local PDF URLs are not exposed to learners.
- Viewer-level copy/right-click/download controls are implemented as deterrents only, not DRM.

### Notes

- PageFlip files must be installed locally under `thirdpartylibs/pageflip/`.
- StPageFlip is documented upstream as MIT licensed.
- Production release still requires Moodle staging validation, AMD build generation and manual QA.

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
