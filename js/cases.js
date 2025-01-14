/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */

glpi_pm = {
   add_tab_panel: function (name, title, html){
      //debugger ;
      var loctabs = $('#tabspanel');
      if( !loctabs[0].children[name] ) { // panel is not yet existing, create one
         if (loctabs.find('a[data-bs-target=\"#'+name+'\"]').length == 0 ) {
            loctabs.append("<li class='nav-item ms-0'><a class='nav-link justify-content-between pe-1' href='#' data-bs-toggle='tab' title='" + title + "' data-bs-target='#" + name + "'>" + title + "</a></li>" );
         }
         var select = loctabs.next('select');
         select.append("<option value='" + (parseInt(select.children().last().val()) + 1) + "'>" + title + "</option >")
         var tabcontentdiv = select.next('div');
         tabcontentdiv.append("<div class='tab-pane fade' role='tabpanel' id='" + name + "'>" + html + "</div>");
         $("a[data-bs-target='#" +name +"']").tab("show");
         //tabcontentdiv.tabs('refresh'); // to show the panel
      } 
      // activate it
     //var tabIndex = loctabs.find('a[href=\"#'+name+'\"]').parent().index();
     // loctabs.tabs( 'option', 'active', tabIndex) ; // to activate it
   },


   case_submit_form: function (e) {
      //debugger;

      if (e.defaultPrevented) {
         // means form submit has been cancelled due to wrong values
         return;
      }

      // validation is OK
      if (this.pm_glpi_form_validate) {
         // then we must wait for the case to be saved and routed before saving our form
         // to do this, we must cancel our own submit
         e.preventDefault();

         // to request the iframe to really submit
         $('#' + this.glpi_data.glpi_iframeid)[0].contentWindow.postMessage({
            message: 'dosubmitdynaform',
            glpi_data: this.glpi_data
         }, GLPI_PROCESSMAKER_PLUGIN_DATA.pm_server_URL);
      } else {
         $(this).prepend("<div id='loadingslide'><div class='loadingindicator'>" + __('Loading...') + "</div></div>");
         $('html, body').animate({ scrollTop: 0 }, 'fast');
      }
   },


   case_message: function (e) {
      // to block any unwanted messages
      if (e.origin !== GLPI_PROCESSMAKER_PLUGIN_DATA.pm_server_URL) {
         console.warn('glpi_pm.case_message: sender is ' + e.origin + ', when it should be ' + GLPI_PROCESSMAKER_PLUGIN_DATA.pm_server_URL + '!');
         return;
      }

      let myformjq, myform;
      let data = e.data; // here are data sent by the caseiframe window postMessage method
      switch (data.message) {
         case 'iframeready':
            //debugger;
            if (data.submitform) { // means the submit has been done in iframe and case has been saved
               // the GLPI form must be submitted
               myformjq = $('#' + data.glpi_data.glpi_iframeid).parents('form');
               myform = myformjq[0];

               myform.pm_glpi_form_validate = false;

               if (myformjq.attr('data-submitted') != undefined) {
                  myformjq.attr('data-submitted', 'false');
               }
               let button = myformjq.find('[name=add][type="submit"]');
               myform.requestSubmit(button.length ? button[0] : undefined);
            }
            else {
               e.source.postMessage({
                  message: 'parentready',
                  glpi_data: data.glpi_data
               }, GLPI_PROCESSMAKER_PLUGIN_DATA.pm_server_URL); // to acknowledge the iframeready
            }
            break;

         case 'iframeresize':
            $('#' + data.glpi_data.glpi_iframeid)[0].height = data.height;
            break;

         case 'dovalidateparentform':
            // iframe has been validated then we must validate parent form
            //debugger;

            // set the content field (description)
            if (typeof tinyMCE != 'undefined' && tinyMCE.activeEditor && data.userrequestsumup && data.userrequestsumup != '_') {
               let userrequestsumup = data.userrequestsumup.replace(/(\r\n)|(\r)|(\n)/g, '<br>');
               tinyMCE.activeEditor.setContent(userrequestsumup);
            }

            // the GLPI form must be submitted to validate fields
            myformjq = $('#' + data.glpi_data.glpi_iframeid).parents('form');
            myform = myformjq[0];
            myform.pm_glpi_form_validate = true;
            if (!myform.glpi_data) {
               myform.glpi_data = data.glpi_data;
               myform.addEventListener('submit', glpi_pm.case_submit_form);
            }
            //myformjq.find('[name=add][type="submit"]').trigger("click");
            let button = myformjq.find('[name=add][type="submit"]');
            myform.requestSubmit(button.length ? button[0] : undefined);

            break;

         case 'historydynaformgridpreview':
            //debugger;
            // historyDynaformGridPreview_225414236618935cf976300066744816
            const action = 'historyDynaformGridPreview';
            let actionToDo = action + '_' + data.DYN_UID;
            let iframeid = 'caseiframe-' + actionToDo;

            let acturl = data.glpi_data.glpi_pm_server_URL
               + "/cases/casesHistoryDynaformPage_Ajax?actionAjax=" + action
               + '&DYN_UID=' + data.DYN_UID
               + '&sid=' + data.glpi_data.glpi_sid
               + '&glpi_data=' + encodeURI(
                  JSON.stringify({
                     glpi_url: data.glpi_data.glpi_url,
                     glpi_tabtype : 'historydynaformgridpreview',
                     glpi_tabpanelname: actionToDo,
                     glpi_iframeid: iframeid,
                     glpi_elttagname: 'body',
                     glpi_sid: data.glpi_data.glpi_sid,
                     glpi_app_uid: data.glpi_data.glpi_app_uid,
                     glpi_pro_uid: data.glpi_data.glpi_pro_uid
               }));
            glpi_pm.add_tab_panel(
               'historyDynaformGridPreview_' + data.DYN_UID,
               data.glpi_data.glpi_preview + '(' + data.DYN_TITLE + ')',
               "<iframe id='" + iframeid + "' style='border: none;' width = '100%' src = '" + acturl + "' ></iframe>"
            );
            break;

         case 'click':
            // close the select2
            $('.select2-hidden-accessible').select2('close');

            // close the flatpickr
            document.querySelectorAll("div.flatpickr").forEach((e) => { e._flatpickr.close() });
      };
   }
}


window.addEventListener('message', glpi_pm.case_message);
