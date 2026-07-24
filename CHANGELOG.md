# Changelog

All notable changes to **Drive Resource** are documented in this file.

The internal Moodle component is `mod_videoplayer` for compatibility with previous installations.

## v1.1.20-beta - 2026-07-24

### Fixed

- Removed all Drive Resource viewer rules from the globally compiled `styles.css` bundle.
- Prevented the activity viewer CSS from affecting third-party course formats such as Tiles/Mosaico, Moodle navigation, cards, modals, themes, or unrelated plugins.
- Preserved the complete PDF, ebook, video and fullscreen presentation by moving the former global rules to `styles_activity.css`, which is requested only by `mod/videoplayer/view.php`.

### Changed

- `styles.css` is intentionally limited to documentation comments so Moodle can compile it globally without introducing presentation or interaction rules.
- Release metadata bumped to `1.1.20-beta` with Moodle version `2026072401`.

### Upgrade notes

- Deploy the complete plugin directory so the new `styles_activity.css` file is present.
- Run the normal Moodle upgrade and purge all caches after deployment.
- Perform a browser hard refresh because the previous global module CSS may remain in the browser or theme cache until it is regenerated.
- No changes to `format_tiles` or any other third-party plugin are required.

## v1.1.19-beta - 2026-07-24

### Fixed

- Scoped the fallback fullscreen CSS rule to Drive Resource containers so the plugin cannot accidentally turn an unrelated theme or Moodle element into a fixed full-viewport layer.
- Prevented potential invisible overlays from intercepting clicks on course cards, activity links, breadcrumbs or other Moodle navigation controls when a theme reuses the generic `is-fallback-fullscreen` class.

### Changed

- Release metadata bumped to `1.1.19-beta` with Moodle version `2026072400` so Moodle registers the CSS hotfix and administrators can purge compiled theme caches after deployment.

### Upgrade notes

- After deploying this release, run the normal Moodle upgrade and purge all caches. Browser hard refresh may also be required because the affected rule is part of Moodle's compiled theme CSS.

## v1.1.18-beta - 2026-07-22

### Fixed

- Fixed Moodle XMLDB `ddldependencyerror` when upgrading legacy installations where `source_idx` already depends on the `videoplayer.source` field.
- Indexed `source` and `type` fields are now migrated safely by dropping their logical XMLDB indexes before field type/default changes and recreating the indexes afterwards.
- The same dependency-safe migration pattern is applied to the legacy `videoplayer_views.completed` field and its `completed_idx` index.
- Legacy nullable values are normalized before enforcing current `NOT NULL` definitions for `source`, `videourl`, `type`, completion and progress fields.
- Legacy progress normalization now checks field existence before issuing data updates, preserving compatibility with partially migrated tables.

### Changed

- Release metadata bumped to `1.1.18-beta` with Moodle version `2026072200`.
- `db/install.xml` metadata aligned to `20260722`.

### Upgrade notes

- Sites stopped by `ddl_dependency_exception` can deploy this release and rerun the normal Moodle upgrade process; the historical upgrade step is intentionally idempotent and recreates required indexes after the field migration.
- Administrators should not manually remove the physical database index when the corrected plugin files are available; XMLDB performs the dependency-safe index lifecycle using logical index definitions.

## v1.1.17-beta - 2026-07-17

### Added

- Dedicated `http_range_proxy` service for browser-facing protected upstream streaming.
- Explicit support for `HEAD`, `Range` and `If-Range` forwarding semantics required by modern media clients.
- Deduplicated ad-hoc PDF cache warming after an uncached first request.

### Changed

- Google Drive PDF cache misses now use a fast-first-byte strategy: the requested PDF range is proxied immediately while the complete cache is warmed asynchronously by Moodle cron.
- Protected upstream streaming no longer emits duplicate `Range` headers.
- Upstream `206 Partial Content`, `Content-Range`, `Content-Length`, `ETag`, `Last-Modified` and `Accept-Ranges` metadata are relayed safely where applicable.
- Upstream error bodies are no longer exposed as successful protected media responses.
- HTML5 video markup no longer hardcodes `video/mp4`, allowing Safari/iOS to negotiate the actual protected response MIME type.
- Video preload changed from `auto` to `metadata` to reduce initial bandwidth and improve mobile startup.
- Plyr initialization now preserves native seek and playback-rate capabilities and applies iOS-compatible inline playback attributes.
- Release metadata bumped to `1.1.17-beta` with Moodle version `2026071700`.

### Security

- Protected source URLs remain server-side only; the browser continues to receive only `protected.php` URLs.
- Relayed upstream header values are sanitized before being sent to the client.
- Protected proxy failures return a generic gateway response instead of leaking upstream response bodies or URLs.

### Performance

- PDF first-open latency no longer waits for a complete Google Drive PDF download when cache is cold.
- Duplicate cache-warming tasks are suppressed by Moodle's ad-hoc task queue.
- Video and PDF range requests stream without loading the complete resource into PHP memory.

## v1.1.16-beta - 2026-06-24

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
- In-memory rendered page cache and neighbor-page prefetch for the protected book viewer.
- Desktop book spine, center fold shadows and directional ebook-style page turn effects.
- Dedicated book control placement stylesheet for coherent navigation and fullscreen UI.
- Protected stream cache diagnostic header `X-Drive-Resource-Cache`.
- Deterministic server-side cache warming for protected Google Drive PDFs.
- Internal `mod_videoplayer\local\protected_stream` service for protected byte-range delivery, PDF cache warming and cache cleanup.

### Changed

- Release metadata bumped to `1.1.16-beta` with Moodle version `2026062412`.
- `protected.php` is now a thin authorised endpoint and delegates streaming, proxying and cache operations to the protected stream service.
- Google Drive PDF cache warming is reused by both the protected endpoint and the ad-hoc precache task, reducing duplicated cURL/cache code.
- Scheduled PDF cache cleanup now delegates to the shared protected stream service.
- `save_progress.php` now delegates business logic to internal services.
- PDF rendering context now supports both standard and ebook modes.
- Activity form now supports Google Drive and local protected PDF sources.
- Completion can be calculated from PDF page progress.
- README updated for protected local PDF and ebook workflows.
- PDF viewer mobile layout now uses larger touch targets, safer viewport units and reduced chrome spacing.
- PDF visual presentation was refactored into focused stylesheets for maintainability.
- PDF resources now render through the protected book viewer by default.
- Protected range responses now use short private browser caching with `no-transform` instead of global `no-store`.
- Protected streaming chunk size increased to reduce PHP flush overhead.
- Book viewer pages now receive left/right page classes and turn-direction classes for realistic desktop transitions.
- Book viewer controls now place page status at the top center, fullscreen at the top right and previous/next controls at the viewer sides.
- Desktop book spine visuals now use a softer premium gradient to avoid dark hard lines between pages.
- Desktop book pages now use a subtle static curvature effect with perspective, light page bending and inner paper shading.
- Mobile book navigation now overlays previous/next controls inside the PDF area instead of placing them below the document.
- Protected book PDFs now start from page 1 on every page load instead of resuming the last viewed page.
- Book viewer now hides the loading overlay as soon as the first visible page is rendered instead of waiting for the full desktop spread.
- Local Moodle PDFs are streamed directly from Moodle File API storage when possible, avoiding a full temporary copy before delivery.
- Google Drive PDFs are now downloaded once into Moodle local cache before serving PDF.js byte-range requests.

### Security

- Removed the default `guest` archetype from `mod/videoplayer:view` so protected resources require normal authenticated/enrolled access unless an administrator explicitly overrides permissions.
- Local PDFs are stored outside the web root in Moodle private file storage.
- Local PDF access requires Moodle login, module context and `mod/videoplayer:view` capability.
- Direct local PDF URLs are not exposed to learners.
- Viewer-level copy/right-click/download controls are implemented as deterrents only, not DRM.
- Protected resource caching is private to the authenticated browser and keeps `no-transform` to preserve byte ranges.
- Cached Google Drive PDFs remain under Moodle `localcachedir` and are served only after Moodle login, module context and capability validation.

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
