# Drive Resource manual test checklist

Run these tests on a staging Moodle site with developer debugging enabled before merging to `main`.

## Environment

- Moodle target staging version installed.
- PHP 8.2+.
- PHP cURL enabled.
- Moodle cron and ad-hoc tasks running.
- PDF.js files installed locally.
- Plyr `plyr.min.js` and `plyr.css` installed locally.
- PageFlip files installed locally when testing optional ebook behavior.
- Browser cache and Moodle caches purged before cold-cache tests.

## Fresh install

- Install the plugin from a clean database state.
- Confirm no XMLDB errors.
- Confirm `thirdpartylibs.xml` is accepted.
- Confirm capabilities are available in role definitions.
- Confirm the activity appears as Drive Resource.

## Upgrade from previous version

- Install current `main` version first.
- Create existing Google Drive PDF and video activities.
- Upgrade to the feature branch.
- Confirm Moodle upgrade completes.
- Confirm existing activities still open.
- Confirm no database migration is unexpectedly required for `1.1.17-beta`.

## Authentication and authorization

- Access `protected.php?id=<cmid>` while logged out: access must be denied.
- Access as guest: denied by default.
- Access as unenrolled user without capability: denied.
- Access as enrolled learner: allowed according to role.
- Access as teacher: allowed according to role.
- Confirm protected viewer HTML contains no raw Google Drive file ID, download URL or preview URL.

## Local protected PDF

- Create a Drive Resource activity using Local protected PDF.
- Confirm the PDF is stored outside the web root.
- Open as a learner.
- Confirm the PDF renders through `protected.php`.
- Confirm progress is saved.

### Local byte-range protocol

Test authenticated requests for:

```text
bytes=0-0
bytes=0-1023
bytes=1024-
bytes=-500
```

Verify:

- valid ranges return HTTP `206`;
- `Content-Range` start/end values are correct;
- `Content-Length` equals the returned segment length;
- `bytes=-500` returns the final 500 bytes, not the first 500 bytes;
- an unsatisfiable range returns HTTP `416` and `Content-Range: bytes */<size>`;
- `HEAD` returns headers without a response body.

## Google Drive PDF cold-cache performance

- Clear the plugin PDF cache for the test resource.
- Open the PDF as a learner.
- Confirm the first protected response reports `MISS_QUEUED` or `MISS`.
- Confirm the first visible page begins loading without waiting for the complete PDF cache download.
- Confirm a `precache_pdf` ad-hoc task is queued when cron is available.
- Run cron/ad-hoc tasks.
- Reopen the PDF.
- Confirm the protected response reports `HIT`.
- Confirm the PDF content and visual quality are identical before and after cache warming.
- Confirm no PDF rasterization or recompression artifacts are introduced.

## PDF viewer behavior

- Test a small PDF under 10 pages.
- Test a medium PDF around 50 pages.
- Test a large PDF over 100 pages.
- Navigate forward and backward.
- Test fullscreen.
- Test portrait and landscape PDFs.
- Rotate a mobile device while a page is open.
- Confirm no JavaScript console errors.
- Confirm memory remains stable during repeated navigation.

## Protected video desktop

- Create a Google Drive video activity.
- Confirm the browser source points only to `protected.php`.
- Play and pause.
- Seek forward and backward several times.
- Change playback speed.
- Enter and exit fullscreen.
- Confirm no upstream Google Drive UI appears.
- Confirm no raw Drive URL is rendered in page HTML.

### Video range protocol

Test authenticated protected endpoint requests for:

```text
Range: bytes=0-1
Range: bytes=<middle>-<middle+1023>
```

Verify, when the upstream supports ranges:

- HTTP `206` is preserved;
- `Content-Range` is present and correct;
- `Content-Length` matches the requested range;
- `Accept-Ranges: bytes` is present when supported;
- only one outgoing range is sent by the proxy path;
- upstream 4xx/5xx response bodies are not exposed as successful HTTP 200 media.

## iPhone/iPad Safari video

Test on at least one physical iPhone when possible:

- initial play from a user tap;
- inline playback;
- pause and resume;
- seek forward and backward;
- repeated seeks after several minutes of playback;
- playback speed where supported;
- native/Plyr fullscreen behavior;
- portrait-to-landscape rotation;
- lock screen and unlock while paused/playing;
- network transition between Wi-Fi and mobile data when practical;
- reload and replay the same protected video;
- confirm native controls remain usable if Plyr initialization is intentionally blocked.

Check Safari Web Inspector/network logs for failed range requests or unexpected HTML responses from the protected media endpoint.

## Android and Moodle app

- Repeat play, pause, seek and fullscreen tests in Android Chrome.
- Repeat core playback tests in Moodle app WebView when the plugin is intended for app use.

## Gamification

- Enable gamification.
- Reach first page, 25%, 50%, 75% and completion.
- Confirm rewards are not duplicated after refresh.
- Confirm total points update.
- Confirm events are present in Moodle logs.

## Completion

- Configure completion percentage.
- Read/view past the threshold.
- Confirm Moodle completion is marked.
- Confirm `resource_completed` fires only once per user completion transition.

## Backup and restore

- Backup a course with a local PDF Drive Resource.
- Include user data.
- Restore into another course.
- Confirm PDF file restores.
- Confirm activity settings restore.
- Confirm progress restores when user data is included.
- Confirm rewards restore when user data is included.

## Privacy API

- Run privacy export for a user with progress and rewards.
- Confirm progress fields are exported.
- Confirm rewards are exported.
- Delete user data for the activity context.
- Confirm `videoplayer_views` and `videoplayer_rewards` records are removed.

## Final performance and security checks

- Confirm no full-file PHP buffering for large protected resources.
- Confirm cold PDF first-open latency is lower than the previous synchronous warm path.
- Confirm repeated PDF requests use local cache after warming.
- Confirm local/cache suffix ranges are correct.
- Confirm no direct source URLs are leaked.
- Confirm Moodle developer debug log has no new warnings/errors.
- Confirm browser console is clean.
