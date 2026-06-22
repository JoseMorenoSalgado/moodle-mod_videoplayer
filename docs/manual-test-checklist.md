# Drive Resource manual test checklist

Run these tests on a staging Moodle site with developer debugging enabled before merging to `main`.

## Environment

- Moodle 4.x or 5.x staging site.
- PHP 8.2+.
- Cron configured.
- PDF.js files installed locally.
- PageFlip files installed locally when testing ebook flipbook.
- Browser cache and Moodle cache purged.

## Fresh install

- Install the plugin from a clean database state.
- Confirm no XMLDB errors.
- Confirm `thirdpartylibs.xml` is accepted.
- Confirm capabilities are available in role definitions.
- Confirm the activity appears as Drive Resource.

## Upgrade from previous version

- Install current `main` version first.
- Create at least one existing Google Drive activity.
- Upgrade to the feature branch.
- Confirm `db/upgrade.php` completes.
- Confirm existing activities still open.
- Confirm new fields have safe defaults.

## Local protected PDF

- Create a Drive Resource activity.
- Select Local protected PDF.
- Upload a PDF.
- Save and display.
- Confirm the PDF is not stored under web root.
- Open the activity as student.
- Confirm `protected.php` requires login.
- Confirm unenrolled users cannot access the file.

## Standard PDF mode

- Select Standard PDF viewer.
- Open as student.
- Navigate pages.
- Use fullscreen.
- Confirm no JavaScript console errors.
- Confirm progress is saved.

## Ebook mode

- Select Protected ebook viewer.
- Confirm PageFlip files exist.
- Open as student.
- Confirm flipbook renders.
- Turn pages forward and backward.
- Test mobile viewport.
- Test fullscreen.
- Confirm fallback still works if PageFlip files are temporarily removed.

## Gamification

- Enable gamification.
- Open as student.
- Reach first page, 25%, 50%, 75% and completion.
- Confirm rewards are not duplicated after refresh.
- Confirm total points update.
- Confirm events are present in Moodle logs.

## Completion

- Configure completion percentage.
- Read past the threshold.
- Confirm Moodle completion is marked.
- Confirm `resource_completed` fires only once per user completion transition.

## Backup and restore

- Backup a course with local PDF Drive Resource.
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

## Security checks

- Access `protected.php?id=<cmid>` while logged out.
- Access as guest.
- Access as unenrolled user.
- Access as enrolled student.
- Verify direct Google URLs are not shown in local PDF mode HTML.
- Verify right-click/copy deterrents work inside the viewer.

## Performance checks

- Test with small PDF under 10 pages.
- Test with medium PDF around 50 pages.
- Test with large PDF over 100 pages.
- Confirm memory usage remains stable.
- Confirm Range requests work for local PDF delivery.
- Confirm no full-file PHP buffering for large protected resources.
