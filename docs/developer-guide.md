# Drive Resource developer guide

## Component identity

The Moodle component remains `mod_videoplayer`. Do not rename it: installations depend on this identifier for upgrades, capabilities, tables, files, privacy and backup mappings.

## Supported development target

- Moodle: 5.0–5.2.
- Baseline branch: `MOODLE_500_STABLE`.
- Minimum Moodle version: `2025041400`.
- PHP: 8.2 and 8.3.
- CI databases: MariaDB 10.11 and PostgreSQL 15.

Moodle 4.x compatibility must not be claimed unless a separate branch, metadata range and full CI matrix are provided.

## Engineering rules

- Moodle Coding Style takes precedence.
- Use Moodle APIs instead of direct filesystem/database shortcuts.
- Keep public endpoints thin and delegate business logic.
- Bundle browser libraries locally; no runtime CDN.
- Keep methods cohesive and streaming memory-bounded.
- Update source and compiled AMD files together.
- Review File API, Privacy API, Backup/Restore, Events and Completion for every data-model change.

## Main paths

```text
amd/src/                  AMD source
amd/build/                production AMD bundles
backup/moodle2/           Backup and Restore
classes/event/            Moodle events
classes/external/         AJAX/External API
classes/local/            application services
classes/privacy/          Privacy API
db/                       XMLDB, capabilities, services and tasks
tests/                    Moodle PHPUnit tests
thirdpartylibs/           local third-party libraries
styles.css                intentionally empty global stylesheet
styles_activity.css       activity-only base presentation
```

## Moodle 5.0 API contract

The plugin may use APIs verified in Moodle 5.0, including `core_external`, `core\task`, `core_privacy`, `completion_info`, File API, XMLDB, Backup/Restore and `core/ajax`/`core/notification`.

Before adopting a newer API, verify it exists in `MOODLE_500_STABLE`. Do not introduce a Moodle 5.1/5.2-only class or AMD module while `500` remains in `$plugin->supported`.

## Protected delivery

`protected.php` must execute this order:

```text
required id
→ course module
→ course
→ activity instance
→ require_login
→ context_module
→ require_capability
→ close session write lock
→ protected_stream or http_range_proxy
```

Never return raw Google Drive IDs, direct download URLs, preview URLs or upstream error bodies.

`protected_stream` owns local/cache files. `http_range_proxy` owns upstream HTTP streaming. Never send both a manual `Range` header and `CURLOPT_RANGE` for the same request.

## PDF performance

- Render visible pages only.
- Bound canvas cache size.
- Prefetch a small neighbouring set.
- Ignore stale renders after resize/orientation changes.
- Proxy cold ranges immediately and warm complete cache through a deduplicated ad-hoc task.
- Preserve original PDF bytes.

## Video development

- Keep native HTML5 controls as fallback.
- Preserve `playsinline` and `webkit-playsinline`.
- Use metadata preload unless measured evidence requires otherwise.
- Preserve valid `206 Partial Content` metadata for Safari/iOS seeking.
- Do not force a MIME type that conflicts with the protected response.

## CSS isolation

Root `styles.css` is global in Moodle and must contain no viewer presentation. Shared viewer CSS belongs in `styles_activity.css`, loaded explicitly by `view.php`.

All selectors, animations and variables must use a `mod-videoplayer` or `drive-resource` prefix. Never use unscoped generic classes for fullscreen, overlays, loading or active state.

## Database upgrades

For schema changes:

1. update `db/install.xml`;
2. add an idempotent `db/upgrade.php` step;
3. handle dependent indexes before changing indexed fields;
4. update Backup/Restore and Privacy API;
5. bump `version.php`;
6. run savepoint validation on Moodle 5.0.

Code-only releases may bump the version without adding an empty upgrade step.

## Automated tests

`tests/drive_test.php` validates supported URLs, file IDs, resource detection and protected export endpoints.

`tests/moodle50_compatibility_test.php` validates minimum core metadata, the supported branch range, PHP baseline and required Moodle APIs.

The CI workflow must pass:

- PHP lint;
- PHPCS;
- PHPDoc;
- plugin validation;
- upgrade savepoints;
- Mustache;
- Grunt/AMD;
- PHPUnit.

## Commercial release gate

A Moodle 5.0 release is not approved until all CI combinations pass and staging verifies:

- fresh install and upgrade;
- course navigation with Tiles/Mosaico and a standard format;
- local and Drive PDF opening;
- video start/seek/resume on physical iPhone Safari;
- valid and invalid byte ranges;
- progress and completion;
- backup/restore;
- Privacy API export/delete;
- no leaked Google Drive URLs;
- no developer-debug warnings.
