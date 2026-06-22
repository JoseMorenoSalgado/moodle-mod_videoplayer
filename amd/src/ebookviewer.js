// This file is part of Moodle - http://moodle.org/

/**
 * Protected ebook viewer for Drive Resource using local PDF.js and optional StPageFlip.
 *
 * @module     mod_videoplayer/ebookviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
    const PAGEFLIP_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pageflip/page-flip.browser.js';
    const SAVE_INTERVAL = 10000;
    const MAX_INITIAL_RENDER_PAGES = 80;

    let pdfjsPromise = null;
    let pageFlipPromise = null;

    /**
     * Load local PDF.js.
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
     * Load local PageFlip browser build if installed.
     *
     * @returns {Promise<Function|null>}
     */
    const loadPageFlip = function() {
        if (!pageFlipPromise) {
            pageFlipPromise = new Promise(function(resolve) {
                if (window.St && window.St.PageFlip) {
                    resolve(window.St.PageFlip);
                    return;
                }

                const script = document.createElement('script');
                script.src = PAGEFLIP_URL;
                script.async = true;
                script.onload = function() {
                    resolve(window.St && window.St.PageFlip ? window.St.PageFlip : null);
                };
                script.onerror = function() {
                    resolve(null);
                };
                document.head.appendChild(script);
            });
        }
        return pageFlipPromise;
    };

    /**
     * Hide or show a node.
     *
     * @param {HTMLElement|null} node
     * @param {boolean} value
     */
    const hide = function(node, value) {
        if (node) {
            node.hidden = value;
        }
    };

    /**
     * Block basic copy/download gestures inside the viewer.
     *
     * @param {Event} event
     * @returns {boolean}
     */
    const block = function(event) {
        event.preventDefault();
        event.stopPropagation();
        return false;
    };

    /**
     * Harden viewer UX. This is not a substitute for server-side access control.
     *
     * @param {HTMLElement} root
     */
    const hardenViewer = function(root) {
        if (root.getAttribute('data-disable-context-menu') !== '1') {
            return;
        }

        ['contextmenu', 'dragstart', 'copy', 'cut', 'paste', 'selectstart'].forEach(function(name) {
            root.addEventListener(name, block, true);
        });
        root.addEventListener('keydown', function(event) {
            const key = (event.key || '').toLowerCase();
            if ((event.ctrlKey || event.metaKey) && ['s', 'p', 'c', 'a'].indexOf(key) !== -1) {
                block(event);
            }
        }, true);
    };

    /**
     * Notify newly earned rewards.
     *
     * @param {HTMLElement} root
     * @param {Array} rewards
     */
    const notifyRewards = function(root, rewards) {
        const region = root.querySelector('[data-region="ebook-achievements"]');
        if (!region || !rewards || !rewards.length) {
            return;
        }

        rewards.forEach(function(reward) {
            const item = document.createElement('div');
            item.className = 'alert alert-success mod-videoplayer-reward';
            item.textContent = reward.label + ' +' + reward.points;
            region.appendChild(item);
            window.setTimeout(function() {
                item.remove();
            }, 7000);
        });
    };

    /**
     * Build a canvas for a PDF page.
     *
     * @param {Object} page
     * @param {number} maxWidth
     * @returns {Promise<HTMLCanvasElement>}
     */
    const renderCanvas = function(page, maxWidth) {
        const base = page.getViewport({scale: 1});
        const scale = Math.min(Math.max(maxWidth / base.width, 0.5), 2);
        const viewport = page.getViewport({scale: scale});
        const outputScale = Math.min(window.devicePixelRatio || 1, 2);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        canvas.className = 'mod-videoplayer-ebook-page-canvas';
        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = Math.floor(viewport.width) + 'px';
        canvas.style.height = Math.floor(viewport.height) + 'px';
        canvas.setAttribute('draggable', 'false');

        return page.render({
            canvasContext: context,
            viewport: viewport,
            transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
        }).promise.then(function() {
            return canvas;
        });
    };

    /**
     * Create a PageFlip page wrapper.
     *
     * @param {HTMLCanvasElement} canvas
     * @param {number} pagenumber
     * @returns {HTMLElement}
     */
    const createPageNode = function(canvas, pagenumber) {
        const page = document.createElement('div');
        page.className = 'mod-videoplayer-ebook-page';
        page.setAttribute('data-page-number', String(pagenumber));
        page.appendChild(canvas);
        return page;
    };

    /**
     * Initialise one ebook instance.
     *
     * @param {HTMLElement} root
     * @param {Object} pdfjsLib
     * @param {Function|null} PageFlip
     */
    const initViewer = function(root, pdfjsLib, PageFlip) {
        const pdfUrl = root.getAttribute('data-pdf-url');
        const cmid = parseInt(root.getAttribute('data-cmid'), 10) || 0;
        const initialPage = Math.max(1, parseInt(root.getAttribute('data-initial-page'), 10) || 1);
        const stage = root.querySelector('[data-region="ebook-stage"]');
        const fallbackCanvas = root.querySelector('.mod-videoplayer-pdfjs-canvas');
        const previous = root.querySelector('[data-action="previous-page"]');
        const next = root.querySelector('[data-action="next-page"]');
        const fullscreen = root.querySelector('[data-action="fullscreen"]');
        const currentPageNode = root.querySelector('[data-region="current-page"]');
        const totalPagesNode = root.querySelector('[data-region="total-pages"]');
        const loading = root.querySelector('[data-region="pdfjs-loading"]');
        const container = root.closest('.mod-videoplayer-container') || document;
        const pointsNode = container.querySelector('[data-region="ebook-points"]');
        const progressNode = container.querySelector('[data-region="ebook-progress"]');

        if (!pdfUrl || !stage) {
            if (window.require) {
                window.require(['mod_videoplayer/pdfviewer'], function(PdfViewer) {
                    PdfViewer.init();
                });
            }
            return;
        }

        hardenViewer(root);
        hide(loading, false);

        let pdfDocument = null;
        let pageNumber = initialPage;
        let pageFlip = null;
        let activeSeconds = 0;
        let lastTick = Date.now();
        let lastSave = 0;
        let completed = false;

        const updateStatus = function() {
            if (!pdfDocument) {
                return;
            }
            if (currentPageNode) {
                currentPageNode.textContent = String(pageNumber);
            }
            if (totalPagesNode) {
                totalPagesNode.textContent = String(pdfDocument.numPages);
            }
            if (previous) {
                previous.disabled = pageNumber <= 1;
            }
            if (next) {
                next.disabled = pageNumber >= pdfDocument.numPages;
            }
        };

        const completionPercent = function() {
            if (!pdfDocument || !pdfDocument.numPages) {
                return 0;
            }
            return Math.min(100, Math.round((pageNumber / pdfDocument.numPages) * 10000) / 100);
        };

        const saveProgress = function(force) {
            if (!cmid || !pdfDocument) {
                return Promise.resolve();
            }
            const now = Date.now();
            if (!force && now - lastSave < SAVE_INTERVAL) {
                return Promise.resolve();
            }
            activeSeconds += Math.max(0, Math.round((now - lastTick) / 1000));
            lastTick = now;
            lastSave = now;
            const percent = completionPercent();
            completed = completed || percent >= 100;

            return Ajax.call([{
                methodname: 'mod_videoplayer_save_progress',
                args: {
                    cmid: cmid,
                    progress: activeSeconds,
                    completed: completed,
                    completionpercentage: percent,
                    lastpage: pageNumber,
                    totalpages: pdfDocument.numPages,
                    timespent: activeSeconds
                }
            }])[0].then(function(response) {
                if (response) {
                    completed = Boolean(response.completed);
                    if (pointsNode) {
                        pointsNode.textContent = 'Points: ' + response.points;
                    }
                    if (progressNode) {
                        progressNode.textContent = response.completionpercentage + '%';
                    }
                    notifyRewards(root, response.rewards);
                }
                return response;
            }).catch(Notification.exception);
        };

        const goToPage = function(num) {
            if (!pdfDocument) {
                return;
            }
            pageNumber = Math.max(1, Math.min(pdfDocument.numPages, num));
            if (pageFlip) {
                pageFlip.flip(pageNumber - 1);
            }
            updateStatus();
            saveProgress(false);
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
        if (fullscreen) {
            fullscreen.addEventListener('click', function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else if (root.requestFullscreen) {
                    root.requestFullscreen().catch(function() {
                        root.classList.add('is-fallback-fullscreen');
                    });
                } else {
                    root.classList.add('is-fallback-fullscreen');
                }
            });
        }

        window.addEventListener('beforeunload', function() {
            saveProgress(true);
        });
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                saveProgress(true);
            } else {
                lastTick = Date.now();
            }
        });

        pdfjsLib.getDocument({url: pdfUrl, withCredentials: true, rangeChunkSize: 262144}).promise.then(function(pdf) {
            pdfDocument = pdf;
            pageNumber = Math.min(initialPage, pdfDocument.numPages);
            updateStatus();

            const pagesToRender = Math.min(pdfDocument.numPages, MAX_INITIAL_RENDER_PAGES);
            const maxWidth = Math.min(Math.max(root.clientWidth / (window.innerWidth < 768 ? 1 : 2), 320), 680);
            const chain = [];

            for (let i = 1; i <= pagesToRender; i++) {
                chain.push(pdfDocument.getPage(i).then(function(page) {
                    return renderCanvas(page, maxWidth).then(function(canvas) {
                        stage.appendChild(createPageNode(canvas, i));
                    });
                }));
            }

            return Promise.all(chain);
        }).then(function() {
            if (!PageFlip) {
                hide(stage, false);
                if (fallbackCanvas) {
                    fallbackCanvas.hidden = true;
                }
                hide(loading, true);
                saveProgress(true);
                return;
            }

            pageFlip = new PageFlip(stage, {
                width: 520,
                height: 720,
                size: 'stretch',
                minWidth: 280,
                maxWidth: 720,
                minHeight: 360,
                maxHeight: 960,
                maxShadowOpacity: 0.25,
                showCover: false,
                mobileScrollSupport: true,
                usePortrait: true,
                startPage: Math.max(0, pageNumber - 1)
            });

            pageFlip.loadFromHTML(Array.prototype.slice.call(stage.querySelectorAll('.mod-videoplayer-ebook-page')));
            pageFlip.on('flip', function(event) {
                pageNumber = Math.min(pdfDocument.numPages, Math.max(1, event.data + 1));
                updateStatus();
                saveProgress(false);
            });

            hide(stage, false);
            if (fallbackCanvas) {
                fallbackCanvas.hidden = true;
            }
            hide(loading, true);
            saveProgress(true);
        }).catch(function(error) {
            Notification.exception(error);
            if (window.require) {
                window.require(['mod_videoplayer/pdfviewer'], function(PdfViewer) {
                    PdfViewer.init();
                });
            }
        });
    };

    /**
     * Initialise all ebook viewers on the page.
     */
    const init = function() {
        const viewers = Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-pdfjs-viewer[data-display-mode="ebook"]'));
        if (!viewers.length) {
            return;
        }

        Promise.all([loadPdfJs(), loadPageFlip()]).then(function(results) {
            const pdfjsLib = results[0];
            const PageFlip = results[1];
            viewers.forEach(function(root) {
                initViewer(root, pdfjsLib, PageFlip);
            });
        }).catch(function(error) {
            Notification.exception(error);
            if (window.require) {
                window.require(['mod_videoplayer/pdfviewer'], function(PdfViewer) {
                    PdfViewer.init();
                });
            }
        });
    };

    return {init: init};
});
