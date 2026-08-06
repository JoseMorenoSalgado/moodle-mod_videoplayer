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
styles_pageflip_fix.css   protected Ebook presentation and effects
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

## PDF.js module loading

PDF.js is shipped as local ES modules:

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

Do not call `import(PDFJS_URL)` directly from an AMD source. Moodle's Babel build transforms dynamic imports into RequireJS requests, but `.mjs` is not an AMD module. This can pass lint and still fail at runtime on mobile browsers.

All protected PDF viewers must depend on:

```text
mod_videoplayer/pdfjsloader
```

The loader must:

- create a local `<script type="module">` for `pdf.min.mjs`;
- validate `window.pdfjsLib.getDocument` and `GlobalWorkerOptions`;
- configure only the local `pdf.worker.min.mjs`;
- cache one loading promise per page;
- reject through a controlled Promise error;
- never use CDN, `eval`, arbitrary URLs or a RequireJS conversion.

After modifying `amd/src/pdfjsloader.js`, rebuild with Moodle Grunt and verify:

```bash
npx grunt amd --root=mod/videoplayer
```

The generated `amd/build/pdfjsloader.min.js` must contain `createElement("script")` and `type="module"`, and must not contain `_systemImportTransformerGlobalIdentifier`.

## Responsive Ebook contract

`amd/src/ebookviewer.js` owns the commercial Ebook experience. The required behavior is:

- physical phones show exactly one page in portrait and landscape;
- desktop-sized viewer containers show two facing pages;
- layout changes preserve the current page;
- the current phone page or current desktop spread renders first;
- only adjacent pages are prefetched during browser idle time;
- the document is represented initially by lightweight PageFlip placeholders;
- completion uses the furthest visible page and never decreases when turning back;
- PageFlip effects remain local and scoped through `styles_pageflip_fix.css`;
- standard PDF.js remains the controlled fallback when PageFlip cannot initialize.

Phone detection must not depend solely on viewport width. It combines container width, coarse-pointer capability and the physical short screen side so a phone rotated to landscape does not become a desktop spread.

Do not reintroduce a loop that renders dozens of pages before PageFlip initialization. The former eager-render constant `MAX_INITIAL_RENDER_PAGES` is prohibited by CI.

## PDF performance

- Render only the active phone page or active desktop spread.
- Create lightweight PageFlip placeholders instead of eagerly rendering the document.
- Prefetch only adjacent pages during browser idle time.
- Keep phones in single-page mode after orientation changes.
- Use a two-page spread only when the viewer container can support it.
- Bound the device-pixel-ratio multiplier used by canvases.
- Ignore or replace undersized renders after meaningful resize/fullscreen changes.
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

Protected Ebook-specific rules belong in `styles_pageflip_fix.css` and must be requested only when `displaymode === 'ebook'`. All selectors, keyframes and variables must use a `mod-videoplayer` or `drive-resource` prefix. Never use unscoped generic classes for fullscreen, overlays, loading or active state.

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
- PDF.js native-ESM bundle contract;
- responsive Ebook source/build contract;
- PHPUnit.

## Commercial release gate

A Moodle 5.0 release is not approved until all CI combinations pass and staging verifies:

- fresh install and upgrade;
- course navigation with Tiles/Mosaico and a standard format;
- local and Drive PDF opening;
- one-page Ebook on a physical phone in portrait and landscape;
- two facing Ebook pages on a desktop-width browser;
- smooth swipe, button and corner page-turn effects;
- lazy first render without processing the complete document;
- PDF.js initialization on the affected physical Android/iOS browser without `workerSrc` errors;
- video start/seek/resume on physical iPhone Safari;
- valid and invalid byte ranges;
- progress and completion;
- backup/restore;
- Privacy API export/delete;
- no leaked Google Drive URLs;
- no developer-debug warnings.

## PDF renderer contract

All production PDF changes must preserve `videoplayer_get_safe_pdf_displaymode()` and the single PDF.js rendering path. Do not reintroduce PageFlip from `view.php`. Any future animated reader must be isolated behind an explicit experimental feature flag, include a mobile PDF.js fallback, remain idempotent, and pass physical-device regression tests before release.
