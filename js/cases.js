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

function showMask(elt) {
    if( !elt.defaultPrevented ) {
        Ext.getBody().moveTo(0, 0);
        var myMask = new Ext.LoadMask(Ext.getBody(), { removeMask: false });
        myMask.show();
    }
};


function onTaskFrameLoad(event, delIndex, hideClaimButton, csrf) {
    //alert("Loaded frame " + delIndex);
    var taskFrameId = event.target.id; //"caseiframe-" + delIndex;
    var bShowHideNextStep = false ; // not done yet!
    var bHideClaimCancelButton = false; // To manage 'Claim' button
    var taskFrameTimerCounter = 0;
    var redimIFrame = false;
    //debugger;
    var taskFrameTimer = window.setInterval(function () {
        try {
            var locContentDocument;
            var taskFrame = document.getElementById(taskFrameId);

            if (taskFrame != undefined && taskFrame.contentDocument != undefined) {
                // here we've caught the content of the iframe

                // then look if btnGLPISendRequest exists,
                locContentDocument = taskFrame.contentDocument;
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
                        var action = node.action.split('?');
                        node.action = GLPI_HTTP_CASE_FORM + '?' + action[1] + '&DEL_INDEX=' + delIndex;

                        // add an element that will be the csrf data code for the POST
                        //debugger;
                        var csrfElt = document.createElement("input");
                        csrfElt.setAttribute("type", "hidden");
                        csrfElt.setAttribute("name", "_glpi_csrf_token") ;
                        csrfElt.setAttribute("value", csrf) ;
                        node.appendChild(csrfElt);

                        // try to add showMask function to submit event
                        // TODO
                        //node.addEventListener('submit', showMask, true);
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
                    var newHeight;
                    //var locElt = locContentDocument.getElementsByTagName("table")[0];
                    var locElt = locContentDocument.getElementsByTagName("body")[0];
                    newHeight = parseInt(getComputedStyle(locElt, null).getPropertyValue('height'), 10) + 60;
                    //if (locElt)
                    //    newHeight = (locElt.clientHeight < 400 ? 400 : locElt.clientHeight) + locElt.offsetParent.offsetTop ;
                    //else {
                    //    locElt = locContentDocument.getElementsByTagName("form")[0];
                    //    newHeight = (locElt.clientHeight < 400 ? 400 : locElt.clientHeight) + locElt.offsetTop ;
                    //}
                    //locElt.clientHeight = newHeight; // don't know if this is neccessary!!! --> bugs on IE8
                    //NOT NEEDED WITH jQuery: var elts = $('#processmakertabpanel').tabs();//.getItem('task-' + delIndex).setHeight(newHeight);
                    //debugger;
                    taskFrame.height = newHeight ;
                    redimIFrame = true;
                }
            }

            taskFrameTimerCounter = taskFrameTimerCounter + 1;

            if (taskFrameTimerCounter > 3000 || bShowHideNextStep || bHideClaimCancelButton) // either timeout or hiding is done
                window.clearInterval(taskFrameTimer);

        } catch (evt) {
            // nothing to do here for the moment
        }

    }, 10);

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
                    newHeight = parseInt(getComputedStyle(locElt, null).getPropertyValue('height'), 10) ;

                    tabs.getItem('task-' + delIndex).setHeight(newHeight);
                    taskFrame.height = newHeight;
                    redimIFrame = true;
                }
            }

            taskFrameTimerCounter = taskFrameTimerCounter + 1;

            if (taskFrameTimerCounter > 3000 || redimIFrame) // timeout
                window.clearInterval(taskFrameTimer);

        } catch (evt) {
            // nothing to do here for the moment
        }

    }, 10);
}
function clearClass(lociFrame) {

   //var otherFrameTimerCounter = 0;
   //var otherFrameTimer = window.setInterval(function () {
        try {
            var locElt = lociFrame.contentDocument.getElementsByTagName('body')[0];
            if (locElt != undefined && locElt.className != '') {
                //debugger;
                locElt.className = '';
         //   window.clearInterval(otherFrameTimer);
         //} else {
         //   otherFrameTimerCounter = otherFrameTimerCounter + 1;
         //   if (otherFrameTimerCounter > 3000 )
         //      window.clearInterval(otherFrameTimer);
            }
        } catch (ev) {
            
        }
   //}, 10);
}

function onOtherFrameLoad(tabPanelName, frameName, eltTagName, isMap3) {
    var otherFrameId = frameName; 
    var otherFrameTimerCounter = 0;
    var redimIFrame = false;
    //debugger;
    if (isMap3 == undefined)
        isMap3 = false;

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
                            newHeight = (locElt.offsetHeight < 500 ? 500 : locElt.offsetHeight) ;
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
                        otherFrame.height = newHeight ;
                        redimIFrame = true;
                    }
                }
            }

            otherFrameTimerCounter = otherFrameTimerCounter + 1;

            if (otherFrameTimerCounter > 3000 || redimIFrame)
                window.clearInterval(otherFrameTimer);

        } catch (ev) {
            // nothing to do here for the moment
        }
    }, 10);

}




