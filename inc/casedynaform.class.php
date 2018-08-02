<?php

/**
 * PluginProcessmakerCasemap short summary.
 *
 * casemap description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasedynaform extends CommonDBTM {

   static function displayTabContentForItem(CommonGLPI $case, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI, $PM_SOAP;

      $config = $PM_SOAP->config;
      $rand = rand();

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'


      echo "<script type='text/javascript'>
               var historyGridListChangeLogGlobal = { viewIdHistory: '', viewIdDin: '', viewDynaformName: '', idHistory: '' } ;
               var ActionTabFrameGlobal = { tabData: '', tabName: '', tabTitle: '' } ;
               function urldecode(url) {
                  return decodeURIComponent(url.replace(/\+/g, ' '));
               }
               function addTabPanel(name, title, html){
                  //debugger ;
                  var loctabs = $('#tabspanel').next('div[id^=tabs]');
                  if( !loctabs[0].children[name] ) { // panel is not yet existing, create one
                     if( loctabs.find('a[href=\"#'+name+'\"]').length == 0 ) {
                        loctabs.find('ul').append( '<li><a href=\'#' + name + '\'>' + title + '</a></li>' );
                     }
                     $.ajax( { url: '".$PM_SOAP->serverURL."/cases/cases_Open?sid=".$PM_SOAP->getPMSessionID()."&APP_UID={$case->fields['case_guid']}&DEL_INDEX=1&action=TO_DO&glpi_init_case=1&glpi_domain={$config->fields['domain']}',
                              complete: function() {
                                    //debugger;
                                    loctabs.append( '<div id=\'' + name + '\'>' + html + '</div>');
                                    loctabs.tabs('refresh'); // to show the panel
                                    var tabIndex = loctabs.find('a[href=\"#'+name+'\"]').parent().index();
                                    loctabs.tabs( 'option', 'active', tabIndex) ; // to activate it
                                 } });
                  } else { // only activate it
                     var tabIndex = loctabs.find('a[href=\"#'+name+'\"]').parent().index();
                     loctabs.tabs( 'option', 'active', tabIndex) ; // to activate it
                  }
               }
               var Actions = { tabFrame: function( actionToDo ) {
                           //debugger ;
                           if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) {
                              actionToDo = actionToDo.replace('_', '$') ;
                              var act = actionToDo.replace( '$', '&DYN_UID=') ;
                              addTabPanel( actionToDo,
                                       ActionTabFrameGlobal.tabTitle,
                                       '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand\' ></iframe>'
                                       );
                           }
                        }
                    } ;
      </script>";

      $caseURL = $PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand";

      $iframe = "<iframe
                  id='caseiframe-historyDynaformPage'
                  style='border: none;'
                  width='100%'
                  src='$caseURL'
                  onload=\"onOtherFrameLoad( 'historyDynaformPage', 'caseiframe-historyDynaformPage', 'body', 0 );\">
                 </iframe>";

      $PM_SOAP->initCaseAndShowTab(['APP_UID' => $case->fields['case_guid'], 'DEL_INDEX' => 1], $iframe, $rand) ;

   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      return __('Dynaforms', 'processmaker');
   }

}