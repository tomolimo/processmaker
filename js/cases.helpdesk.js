
var oldHandler;
var submitButton;
var caseIFrame;

function onClickContinue(obj) {
   //debugger;
   contentDocument = caseIFrame.contentDocument;
   var txtAreaUserRequestSumUp = contentDocument.getElementById('form[UserRequestSumUp]');
   if (txtAreaUserRequestSumUp) {
      $("textarea[name='content']").val($(txtAreaUserRequestSumUp).val());
   } else {
      $("textarea[name='content']").val('_');
   }

   // call old handler if any
   //debugger;
   if (obj != undefined && oldHandler) {
      oldHandler(obj.target);
   }
   // hide the iFrame
   caseIFrame.style.visibility = 'hidden';

   // trigger a click on the 'add' button of the ticket 
   submitButton.click();
}


// used to find an element in a list and to hide it!
function bGLPIHideElement(eltList, attribute, value) {
   var ret = false;
   for (var i = 0; i < eltList.length; i++) {
      var node = eltList[i];
      if (node.getAttribute(attribute) == value) {
         // hide the link
         node.style.display = 'none';
         ret = true;
      }
   }
   return ret;
}

function onLoadFrame( evt, caseId, delIndex, caseNumber, processName ) {
   var caseTimerCounter = 0;
   var redimIFrame = false;
   var bButtonContinue = false;
   var caseTimer = window.setInterval(function () {
      //debugger ;
      // look for caseiframe iFrame

      caseIFrame = document.getElementById('caseiframe');

      var contentDocument;
      try {
         contentDocument = caseIFrame.contentDocument;
      } catch (ex) {
         contentDocument = false;
      }
      if (caseIFrame != undefined && contentDocument) {
         var buttonContinue = contentDocument.getElementById('form[btnGLPISendRequest]');
         var linkList = contentDocument.getElementsByTagName('a');
         
         if (!bButtonContinue && buttonContinue != undefined && linkList != undefined && linkList.length > 0) {
            bButtonContinue = true; //window.clearInterval(caseTimer); // to be sure that it will be done only one time
            // change action for the attached form and add some parameters
            //debugger;

            bGLPIHideElement(linkList, 'href', 'cases_Step?TYPE=ASSIGN_TASK&UID=-1&POSITION=10000&ACTION=ASSIGN');
            
            oldHandler = buttonContinue.onclick;
            buttonContinue.onclick = onClickContinue;
            
            submitButton = $("input[name='add'][type=submit]")[0];
            submitButton.insertAdjacentHTML('beforebegin', "<input type='hidden' name='processmaker_action' value='routecase'/>");
            submitButton.insertAdjacentHTML('beforebegin', "<input type='hidden' name='processmaker_caseid' value='" + caseId + "'/>");
            submitButton.insertAdjacentHTML('beforebegin', "<input type='hidden' name='processmaker_delindex' value='" + delIndex + "'/>");
            submitButton.insertAdjacentHTML('beforebegin', "<input type='hidden' name='processmaker_casenum' value='" + caseNumber + "'/>");

            $("input[name='name'][type=text]")[0].value = processName;

         }

         // try to redim caseIFrame
         if (!redimIFrame) {
            redimIFrame = true; // to prevent several timer creation

            // redim one time
            redimTaskFrame(caseIFrame);

            // redim each second
            var redimFrameTimer = window.setInterval(function () {
               redimTaskFrame(caseIFrame);
            }, 1000);
         }
      }

      if ( caseTimerCounter > 3000 ) {
         window.clearInterval(caseTimer);
      } else {
         caseTimerCounter = caseTimerCounter + 1;
      }

   }, 10);
}

function redimTaskFrame(taskFrame) {
   var newHeight;
   try {
      //var locElt = locContentDocument.getElementsByTagName("table")[0];
      var locElt = taskFrame.contentDocument.getElementsByTagName("html")[0];
      newHeight = parseInt(getComputedStyle(locElt, null).getPropertyValue('height'), 10);
      if (newHeight < 400) {
         newHeight = 400;
      }

      taskFrame.height = newHeight;
   } catch (e) {
   }
}