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
      global $CFG_GLPI, $PM_SOAP, $LANG;

      $config = $PM_SOAP->config;
      $rand = rand();

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);

      //if( actionToDo == 'historyDynaformPage' ) {
      //                          addTabPanel( actionToDo,
      //                                  '".$LANG['processmaker']['item']['case']['dynaforms']."',
      //                                  '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
      //                                  );
      //                      }

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'


      echo "<script type='text/javascript'>
               var historyGridListChangeLogGlobal = { viewIdHistory: '', viewIdDin: '', viewDynaformName: '', idHistory: '' } ;
               var ActionTabFrameGlobal = { tabData: '', tabName: '', tabTitle: '' } ;
               function addTabPanel(name, title, html){
                  //debugger ;
                  var loctabs = $('#tabspanel').next('div[id^=tabs]');
                  if( !loctabs[0].children[name] ) { // panel is not yet existing, create one
                     //tabs.tabs('add');
                     if( loctabs.find('a[href=\"#'+name+'\"]').length == 0 ) {
                        loctabs.find('ul').append( '<li><a href=\'#' + name + '\'>' + title + '</a></li>' );
                     }
                     //debugger ;
                     loctabs.append( '<div id=\'' + name + '\'>' + html + '</div>');
                     loctabs.tabs('refresh'); // to show the panel
                  }
                  var tabIndex = loctabs.find('a[href=\"#'+name+'\"]').parent().index();
                  loctabs.tabs( 'option', 'active', tabIndex) ; // to activate it
               }
               var Actions = { tabFrame: function( actionToDo ) {
                           //debugger ;
                           //if( actionToDo == 'dynaformViewFromHistory' ) {
                           //     actionToDo = 'dynaformChangeLogViewHistory_' + historyGridListChangeLogGlobal.viewIdDin + historyGridListChangeLogGlobal.dynDate.replace(/ /g, '_').replace(/:/g, '-') ;
                           //     ajaxResponse = $.parseJSON(historyGridListChangeLogGlobal.viewDynaformName);
                           //     addTabPanel( actionToDo,
                           //             ajaxResponse.dynTitle + ' <sup>(' + historyGridListChangeLogGlobal.dynDate + ')</sup>',
                           //             '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/ajaxListener?action=dynaformViewFromHistory&DYN_UID=' + historyGridListChangeLogGlobal.viewIdDin + \"&HISTORY_ID=\" + historyGridListChangeLogGlobal.viewIdHistory + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                           //             );
                           // } else
                           if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) {
                              actionToDo = actionToDo.replace('_', '$') ;
                              var act = actionToDo.replace( '$', '&DYN_UID=') ;
                              addTabPanel( actionToDo,
                                       ActionTabFrameGlobal.tabTitle,
                                       '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                       );
                           }
                           //if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) {
                           //   actionToDo = actionToDo.replace('_', '$') ;
                           //   var act = actionToDo.replace( '$', '&DYN_UID=') ;
                           //   var new_window = window.open('{$PM_SOAP->serverURL}/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}', '_blank');
                           //   if (new_window) {
                           //      // set title
                           //      new_window.document.title = ActionTabFrameGlobal.tabTitle;
                           //   }
                           //}

                            //} else
                            //if( actionToDo.search( '^historyDynaformGridHistory' ) == 0) {
                            //    var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                            //        var act = 'showDynaformListHistory&PRO_UID=' + ajaxResponse.PRO_UID + '&APP_UID=' + ajaxResponse.APP_UID + '&TAS_UID=-1&DYN_UID=' + ajaxResponse.DYN_UID;
                            //    addTabPanel( actionToDo,
                            //            ActionTabFrameGlobal.tabTitle,
                            //            '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                            //            );
                            //} else
                            //if( actionToDo.search( '^dynaformChangeLogViewHistory' ) == 0) {
                            //    var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                            //    actionToDo='dynaformChangeLogViewHistory' + ajaxResponse.dynUID + ajaxResponse.dynDate ;
                            //    //actionToDo = actionToDo.replace(' ', '_').replace(':', '-');
                            //        var act = 'dynaformChangeLogViewHistory&DYN_UID=' + ajaxResponse.dynUID + '&HISTORY_ID=' + ajaxResponse.tablename;
                            //    addTabPanel( actionToDo,
                            //            ActionTabFrameGlobal.tabTitle,
                            //            '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                            //            );
                            //}
                        }
                    } ;
      </script>";

      $caseURL = $PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand&glpi_domain={$config->fields['domain']}&GLPI_APP_UID={$case->fields['case_guid']}&GLPI_PRO_UID={$proj->fields['process_guid']}";

      echo "<iframe id='caseiframe-historyDynaformPage' style='border: none;' width='100%' src='$caseURL'
            onload=\"onOtherFrameLoad( 'historyDynaformPage', 'caseiframe-historyDynaformPage', 'body', 0 );\"></iframe>'";
   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      global $LANG;
      return $LANG['processmaker']['item']['case']['viewdynaforms'];
   }

}