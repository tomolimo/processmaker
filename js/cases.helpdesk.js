
var oldHandler;
var submitButton;
var caseIFrame;

function onClickContinue(obj) {
   //debugger;
   // call old handler
   if (obj != undefined && oldHandler) {
      oldHandler(obj.target);
   }
   // hide the iFrame
   caseIFrame.style.visibility = 'hidden';

   // call new handler
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
   //var bAreaUseRequestSumUp = false;
   var bButtonContinue = false;
   var caseTimer = window.setInterval(function () {
      //debugger ;
      // look for frmDerivation form

      caseIFrame = document.getElementById('caseiframe');

      var contentDocument;
      try {
         contentDocument = caseIFrame.contentDocument;
      } catch (ex) {
         contentDocument = false;
      }
      if (caseIFrame != undefined && contentDocument) {
         var buttonContinue = contentDocument.getElementById('form[btnGLPISendRequest]');
         var txtAreaUseRequestSumUp = contentDocument.getElementById('form[UserRequestSumUp]');
         var linkList = contentDocument.getElementsByTagName('a');
         if (txtAreaUseRequestSumUp != undefined) {
            //debugger;
            $("textarea[name='content']")[0].value = txtAreaUseRequestSumUp.value;
         } else {
            $("textarea[name='content']")[0].value = '_';
         }

         if (!bButtonContinue && buttonContinue != undefined && linkList != undefined && linkList.length > 0) {
            bButtonContinue = true; //window.clearInterval(caseTimer); // to be sure that it will be done only one time
            // change action for the attached form and add some parameters
            //debugger;

            bGLPIHideElement(linkList, 'href', 'cases_Step?TYPE=ASSIGN_TASK&UID=-1&POSITION=10000&ACTION=ASSIGN');

            //buttonContinue.form.action = null; //'';
            //if (buttonContinue.addEventListener)
            //    buttonContinue.addEventListener("click", onClickContinue, false);
            //else
            //    buttonContinue.attachEvent("onclick", onClickContinue);
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
            var locElt = contentDocument.getElementsByTagName("form")[0];
            var newHeight = (locElt.clientHeight < 300 ? 300 : locElt.clientHeight) + locElt.offsetParent.offsetTop + 10;
            caseIFrame.height = newHeight;
            redimIFrame = true;
         }
      }

      if ( caseTimerCounter > 3000 ) {
         window.clearInterval(caseTimer);
      } else {
         caseTimerCounter = caseTimerCounter + 1;
      }

   }, 10);
}
