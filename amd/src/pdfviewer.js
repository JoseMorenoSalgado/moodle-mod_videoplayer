define(['core/notification'],function(Notification){
var PDFJS_URL=M.cfg.wwwroot+'/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
var PDFJS_WORKER_URL=M.cfg.wwwroot+'/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
var pdfjsPromise=null;
var loadPdfJs=function(){if(!pdfjsPromise){pdfjsPromise=import(PDFJS_URL).then(function(pdfjsLib){pdfjsLib.GlobalWorkerOptions.workerSrc=PDFJS_WORKER_URL;return pdfjsLib;});}return pdfjsPromise;};
var hide=function(node,value){if(node){node.hidden=value;}};
var showError=function(root,error){hide(root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap'),true);hide(root.querySelector('.mod-videoplayer-pdfjs-topbar'),true);hide(root.querySelector('[data-region="pdfjs-loading"]'),true);hide(root.querySelector('[data-region="pdfjs-error"]'),false);if(error&&window.console){window.console.error(error);}};
var initViewer=function(root,pdfjsLib){
var pdfUrl=root.getAttribute('data-pdf-url');
var canvas=root.querySelector('.mod-videoplayer-pdfjs-canvas');
var previous=root.querySelector('[data-action="previous-page"]');
var next=root.querySelector('[data-action="next-page"]');
var fullscreen=root.querySelector('[data-action="fullscreen"]');
var currentPageNode=root.querySelector('[data-region="current-page"]');
var totalPagesNode=root.querySelector('[data-region="total-pages"]');
var loading=root.querySelector('[data-region="pdfjs-loading"]');
var wrap=root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap');
if(!pdfUrl||!canvas){showError(root);return;}
var context=canvas.getContext('2d');
var pdfDocument=null,pageNumber=1,rendering=false,pendingPage=null,firstRender=true;
var updateButtons=function(){if(!pdfDocument){return;}if(previous){previous.disabled=pageNumber<=1;}if(next){next.disabled=pageNumber>=pdfDocument.numPages;}if(currentPageNode){currentPageNode.textContent=String(pageNumber);}if(totalPagesNode){totalPagesNode.textContent=String(pdfDocument.numPages);}};
var prefetch=function(num){if(!pdfDocument||num<1||num>pdfDocument.numPages){return;}pdfDocument.getPage(num).catch(function(){});};
var renderPage=function(num){rendering=true;canvas.classList.add('is-rendering');if(firstRender){hide(loading,false);}pdfDocument.getPage(num).then(function(page){var availableWidth=wrap?Math.max(wrap.clientWidth-24,320):900;var availableHeight=wrap?Math.max(wrap.clientHeight-24,360):900;var base=page.getViewport({scale:1});var fitWidth=availableWidth/base.width;var fitHeight=availableHeight/base.height;var cssScale=document.fullscreenElement?Math.min(fitWidth,fitHeight):fitWidth;var viewport=page.getViewport({scale:cssScale});var outputScale=document.fullscreenElement?Math.min(window.devicePixelRatio||1,3):Math.min(window.devicePixelRatio||1,2.25);canvas.width=Math.floor(viewport.width*outputScale);canvas.height=Math.floor(viewport.height*outputScale);canvas.style.width=Math.floor(viewport.width)+'px';canvas.style.height=Math.floor(viewport.height)+'px';context.setTransform(1,0,0,1,0,0);return page.render({canvasContext:context,viewport:viewport,transform:outputScale!==1?[outputScale,0,0,outputScale,0,0]:null}).promise;}).then(function(){rendering=false;firstRender=false;hide(loading,true);canvas.classList.remove('is-rendering');updateButtons();prefetch(pageNumber+1);prefetch(pageNumber-1);if(pendingPage!==null){var nextPage=pendingPage;pendingPage=null;renderPage(nextPage);}}).catch(function(error){rendering=false;Notification.exception(error);showError(root,error);});};
var queue=function(num){if(rendering){pendingPage=num;}else{renderPage(num);}};
var go=function(num){if(!pdfDocument){return;}pageNumber=Math.max(1,Math.min(pdfDocument.numPages,num));queue(pageNumber);};
if(previous){previous.addEventListener('click',function(){go(pageNumber-1);});}
if(next){next.addEventListener('click',function(){go(pageNumber+1);});}
if(fullscreen){fullscreen.addEventListener('click',function(){if(document.fullscreenElement){document.exitFullscreen();}else if(root.requestFullscreen){root.requestFullscreen();}});document.addEventListener('fullscreenchange',function(){if(pdfDocument){queue(pageNumber);}});}
window.addEventListener('resize',function(){if(pdfDocument){queue(pageNumber);}});
hide(loading,false);
pdfjsLib.getDocument({url:pdfUrl,withCredentials:true,rangeChunkSize:262144}).promise.then(function(pdf){pdfDocument=pdf;updateButtons();renderPage(pageNumber);}).catch(function(error){Notification.exception(error);showError(root,error);});
};
var init=function(){var viewers=Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-pdfjs-viewer'));if(!viewers.length){return;}loadPdfJs().then(function(pdfjsLib){viewers.forEach(function(root){initViewer(root,pdfjsLib);});}).catch(function(error){Notification.exception(error);viewers.forEach(function(root){showError(root,error);});});};
return{init:init};
});
