# Local PageFlip library

Drive Resource loads the protected ebook viewer from local files only. Do not use a CDN in production.

Place the browser build files here:

```text
thirdpartylibs/pageflip/page-flip.browser.js
thirdpartylibs/pageflip/page-flip.css
```

The AMD module `mod_videoplayer/ebookviewer` tries to load `page-flip.browser.js` from this directory. If the file is missing, the viewer falls back to the protected PDF.js canvas flow so the activity remains usable.

Before shipping a commercial release, verify the current PageFlip package license and include the required third-party notices in Moodle `thirdpartylibs.xml` and the project documentation.
