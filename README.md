# Drive Resource for Moodle

**Drive Resource** is a protected Moodle activity for publishing Google Drive and Moodle-private learning resources without exposing the upstream file URL in plugin-owned PDF and video viewers.

The internal Moodle component remains `mod_videoplayer` for upgrade, capability, database and backup compatibility. The commercial product name is **Drive Resource**.

## Supported platform

| Component | Supported |
|---|---|
| Moodle | 5.0, 5.1 and 5.2 |
| Minimum Moodle build | `2025041400` |
| PHP | 8.2 and 8.3 |
| Databases validated by CI | MariaDB and PostgreSQL |
| Browser libraries | Bundled locally; no runtime CDN |

`version.php` declares `$plugin->supported = [500, 502]`. Moodle 4.x is not part of the current release contract.

## Main capabilities

- Protected Google Drive video, PDF, image, document, spreadsheet and presentation delivery.
- Local protected PDFs stored with Moodle File API.
- Moodle-owned `protected.php` endpoint with login, course-module, context and capability checks.
- Streaming without loading complete media into PHP memory.
- `HEAD`, `Range`, `If-Range`, `206 Partial Content` and `416` handling.
- Local PDF.js rendering with selectable protected Ebook/PageFlip and standard PDF.js modes.
- Responsive Ebook with one page on phones and two facing pages on desktop-sized viewers.
- Lazy PDF rendering of the current page or spread with adjacent-page prefetch.
- Local Plyr enhancement over native HTML5 video.
- iPhone/iPad-oriented inline playback and seeking.
- Fast-first-byte PDF proxying with deduplicated asynchronous cache warming.
- Progress, completion, Moodle events, Privacy API and Backup/Restore integration.
- Activity-only CSS that cannot override third-party course formats such as Tiles/Mosaico.

## Requirements

- Moodle 5.0 or newer within the declared supported range.
- PHP 8.2+ with cURL, DOM, Fileinfo, GD, Intl, Mbstring, OpenSSL, SimpleXML, Sodium, XML and ZIP.
- HTTPS in production.
- Moodle cron running frequently.
- Writable `$CFG->localcachedir`.

## Installation

Copy the complete plugin directory to:

```text
mod/videoplayer
```

The complete directory is required, including:

```text
styles_activity.css
styles_pageflip_fix.css
amd/build/pdfjsloader.min.js
amd/build/ebookviewer.min.js
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
thirdpartylibs/plyr/plyr.css
thirdpartylibs/plyr/plyr.min.js
```

Run:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Release `1.1.26-beta` makes the protected Ebook adaptive and incremental. Phones show one page in portrait or landscape; desktop-sized viewers show two facing pages. PDF.js renders only the visible page or spread and prefetches adjacent pages during idle time. PageFlip provides the local paper, gutter, shadow, corner and page-turn effects.

Release `1.1.25-beta` fixes mobile PDF.js initialization by loading the bundled ES module through a native local `<script type="module">` element. Moodle's AMD build must not transform `pdf.min.mjs` into a RequireJS request. The shared `pdfjsloader` validates the API before assigning the bundled worker URL.

Release `1.1.24-beta` restored the protected Ebook/PageFlip execution path. Historical `displaymode = standard` records are treated as Ebook because earlier releases stored that value in a hidden field even when the activity displayed the ebook experience. The newly selectable standard PDF.js mode is stored as `pdfjs`.

After upgrading, clear the Moodle caches and the browser/site cache so previous Mustache, JavaScript and theme entries are discarded.

## Protected delivery architecture

```text
Google Drive link / Moodle private PDF
↓
Drive Resource activity
↓
protected.php
↓
require_login + course module + context_module + capability
↓
protected_stream OR http_range_proxy
↓
local PDF.js / PageFlip / HTML5 video viewer
↓
learner
```

The browser receives only Moodle URLs for protected PDF and video delivery. Raw Google Drive file IDs, direct download URLs and preview URLs must remain server-side.

## PDF delivery

Cold Google Drive PDFs use this flow:

```text
PDF.js range request
↓
Moodle authorisation
↓
queue deduplicated precache task
↓
proxy requested bytes immediately
↓
cron warms the complete local cache
↓
later requests use the protected cache
```

The original PDF bytes are preserved. The plugin does not recompress, rasterise or transcode the document.

Cache path:

```text
$CFG->localcachedir/mod_videoplayer/pdf/
```

### PDF.js loading boundary

The PDF.js application and worker are bundled locally as ES modules. Viewer modules do not call `import()` directly because Moodle's AMD/Babel pipeline transforms dynamic imports into RequireJS requests, while `pdf.min.mjs` is not an AMD module.

```text
Ebook / responsive book / standard PDF viewer
↓
mod_videoplayer/pdfjsloader
↓
local script type="module"
↓
window.pdfjsLib validation
↓
local pdf.worker.min.mjs configuration
```

The loader is cached once per page and reports a controlled viewer error when the local module cannot be initialized.

### PDF display modes

**Protected Ebook** is the default. Phones display one protected page at a time, including after rotation, while desktop-sized viewers display two facing pages. PDF.js renders only the visible page or spread and a small adjacent prefetch set. PageFlip provides touch navigation, page corners, paper texture, centre gutter, shadows and page-turn effects.

**Standard PDF.js** provides a single-page canvas with zoom, fit, previous/next navigation and fullscreen controls.

The display mode is selected in the activity form. Existing activities created while the mode field was hidden are automatically interpreted as Ebook activities.

## Video delivery

Protected video uses an HTML5 `<video>` element and local Plyr enhancement. It preserves native controls as fallback, uses `preload="metadata"`, keeps `playsinline`/`webkit-playsinline`, and relies on valid byte-range responses for Safari/iOS seeking.

## CSS isolation

Moodle compiles a module's root `styles.css` into the global theme bundle. Drive Resource intentionally keeps that file free of viewer rules.

Shared viewer presentation lives in `styles_activity.css`. Ebook-specific PageFlip presentation lives in `styles_pageflip_fix.css`. Both are loaded only by `mod/videoplayer/view.php`, preventing Drive Resource from affecting course cards, navigation, modals, themes or third-party course formats.

## Automated compatibility validation

GitHub Actions runs `.github/workflows/moodle-50-ci.yml` against:

- Moodle `MOODLE_500_STABLE`;
- PHP 8.2 and 8.3;
- MariaDB 10.11 and PostgreSQL 15.

The workflow performs PHP lint, Moodle coding style, PHPDoc validation, plugin validation, upgrade-savepoint checks, Mustache validation, AMD/JavaScript validation, the PDF.js native-ESM bundle contract, the responsive Ebook contract and PHPUnit tests.

## Development

AMD sources are under `amd/src/` and production bundles under `amd/build/`.

```bash
npx grunt amd
```

Any change to an AMD source must include its rebuilt production bundle. The generated `pdfjsloader.min.js` must contain native module-script creation and must not contain Moodle's dynamic-import transformer. The Ebook viewer must retain phone single-page mode, desktop two-page mode and lazy visible-page rendering.

## Documentation

- `docs/architecture.md`
- `docs/developer-guide.md`
- `docs/installation.md`
- `docs/security.md`
- `docs/manual-test-checklist.md`

## Release

- Release: `1.1.26-beta`
- Moodle plugin version: `2026080505`
- Component: `mod_videoplayer`
- Product: Drive Resource
- Supported Moodle branches: 5.0–5.2
- Minimum PHP: 8.2

## License

GNU GPL v3 or later. Third-party libraries and licences are declared in `thirdpartylibs.xml`.

## Maintainer

Elearning Cloud  
https://elearningcloud.io
