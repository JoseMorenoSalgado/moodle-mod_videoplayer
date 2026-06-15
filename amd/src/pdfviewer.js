// This file is part of Moodle - http://moodle.org/

/**
 * Lightweight PDF.js viewer for Drive Resource.
 *
 * This module loads PDF.js from a CDN as an initial integration layer.
 * A future production-hardening step should vendor PDF.js into the plugin
 * to avoid external CDN dependencies.
 *
 * @module     mod_videoplayer/pdfviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification'], function(Notification) {
    const PDFJS_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs';
    const PDFJS_WORKER_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';

    let pdfjsPromise = null;

    /**
     * Load PDF.js as an ES module.
     *
     * @returns {Promise<Object>}
     */
    const loadPdfJs = function() {
        if (!pdfjsPromise) {
            pdfjsPromise = import(PDFJS_URL).then(function(pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
                return pdfjsLib;
            });
        }
        return pdfjsPromise;
    };

    /**
     * Show iframe fallback.
     *
     * @param {HTMLElement} root
     */
    const showFallback = function(root) {
        const canvasWrap = root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap');
        const toolbar = root.querySelector('.mod-videoplayer-pdfjs-toolbar');
        const fallback = root.querySelector('.mod-videoplayer-pdfjs-fallback');

        if (canvasWrap) {
            canvasWrap.hidden = true;
        }
        if (toolbar) {
            toolbar.hidden = true;
        }
        if (fallback) {
            fallback.hidden = false;
        }
    };

    /**
     * Initialise one viewer.
     *
     * @param {HTMLElement} root
     * @param {Object} pdfjsLib
     */
    const initViewer = function(root, pdfjsLib) {
        const pdfUrl = root.getAttribute('data-pdf-url');
        const canvas = root.querySelector('.mod-videoplayer-pdfjs-canvas');
        const previous = root.querySelector('[data-action="previous-page"]');
        const next = root.querySelector('[data-action="next-page"]');
        const zoomIn = root.querySelector('[data-action="zoom-in"]');
        const zoomOut = root.querySelector('[data-action="zoom-out"]');
        const currentPageNode = root.querySelector('[data-region="current-page"]');
        const totalPagesNode = root.querySelector('[data-region="total-pages"]');

        if (!pdfUrl || !canvas) {
            showFallback(root);
            return;
        }

        const context = canvas.getContext('2d');
        let pdfDocument = null;
        let pageNumber = 1;
        let scale = 1.2;
        let rendering = false;
        let pendingPage = null;

        const updateButtons = function() {
            if (!pdfDocument) {
                return;
            }
            if (previous) {
                previous.disabled = pageNumber <= 1;
            }
            if (next) {
                next.disabled = pageNumber >= pdfDocument.numPages;
            }
            if (currentPageNode) {
                currentPageNode.textContent = String(pageNumber);
            }
            if (totalPagesNode) {
                totalPagesNode.textContent = String(pdfDocument.numPages);
            }
        };

        const renderPage = function(num) {
            rendering = true;
            pdfDocument.getPage(num).then(function(page) {
                const wrap = root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap');
                const availableWidth = wrap ? Math.max(wrap.clientWidth - 24, 320) : 900;
                const originalViewport = page.getViewport({scale: 1});
                const responsiveScale = Math.min(scale, availableWidth / originalViewport.width);
                const viewport = page.getViewport({scale: responsiveScale});
                const outputScale = window.devicePixelRatio || 1;

                canvas.width = Math.floor(viewport.width * outputScale);
                canvas.height = Math.floor(viewport.height * outputScale);
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';

                const transform = outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null;

                return page.render({
                    canvasContext: context,
                    viewport: viewport,
                    transform: transform
                }).promise;
            }).then(function() {
                rendering = false;
                updateButtons();

                if (pendingPage !== null) {
                    const nextPage = pendingPage;
                    pendingPage = null;
                    renderPage(nextPage);
                }
            }).catch(function(error) {
                rendering = false;
                Notification.exception(error);
                showFallback(root);
            });
        };

        const queueRenderPage = function(num) {
            if (rendering) {
                pendingPage = num;
            } else {
                renderPage(num);
            }
        };

        const goToPage = function(num) {
            if (!pdfDocument) {
                return;
            }
            pageNumber = Math.max(1, Math.min(pdfDocument.numPages, num));
            queueRenderPage(pageNumber);
        };

        if (previous) {
            previous.addEventListener('click', function() {
                goToPage(pageNumber - 1);
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                goToPage(pageNumber + 1);
            });
        }
        if (zoomIn) {
            zoomIn.addEventListener('click', function() {
                scale = Math.min(3, scale + 0.15);
                queueRenderPage(pageNumber);
            });
        }
        if (zoomOut) {
            zoomOut.addEventListener('click', function() {
                scale = Math.max(0.5, scale - 0.15);
                queueRenderPage(pageNumber);
            });
        }

        window.addEventListener('resize', function() {
            if (pdfDocument) {
                queueRenderPage(pageNumber);
            }
        });

        pdfjsLib.getDocument({url: pdfUrl, withCredentials: true}).promise.then(function(pdf) {
            pdfDocument = pdf;
            updateButtons();
            renderPage(pageNumber);
        }).catch(function(error) {
            Notification.exception(error);
            showFallback(root);
        });
    };

    /**
     * Initialise all PDF.js viewers on the page.
     */
    const init = function() {
        const viewers = Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-pdfjs-viewer'));
        if (!viewers.length) {
            return;
        }

        loadPdfJs().then(function(pdfjsLib) {
            viewers.forEach(function(root) {
                initViewer(root, pdfjsLib);
            });
        }).catch(function(error) {
            Notification.exception(error);
            viewers.forEach(showFallback);
        });
    };

    return {
        init: init
    };
});
