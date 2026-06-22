// This file is part of Moodle - http://moodle.org/

/**
 * Protected PDF.js ebook viewer for Drive Resource.
 *
 * @module     mod_videoplayer/pdfviewer
 * @copyright  2026 Jose Erasmo Moreno Salgado - Elearning Cloud
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const PDFJS_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
    const PDFJS_WORKER_URL = M.cfg.wwwroot + '/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
    const SAVE_INTERVAL = 10000;
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

    const showError = function(root, error) {
        hide(root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap'), true);
        hide(root.querySelector('.mod-videoplayer-pdfjs-topbar'), true);
        hide(root.querySelector('[data-region="pdfjs-loading"]'), true);
        hide(root.querySelector('[data-region="pdfjs-error"]'), false);
        if (error && window.console) {
            window.console.error(error);
        }
    };

    const hardenViewer = function(root, canvas) {
        if (root.getAttribute('data-disable-context-menu') !== '1') {
            return;
        }
        [root, canvas].forEach(function(node) {
            if (!node) {
                return;
            }
            node.setAttribute('draggable', 'false');
            ['contextmenu', 'dragstart', 'copy', 'cut', 'paste', 'selectstart'].forEach(function(name) {
                node.addEventListener(name, block, true);
            });
        });
        root.addEventListener('keydown', function(event) {
            const key = (event.key || '').toLowerCase();
            if ((event.ctrlKey || event.metaKey) && ['s', 'p', 'c', 'a'].indexOf(key) !== -1) {
                block(event);
            }
        }, true);
    };

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

    const initViewer = function(root, pdfjsLib) {
        const pdfUrl = root.getAttribute('data-pdf-url');
        const cmid = parseInt(root.getAttribute('data-cmid'), 10) || 0;
        const canvas = root.querySelector('.mod-videoplayer-pdfjs-canvas');
        const previous = root.querySelector('[data-action="previous-page"]');
        const next = root.querySelector('[data-action="next-page"]');
        const fullscreen = root.querySelector('[data-action="fullscreen"]');
        const currentPageNode = root.querySelector('[data-region="current-page"]');
        const totalPagesNode = root.querySelector('[data-region="total-pages"]');
        const loading = root.querySelector('[data-region="pdfjs-loading"]');
        const wrap = root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap');
        const container = root.closest('.mod-videoplayer-container') || document;
        const pointsNode = container.querySelector('[data-region="ebook-points"]');
        const progressNode = container.querySelector('[data-region="ebook-progress"]');

        if (!pdfUrl || !canvas) {
            showError(root);
            return;
        }

        hardenViewer(root, canvas);
        const context = canvas.getContext('2d');
        let pdfDocument = null;
        let pageNumber = Math.max(1, parseInt(root.getAttribute('data-initial-page'), 10) || 1);
        let rendering = false;
        let pendingPage = null;
        let firstRender = true;
        let lastSave = 0;
        let activeSeconds = 0;
        let lastTick = Date.now();
        let completed = false;

        const isFullscreen = function() {
            return document.fullscreenElement || root.classList.contains('is-fallback-fullscreen');
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

        const prefetch = function(num) {
            if (!pdfDocument || num < 1 || num > pdfDocument.numPages) {
                return;
            }
            pdfDocument.getPage(num).catch(function() {});
        };

        const renderPage = function(num) {
            rendering = true;
            canvas.classList.add('is-rendering');
            if (firstRender) {
                hide(loading, false);
            }
            pdfDocument.getPage(num).then(function(page) {
                const availableWidth = wrap ? Math.max(wrap.clientWidth - 24, 320) : 900;
                const availableHeight = wrap ? Math.max(wrap.clientHeight - 24, 360) : 900;
                const base = page.getViewport({scale: 1});
                const fitWidth = availableWidth / base.width;
                const fitHeight = availableHeight / base.height;
                const cssScale = isFullscreen() ? Math.min(fitWidth, fitHeight) : fitWidth;
                const viewport = page.getViewport({scale: cssScale});
                const outputScale = isFullscreen() ? Math.min(window.devicePixelRatio || 1, 3) : Math.min(window.devicePixelRatio || 1, 2.25);
                canvas.width = Math.floor(viewport.width * outputScale);
                canvas.height = Math.floor(viewport.height * outputScale);
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';
                context.setTransform(1, 0, 0, 1, 0, 0);
                return page.render({
                    canvasContext: context,
                    viewport: viewport,
                    transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
                }).promise;
            }).then(function() {
                rendering = false;
                firstRender = false;
                hide(loading, true);
                canvas.classList.remove('is-rendering');
                updateButtons();
                prefetch(pageNumber + 1);
                prefetch(pageNumber - 1);
                saveProgress(false);
                if (pendingPage !== null) {
                    const nextPage = pendingPage;
                    pendingPage = null;
                    renderPage(nextPage);
                }
            }).catch(function(error) {
                rendering = false;
                Notification.exception(error);
                showError(root, error);
            });
        };

        const queue = function(num) {
            if (rendering) {
                pendingPage = num;
            } else {
                renderPage(num);
            }
        };

        const go = function(num) {
            if (!pdfDocument) {
                return;
            }
            pageNumber = Math.max(1, Math.min(pdfDocument.numPages, num));
            queue(pageNumber);
        };

        if (previous) {
            previous.addEventListener('click', function() {
                go(pageNumber - 1);
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                go(pageNumber + 1);
            });
        }
        if (fullscreen) {
            fullscreen.addEventListener('click', function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                    return;
                }
                if (root.requestFullscreen) {
                    root.requestFullscreen().catch(function() {
                        root.classList.add('is-fallback-fullscreen');
                        queue(pageNumber);
                    });
                } else {
                    root.classList.add('is-fallback-fullscreen');
                    queue(pageNumber);
                }
            });
            document.addEventListener('fullscreenchange', function() {
                if (!document.fullscreenElement) {
                    root.classList.remove('is-fallback-fullscreen');
                }
                if (pdfDocument) {
                    queue(pageNumber);
                }
            });
        }

        window.addEventListener('resize', function() {
            if (pdfDocument) {
                queue(pageNumber);
            }
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
            pageNumber = Math.min(pageNumber, pdfDocument.numPages);
            updateButtons();
            renderPage(pageNumber);
        }).catch(function(error) {
            Notification.exception(error);
            showError(root, error);
        });
    };

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
            viewers.forEach(function(root) {
                showError(root, error);
            });
        });
    };

    return {init: init};
});
