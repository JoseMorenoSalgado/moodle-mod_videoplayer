// This file is part of Moodle - http://moodle.org/

/**
 * Protected responsive book viewer for Drive Resource.
 *
 * Desktop renders a two-page spread. Mobile renders one page with a light
 * flip-style transition. The PDF source remains protected through Moodle.
 *
 * @module     mod_videoplayer/bookviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
    const SAVE_INTERVAL = 10000;
    const MOBILE_QUERY = '(max-width: 767.98px)';
    const MOBILE_CACHE_LIMIT = 5;
    const DESKTOP_CACHE_LIMIT = 8;
    const PREFETCH_DELAY = 80;
    let pdfjsPromise = null;

    /**
     * Load local PDF.js once.
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
     * Whether current viewport is mobile.
     *
     * @returns {boolean}
     */
    const isMobile = function() {
        return window.matchMedia(MOBILE_QUERY).matches;
    };

    /**
     * Hide or show a DOM node.
     *
     * @param {HTMLElement|null} node
     * @param {boolean} value
     * @returns {void}
     */
    const hide = function(node, value) {
        if (node) {
            node.hidden = value;
        }
    };

    /**
     * Block unsafe viewer interactions.
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
     * Apply viewer-level deterrents. Server-side authorization remains mandatory.
     *
     * @param {HTMLElement} root
     * @returns {void}
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
     * Initialise one book viewer.
     *
     * @param {HTMLElement} root
     * @param {Object} pdfjsLib
     * @returns {void}
     */
    const initViewer = function(root, pdfjsLib) {
        const pdfUrl = root.getAttribute('data-pdf-url');
        const cmid = parseInt(root.getAttribute('data-cmid'), 10) || 0;
        const initialPage = Math.max(1, parseInt(root.getAttribute('data-initial-page'), 10) || 1);
        const reader = root.closest('.mod-videoplayer-book-reader') || root;
        const stage = root.querySelector('[data-region="book-stage"]');
        const pagesRegion = root.querySelector('[data-region="book-pages"]');
        const previous = root.querySelector('[data-action="previous-page"]');
        const next = root.querySelector('[data-action="next-page"]');
        const fullscreen = root.querySelector('[data-action="fullscreen"]');
        const currentPageNode = root.querySelector('[data-region="current-page"]');
        const totalPagesNode = root.querySelector('[data-region="total-pages"]');
        const loading = root.querySelector('[data-region="book-loading"]');
        const error = root.querySelector('[data-region="book-error"]');
        const progressNode = (root.closest('.mod-videoplayer-container') || document).querySelector('[data-region="book-progress"]');

        if (!pdfUrl || !stage || !pagesRegion) {
            hide(loading, true);
            hide(error, false);
            return;
        }

        hardenViewer(root);

        let pdfDocument = null;
        let pageNumber = initialPage;
        let rendering = false;
        let pendingPage = null;
        let lastSave = 0;
        let activeSeconds = 0;
        let lastTick = Date.now();
        let completed = false;
        let touchStartX = 0;
        let touchStartY = 0;
        let touchMoved = false;
        let renderVersion = 0;
        let turnDirection = 'forward';

        const pageCache = new Map();
        const renderPromises = new Map();

        /**
         * Get start page for desktop spread.
         *
         * @param {number} num
         * @returns {number}
         */
        const getSpreadStart = function(num) {
            if (isMobile()) {
                return num;
            }
            return num <= 1 ? 1 : (num % 2 === 0 ? num : num - 1);
        };

        /**
         * Get currently visible pages.
         *
         * @returns {number[]}
         */
        const getVisiblePages = function() {
            if (!pdfDocument) {
                return [];
            }
            if (isMobile()) {
                return [pageNumber];
            }
            const start = getSpreadStart(pageNumber);
            const pages = [start];
            if (start + 1 <= pdfDocument.numPages) {
                pages.push(start + 1);
            }
            return pages;
        };

        /**
         * Build a cache key tied to layout mode and page width.
         *
         * @param {number} pageIndex
         * @returns {string}
         */
        const getCacheKey = function(pageIndex) {
            return [isMobile() ? 'm' : 'd', Math.round(getPageWidth()), pageIndex].join(':');
        };

        /**
         * Limit rendered canvas cache to avoid memory pressure.
         *
         * @returns {void}
         */
        const pruneCache = function() {
            const limit = isMobile() ? MOBILE_CACHE_LIMIT : DESKTOP_CACHE_LIMIT;
            while (pageCache.size > limit) {
                const firstKey = pageCache.keys().next().value;
                pageCache.delete(firstKey);
            }
        };

        /**
         * Clear cached pages after layout dimension changes.
         *
         * @returns {void}
         */
        const clearPageCache = function() {
            pageCache.clear();
            renderPromises.clear();
        };

        /**
         * Update page counter and navigation state.
         *
         * @returns {void}
         */
        const updateStatus = function() {
            if (!pdfDocument) {
                return;
            }
            if (currentPageNode) {
                const pages = getVisiblePages();
                currentPageNode.textContent = pages.length > 1 ? pages[0] + '-' + pages[1] : String(pageNumber);
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

        /**
         * Current completion percentage.
         *
         * @returns {number}
         */
        const completionPercent = function() {
            if (!pdfDocument || !pdfDocument.numPages) {
                return 0;
            }
            return Math.min(100, Math.round((pageNumber / pdfDocument.numPages) * 10000) / 100);
        };

        /**
         * Save reading progress.
         *
         * @param {boolean} force
         * @returns {Promise}
         */
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
                    if (progressNode) {
                        progressNode.textContent = response.completionpercentage + '%';
                    }
                }
                return response;
            }).catch(Notification.exception);
        };

        /**
         * Calculate target page width for current layout.
         *
         * @returns {number}
         */
        const getPageWidth = function() {
            const stageWidth = Math.max(stage.clientWidth - (isMobile() ? 24 : 128), 280);
            return isMobile() ? Math.min(stageWidth, 720) : Math.min(stageWidth / 2, 560);
        };

        /**
         * Render one page to canvas with in-memory cache.
         *
         * @param {number} pageIndex
         * @returns {Promise<HTMLCanvasElement>}
         */
        const renderPageCanvas = function(pageIndex) {
            const cacheKey = getCacheKey(pageIndex);

            if (pageCache.has(cacheKey)) {
                const cached = pageCache.get(cacheKey);
                pageCache.delete(cacheKey);
                pageCache.set(cacheKey, cached);
                return Promise.resolve(cached);
            }

            if (renderPromises.has(cacheKey)) {
                return renderPromises.get(cacheKey);
            }

            const promise = pdfDocument.getPage(pageIndex).then(function(page) {
                const base = page.getViewport({scale: 1});
                const targetWidth = getPageWidth();
                const scale = Math.min(Math.max(targetWidth / base.width, 0.5), 2.2);
                const viewport = page.getViewport({scale: scale});
                const outputScale = Math.min(window.devicePixelRatio || 1, 2);
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

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
                    pageCache.set(cacheKey, canvas);
                    pruneCache();
                    return canvas;
                });
            }).finally(function() {
                renderPromises.delete(cacheKey);
            });

            renderPromises.set(cacheKey, promise);
            return promise;
        };

        /**
         * Get neighbor pages to prefetch.
         *
         * @returns {number[]}
         */
        const getPrefetchPages = function() {
            if (!pdfDocument) {
                return [];
            }

            if (isMobile()) {
                return [pageNumber + 1, pageNumber - 1]
                    .filter(function(num) {
                        return num >= 1 && num <= pdfDocument.numPages;
                    });
            }

            const start = getSpreadStart(pageNumber);
            return [start + 2, start + 3, start - 1, start - 2]
                .filter(function(num) {
                    return num >= 1 && num <= pdfDocument.numPages;
                });
        };

        /**
         * Prefetch neighbor pages after visible pages are ready.
         *
         * @returns {void}
         */
        const prefetchPages = function() {
            window.setTimeout(function() {
                getPrefetchPages().forEach(function(num) {
                    renderPageCanvas(num).catch(function() {
                        // Prefetch is best-effort and should never block reading.
                    });
                });
            }, PREFETCH_DELAY);
        };

        /**
         * Build one page node with book-side classes.
         *
         * @param {HTMLCanvasElement} canvas
         * @param {number} pageIndex
         * @param {number} position
         * @returns {HTMLElement}
         */
        const createPageNode = function(canvas, pageIndex, position) {
            const pageNode = document.createElement('div');
            pageNode.className = 'mod-videoplayer-book-page';
            pageNode.setAttribute('data-page-number', String(pageIndex));

            if (isMobile()) {
                pageNode.classList.add('mod-videoplayer-book-page-single', 'is-mobile-turning');
                pageNode.classList.add(turnDirection === 'backward' ? 'is-mobile-turning-backward' : 'is-mobile-turning-forward');
            } else {
                const side = position === 0 ? 'left' : 'right';
                pageNode.classList.add('mod-videoplayer-book-page-' + side);
                if (turnDirection === 'forward' && side === 'right') {
                    pageNode.classList.add('is-turning-forward');
                } else if (turnDirection === 'backward' && side === 'left') {
                    pageNode.classList.add('is-turning-backward');
                }
            }

            pageNode.appendChild(canvas);
            return pageNode;
        };

        /**
         * Render current spread or mobile page.
         *
         * @returns {void}
         */
        const renderSpread = function() {
            if (!pdfDocument || rendering) {
                return;
            }

            const currentRenderVersion = ++renderVersion;
            rendering = true;
            pagesRegion.classList.remove('is-turning-forward', 'is-turning-backward');
            pagesRegion.classList.add('is-turning', turnDirection === 'backward' ? 'is-turning-backward' : 'is-turning-forward');
            hide(loading, false);
            pagesRegion.innerHTML = '';

            const visiblePages = getVisiblePages();
            const renderers = visiblePages.map(function(num, position) {
                return renderPageCanvas(num).then(function(canvas) {
                    if (currentRenderVersion !== renderVersion) {
                        return;
                    }
                    pagesRegion.appendChild(createPageNode(canvas, num, position));
                });
            });

            Promise.all(renderers).then(function() {
                if (currentRenderVersion !== renderVersion) {
                    return;
                }
                if (!isMobile() && visiblePages.length === 1) {
                    const empty = document.createElement('div');
                    empty.className = 'mod-videoplayer-book-page mod-videoplayer-book-page-right is-empty';
                    pagesRegion.appendChild(empty);
                }
                rendering = false;
                hide(loading, true);
                pagesRegion.classList.remove('is-turning', 'is-turning-forward', 'is-turning-backward');
                updateStatus();
                saveProgress(false);
                prefetchPages();
                if (pendingPage !== null) {
                    const queued = pendingPage;
                    pendingPage = null;
                    goToPage(queued);
                }
            }).catch(function(err) {
                rendering = false;
                hide(loading, true);
                pagesRegion.classList.remove('is-turning', 'is-turning-forward', 'is-turning-backward');
                hide(error, false);
                Notification.exception(err);
            });
        };

        /**
         * Go to target page.
         *
         * @param {number} num
         * @returns {void}
         */
        const goToPage = function(num) {
            if (!pdfDocument) {
                return;
            }
            const previousPage = pageNumber;
            const safe = Math.max(1, Math.min(pdfDocument.numPages, num));
            const targetPage = isMobile() ? safe : getSpreadStart(safe);
            turnDirection = targetPage < previousPage ? 'backward' : 'forward';
            pageNumber = targetPage;
            if (rendering) {
                pendingPage = pageNumber;
                return;
            }
            renderSpread();
        };

        if (previous) {
            previous.addEventListener('click', function() {
                goToPage(pageNumber - (isMobile() ? 1 : 2));
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                goToPage(pageNumber + (isMobile() ? 1 : 2));
            });
        }
        if (fullscreen) {
            fullscreen.addEventListener('click', function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                    return;
                }
                if (reader.requestFullscreen) {
                    reader.requestFullscreen().catch(function() {
                        reader.classList.add('is-fallback-fullscreen');
                    });
                } else {
                    reader.classList.add('is-fallback-fullscreen');
                }
            });
            document.addEventListener('fullscreenchange', function() {
                if (!document.fullscreenElement) {
                    reader.classList.remove('is-fallback-fullscreen');
                }
                clearPageCache();
                window.setTimeout(renderSpread, 160);
            });
        }

        stage.addEventListener('touchstart', function(event) {
            if (!event.touches || event.touches.length !== 1) {
                return;
            }
            touchMoved = false;
            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
        }, {passive: true});

        stage.addEventListener('touchmove', function(event) {
            if (!event.touches || event.touches.length !== 1) {
                return;
            }
            const dx = event.touches[0].clientX - touchStartX;
            const dy = event.touches[0].clientY - touchStartY;
            touchMoved = Math.abs(dx) > 20 || Math.abs(dy) > 20;
        }, {passive: true});

        stage.addEventListener('touchend', function(event) {
            if (!touchMoved || !pdfDocument) {
                return;
            }
            const changed = event.changedTouches && event.changedTouches[0];
            if (!changed) {
                return;
            }
            const dx = changed.clientX - touchStartX;
            const dy = changed.clientY - touchStartY;
            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.35) {
                return;
            }
            goToPage(pageNumber + (dx < 0 ? 1 : -1));
        }, {passive: true});

        window.addEventListener('resize', function() {
            clearPageCache();
            window.setTimeout(renderSpread, 120);
        });
        window.addEventListener('orientationchange', function() {
            clearPageCache();
            window.setTimeout(renderSpread, 280);
        });
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

        hide(loading, false);
        pdfjsLib.getDocument({url: pdfUrl, withCredentials: true, rangeChunkSize: 262144}).promise.then(function(pdf) {
            pdfDocument = pdf;
            pageNumber = Math.min(initialPage, pdfDocument.numPages);
            if (!isMobile()) {
                pageNumber = getSpreadStart(pageNumber);
            }
            updateStatus();
            renderSpread();
        }).catch(function(err) {
            hide(loading, true);
            hide(error, false);
            Notification.exception(err);
        });
    };

    /**
     * Initialise all book viewers.
     *
     * @returns {void}
     */
    const init = function() {
        const roots = Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-book-reader'));
        if (!roots.length) {
            return;
        }
        loadPdfJs().then(function(pdfjsLib) {
            roots.forEach(function(root) {
                initViewer(root, pdfjsLib);
            });
        }).catch(function(err) {
            Notification.exception(err);
            roots.forEach(function(root) {
                hide(root.querySelector('[data-region="book-loading"]'), true);
                hide(root.querySelector('[data-region="book-error"]'), false);
            });
        });
    };

    return {
        init: init
    };
});
