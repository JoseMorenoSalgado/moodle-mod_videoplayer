# Third-party libraries

Drive Resource must use local third-party libraries only. CDN references, remote script tags and externally hosted viewer assets are not allowed in production.

## Registered libraries

The plugin declares third-party libraries in `thirdpartylibs.xml`.

| Library | Local path | License | Purpose |
| --- | --- | --- | --- |
| PDF.js | `thirdpartylibs/pdfjs/` | Apache License 2.0 | Protected PDF rendering through local PDF.js. |
| Plyr | `thirdpartylibs/plyr/` | MIT | Enhanced HTML5 media player for protected video playback. |
| StPageFlip | `thirdpartylibs/pageflip/` | MIT | Optional page-turning library for ebook mode. |

## Required files

```text
thirdpartylibs/pdfjs/pdf.min.mjs
thirdpartylibs/pdfjs/pdf.worker.min.mjs
thirdpartylibs/plyr/plyr.min.js
thirdpartylibs/plyr/plyr.css
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

PageFlip is optional at runtime. If it is missing, the ebook viewer must fall back to the protected PDF.js canvas viewer.

## Moodle review checklist

Before creating a release package:

- confirm that every bundled third-party file matches the version declared in `thirdpartylibs.xml`.
- confirm that minified files retain upstream license comments when required by the upstream license.
- confirm that no JavaScript or CSS file loads assets from a CDN.
- confirm that `thirdpartylibs.xml` remains valid XML.
- confirm that local library files are not stored under `amd/src` or `amd/build`.
- confirm that AMD modules only load local URLs under `/mod/videoplayer/thirdpartylibs/`.

## Runtime rule

Learner-facing viewers must never depend on public CDNs. The plugin should continue to work in restricted school networks where only Moodle is reachable.
