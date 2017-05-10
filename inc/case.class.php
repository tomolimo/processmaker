<?php

/**
 * ticketcase short summary.
 *
 * ticketcase description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCase extends CommonDBTM {

   /**
    * Summary of getTabNameForItem
    * @param CommonGLPI $item         is the item
    * @param mixed      $withtemplate has template
    * @return array os strings
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      $item_id = $item->getID();
      $item_type = $item->getType();
      if (self::getCaseFromItemTypeAndItemId($item_type, $item_id )) {
         return array( 'processmakercases' => $LANG['processmaker']['item']['tab']."<sup>(".$this->fields['case_status'].")</sup>" );
      } else {
         return array( 'processmakercases' => $LANG['processmaker']['item']['tab'] );
      }

   }

    /**
     * Summary of getCaseIdFromItemTypeAndItemId
     * @param mixed $itemType is the item type
     * @param mixed $itemId   is the item id
     * @return mixed case id
     */
   static function getCaseIdFromItemTypeAndItemId( $itemType, $itemId) {
      global $DB;

      $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE items_id=$itemId and itemtype='$itemType';";
      if (($res = $DB->query($query) )  && $DB->numrows($res) > 0) {
                $row = $DB->fetch_array($res);
                return $row['id'];
      }
      return false;
   }


    /**
     * Summary of getCaseFromItemTypeAndItemId
     * @param mixed $itemType is the item type
     * @param mixed $itemId   is the item id
     * @return mixed: returns false when there is no case associated with the item, else fills in the item fields from DB, and returns true
     */
   function getCaseFromItemTypeAndItemId($itemType, $itemId) {

      if ($caseId = self::getCaseIdFromItemTypeAndItemId( $itemType, $itemId)) {
            return $this->getFromDB( $caseId );
      }

      return false;
   }

    /**
     * Summary of getFromDB
     * @param mixed $ID id of needed object
     * @return mixed object if found, else false
     */
   function getFromDB($ID) {
      global $DB;
      // Search for object in database and fills 'fields'

      // != 0 because 0 is consider as empty
      if (strlen($ID)==0) {
         return false;
      }

      $query = "SELECT *
                FROM `".$this->getTable()."`
                WHERE `".$this->getIndexName()."` = '".$ID."'";

      if (($result = $DB->query($query))  && $DB->numrows($result)==1) {
             $this->fields = $DB->fetch_assoc($result);
             $this->post_getFromDB();

             return true;
      }

      return false;
   }


    /**
     * Summary of getVariables
     *      Gets variables from a case.
     * @param array $vars an array of variable name that will be read from the case as case variables
     * @return array an associative array (variable_name => value). The returned array can be empty if requested variables are not found.
     */
   function getVariables( $vars = array() ) {
      global $PM_DB;

      $locVars = array( );
      $app_data = array(); // by default

      $caseId = $this->getID();

      $query = "SELECT APP_DATA FROM APPLICATION WHERE APP_UID='$caseId';";

      if (($res = $PM_DB->query($query)) &&  $PM_DB->numrows($res) > 0) {
         $row = $PM_DB->fetch_assoc($res);
         $app_data = unserialize($row['APP_DATA'] );
         $locVars = array_intersect_key( $app_data, array_flip($vars) );
      }

      return $locVars;
   }

    /**
     * Summary of sendVariables
     *      Sends variables to a case.
     *      BEWARE that this will not work correctly for values containning special chars like \
     *      instead use the web service function from PluginProcessmakerProcessmaker class
     * @param array $vars an array of associative variables (name => value) that will be injected into the case as case variables
     * @return true if variables have been saved to the case, false otherwise
     */
   function sendVariables( $vars = array() ) {
      global $PM_DB;

      $variablesSent = false; // be default
      $app_data = array(); // by default

      $caseId = $this->getID();

      $query = "SELECT APP_DATA FROM APPLICATION WHERE APP_UID='$caseId';";

      if (($res = $PM_DB->query($query)) && $PM_DB->numrows($res) > 0) {
         $row = $PM_DB->fetch_assoc($res);
         $app_data = unserialize($row['APP_DATA'] );
      }

      $app_data = array_replace( $app_data, $vars );
      $serialized = serialize( $app_data );

      $query = "UPDATE APPLICATION SET APP_DATA='$serialized' WHERE APP_UID='$caseId';";
      $res = $PM_DB->query( $query );

      if ($PM_DB->affected_rows() == 1) {
         $variablesSent = true;
      }

      return $variablesSent;
   }


    /**
     * Summary of displayTabContentForItem
     * @param CommonGLPI $item         is the item
     * @param mixed      $tabnum       is the tab num
     * @param mixed      $withtemplate has template
     * @return mixed
     */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $LANG, $DB, $CFG_GLPI;

      $config = PluginProcessmakerConfig::getInstance();

      if ($config->fields['maintenance'] == 0) {

         $item_id = $item->getID();
         $item_type = $item->getType();
         ////retrieve container for current tab
         //$container = new self;
         //$found_c = $container->find("`itemtype` = '$item_type' AND `items_id` = $item_id ");

         //echo "Show the frame";
         $rand = rand();
         echo "<form style='margin-bottom: 0px' name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
         echo "<div class='center'> <table id='processmakercasemenu' style='margin-bottom: 0px' class='tab_cadre_fixe'>";
         echo Html::scriptBlock("$('#processmakercasemenu').css('max-width', 'none');");
         echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['tab']."</th></tr>";

         $pmCaseUser = false; // initial value: no user
         // first search for the case
         if (self::getCaseIdFromItemTypeAndItemId($item_type, $item_id )) {
            $myProcessMaker = new PluginProcessmakerProcessmaker( );
            $myProcessMaker->login();
            $caseInfo = $myProcessMaker->getCaseFromItem( $item_type, $item_id );
            if ($caseInfo->caseStatus != 'CANCELLED' && $caseInfo->caseStatus != 'COMPLETED') {
               // need to get info on the thread of the GLPI current user
               // we must retreive currentGLPI user from this array
               $GLPICurrentPMUserId = PluginProcessmakerUser::getPMUserId( Session::getLoginUserID() );
               $pmCaseUser = $caseInfo->currentUsers[0]; // by default currently manage only one task at a time, must define tab management for several tasks
               foreach ($caseInfo->currentUsers as $caseUser) {
                  if ($caseUser->userId == $GLPICurrentPMUserId) {
                     $pmCaseUser = $caseUser;
                     break;
                  }
               }
            }
            $locDelIndex = 1; // by default
            switch ($caseInfo->caseStatus) {
               case "CANCELLED"  :
                  echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['cancelledcase']."</th></tr>";
                  $paramsURL = "DEL_INDEX=1";
                  //                    echo "<tr class='tab_bg_1' ><td id='GLPI-PM-DEL_INDEX' ><script>var GLPI_DEL_INDEX = 1; </script></td></tr>" ;
                  break;

               case "DRAFT" :
               case "TO_DO" :

                  $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus;
                  $locDelIndex = $pmCaseUser->delIndex;
                  if ($pmCaseUser->userId != '') {
                     echo "<tr class='tab_bg_1'>";

                     if ($GLPICurrentPMUserId == $pmCaseUser->userId) {
                        // then propose a button to cancel case only when assigned user is == to glpi  current user
                        echo "<td class='tab_bg_2' >";
                        echo $LANG['processmaker']['item']['cancelcase'];
                        echo "</td><td class='tab_bg_2'>";
                        echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                        echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";
                        //echo "<input type='hidden' name='plugin_processmaker_delIndex' value='".$pmCaseUser->delIndex."'>";
                        //echo "<input type='hidden' name='plugin_processmaker_userId' value='".$pmCaseUser->userId."'>";
                        echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttoncancelcaseconfirmation']."\") ;  cancelMyMask = !ret ;  return ret;'   type='submit' name='cancel' value='".$LANG['processmaker']['item']['buttoncancelcase']."' class='submit'>";
                        echo "</td>";
                     }

                     if ($caseInfo->caseStatus == "DRAFT" || (plugin_processmaker_haveRight("deletecase", DELETE) && $_SESSION['glpiactiveprofile']['interface'] == 'central')) {
                        // then propose a button to delete case
                        echo "<td class='tab_bg_2'>";
                        echo $LANG['processmaker']['item']['deletecase'];
                        echo "</td><td class='tab_bg_2'>";
                        echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                        echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";

                        echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttondeletecaseconfirmation']."\"); cancelMyMask = !ret ; return ret;'  type='submit' name='delete' value='".$LANG['processmaker']['item']['buttondeletecase']."' class='submit' >";

                        echo "</td>";

                     }

                     echo "</form>";

                     echo "</td></tr>";
                  }

                  break;
               case "COMPLETED" :
                  echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['completedcase']."</th></tr>";
                  $paramsURL = "DEL_INDEX="; // DEL_INDEX is not set to tell PM to show the current task i.e.: the last one
                  break;
            }

            $proj = new PluginProcessmakerProcess;
            $proj->getFromDBbyExternalID( $caseInfo->processId );
            $project_type = $proj->fields['project_type'];
            // then propose a button to view case history
            echo "<tr class='tab_bg_1' >";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='submit' onclick=\"javascript:Actions.tabFrame('caseMap');\" value='".$LANG['processmaker']['item']['case']['viewcasemap']."'>";
            echo "</td>";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='submit' onclick=\"javascript:Actions.tabFrame('caseHistory');\" value='".$LANG['processmaker']['item']['case']['viewcasehistory']."'>";
            echo "</td>";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='submit' onclick=\"javascript:Actions.tabFrame('caseChangeLogHistory');\" value='".$LANG['processmaker']['item']['case']['viewcasechangeloghistory']."'>";
            echo "</td>";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='submit' onclick=\"javascript:Actions.tabFrame('historyDynaformPage');\" value='".$LANG['processmaker']['item']['case']['viewdynaforms']."'>";
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

            // processmakertabpaneltable  is used to align the tabs
            echo "<table id=processmakertabpaneltable style='margin-bottom: 0px; width:100%;' class='tab_cadre_fixe'>";
            echo Html::scriptBlock("$('#processmakertabpaneltable').css('max-width', 'none');");
            echo "<tr><td>";

            //
            // Processmaker tab panels
            // need to have a global variable which contains tab id
            // used only one time for activated panel
            $arrayProcessmakerTabPanel = array();
            echo "<div id=processmakertabpanel >";
            // first define tabs
            echo "    <ul>";
            //echo "            <li><a href='#tabs-1'>Nunc tincidunt</a></li>";
            $arrayProcessmakerTabPanel[] = "tabs-1";
            if ($pmCaseUser) {
               foreach ($caseInfo->currentUsers as $caseUser) {
                  echo "<li><a href='#task-".$caseUser->delIndex."'>".($caseUser->userId != $GLPICurrentPMUserId?"<i><sub>".$LANG['processmaker']['item']['task']['task'].$caseUser->taskName."</sub></i>":$LANG['processmaker']['item']['task']['task'].$caseUser->taskName)."</a></li>";
                  $arrayProcessmakerTabPanel[] = "task-".$caseUser->delIndex;
               }
            } else {
               // no user means CANCELLED or COMPLETED
               // then create artificial panel to host case infos
               echo "<li><a href='#caseInfo'>".$LANG['processmaker']['item']['case']['caseinfo']."</a></li>";
               $arrayProcessmakerTabPanel[] = "caseInfo";
            }
            echo "</ul>";

            // second define panels
            //echo "<div id='tabs-1'>
            //            <p>Proin elit arcu, rutrum commodo, vehicula tempus, commodo a, risus. Curabitur nec arcu. Donec sollicitudin mi sit amet mauris. Nam elementum quam ullamcorper ante. Etiam aliquet massa et lorem. Mauris dapibus lacus auctor risus. Aenean tempor ullamcorper leo. Vivamus sed magna quis ligula eleifend adipiscing. Duis orci. Aliquam sodales tortor vitae ipsum. Aliquam nulla. Duis aliquam molestie erat. Ut et mauris vel pede varius sollicitudin. Sed ut dolor nec orci tincidunt interdum. Phasellus ipsum. Nunc tristique tempus lectus.</p>
            //      </div>";
            if ($pmCaseUser) {
               $csrf = Session::getNewCSRFToken();
               foreach ($caseInfo->currentUsers as $caseUser) {
                  // for each task, if task is to be claimed, we need to verify that current user can claim it by checking if he/she is in the group assigned to the task
                  $hide_claim_button=false; // by default
                  if (!$caseUser->userId) {
                     // current task is to claimed
                     // get task user list
                     $query = "SELECT items_id, itemtype FROM glpi_plugin_processmaker_tasks WHERE case_id = '".$caseInfo->caseId."' AND del_index =".$caseUser->delIndex;
                     foreach ($DB->request($query) as $row) {
                        // normally there is only one task
                        $task = getItemForItemtype( $row['itemtype'] );
                        $task->getFromDB( $row['items_id'] );
                        // check if this group can be found in the current user's groups
                        if (!isset($_SESSION['glpigroups']) || !in_array( $task->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
                           $hide_claim_button=true;
                        }
                     }
                  }
                  echo "<div id='task-".$caseUser->delIndex."'>";
                  // to load users for task re-assign only when task is not to be 'claimed'
                  if ($caseUser->userId) {
                     echo "<div class='tab_bg_2' id='divUsers-".$caseUser->delIndex."' >Loading...</div>";
                     echo "<script>$('#divUsers-".$caseUser->delIndex."').load( '".$CFG_GLPI["root_doc"]."/plugins/processmaker/ajax/task_users.php?caseId=".$caseInfo->caseId."&itemId=".$item_id."&itemType=".$item_type."&userId=".$caseUser->userId."&taskId=".$caseUser->taskId."&delIndex=".$caseUser->delIndex."&delThread=".$caseUser->delThread."&rand=$rand' ); </script>";
                  }
                  echo "<iframe id='caseiframe-task-".$caseUser->delIndex."' onload='onTaskFrameLoad( event, ".$caseUser->delIndex.", ".($hide_claim_button?"true":"false").", \"$csrf\" );' style='border:none;' class='tab_bg_2' width='100%' src='";
                  $url = $myProcessMaker->serverURL."/cases/cases_Open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&DEL_INDEX=".$caseUser->delIndex."&action=TO_DO";
                  //if( $caseUser->userId == $GLPICurrentPMUserId || $caseUser->userId == '' ) {
                      echo $url;
                  //} else {
                  //    echo $CFG_GLPI["root_doc"]."/plugins/processmaker/ajax/task_resume.php?username=".urlencode( $caseUser->userName )."&taskname=".urlencode( $caseUser->taskName )."&url=".urlencode( $url ) ;
                  //}
                  echo "&rand=$rand'></iframe></div>";
               }
            } else {
                // no user means CANCELLED or COMPLETED
                // then create artificial panel to host case infos
                echo "<div id='caseInfo'>";
                $url = $myProcessMaker->serverURL."/cases/cases_Open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&action=TO_DO";
                echo "<iframe id=\"caseiframe-caseInfo\" onload=\"onOtherFrameLoad( 'caseInfo', 'caseiframe-caseInfo', 'body' );\" style=\"border:none;\" class=\"tab_bg_2\" width=\"100%\" src=\"$url&rand=$rand\"></iframe></div>";
            }
            echo "</div>";
            // end of tabs/panels

            echo "</td></tr>";
            echo "<tr class='tab_bg_1' ><td  colspan=4 >";
            if ($pmCaseUser) {
                $activePanel = 'task-'.$pmCaseUser->delIndex;
            } else {
                $activePanel = 'caseInfo';
            }
            $caseMapUrl = $myProcessMaker->serverURL.($project_type=='bpmn' ? "/designer?prj_uid=".$caseInfo->processId."&prj_readonly=true&app_uid=".$caseInfo->caseId : "/cases/ajaxListener?action=processMap&rand=$rand");
            echo "<script>
                function addTabPanel( name, title, html ){
                    //debugger ;
                    if( !$('#processmakertabpanel')[0].children[name] ) { // panel is not yet existing, create one
                        //var num_tabs = $('#processmakertabpanel ul li').length ;
                        $('#processmakertabpanel ul').append( '<li><a href=\'#' + name + '\'>' + title + '</a></li>' );
                        //debugger ;
                        $('#processmakertabpanel').append( '<div id=\'' + name + '\'>' + html + '</div>');
                        $('#processmakertabpanel').tabs('refresh'); // to show the panel
                    }
                    var tabIndex = $('#processmakertabpanel a[href=\"#'+name+'\"]').parent().index();
                    $('#processmakertabpanel').tabs( 'option', 'active', tabIndex) ; // to activate it
                    //$('#processmakertabpanel').tabs( 'option', 'collapsible', true );
                }
                var historyGridListChangeLogGlobal = { viewIdHistory: '', viewIdDin: '', viewDynaformName: '', idHistory: '' } ;
                var ActionTabFrameGlobal = { tabData: '', tabName: '', tabTitle: '' } ;

                var Actions = { tabFrame: function( actionToDo ) {
                                                       // debugger ;
                            if( actionToDo == 'caseMap' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casemap']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", ".($project_type=='bpmn' ? "true" : "false" )." );\' width=\'100%\' src=\'$caseMapUrl\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'caseHistory' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casehistory']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/ajaxListener?action=caseHistory&rand=$rand\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'caseChangeLogHistory' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casechangeloghistory']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/ajaxListener?action=changeLogHistory&rand=$rand\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'dynaformViewFromHistory' ) {
                                actionToDo = 'dynaformChangeLogViewHistory_' + historyGridListChangeLogGlobal.viewIdDin + historyGridListChangeLogGlobal.dynDate.replace(/ /g, '_').replace(/:/g, '-') ;
                                ajaxResponse = $.parseJSON(historyGridListChangeLogGlobal.viewDynaformName);
                                addTabPanel( actionToDo,
                                        ajaxResponse.dynTitle + ' <sup>(' + historyGridListChangeLogGlobal.dynDate + ')</sup>',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/ajaxListener?action=dynaformViewFromHistory&DYN_UID=' + historyGridListChangeLogGlobal.viewIdDin + \"&HISTORY_ID=\" + historyGridListChangeLogGlobal.viewIdHistory + '&rand=$rand\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'historyDynaformPage' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['dynaforms']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^changeLog' ) == 0 ) {
                                actionToDo = 'changeLog' ;
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['changelog']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/ajaxListener?action=changeLogTab&idHistory=' + historyGridListChangeLogGlobal.idHistory + '&rand=$rand\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) {
                                actionToDo = actionToDo.replace('_', '$') ;
                                    var act = actionToDo.replace( '$', '&DYN_UID=') ;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^historyDynaformGridHistory' ) == 0) {
                                var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                                    var act = 'showDynaformListHistory&PRO_UID=' + ajaxResponse.PRO_UID + '&APP_UID=' + ajaxResponse.APP_UID + '&TAS_UID=-1&DYN_UID=' + ajaxResponse.DYN_UID;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^dynaformChangeLogViewHistory' ) == 0) {
                                var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                                actionToDo='dynaformChangeLogViewHistory' + ajaxResponse.dynUID + ajaxResponse.dynDate ;
                                //actionToDo = actionToDo.replace(' ', '_').replace(':', '-');
                                    var act = 'dynaformChangeLogViewHistory&DYN_UID=' + ajaxResponse.dynUID + '&HISTORY_ID=' + ajaxResponse.tablename;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand\' ></iframe>'
                                        );
                           }
                        }
                    } ;

                $(function() {
//debugger;
                    $('#processmakertabpanel').tabs( {active: ".array_search( $activePanel, $arrayProcessmakerTabPanel )."});
                    //$('#processmakertabpanel').scrollabletabs();
                    //$('#processmakertabpanel').position({
                    //  my: 'left top',
                    //  at: 'left top',
                    //  of: '#processmakertabpaneltable'
                    //});
                    $('#processmakertabpanel').removeClass( 'ui-tabs' ) ;
                    //debugger ;
                    $('#processmakertabpanel').tabs({activate: function (event, ui) {
                                                            try {
                                                                //debugger;
                                                                if( typeof onOtherFrameLoad == 'function' )
                                            var newPanel = ui.newPanel.selector.replace('#', '') ;
                                            var panelType = newPanel.split( '-' )[ 0 ].split( '$' )[ 0 ].split( '_' ) ;
                                            var searchTag = '' ;
                                            switch( panelType[0] ) {
                                                case 'task' :
                                                    searchTag = 'table' ;
                                                    break ;

                                                case 'historyDynaformGridPreview' :
                                                case 'dynaformChangeLogViewHistory' :
                                                    searchTag = 'form' ;
                                                    break ;

                                                case 'caseInfo' :
                                                case 'caseMap' :
                                                case 'caseHistory' :
                                                case 'changeLog' :
                                                case 'historyDynaformPage' :
                                                case 'dynaformChangeLogViewHistory' :
                                                case 'historyDynaformGridHistory' :
                                                default :
                                                    searchTag = 'body' ;
                                                    break ;
                                                                }
                                            onOtherFrameLoad( newPanel, 'caseiframe-' + newPanel, searchTag, ".($project_type=='bpmn' ? "true" : "false" )."  ) ;
                                                            } catch( evt ) {
                                                                //debugger;
                                                            }
                                                        }
                    });

            ";

            echo "});

            ";

            echo    "</script>";

            //////echo "<iframe id='caseiframe' onload='onGLPILoadFrame( event ) ;' height='1080px' style='border:none;' width='100%' src='".$myProcessMaker->serverURL."/cases/open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&rand=$rand' >" ;
            //////echo "</iframe>";
            echo "</td></tr>";

         } else {

            //********************************
            // no running case for this ticket
            // propose to start one
            //********************************
            echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['nocase'];

            // check if item is not solved nor closed
            if ($item->fields['status'] != 'solved' && $item->fields['status'] != 'closed' && $_SESSION['glpiactiveprofile']['interface'] != 'helpdesk') {
               // propose case start
               echo "&nbsp;-&nbsp;".$LANG['processmaker']['item']['startone'];
               echo "</th></tr>";

               echo "<tr class='tab_bg_2'><td class='tab_bg_2' colspan='1'>";
               echo $LANG['processmaker']['item']['selectprocess']."&nbsp;";
               echo "<input type='hidden' name='action' value='newcase'>";
               echo "<input type='hidden' name='id' value='$item_id'>";
               echo "<input type='hidden' name='itemtype' value='$item_type'>";
               //                Dropdown::show('PluginProcessmakerProcessmaker', array( 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_process_id', 'condition' => "is_active=1")); // condition is used to prevent start of none-active cases
               PluginProcessmakerProcess::dropdown(array( 'value' => 0, 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_process_id', 'condition' => "is_active=1"));
               echo "</td><td class='tab_bg_2'>";
               echo "<input type='submit' name='additem' value='".$LANG['processmaker']['item']['start']."' class='submit'>";
               echo "</td></tr>";
            } else {
               echo "</th></tr>";
            }
         }

         echo "</table>";
         Html::closeForm(true );
         //echo "</form>";

      } else {
         // under maintenance
         echo $LANG['processmaker']['config']['undermaintenance'];
      }

      return true;
   }

   /**
   * Summary of deleteTasks
   * will delete all tasks associated with this case from the item
   * @return true if tasks have been deleted from associated item and from case table
   */
   private function deleteTasks( ) {
      global $DB;
         $ret = false;

         $query = "DELETE from glpi_".$this->fields['itemtype']."tasks where id in (select items_id from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."')";
      if ($DB->query( $query )) {
         $query = "DELETE from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."'";
         if ($DB->query( $query )) {
            $ret = true;
         }
      }
      return $ret;
   }


    /**
     * Summary of deleteCase
     * will delete case and all tasks associated with this case from the item
     * @return true if case and tasks have been deleted from associated item and from case table
     */
   function deleteCase( ) {
      global $DB;
      $ret = false;

      if ($this->deleteTasks()) {
         if ($this->deleteFromDB( )) {
            $ret = true;
         }
      }
      return $ret;
   }



    /**
     * Summary of cancelTasks
     * will mark as information all to_do tasks
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if tasks have been deleted from associated item and from case table
     */
   private function cancelTasks( ) {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         $query = "UPDATE glpi_".$this->fields['itemtype']."tasks SET state=0,users_id_tech=0,begin=NULL,end=NULL  WHERE state=1 AND id in (select items_id from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."')";
         if ($DB->query( $query )) {
            $ret = true;
         }
      }
      return $ret;
   }



    /**
     * Summary of cancelCase
     * will cancel case and mark 'to_do' tasks associated with this case from the item as information
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if case and tasks have been cancelled or marked from associated item and from case table
     */
   function cancelCase( ) {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         if ($this->cancelTasks()) {
            if ($this->update( array( 'id' => $this->getID(), 'case_status' => 'CANCELLED' ) )) {
                $ret=true;
            }
         }
      }

      return $ret;
   }

    /**
     * Summary of canSolve
     * To know if a Ticket (Problem or Change) can be solved
     * i.e. the case permits solving of item
     * @param mixed $item is the item
     * @return bool true to permit solve, false otherwise
     */
   public static function canSolve ($item) {
      $myCase = new self;
      if ($myCase->getCaseFromItemTypeAndItemId( $item['item']->getType(), $item['item']->getID() )) {
         $pmVar = $myCase->getVariables( array( 'GLPI_ITEM_CAN_BE_SOLVED' ) );
         if ($myCase->fields['case_status'] != 'COMPLETED' && $myCase->fields['case_status'] != 'CANCELLED' && (!isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) || $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1)) {
            // then item can't be solved
            return false;
         }
      }
      return true;
   }

    /**
     * Summary of getToDoTasks
     * @param mixed $parm is a Ticket, a Problem or a Change
     * @return array list of tasks with status 'to do' for case associated with item
     */
   public static function getToDoTasks($parm) {
      $myCase = new self;

      if ($myCase->getCaseFromItemTypeAndItemId( $parm->getType(), $parm->getID() )) {
         return PluginProcessmakerTask::getToDoTasks( $myCase->getID(), $parm->getType()."Task" );
      }
      return array();
   }
}