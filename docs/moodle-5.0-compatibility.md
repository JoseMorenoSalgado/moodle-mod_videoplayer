# Moodle 5.0 compatibility audit

## Product contract

Drive Resource `1.1.22-beta` supports Moodle 5.0, 5.1 and 5.2.

```php
$plugin->requires = 2025041400;
$plugin->supported = [500, 502];
```

The supported PHP runtime is 8.2 or 8.3.

## Automated matrix

The GitHub Actions workflow `.github/workflows/moodle-50-ci.yml` installs the plugin on `MOODLE_500_STABLE` using:

- PHP 8.2 with MariaDB 10.11;
- PHP 8.2 with PostgreSQL 15;
- PHP 8.3 with MariaDB 10.11;
- PHP 8.3 with PostgreSQL 15.

Each environment executes:

1. clean Moodle and plugin installation;
2. PHP syntax validation;
3. Moodle Coding Style;
4. PHPDoc validation;
5. official plugin validation;
6. XMLDB upgrade-savepoint validation;
7. Mustache validation;
8. AMD/JavaScript validation;
9. PHPUnit.

## Compatibility changes completed

- Declared the Moodle 5.0–5.2 supported range in `version.php`.
- Replaced the obsolete Moodle 4.1 CI definition with a Moodle 5.0 matrix.
- Made `videoplayer.videourl` nullable for Moodle-local protected PDFs.
- Added an idempotent XMLDB upgrade step that preserves existing Drive URLs.
- Normalised Backup/Restore task classes for Moodle 5.0.
- Added PHPUnit tests for Drive URL handling and the Moodle 5.0 API contract.
- Added the standard `videoplayer:addinstance` capability language string.
- Formatted the English and Spanish language packs with the ruleset loaded by an installed Moodle 5.0 environment.
- Added PHPUnit coverage attributes required by the Moodle test standard.
- Kept viewer CSS outside the globally compiled module stylesheet to preserve third-party course-format navigation.

## APIs reviewed

The implementation uses APIs available in Moodle 5.0:

- Activity module callbacks and `moodleform_mod`;
- File API;
- Completion API;
- Events API;
- External API under `core_external`;
- scheduled and ad-hoc Task API;
- Privacy API;
- Backup and Restore API;
- XMLDB;
- AMD `core/ajax` and `core/notification`.

No Moodle 5.1- or 5.2-only API is required by the current plugin code.

## Required staging validation

Automated compatibility does not replace functional QA on the target site. Before production deployment, validate:

- upgrade from the currently installed Drive Resource version;
- activity creation and editing;
- local protected PDF delivery;
- Google Drive PDF cold-cache and cache-hit behaviour;
- video start, pause, seeking and resume;
- physical iPhone Safari playback;
- progress and Completion API state;
- Backup/Restore;
- Privacy API export and deletion;
- Tiles/Mosaico animated navigation without modifying the third-party format;
- Moodle developer debugging with no new warnings.
