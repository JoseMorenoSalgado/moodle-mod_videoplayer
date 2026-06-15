# PDF.js local files

Place the official PDF.js distribution files in this directory:

```text
pdf.min.mjs
pdf.worker.min.mjs
```

Recommended source:

- https://github.com/mozilla/pdf.js/releases

Download a `pdfjs-*-dist.zip` release and copy:

```text
build/pdf.min.mjs
build/pdf.worker.min.mjs
```

into this directory.

Drive Resource loads PDF.js from this local path:

```text
/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs
/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs
```

Do not rename the files unless you also update `amd/src/pdfviewer.js`.
