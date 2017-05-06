//debugger;
// To manage submits to cases.front.php
var loc_split = window.location.href.split('/');
var GLPI_HTTP_CASE_FORM = window.location.href.split('/', loc_split.length-2 ).join('/') + '/plugins/processmaker/front/cases.front.php'; // http://hostname/glpi/...
// to manage reloads
var GLPI_RELOAD_PARENT = window; //.location;
var GLPI_DURING_RELOAD = false;

// used to find an element in a list and to hide it!
function bGLPIHideElement(eltList, attribute, value) {
    var ret = false;
   for (var i = 0; i < eltList.length && !ret; i++) {
      var node = eltList[i];
      if (node.getAttribute(attribute) == value) {
         // hide the link
         node.style.display = 'none';
         ret = true;
      }
   }
    return ret;
}


function displayOverlay() {
   //debugger;
   // don't use displayOverlay when submit input open new tab or update parent ( example: pdf generation )
   if (!($(this).is('input[type=submit]')
       && $(this).parents('form').length > 0
       && ($(this).parents('form').first().attr('target') == '_blank' || $(this).parents('form').first().attr('target') == '_parent'))) {
      $("<div class='ui-widget-overlay ui-front'></div>").appendTo("body");

      var timer = window.setInterval(function () {
         var count = $('.ui-widget-overlay.ui-front').length;
         if (count == 2) {
            $($('.ui-widget-overlay.ui-front')[1]).remove();
            window.clearInterval(timer);
         }

      }, 10);
   }
}


function onTaskFrameLoad(event, delIndex, hideClaimButton, csrf) {
   //alert("Loaded frame " + delIndex);
   var taskFrameId = event.target.id; //"caseiframe-" + delIndex;
   var bShowHideNextStep = false; // not done yet!
   var bHideClaimCancelButton = false; // To manage 'Claim' button
   var taskFrameTimerCounter = 0;
   var redimIFrame = false;
   //debugger;
   var taskFrameTimer = window.setInterval(function () {
      try {
         var locContentDocument;
         var taskFrame = document.getElementById(taskFrameId);
         try {
            locContentDocument = taskFrame.contentDocument;
         } catch (ex) {
            locContentDocument = false;
         }
         if (taskFrame != undefined && locContentDocument != undefined) {
            // here we've caught the content of the iframe

            // if task resumé, then hide the form part
            //debugger;
            //var form_resume = locContentDocument.getElementsByName('cases_Resume');
            //if (form_resume.length > 0 && form_resume[0].style.display != 'none') {
            //   form_resume[0].style.display = 'none';
            //}

            // then look if btnGLPISendRequest exists,
            var locElt = locContentDocument.getElementById('form[btnGLPISendRequest]');
            if (!bShowHideNextStep && locElt != undefined ) {
               var linkList = locContentDocument.getElementsByTagName('a');
               if (bGLPIHideElement(linkList, 'href', 'cases_Step?TYPE=ASSIGN_TASK&UID=-1&POSITION=10000&ACTION=ASSIGN')) {
                  // the next step link is hidden

                  // if yes then change the link behind the button itself
                  locElt.type = 'submit';
                  locElt.onclick = null; // in order to force use of the action of form POST
                  var formList = locContentDocument.getElementsByTagName('form');

                  // if yes then change the action of the form POST
                  var node = formList[0]; // must have one element in list: in a dynaform there is one and only one HTML form
                  node.setAttribute('actionBackup', node.action);
                  var action = node.action.split('?');
                  node.action = GLPI_HTTP_CASE_FORM + '?' + action[1] + '&DEL_INDEX=' + delIndex;

                  // add an element that will be the csrf data code for the POST
                  //debugger;
                  var csrfElt = document.createElement("input");
                  csrfElt.setAttribute("type", "hidden");
                  csrfElt.setAttribute("name", "_glpi_csrf_token");
                  csrfElt.setAttribute("value", csrf);
                  node.appendChild(csrfElt);

                  // add showMask function to submit event
                  //node.addEventListener('submit', displayOverlay, true);
               } else {
                  // then hide the button itself
                  locElt.style.display = 'none';
               }

               bShowHideNextStep = true;
            }

            // Hide 'Cancel' button on 'Claim' form
            var cancelButton = locContentDocument.getElementById('form[BTN_CANCEL]');
            if (cancelButton != undefined && !bHideClaimCancelButton) {
               cancelButton.style.display = 'none';
               // hides claim button if asked for
               if (hideClaimButton) {
                  claimButton = locContentDocument.getElementById('form[BTN_CATCH]');
                  claimButton.style.display = 'none';
               }
               // to manage Claim
               var formList = locContentDocument.getElementsByTagName('form');
               var node = formList[0]; // must have one element in list: in a dynaform there is one and only one HTML form
               node.setAttribute('actionBackup', node.action);

               var action = node.action.split('?');
               node.action = GLPI_HTTP_CASE_FORM + '?' + action[1] + '&DEL_INDEX=' + delIndex;

               bHideClaimCancelButton = true;
               // TODO
               //node.addEventListener('submit', showMask);
            }

            // to force immediate reload of GLPI item form
            var forcedReload = locContentDocument.getElementById('GLPI_FORCE_RELOAD');
            if (forcedReload != undefined && !GLPI_DURING_RELOAD) {
               //showMask();
               GLPI_DURING_RELOAD = true; // to prevent double reload
               window.clearInterval(taskFrameTimer); // stop timer
               GLPI_RELOAD_PARENT.location.reload();
            }

            // try to redim caseIFrame
            if (!redimIFrame) {
               redimTaskFrame(taskFrame, delIndex);
               var redimFrameTimer = window.setInterval(function () {
                  redimTaskFrame(taskFrame, delIndex);
               }, 1000);

               redimIFrame = true;
            }
         }

         taskFrameTimerCounter = taskFrameTimerCounter + 1;

         if (taskFrameTimerCounter > 3000 || bShowHideNextStep || bHideClaimCancelButton) { // either timeout or hiding is done
            window.clearInterval(taskFrameTimer);
         }

      } catch (evt) {
         // nothing to do here for the moment
      }

    }, 10);

}

function redimTaskFrame(taskFrame, delIndex) {
   var newHeight;
   try {
      //var locElt = locContentDocument.getElementsByTagName("table")[0];
      var locElt = taskFrame.contentDocument.getElementsByTagName("body")[0];
      newHeight = parseInt(getComputedStyle(locElt, null).getPropertyValue('height'), 10);
      if (newHeight < 500) {
         newHeight = 500;
      }

      taskFrame.height = newHeight;
   } catch (e) {
   }
}

function onTaskFrameActivation(delIndex) {
   var taskFrameId = "caseiframe-" + delIndex;
   var taskFrameTimerCounter = 0;
   var redimIFrame = false;

   var taskFrameTimer = window.setInterval(function () {
      try {
         var locContentDocument;
         var taskFrame = document.getElementById(taskFrameId);

         if (taskFrame != undefined && taskFrame.contentDocument != undefined) {
            // here we've caught the content of the iframe
            locContentDocument = taskFrame.contentDocument;

            // try to redim caseIFrame
            if (!redimIFrame) {
               var newHeight;
               var locElt = locContentDocument.getElementsByTagName("body")[0];
               newHeight = parseInt(getComputedStyle(locElt, null).getPropertyValue('height'), 10);

               tabs.getItem('task-' + delIndex).setHeight(newHeight);
               taskFrame.height = newHeight;
               redimIFrame = true;
            }
         }

         taskFrameTimerCounter = taskFrameTimerCounter + 1;

         if (taskFrameTimerCounter > 3000 || redimIFrame) { // timeout
            window.clearInterval(taskFrameTimer);
         }

      } catch (evt) {
         // nothing to do here for the moment
      }

    }, 10);
}
function clearClass(lociFrame) {

   try {
      var locElt = lociFrame.contentDocument.getElementsByTagName('body')[0];
      if (locElt != undefined && locElt.className != '') {
            //debugger;
            locElt.className = '';

      }
   } catch (ev) {

   }
}

function onOtherFrameLoad(tabPanelName, frameName, eltTagName, isMap3) {
   var otherFrameId = frameName;
   var otherFrameTimerCounter = 0;
   var redimIFrame = false;
   //debugger;
   if (isMap3 == undefined) {
       isMap3 = false;
   }

   var otherFrameTimer = window.setInterval(function () {
      try {

         var locContentDocument;
         var otherFrame = document.getElementById(otherFrameId);

         if (otherFrame != undefined && otherFrame.contentDocument != undefined) {
            // here we've caught the content of the iframe
            clearClass(otherFrame);

            locContentDocument = otherFrame.contentDocument;

            // try to redim otherFrame
            // and tabPanel
            if (!redimIFrame) {
               var locElt;
               // isMap3 == true
               // map is bpmn
               // must look at div with special CSS class name to get newHeight and should change offset and size of parent div
               if (!isMap3) {
                  if (tabPanelName == 'caseMap') {
                     //                            locElt = locContentDocument.querySelectorAll('div.panel_content___processmaker')[0];
                     locElt = locContentDocument.querySelectorAll('div.panel_containerWindow___processmaker')[0];
                     locElt2 = locContentDocument.getElementById('pm_target');
                     locElt2.style.height = locElt.clientHeight + 'px';
                  } else {
                     locElt = locContentDocument.getElementsByTagName(eltTagName)[0];
                  }
               } else {
                  locElt = locContentDocument.querySelectorAll('div.pmui-pmpool')[0];
               }
               if (locElt != undefined) {
                  var newHeight;
                  if (isMap3) {
                     locElt.offsetParent.style.top = 0;
                     locElt.offsetParent.style.width = locElt.offsetWidth + 10 + locElt.offsetLeft + 'px';
                     locElt.offsetParent.style.height = locElt.offsetHeight + locElt.offsetTop + 'px';
                     newHeight = (locElt.offsetHeight < 500 ? 500 : locElt.offsetHeight) + locElt.offsetParent.offsetTop + 30;
                  } else {
                     newHeight = (locElt.offsetHeight < 500 ? 500 : locElt.offsetHeight);
                  }
                  if (tabPanelName == 'caseMap') {
                     // trick to force scrollbar to be shown
                     locElt.offsetParent.style.overflow = 'visible';
                     locElt.offsetParent.style.overflow = 'hidden';
                  }
                  if (locElt.scrollHeight && locElt.scrollHeight > newHeight) {
                     newHeight = locElt.scrollHeight;
                  }
                  //NOT NEEDED WITH jQuery: tabs.getItem(tabPanelName).setHeight(newHeight);
                  otherFrame.height = newHeight;
                  redimIFrame = true;
               }
            }
         }

         otherFrameTimerCounter = otherFrameTimerCounter + 1;

         if (otherFrameTimerCounter > 3000 || redimIFrame) {
            window.clearInterval(otherFrameTimer);
         }

      } catch (ev) {
         // nothing to do here for the moment
      }
    }, 10);

}
