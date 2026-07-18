# Drive Resource 1.1.18 production release gate

This document records the release gate for `1.1.18-beta` (`2026071702`).

## Automated gates

The release candidate must pass both repository workflows on the exact commit proposed for deployment.

The final automated run must occur after the precise progress-report, language, changelog and formal-CI remediation changes are present in the branch. A previously green commit is not sufficient for release approval.

### Drive Resource quality

Required checks:

- PHP 8.2 syntax.
- PHP 8.3 syntax.
- JavaScript syntax.
- XML well-formedness.
- protected-architecture guardrails.
- local PDF.js/Plyr asset presence.
- no runtime CDN dependency.
- critical AMD source/build consistency.

### Moodle Plugin CI

The formal matrix is:

- Moodle 5.0 with PHP 8.2 and PostgreSQL 16.
- Moodle 5.2 with PHP 8.3 and PostgreSQL 16.

Required formal checks:

- Moodle environment installation.
- PHP lint.
- Moodle Code Checker (PHPCS).
- Moodle PHPDoc Checker.
- plugin validation.
- XMLDB upgrade savepoints.
- Mustache lint/HTML validation.
- Grunt JavaScript/CSS checks.
- PHPUnit.

Legacy unused `ebookviewer` AMD files and the obsolete native PDF iframe template were removed during formal remediation rather than excluded from quality checks.

## Staging gates

Automated CI does not replace Moodle runtime validation. Before production deployment:

- upgrade an existing staging installation to `2026071702`;
- confirm XMLDB adds `visitedpages`, `lastsecond`, `totalseconds` and `watchedranges`;
- enable Moodle developer debugging and confirm no warnings/notices during the test matrix;
- confirm cron and ad-hoc PDF cache warming execute normally;
- verify cold PDF response (`MISS_QUEUED`/`MISS`) followed by warmed cache (`HIT`);
- verify authenticated `206` range responses and invalid-range `416` behavior;
- verify PDF search, navigation, zoom, fullscreen, swipe and resume across two sessions;
- verify video play, pause, repeated seek, unique watched-range progress and resume across two sessions;
- verify Docs, Sheets and Slides render through local PDF.js without Google preview UI;
- verify protected images never expose a direct Drive URL in learner HTML;
- verify Backup/Restore with user data;
- verify Privacy API export/delete;
- verify Completion API and events.

## Physical mobile gates

At least one physical iPhone/iPad Safari test is mandatory before production approval:

- tap-to-play;
- inline playback;
- repeated seek;
- resume from saved second;
- fullscreen/native presentation;
- portrait/landscape rotation;
- lock/unlock recovery;
- replay after reload.

Repeat the core media/PDF smoke tests on Android Chrome.

## Release decision

Production approval requires:

```text
AUTOMATED CI GREEN
+
MOODLE STAGING GREEN
+
PHYSICAL IOS GREEN
+
SECURITY/RANGE TESTS GREEN
+
BACKUP/RESTORE + PRIVACY GREEN
```

Do not merge or deploy solely because GitHub reports the pull request as mergeable.

## Separate enterprise milestone

Private Google Drive access through site-owned OAuth/service-account Drive API credentials is tracked separately. `1.1.18-beta` must not be advertised as supporting private enterprise Drive files until that integration is implemented and tested.
