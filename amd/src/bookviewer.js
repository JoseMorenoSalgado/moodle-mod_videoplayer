// This file is part of Moodle - http://moodle.org/

/**
 * Protected responsive PDF book viewer for Drive Resource.
 *
 * Desktop renders a two-page spread and mobile renders one page. The viewer
 * persists exact observed pages and cumulative active reading time.
 *
 * @module     mod_videoplayer/bookviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
    const SAVE_INTERVAL = 10000;
    const HEARTBEAT_INTERVAL = 30000;
    const MOBILE_QUERY = '(max-width: 767.98px)';
    const MOBILE_CACHE_LIMIT = 5;
    const DESKTOP_CACHE_LIMIT = 8;
    const MAX_VISITED_PAGES = 20000;
    let pdfjsPromise = null;

    const loadPdfJs = function() {
        if (!pdfjsPromise) {
            pdfjsPromise = import(PDFJS_URL).then(function(pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
                return pdfjsLib;
            });
        }
        return pdfjsPromise;
    };

    const isMobile = function() {
        return window.matchMedia(MOBILE_QUERY).matches;
    };

    const hide = function(node, value) {
        if (node) {
            node.hidden = value;
        }
    };

    const block = function(event) {
        event.preventDefault();
        event.stopPropagation();
        return false;
    };

    const parseVisitedPages = function(value) {
        let parsed;
        try {
            parsed = JSON.parse(value || '[]');
        } catch (error) {
            parsed = [];
        }
        if (!Array.isArray(parsed)) {
            return [];
        }

        const unique = new Set();
        parsed.slice(0, MAX_VISITED_PAGES).forEach(function(page) {
            const num = parseInt(page, 10);
            if (num > 0) {
                unique.add(num);
            }
        });
        return Array.from(unique);
    };

    const hardenViewer = function(root) {
        if (root.getAttribute('data-disable-context-menu') !== '1') {
            return;
        }
        ['contextmenu', 'dragstart', 'copy', 'cut', 'paste', 'selectstart'].forEach(function(name) {
            root.addEventListener(name, block, true);
        });
        root.addEventListener('keydown', function(event) {
            const key = (event.key || '').toLowerCase();
            if ((event.ctrlKey || event.metaKey) && ['s', 'p'].indexOf(key) !== -1) {
                block(event);
            }
        }, true);
    };

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
        const container = root.closest('.mod-videoplayer-container') || document;
        const progressNode = container.querySelector('[data-region="book-progress"]');

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
        let activeSeconds = Math.max(0, parseFloat(root.getAttribute('data-initial-progress')) || 0);
        let timeSpent = Math.max(0, parseInt(root.getAttribute('data-initial-time-spent'), 10) || 0);
        let lastTick = Date.now();
        let completed = root.getAttribute('data-initial-completed') === '1';
        let touchStartX = 0;
        let touchStartY = 0;
        let touchMoved = false;
        let turnDirection = 'forward';
        let saveInFlight = null;
        let saveQueued = false;
        const visitedPages = new Set(parseVisitedPages(root.getAttribute('data-visited-pages')));
        const pageCache = new Map();
        const renderPromises = new Map();

        const getSpreadStart = function(num) {
            if (isMobile()) {
                return num;
            }
            if (num <= 1) {
                return 1;
            }
            return num % 2 === 0 ? num : num - 1;
        };

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

        const getPageWidth = function() {
            const stageWidth = Math.max(stage.clientWidth - (isMobile() ? 24 : 128), 280);
            return isMobile() ? Math.min(stageWidth, 720) : Math.min(stageWidth / 2, 560);
        };

        const getCacheKey = function(pageIndex) {
            return [isMobile() ? 'm' : 'd', Math.round(getPageWidth()), pageIndex].join(':');
        };

        const pruneCache = function() {
            const limit = isMobile() ? MOBILE_CACHE_LIMIT : DESKTOP_CACHE_LIMIT;
            while (pageCache.size > limit) {
                pageCache.delete(pageCache.keys().next().value);
            }
        };

        const clearPageCache = function() {
            pageCache.clear();
            renderPromises.clear();
        };

        const serializeVisitedPages = function() {
            return JSON.stringify(Array.from(visitedPages).sort(function(a, b) {
                return a - b;
            }).slice(0, MAX_VISITED_PAGES));
        };

        const absorbServerPages = function(value) {
            parseVisitedPages(value).forEach(function(page) {
                if (!pdfDocument || page <= pdfDocument.numPages) {
                    visitedPages.add(page);
                }
            });
        };

        const completionPercent = function() {
            if (!pdfDocument || !pdfDocument.numPages) {
                return 0;
            }
            return Math.min(100, Math.round((visitedPages.size / pdfDocument.numPages) * 10000) / 100);
        };

        const updateStatus = function() {
            if (!pdfDocument) {
                return;
            }
            const pages = getVisiblePages();
            if (currentPageNode) {
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

        const accrueActiveTime = function() {
            const now = Date.now();
            const delta = Math.max(0, Math.round((now - lastTick) / 1000));
            activeSeconds += delta;
            timeSpent += delta;
            lastTick = now;
        };

        const saveProgress = function(force) {
            if (!cmid || !pdfDocument) {
                return Promise.resolve();
            }
            const now = Date.now();
            if (!force && now - lastSave < SAVE_INTERVAL) {
                return Promise.resolve();
            }
            accrueActiveTime();
            lastSave = now;

            if (saveInFlight) {
                saveQueued = saveQueued || force;
                return saveInFlight;
            }

            const percent = completionPercent();
            saveInFlight = Ajax.call([{
                methodname: 'mod_videoplayer_save_progress',
                args: {
                    cmid: cmid,
                    progress: activeSeconds,
                    completed: completed || percent >= 100,
                    completionpercentage: percent,
                    lastpage: pageNumber,
                    totalpages: pdfDocument.numPages,
                    visitedpages: serializeVisitedPages(),
                    lastsecond: 0,
                    totalseconds: 0,
                    watchedranges: '',
                    timespent: timeSpent
                }
            }])[0].then(function(response) {
                if (response) {
                    completed = Boolean(response.completed);
                    activeSeconds = Math.max(activeSeconds, parseFloat(response.progress) || 0);
                    timeSpent = Math.max(timeSpent, parseInt(response.timespent, 10) || 0);
                    absorbServerPages(response.visitedpages || '');
                    if (progressNode) {
                        progressNode.textContent = response.completionpercentage + '%';
                    }
                }
                return response;
            }).catch(Notification.exception).finally(function() {
                saveInFlight = null;
                if (saveQueued) {
                    saveQueued = false;
                    window.setTimeout(function() {
                        saveProgress(true);
                    }, 0);
                }
            });
            return saveInFlight;
        };

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

            let renderedCanvas = null;
            const promise = pdfDocument.getPage(pageIndex).then(function(page) {
                const base = page.getViewport({scale: 1});
                const targetWidth = getPageWidth();
                const scale = Math.min(Math.max(targetWidth / base.width, 0.5), 2.2);
                const viewport = page.getViewport({scale: scale});
                const outputScale = Math.min(window.devicePixelRatio || 1, 2);
                renderedCanvas = document.createElement('canvas');
                const context = renderedCanvas.getContext('2d', {alpha: false});

                renderedCanvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
                renderedCanvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
                renderedCanvas.style.width = Math.floor(viewport.width) + 'px';
                renderedCanvas.style.height = Math.floor(viewport.height) + 'px';
                renderedCanvas.setAttribute('draggable', 'false');

                return page.render({
                    canvasContext: context,
                    viewport: viewport,
                    transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
                }).promise;
            }).then(function() {
                pageCache.set(cacheKey, renderedCanvas);
                pruneCache();
                return renderedCanvas;
            }).finally(function() {
                renderPromises.delete(cacheKey);
            });

            renderPromises.set(cacheKey, promise);
            return promise;
        };

        const createPageNode = function(canvas, pageIndex, position) {
            const pageNode = document.createElement('div');
            pageNode.className = 'mod-videoplayer-book-page';
            pageNode.setAttribute('data-page-number', String(pageIndex));

            if (isMobile()) {
                pageNode.classList.add('mod-videoplayer-book-page-single', 'is-mobile-turning');
                pageNode.classList.add(turnDirection === 'backward'
                    ? 'is-mobile-turning-backward'
                    : 'is-mobile-turning-forward');
            } else {
                const side = position === 0 ? 'left' : 'right';
                pageNode.classList.add('mod-videoplayer-book-page-' + side);
            }
            pageNode.appendChild(canvas);
            return pageNode;
        };

        const finishRender = function(visiblePages) {
            visiblePages.forEach(function(page) {
                visitedPages.add(page);
            });
            rendering = false;
            hide(loading, true);
            pagesRegion.classList.remove('is-turning', 'is-turning-forward', 'is-turning-backward');
            updateStatus();
            saveProgress(false);

            if (pendingPage !== null) {
                const queued = pendingPage;
                pendingPage = null;
                goToPage(queued);
            }
        };

        const renderSpread = function() {
            if (!pdfDocument || rendering) {
                return;
            }

            rendering = true;
            const visiblePages = getVisiblePages();
            pagesRegion.classList.remove('is-turning-forward', 'is-turning-backward');
            pagesRegion.classList.add('is-turning', turnDirection === 'backward'
                ? 'is-turning-backward'
                : 'is-turning-forward');
            hide(loading, false);
            pagesRegion.innerHTML = '';

            Promise.all(visiblePages.map(function(num) {
                return renderPageCanvas(num);
            })).then(function(canvases) {
                canvases.forEach(function(canvas, index) {
                    pagesRegion.appendChild(createPageNode(canvas, visiblePages[index], index));
                });
                finishRender(visiblePages);
                return null;
            }).catch(function(err) {
                rendering = false;
                hide(loading, true);
                pagesRegion.classList.remove('is-turning', 'is-turning-forward', 'is-turning-backward');
                hide(error, false);
                Notification.exception(err);
            });
        };

        const goToPage = function(num) {
            if (!pdfDocument) {
                return;
            }
            const safe = Math.max(1, Math.min(pdfDocument.numPages, num));
            const target = isMobile() ? safe : getSpreadStart(safe);
            turnDirection = target < pageNumber ? 'backward' : 'forward';
            if (rendering) {
                pendingPage = target;
                return;
            }
            pageNumber = target;
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
                if (reader.classList.contains('is-fallback-fullscreen')) {
                    reader.classList.remove('is-fallback-fullscreen');
                    clearPageCache();
                    renderSpread();
                    return;
                }
                if (reader.requestFullscreen) {
                    reader.requestFullscreen().catch(function() {
                        reader.classList.add('is-fallback-fullscreen');
                        clearPageCache();
                        renderSpread();
                    });
                } else {
                    reader.classList.add('is-fallback-fullscreen');
                    clearPageCache();
                    renderSpread();
                }
            });
            document.addEventListener('fullscreenchange', function() {
                if (!document.fullscreenElement) {
                    reader.classList.remove('is-fallback-fullscreen');
                }
                clearPageCache();
                window.setTimeout(function() {
                    if (!rendering) {
                        renderSpread();
                    } else {
                        pendingPage = pageNumber;
                    }
                }, 160);
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
            if (Math.abs(dx) >= 60 && Math.abs(dx) >= Math.abs(dy) * 1.35) {
                goToPage(pageNumber + (dx < 0 ? 1 : -1));
            }
        }, {passive: true});

        let resizeTimer = null;
        window.addEventListener('resize', function() {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function() {
                clearPageCache();
                if (rendering) {
                    pendingPage = pageNumber;
                } else {
                    pageNumber = isMobile() ? pageNumber : getSpreadStart(pageNumber);
                    renderSpread();
                }
            }, 180);
        });

        window.addEventListener('pagehide', function() {
            saveProgress(true);
        });
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                saveProgress(true);
            } else {
                lastTick = Date.now();
            }
        });
        window.setInterval(function() {
            if (!document.hidden) {
                saveProgress(false);
            }
        }, HEARTBEAT_INTERVAL);

        hide(loading, false);
        pdfjsLib.getDocument({
            url: pdfUrl,
            withCredentials: true,
            rangeChunkSize: 262144,
            disableAutoFetch: false,
            disableStream: false
        }).promise.then(function(pdf) {
            pdfDocument = pdf;
            Array.from(visitedPages).forEach(function(page) {
                if (page > pdfDocument.numPages) {
                    visitedPages.delete(page);
                }
            });
            pageNumber = Math.min(initialPage, pdfDocument.numPages);
            if (!isMobile()) {
                pageNumber = getSpreadStart(pageNumber);
            }
            updateStatus();
            renderSpread();
            return null;
        }).catch(function(err) {
            hide(loading, true);
            hide(error, false);
            Notification.exception(err);
        });
    };

    const init = function() {
        const roots = Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-book-reader'));
        if (!roots.length) {
            return;
        }
        loadPdfJs().then(function(pdfjsLib) {
            roots.forEach(function(root) {
                if (root.dataset.bookViewerReady === '1') {
                    return;
                }
                root.dataset.bookViewerReady = '1';
                initViewer(root, pdfjsLib);
            });
            return null;
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
