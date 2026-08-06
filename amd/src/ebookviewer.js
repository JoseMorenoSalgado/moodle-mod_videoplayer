// This file is part of Moodle - http://moodle.org/

/**
 * Protected responsive ebook viewer for Drive Resource.
 *
 * PDF pages are rendered lazily. Phones display one page and larger screens
 * display a two-page spread with the locally bundled StPageFlip effects.
 *
 * @module     mod_videoplayer/ebookviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/* PDF.js rendering uses deliberate promise orchestration; errors remain handled by terminal catches. */
/* eslint-disable promise/always-return */
define(['core/ajax', 'core/notification', 'mod_videoplayer/pdfjsloader'], function(Ajax, Notification, PdfjsLoader) {
    const PAGEFLIP_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pageflip/page-flip.browser.js';
    const PAGEFLIP_SCRIPT_ID = 'mod-videoplayer-pageflip-script';
    const SAVE_INTERVAL = 10000;
    const PHONE_CONTAINER_MAX = 720;
    const PHONE_SHORT_SIDE_MAX = 600;
    const PAGE_MIN_WIDTH = 280;
    const PAGE_MAX_WIDTH = 720;
    const RESIZE_DEBOUNCE = 180;

    let pageFlipPromise = null;

    /**
     * Load the local PageFlip browser build once.
     *
     * @returns {Promise<Function|null>}
     */
    const loadPageFlip = function() {
        if (pageFlipPromise) {
            return pageFlipPromise;
        }

        pageFlipPromise = new Promise(function(resolve) {
            if (window.St && window.St.PageFlip) {
                resolve(window.St.PageFlip);
                return;
            }

            const existing = document.getElementById(PAGEFLIP_SCRIPT_ID);
            if (existing) {
                existing.addEventListener('load', function() {
                    resolve(window.St && window.St.PageFlip ? window.St.PageFlip : null);
                }, {once: true});
                existing.addEventListener('error', function() {
                    resolve(null);
                }, {once: true});
                return;
            }

            const script = document.createElement('script');
            script.id = PAGEFLIP_SCRIPT_ID;
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
     * Clamp a number.
     *
     * @param {number} value
     * @param {number} minimum
     * @param {number} maximum
     * @returns {number}
     */
    const clamp = function(value, minimum, maximum) {
        return Math.max(minimum, Math.min(maximum, value));
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
     * Determine whether the viewer must use the phone single-page layout.
     *
     * The short physical screen side keeps phones in single-page mode after
     * rotation, while the container width protects narrow embedded layouts.
     *
     * @param {HTMLElement} root
     * @returns {boolean}
     */
    const isPhoneLayout = function(root) {
        const screenWidth = window.screen && window.screen.width ? window.screen.width : window.innerWidth;
        const screenHeight = window.screen && window.screen.height ? window.screen.height : window.innerHeight;
        const shortSide = Math.min(screenWidth, screenHeight);
        const coarsePointer = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

        return root.clientWidth < PHONE_CONTAINER_MAX || (coarsePointer && shortSide <= PHONE_SHORT_SIDE_MAX);
    };

    /**
     * Build a high-density canvas for one PDF page.
     *
     * @param {Object} page
     * @param {number} maxWidth
     * @returns {Promise<HTMLCanvasElement>}
     */
    const renderCanvas = function(page, maxWidth) {
        const base = page.getViewport({scale: 1});
        const scale = clamp(maxWidth / base.width, 0.5, 2.5);
        const viewport = page.getViewport({scale: scale});
        const outputScale = Math.min(window.devicePixelRatio || 1, 2.25);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', {alpha: false});

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
     * Create a lightweight PageFlip page placeholder.
     *
     * @param {number} pagenumber
     * @returns {HTMLElement}
     */
    const createPageNode = function(pagenumber) {
        const page = document.createElement('div');
        const placeholder = document.createElement('div');
        const number = document.createElement('span');

        page.className = 'mod-videoplayer-ebook-page is-pending ' +
            (pagenumber % 2 === 0 ? 'is-left-page' : 'is-right-page');
        page.setAttribute('data-page-number', String(pagenumber));
        page.setAttribute('aria-label', 'Page ' + pagenumber);

        placeholder.className = 'mod-videoplayer-ebook-page-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        number.className = 'mod-videoplayer-ebook-page-number';
        number.textContent = String(pagenumber);

        page.appendChild(placeholder);
        page.appendChild(number);
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
        if (root.getAttribute('data-ebook-initialised') === '1') {
            return;
        }
        root.setAttribute('data-ebook-initialised', '1');

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

        if (!pdfUrl || !stage || !PageFlip) {
            root.removeAttribute('data-ebook-initialised');
            root.setAttribute('data-display-mode', 'pdfjs');
            hide(stage, true);
            if (fallbackCanvas) {
                fallbackCanvas.hidden = false;
            }
            if (window.require) {
                window.require(['mod_videoplayer/pdfviewer'], function(PdfViewer) {
                    PdfViewer.init();
                });
            }
            return;
        }

        hardenViewer(root);
        hide(loading, false);
        hide(stage, false);

        let pdfDocument = null;
        let pageNumber = initialPage;
        let furthestPage = initialPage;
        let pageFlip = null;
        let phoneLayout = isPhoneLayout(root);
        let activeSeconds = 0;
        let lastTick = Date.now();
        let lastSave = 0;
        let completed = false;
        let resizeTimer = null;
        const renderedPages = new Map();

        /**
         * Apply layout classes consumed by scoped PageFlip CSS.
         */
        const applyLayout = function() {
            root.classList.toggle('is-phone-single-page', phoneLayout);
            root.classList.toggle('is-desktop-double-page', !phoneLayout);
            stage.classList.toggle('is-phone-single-page', phoneLayout);
            stage.classList.toggle('is-desktop-double-page', !phoneLayout);
            stage.setAttribute('data-ebook-layout', phoneLayout ? 'single' : 'spread');
        };

        /**
         * Last page visible in the current phone/spread layout.
         *
         * @returns {number}
         */
        const lastVisiblePage = function() {
            if (!pdfDocument) {
                return pageNumber;
            }
            return Math.min(pdfDocument.numPages, pageNumber + (phoneLayout ? 0 : 1));
        };

        /**
         * Refresh navigation and page status.
         */
        const updateStatus = function() {
            if (!pdfDocument) {
                return;
            }

            const visibleEnd = lastVisiblePage();
            furthestPage = Math.max(furthestPage, visibleEnd);

            if (currentPageNode) {
                currentPageNode.textContent = phoneLayout || pageNumber === visibleEnd
                    ? String(pageNumber)
                    : pageNumber + '–' + visibleEnd;
            }
            if (totalPagesNode) {
                totalPagesNode.textContent = String(pdfDocument.numPages);
            }
            if (previous) {
                previous.disabled = pageNumber <= 1;
            }
            if (next) {
                next.disabled = visibleEnd >= pdfDocument.numPages;
            }
        };

        /**
         * Completion is monotonic even when the learner turns back.
         *
         * @returns {number}
         */
        const completionPercent = function() {
            if (!pdfDocument || !pdfDocument.numPages) {
                return 0;
            }
            return Math.min(100, Math.round((furthestPage / pdfDocument.numPages) * 10000) / 100);
        };

        /**
         * Persist progress through Moodle External API.
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

        /**
         * Calculate the CSS width for one rendered page.
         *
         * @returns {number}
         */
        const targetPageWidth = function() {
            const stageWidth = stage.clientWidth || root.clientWidth || PAGE_MAX_WIDTH;
            const columns = phoneLayout ? 1 : 2;
            return clamp(Math.floor((stageWidth / columns) - 24), PAGE_MIN_WIDTH, PAGE_MAX_WIDTH);
        };

        /**
         * Render one page if it is absent or below the required resolution.
         *
         * @param {number} number
         * @returns {Promise}
         */
        const renderPage = function(number) {
            if (!pdfDocument || number < 1 || number > pdfDocument.numPages) {
                return Promise.resolve();
            }

            const width = targetPageWidth();
            const existing = renderedPages.get(number);
            if (existing && existing.status === 'ready' && existing.width >= width * 0.92) {
                return Promise.resolve();
            }
            if (existing && existing.status === 'loading') {
                return existing.promise;
            }

            const pageNode = stage.querySelector('[data-page-number="' + number + '"]');
            if (!pageNode) {
                return Promise.resolve();
            }

            pageNode.classList.add('is-rendering');
            pageNode.classList.remove('is-render-error');

            const promise = pdfDocument.getPage(number).then(function(page) {
                return renderCanvas(page, width);
            }).then(function(canvas) {
                const oldCanvas = pageNode.querySelector('.mod-videoplayer-ebook-page-canvas');
                const placeholder = pageNode.querySelector('.mod-videoplayer-ebook-page-placeholder');

                if (oldCanvas) {
                    oldCanvas.replaceWith(canvas);
                } else if (placeholder) {
                    placeholder.replaceWith(canvas);
                } else {
                    pageNode.insertBefore(canvas, pageNode.firstChild);
                }

                pageNode.classList.remove('is-pending', 'is-rendering');
                pageNode.classList.add('is-rendered');
                renderedPages.set(number, {status: 'ready', width: width, promise: Promise.resolve()});
            }).catch(function(error) {
                pageNode.classList.remove('is-rendering');
                pageNode.classList.add('is-render-error');
                renderedPages.delete(number);
                if (window.console) {
                    window.console.error(error);
                }
            });

            renderedPages.set(number, {status: 'loading', width: width, promise: promise});
            return promise;
        };

        /**
         * Render visible pages immediately and neighbours during idle time.
         *
         * @returns {Promise}
         */
        const renderVisiblePages = function() {
            const visible = phoneLayout
                ? [pageNumber]
                : [pageNumber, pageNumber + 1];
            const neighbours = phoneLayout
                ? [pageNumber - 1, pageNumber + 1]
                : [pageNumber - 1, pageNumber + 2];
            const primary = visible.filter(function(number) {
                return number >= 1 && number <= pdfDocument.numPages;
            });

            const scheduleNeighbours = function() {
                const work = function() {
                    neighbours.forEach(function(number) {
                        renderPage(number);
                    });
                };

                if (window.requestIdleCallback) {
                    window.requestIdleCallback(work, {timeout: 800});
                } else {
                    window.setTimeout(work, 80);
                }
            };

            return Promise.all(primary.map(renderPage)).then(function() {
                scheduleNeighbours();
            });
        };

        /**
         * Create all lightweight page containers required by PageFlip.
         */
        const buildPageNodes = function() {
            const fragment = document.createDocumentFragment();
            stage.replaceChildren();

            for (let number = 1; number <= pdfDocument.numPages; number++) {
                fragment.appendChild(createPageNode(number));
            }
            stage.appendChild(fragment);
        };

        /**
         * Build or rebuild the PageFlip instance for the current layout.
         *
         * @returns {Promise}
         */
        const buildPageFlip = function() {
            if (pageFlip && typeof pageFlip.destroy === 'function') {
                pageFlip.destroy();
            }

            buildPageNodes();
            applyLayout();

            pageFlip = new PageFlip(stage, {
                width: 520,
                height: 735,
                size: 'stretch',
                minWidth: phoneLayout ? 260 : 280,
                maxWidth: phoneLayout ? 720 : 620,
                minHeight: phoneLayout ? 360 : 420,
                maxHeight: phoneLayout ? 980 : 920,
                drawShadow: true,
                flippingTime: 900,
                usePortrait: phoneLayout,
                startZIndex: 10,
                autoSize: true,
                maxShadowOpacity: 0.42,
                showCover: false,
                mobileScrollSupport: true,
                clickEventForward: true,
                useMouseEvents: true,
                swipeDistance: 24,
                showPageCorners: true,
                disableFlipByClick: false,
                startPage: Math.max(0, pageNumber - 1)
            });

            pageFlip.loadFromHTML(Array.prototype.slice.call(
                stage.querySelectorAll('.mod-videoplayer-ebook-page')
            ));

            pageFlip.on('flip', function(event) {
                pageNumber = clamp(parseInt(event.data, 10) + 1, 1, pdfDocument.numPages);
                stage.classList.add('has-turned-page');
                window.setTimeout(function() {
                    stage.classList.remove('has-turned-page');
                }, 360);
                updateStatus();
                renderVisiblePages();
                saveProgress(false);
            });

            pageFlip.on('changeState', function(event) {
                stage.classList.toggle('is-page-turning', event.data === 'flipping');
            });

            updateStatus();
            return renderVisiblePages();
        };

        /**
         * Navigate directly when the PageFlip API is unavailable.
         *
         * @param {number} number
         */
        const goToPage = function(number) {
            if (!pdfDocument) {
                return;
            }

            const target = clamp(number, 1, pdfDocument.numPages);
            if (pageFlip && typeof pageFlip.flip === 'function') {
                pageFlip.flip(target - 1, 'top');
            } else {
                pageNumber = target;
                updateStatus();
                renderVisiblePages();
                saveProgress(false);
            }
        };

        if (previous) {
            previous.addEventListener('click', function() {
                if (pageFlip && typeof pageFlip.flipPrev === 'function') {
                    pageFlip.flipPrev('top');
                } else {
                    goToPage(pageNumber - 1);
                }
            });
        }

        if (next) {
            next.addEventListener('click', function() {
                if (pageFlip && typeof pageFlip.flipNext === 'function') {
                    pageFlip.flipNext('top');
                } else {
                    goToPage(lastVisiblePage() + 1);
                }
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

        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement) {
                root.classList.remove('is-fallback-fullscreen');
            }
            if (pdfDocument) {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(function() {
                    renderVisiblePages();
                }, RESIZE_DEBOUNCE);
            }
        });

        window.addEventListener('resize', function() {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function() {
                const nextPhoneLayout = isPhoneLayout(root);
                if (!pdfDocument) {
                    phoneLayout = nextPhoneLayout;
                    applyLayout();
                    return;
                }

                if (nextPhoneLayout !== phoneLayout) {
                    phoneLayout = nextPhoneLayout;
                    buildPageFlip().catch(Notification.exception);
                } else {
                    applyLayout();
                    updateStatus();
                    renderVisiblePages();
                }
            }, RESIZE_DEBOUNCE);
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

        applyLayout();
        pdfjsLib.getDocument({url: pdfUrl, withCredentials: true, rangeChunkSize: 262144}).promise.then(function(pdf) {
            pdfDocument = pdf;
            pageNumber = clamp(initialPage, 1, pdfDocument.numPages);
            furthestPage = pageNumber;
            if (totalPagesNode) {
                totalPagesNode.textContent = String(pdfDocument.numPages);
            }
            return buildPageFlip();
        }).then(function() {
            hide(loading, true);
            if (fallbackCanvas) {
                fallbackCanvas.hidden = true;
            }
            saveProgress(true);
        }).catch(function(error) {
            root.removeAttribute('data-ebook-initialised');
            Notification.exception(error);
            root.setAttribute('data-display-mode', 'pdfjs');
            hide(stage, true);
            if (fallbackCanvas) {
                fallbackCanvas.hidden = false;
            }
            if (window.require) {
                window.require(['mod_videoplayer/pdfviewer'], function(PdfViewer) {
                    PdfViewer.init();
                });
            }
        });
    };

    /**
     * Initialise all protected ebook viewers on the page.
     */
    const init = function() {
        const viewerSelector = '.mod-videoplayer-pdfjs-viewer[data-display-mode="ebook"]';
        const viewers = Array.prototype.slice.call(document.querySelectorAll(viewerSelector));
        if (!viewers.length) {
            return;
        }

        Promise.all([PdfjsLoader.load(), loadPageFlip()]).then(function(results) {
            const pdfjsLib = results[0];
            const PageFlip = results[1];
            viewers.forEach(function(root) {
                initViewer(root, pdfjsLib, PageFlip);
            });
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    return {init: init};
});
