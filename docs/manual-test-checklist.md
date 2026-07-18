# Drive Resource manual production test checklist

Run this checklist on a staging Moodle site with developer debugging enabled before merging/deploying a production release.

## 1. Environment

- Moodle version is 5.0 or newer for the current declared plugin requirement.
- PHP version is supported by that Moodle branch.
- PHP cURL enabled.
- HTTPS enabled.
- Moodle cron running.
- Ad-hoc tasks processed.
- `$CFG->localcachedir` writable.
- PDF.js local files present.
- Plyr local files present.
- PageFlip local files present only if optional book mode is tested.
- Moodle caches purged after upgrade.

## 2. CI gate

The release commit/PR must have green checks for:

- PHP 8.2 syntax.
- PHP 8.3 syntax.
- JavaScript syntax.
- XML well-formedness.
- protected architecture guardrails.
- required local assets.
- no runtime CDN dependency.
- critical PDF AMD source/build consistency.

Do not deploy a commit with missing/failed CI checks.

## 3. Fresh install and upgrade

### Fresh install

- Install on a clean staging database.
- Confirm no XMLDB errors.
- Confirm activity appears as **Drive Resource**.
- Confirm capabilities are created.

### Upgrade

- Start from the previous production release.
- Keep existing Google Drive video/PDF activities.
- Upgrade to `1.1.18-beta`.
- Confirm Moodle upgrade completes.
- Confirm existing activities open.
- Confirm no unexpected DB migration error.

## 4. Authorization

For `protected.php?id=<cmid>`:

- logged out: denied/login required;
- guest: denied by default;
- unenrolled user without capability: denied;
- enrolled learner: allowed;
- teacher with capability: allowed.

Inspect learner HTML:

- no raw Drive file ID;
- no direct Google download URL;
- no Google preview URL;
- no open-in-Drive link;
- no OAuth token.

## 5. Generic file safety

Use a generic `drive.google.com/file/d/...` URL with resource type **Automatic**.

- Saving must require an explicit supported type when MIME cannot be inferred.
- Select Video/PDF/Image explicitly and save successfully.
- Unknown generic `file` type must not render as active same-origin iframe content.

## 6. No Google preview viewer

Test:

- Google Docs;
- Google Sheets;
- Google Slides;
- PDF;
- image;
- video.

Verify no protected learner-facing route loads a Google preview iframe.

Docs/Sheets/Slides must render through the local PDF.js path.

## 7. Local protected PDF protocol

Create a local protected PDF and test authenticated requests:

```text
Range: bytes=0-0
Range: bytes=0-1023
Range: bytes=1024-
Range: bytes=-500
```

Verify:

- valid ranges return `206`;
- `Content-Range` values are correct;
- `Content-Length` equals returned segment size;
- suffix range returns the final bytes;
- unsatisfiable range returns `416` and `Content-Range: bytes */<size>`;
- `HEAD` returns headers with no body.

## 8. Google Drive PDF cold-cache performance

- Clear the test resource cache.
- Open PDF as learner.
- Confirm first response reports `MISS_QUEUED` or `MISS`.
- Confirm PDF.js begins loading without waiting for complete cache warming.
- Confirm a `precache_pdf` ad-hoc task is queued when cron is available.
- Run cron.
- Reopen PDF.
- Confirm response reports `HIT`.
- Compare document quality before/after cache warming.
- Confirm no rasterization/recompression artifacts.

## 9. Standard PDF viewer

Test small (<10 pages), medium (~50 pages) and large (100+ pages) PDFs.

Verify:

- first page renders;
- previous/next works;
- zoom in/out works;
- fit-to-screen works;
- fullscreen works;
- mobile swipe works;
- text search finds a known phrase and navigates to the matching page;
- repeated search moves to another matching page/wraps;
- no JavaScript console errors;
- no full-document eager canvas rendering;
- memory remains stable during repeated navigation.

## 10. PDF progress correctness

Session 1:

- open page 1;
- view several pages;
- navigate backward to an earlier page;
- leave the activity.

Session 2:

- reopen activity;
- confirm resume starts from the actual page where session 1 ended, not the highest page ever reached;
- confirm active time continues from previous stored time;
- confirm completion percentage does not jump to 100% merely by navigating directly near the end;
- confirm Moodle completion occurs only after configured percentage is reached.

## 11. PDF mobile/iOS

On physical iPhone/iPad Safari:

- open large PDF;
- rotate portrait/landscape;
- zoom in/out;
- pan a zoomed page;
- confirm mobile stabilizer does not reset zoom;
- use search;
- enter/exit fullscreen where supported;
- navigate at least 20 pages;
- confirm no horizontal initial offset when a page fits;
- confirm no canvas overflow regression.

Repeat core PDF flow on Android Chrome.

## 12. Protected video range protocol

Use a range-capable Drive video.

Test authenticated protected endpoint:

```text
Range: bytes=0-1
Range: bytes=<middle>-<middle+1023>
```

Verify when upstream supports ranges:

- HTTP `206` preserved;
- valid `Content-Range`;
- correct `Content-Length`;
- `Accept-Ranges: bytes` when available;
- no duplicate upstream range behavior;
- upstream HTML/login/warning page is not returned as HTTP 200 video;
- upstream 4xx/5xx body is not leaked as successful media.

## 13. iPhone/iPad Safari video

Use a physical device.

Test:

- user-tap initial play;
- inline playback;
- pause/resume;
- seek forward/backward;
- repeated seek after several minutes;
- playback speed where supported;
- fullscreen/native presentation;
- portrait/landscape rotation;
- lock/unlock;
- reload and replay;
- native HTML5 controls when Plyr is intentionally unavailable;
- Wi-Fi/mobile-data transition when practical.

Inspect Safari Web Inspector network logs for failed ranges or unexpected HTML responses.

## 14. Android and Moodle app

- Android Chrome play/pause/seek/fullscreen.
- Core video/PDF smoke test in Moodle app WebView if app usage is supported for the release.

## 15. Images

- Create protected Drive image activity.
- Confirm image loads through `protected.php`.
- Confirm responsive scaling desktop/mobile.
- Confirm raw Drive URL is absent from learner HTML.
- Verify watermark when enabled.
- Verify context-menu setting behavior.

## 16. Google Workspace export

For Docs/Sheets/Slides:

- open as learner;
- confirm local PDF.js viewer;
- confirm no Google logo/preview controls;
- confirm search works on exported text when PDF text extraction is available;
- confirm cold/warm cache behavior.

## 17. Completion/events

- Configure completion threshold.
- Reach threshold through legitimate viewing.
- Confirm Moodle module completion.
- Confirm `resource_completed` fires once on transition.
- Confirm `progress_updated` events contain expected state.
- Confirm `course_module_viewed` event.

## 18. Gamification

When enabled:

- confirm rewards are not duplicated after refresh;
- confirm points persist;
- confirm reward event logging.

## 19. Backup and restore

- backup course with Drive Resource activities;
- include user data;
- restore to another course;
- confirm local PDF restores;
- confirm settings restore;
- confirm progress/rewards restore according to Moodle backup behavior;
- confirm no cross-course file leakage.

## 20. Privacy API

- export data for a learner with progress/rewards;
- verify progress, last page, total pages, time, completion, points/rewards;
- delete user data for activity context;
- confirm records removed.

## 21. Final security/performance review

- Moodle developer debug log clean.
- PHP warnings/notices clean.
- browser console clean.
- no direct source URL leakage.
- no Google preview iframe route.
- no runtime CDN requests.
- no full-file PHP memory buffering for large streamed resources.
- PDF cache cleanup task works.
- cron does not accumulate duplicate `precache_pdf` tasks.

## 22. Known commercial limitation acknowledgement

Before release approval, document whether the deployment uses only shareable Drive resources.

Private enterprise Google Drive access through site-owned OAuth/service-account Drive API integration is not part of `1.1.18-beta` and must not be advertised as available until implemented/tested.

## Release decision

Only mark the release **production approved** after:

```text
CI GREEN
+
STAGING MOODLE GREEN
+
PHYSICAL IPHONE VIDEO GREEN
+
PDF COLD/WARM CACHE GREEN
+
SECURITY ACCESS TESTS GREEN
```
