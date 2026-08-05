# Drive Resource installation

## Supported platform

- Moodle 5.0, 5.1 or 5.2.
- Minimum core version: `2025041400`.
- PHP 8.2 or 8.3.
- PHP cURL and the standard extensions required by Moodle 5.0.
- HTTPS, working Moodle cron and writable `$CFG->localcachedir`.

Moodle 4.x is not supported by release `1.1.21-beta`.

## Install or upgrade

Copy the complete plugin directory to:

```text
mod/videoplayer
```

Verify these required files exist:

```text
mod/videoplayer/version.php
mod/videoplayer/styles_activity.css
mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs
mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs
mod/videoplayer/thirdpartylibs/plyr/plyr.css
mod/videoplayer/thirdpartylibs/plyr/plyr.min.js
```

From the Moodle application root run:

```bash
php admin/cli/maintenance.php --enable
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

For cPanel PHP 8.3:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/maintenance.php --enable
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/upgrade.php --non-interactive
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/purge_caches.php
/opt/cpanel/ea-php83/root/usr/bin/php admin/cli/maintenance.php --disable
```

Clear browser caches or test in a private window after upgrading from `1.1.19-beta` or earlier.

## Moodle 5.0 metadata

`version.php` declares:

```php
$plugin->requires = 2025041400;
$plugin->supported = [500, 502];
```

Moodle therefore accepts the plugin on branches 5.0 through 5.2 and rejects older branches outside the product contract.

## Local libraries

All viewer dependencies are local. No runtime CDN is allowed.

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
thirdpartylibs/pageflip/page-flip.browser.js   # optional
thirdpartylibs/pageflip/page-flip.css         # optional
```

PDF.js remains available when optional PageFlip assets are absent. Native HTML5 controls remain available when Plyr enhancement cannot initialise.

## Cron and cache

Cold Google Drive PDFs proxy requested ranges immediately and queue a deduplicated ad-hoc task to warm the complete cache.

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

Cron must process ad-hoc tasks frequently. The PHP/web user must be able to create directories and files under `$CFG->localcachedir`.

## Functional validation on Moodle 5.0

After installation, verify:

1. Administration reports Drive Resource `1.1.21-beta`.
2. A teacher can create and edit an activity.
3. An enrolled learner can open it.
4. Guest and unauthorised users are denied.
5. A local protected PDF opens through PDF.js.
6. A Google Drive PDF opens with cold cache and later reports a cache hit.
7. A protected video starts, pauses and seeks.
8. iPhone Safari supports inline playback, rotation and resume.
9. Progress and completion persist.
10. Backup and Restore preserve configuration and local files.
11. Privacy export/delete completes.
12. Tiles/Mosaico and a standard course format navigate normally before and after opening Drive Resource.

## Range validation

Authenticated requests to Moodle `protected.php` should support:

```text
Range: bytes=0-1
Range: bytes=<middle>-<middle+chunk>
```

Expected valid response:

```text
HTTP 206
Accept-Ranges: bytes
Content-Range: bytes start-end/total
Content-Length: requested-length
```

Invalid ranges should return `416`. Never test by exposing the upstream Google Drive URL.

## Automated validation

The repository workflow `.github/workflows/moodle-50-ci.yml` tests Moodle `MOODLE_500_STABLE` with:

- PHP 8.2 and 8.3;
- MariaDB 10.11 and PostgreSQL 15;
- PHP lint, PHPCS, PHPDoc, plugin validation, savepoints, Mustache, Grunt and PHPUnit.

A green workflow is required before commercial release, but it does not replace staging tests on the actual Moodle 5.0 server, theme, course format and mobile devices.

## Recovery after a failed upgrade

Do not delete courses, activities, tables or physical database indexes. Keep maintenance mode enabled, restore the previous complete `mod/videoplayer` directory if necessary, purge caches and inspect the first PHP/XMLDB error.

Drive Resource does not require editing `format_tiles` or any third-party course-format plugin.
