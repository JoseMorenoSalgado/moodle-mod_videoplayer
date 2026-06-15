define(['core/notification'],function(Notification){
var PDFJS_URL=M.cfg.wwwroot+'/mod/videoplayer/thirdpartylibs/pdfjs/pdf.min.mjs';
var PDFJS_WORKER_URL=M.cfg.wwwroot+'/mod/videoplayer/thirdpartylibs/pdfjs/pdf.worker.min.mjs';
var pdfjsPromise=null;
var loadPdfJs=function(){if(!pdfjsPromise){pdfjsPromise=import(PDFJS_URL).then(function(pdfjsLib){pdfjsLib.GlobalWorkerOptions.workerSrc=PDFJS_WORKER_URL;return pdfjsLib;});}return pdfjsPromise;};
var hide=function(node,value){if(node){node.hidden=value;}};
var showError=function(root,error){hide(root.querySelector('.mod-videoplayer-pdfjs-layout'),true);hide(root.querySelector('.mod-videoplayer-pdfjs-toolbar'),true);hide(root.querySelector('.mod-videoplayer-pdfjs-search'),true);hide(root.querySelector('[data-region="pdfjs-loading"]'),true);hide(root.querySelector('[data-region="pdfjs-error"]'),false);if(error&&window.console){window.console.error(error);}};
var initViewer=function(root,pdfjsLib){
var pdfUrl=root.getAttribute('data-pdf-url');
var canvas=root.querySelector('.mod-videoplayer-pdfjs-canvas');
var previous=root.querySelector('[data-action="previous-page"]');
var next=root.querySelector('[data-action="next-page"]');
var zoomIn=root.querySelector('[data-action="zoom-in"]');
var zoomOut=root.querySelector('[data-action="zoom-out"]');
var fullscreen=root.querySelector('[data-action="fullscreen"]');
var toggleThumbs=root.querySelector('[data-action="toggle-thumbnails"]');
var searchInput=root.querySelector('[data-region="search-input"]');
var searchNext=root.querySelector('[data-action="search-next"]');
var searchPrevious=root.querySelector('[data-action="search-previous"]');
var searchStatus=root.querySelector('[data-region="search-status"]');
var currentPageNode=root.querySelector('[data-region="current-page"]');
var totalPagesNode=root.querySelector('[data-region="total-pages"]');
var thumbs=root.querySelector('[data-region="thumbnails"]');
var loading=root.querySelector('[data-region="pdfjs-loading"]');
var layout=root.querySelector('.mod-videoplayer-pdfjs-layout');
if(!pdfUrl||!canvas){showError(root);return;}
var context=canvas.getContext('2d');
var pdfDocument=null,pageNumber=1,scale=1.2,rendering=false,pendingPage=null,matches=[],matchIndex=-1;
var status=function(text){if(searchStatus){searchStatus.textContent=text;}};
var updateButtons=function(){if(!pdfDocument){return;}if(previous){previous.disabled=pageNumber<=1;}if(next){next.disabled=pageNumber>=pdfDocument.numPages;}if(currentPageNode){currentPageNode.textContent=String(pageNumber);}if(totalPagesNode){totalPagesNode.textContent=String(pdfDocument.numPages);}if(thumbs){Array.prototype.forEach.call(thumbs.querySelectorAll('button'),function(button){button.classList.toggle('active',Number(button.getAttribute('data-page'))===pageNumber);});}};
var renderPage=function(num){rendering=true;pdfDocument.getPage(num).then(function(page){var wrap=root.querySelector('.mod-videoplayer-pdfjs-canvas-wrap');var width=wrap?Math.max(wrap.clientWidth-24,320):900;var base=page.getViewport({scale:1});var finalScale=Math.min(scale,width/base.width);var viewport=page.getViewport({scale:finalScale});var outputScale=window.devicePixelRatio||1;canvas.width=Math.floor(viewport.width*outputScale);canvas.height=Math.floor(viewport.height*outputScale);canvas.style.width=Math.floor(viewport.width)+'px';canvas.style.height=Math.floor(viewport.height)+'px';return page.render({canvasContext:context,viewport:viewport,transform:outputScale!==1?[outputScale,0,0,outputScale,0,0]:null}).promise;}).then(function(){rendering=false;hide(loading,true);hide(layout,false);updateButtons();if(pendingPage!==null){var nextPage=pendingPage;pendingPage=null;renderPage(nextPage);}}).catch(function(error){rendering=false;Notification.exception(error);showError(root,error);});};
var queue=function(num){if(rendering){pendingPage=num;}else{renderPage(num);}};
var go=function(num){if(!pdfDocument){return;}pageNumber=Math.max(1,Math.min(pdfDocument.numPages,num));queue(pageNumber);};
var renderThumb=function(num){if(!thumbs||!pdfDocument){return Promise.resolve();}return pdfDocument.getPage(num).then(function(page){var button=document.createElement('button');var thumbCanvas=document.createElement('canvas');var label=document.createElement('span');var viewport=page.getViewport({scale:0.18});button.type='button';button.className='mod-videoplayer-pdfjs-thumb';button.setAttribute('data-page',String(num));thumbCanvas.width=Math.floor(viewport.width);thumbCanvas.height=Math.floor(viewport.height);label.textContent=String(num);button.appendChild(thumbCanvas);button.appendChild(label);button.addEventListener('click',function(){go(num);});thumbs.appendChild(button);return page.render({canvasContext:thumbCanvas.getContext('2d'),viewport:viewport}).promise;});};
var renderThumbs=function(){if(!thumbs||!pdfDocument){return;}thumbs.innerHTML='';var chain=Promise.resolve();for(var i=1;i<=pdfDocument.numPages;i++){(function(page){chain=chain.then(function(){return renderThumb(page);});})(i);}};
var runSearch=function(direction){if(!pdfDocument||!searchInput){return;}var query=searchInput.value.trim().toLowerCase();if(query===''){matches=[];matchIndex=-1;status('');return;}status('Buscando...');var tasks=[];for(var i=1;i<=pdfDocument.numPages;i++){(function(pageNum){tasks.push(pdfDocument.getPage(pageNum).then(function(page){return page.getTextContent().then(function(content){var text=content.items.map(function(item){return item.str||'';}).join(' ').toLowerCase();return text.indexOf(query)!==-1?pageNum:null;});}));})(i);}Promise.all(tasks).then(function(results){matches=results.filter(function(page){return page!==null;});if(!matches.length){matchIndex=-1;status('0 resultados');return;}matchIndex=direction<0?matches.length-1:0;go(matches[matchIndex]);status((matchIndex+1)+' / '+matches.length);}).catch(function(error){Notification.exception(error);status('Error');});};
var moveSearch=function(direction){if(!matches.length){runSearch(direction);return;}matchIndex+=direction;if(matchIndex<0){matchIndex=matches.length-1;}if(matchIndex>=matches.length){matchIndex=0;}go(matches[matchIndex]);status((matchIndex+1)+' / '+matches.length);};
if(previous){previous.addEventListener('click',function(){go(pageNumber-1);});}
if(next){next.addEventListener('click',function(){go(pageNumber+1);});}
if(zoomIn){zoomIn.addEventListener('click',function(){scale=Math.min(3,scale+0.15);queue(pageNumber);});}
if(zoomOut){zoomOut.addEventListener('click',function(){scale=Math.max(0.5,scale-0.15);queue(pageNumber);});}
if(fullscreen){fullscreen.addEventListener('click',function(){if(document.fullscreenElement){document.exitFullscreen();}else if(root.requestFullscreen){root.requestFullscreen();}});}
if(toggleThumbs){toggleThumbs.addEventListener('click',function(){root.classList.toggle('mod-videoplayer-pdfjs-hide-thumbs');});}
if(searchInput){searchInput.addEventListener('keydown',function(event){if(event.key==='Enter'){runSearch(1);}});searchInput.addEventListener('input',function(){matches=[];matchIndex=-1;status('');});}
if(searchNext){searchNext.addEventListener('click',function(){moveSearch(1);});}
if(searchPrevious){searchPrevious.addEventListener('click',function(){moveSearch(-1);});}
window.addEventListener('resize',function(){if(pdfDocument){queue(pageNumber);}});
hide(layout,true);hide(loading,false);
pdfjsLib.getDocument({url:pdfUrl,withCredentials:true}).promise.then(function(pdf){pdfDocument=pdf;updateButtons();renderPage(pageNumber);renderThumbs();}).catch(function(error){Notification.exception(error);showError(root,error);});
};
var init=function(){var viewers=Array.prototype.slice.call(document.querySelectorAll('.mod-videoplayer-pdfjs-viewer'));if(!viewers.length){return;}loadPdfJs().then(function(pdfjsLib){viewers.forEach(function(root){initViewer(root,pdfjsLib);});}).catch(function(error){Notification.exception(error);viewers.forEach(function(root){showError(root,error);});});};
return{init:init};
});
