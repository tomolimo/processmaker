<?php

/**
 * ticketcase short summary.
 *
 * ticketcase description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCases extends CommonDBTM {


    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
        global $LANG;
        
        $item_id = $item->getID() ;
        $item_type = $item->getType() ;
        if( self::getCaseFromItemTypeAndItemId($item_type, $item_id ) ){ 
            return array( 'processmakercases' => $LANG['processmaker']['item']['tab']."<sup>(".$this->fields['case_status'].")</sup>" );
        } else       
            return array( 'processmakercases' => $LANG['processmaker']['item']['tab'] );
        
    }
    
    /**
     * Summary of getCaseIdFromItemTypeAndItemId
     * @param mixed $itemType 
     * @param mixed $itemId 
     * @return mixed
     */
    static function getCaseIdFromItemTypeAndItemId( $itemType, $itemId) {
        global $DB;
        
        $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE items_id=$itemId and itemtype='$itemType';" ;
	    if( ($res = $DB->query($query) )  && $DB->numrows($res) > 0) {
                  $row = $DB->fetch_array($res);
                  return $row['id'] ;
        }
        return false ;
    }
    
    
    /**
     * Summary of getCaseFromItemTypeAndItemId
     * @param mixed $itemType 
     * @param mixed $itemId 
     * @return mixed: returns false when there is no case associated with the item, else fills in the item fields from DB, and returns true
     */
    function getCaseFromItemTypeAndItemId($itemType, $itemId) {
        
        if( $caseId = self::getCaseIdFromItemTypeAndItemId( $itemType, $itemId) ) 
              return $this->getFromDB( $caseId ) ;
          
        return false ;
    }
    
    /**
     * Summary of getFromDB
     * @param mixed $ID 
     * @return mixed
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

        if ( ($result = $DB->query($query))  && $DB->numrows($result)==1) {
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
     * @return an associative array (variable_name => value). The returned array can be empty if requested variables are not found.
     */
    function getVariables( $vars = array() ) {
        global $DB ;
        
        $locVars = array( ) ;
        $app_data = array() ; // by default
        
        $caseId = $this->getID() ;
        
        $query = "select APP_DATA from wf_workflow.application where APP_UID='$caseId';" ;
        
        if( ($res = $DB->query($query)) &&  $DB->numrows($res) > 0) {
            $row = $DB->fetch_assoc($res);
            $app_data = unserialize($row['APP_DATA'] ) ;            
            $locVars = array_intersect_key( $app_data, array_flip($vars) ) ;
        }
        
        return $locVars ;        
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
         global $DB ;
        
        $variablesSent = false ; // be default
        $app_data = array() ; // by default

        $caseId = $this->getID() ;
        
        $query = "select APP_DATA from wf_workflow.application where APP_UID='$caseId';" ;
        
        
        if( ($res = $DB->query($query)) && $DB->numrows($res) > 0) {
            $row = $DB->fetch_assoc($res);
            $app_data = unserialize($row['APP_DATA'] ) ;            
        }
        
        $app_data = array_replace( $app_data, $vars ) ;
        $serialized = serialize( $app_data ) ;
        
        $query = "update wf_workflow.application set APP_DATA='$serialized' where APP_UID='$caseId';" ;
        $res = $DB->query( $query ) ;
        
        if( $DB->affected_rows() == 1 ) {
            $variablesSent = true ;            
        }
                
        return $variablesSent ;          
    }

    
    /**
     * Summary of displayTabContentForItem
     * @param CommonGLPI $item 
     * @param mixed $tabnum 
     * @param mixed $withtemplate 
     * @return mixed
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
        global $LANG, $DB;
        
        $item_id = $item->getID() ;
        $item_type = $item->getType() ;
        ////retrieve container for current tab
        //$container = new self;
        //$found_c = $container->find("`itemtype` = '$item_type' AND `items_id` = $item_id ");
        
        //echo "Show the frame";                
        $rand = rand();
        echo "<form style='margin-bottom: 0px' name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
        echo "<div class='center'><table style='margin-bottom: 0px' class='tab_cadre_fixehov'>";
        echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['tab']."</th></tr>";
        $pmCaseUser = false ; // initial value: no user
        // first search for the case
        if( self::getCaseIdFromItemTypeAndItemId($item_type, $item_id ) ){  
            $myProcessMaker = new PluginProcessmakerProcessmaker( ) ;
            $myProcessMaker->login(); 
            $caseInfo = $myProcessMaker->getCaseFromItem( $item_type, $item_id ) ;
            if( $caseInfo->caseStatus != 'CANCELLED' && $caseInfo->caseStatus != 'COMPLETED' ) {
                // need to get info on the thread of the GLPI current user
                // we must retreive currentGLPI user from this array
                $GLPICurrentPMUserId = PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() ) ; 
                $pmCaseUser = $caseInfo->currentUsers[0] ; // by default currently manage only one task at a time, must define tab management for several tasks
                foreach( $caseInfo->currentUsers as $caseUser) {
                    if( $caseUser->userId == $GLPICurrentPMUserId ){
                        $pmCaseUser = $caseUser ;
                        break ;
                    } 
                }
                //if( $pmCaseUser->delThreadStatus == 'PAUSE' ) {
                //    // means the case itself may not be PAUSED, but task assigned to current GLPI user is paused...
                //    // then forced PAUSE view for this thread
                //    // and give possibility to unpause it
                //    $caseInfo->caseStatus = 'PAUSED' ;
                //}
            } 
            $locDelIndex = 1 ; // by default            
            switch ( $caseInfo->caseStatus ) {
                case "CANCELLED"  :
                    echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['cancelledcase']."</th></tr>";
                    $paramsURL = "DEL_INDEX=1" ;
//                    echo "<tr class='tab_bg_1' ><td id='GLPI-PM-DEL_INDEX' ><script>var GLPI_DEL_INDEX = 1; </script></td></tr>" ;
                    break;
                //case 'PAUSED' :
                //    // we need to add a button to unpause the case
                //    //echo "<input type='hidden' name='id' value='$item_id'>";
                //    //echo "<input type='hidden' name='itemtype' value='$item_type'>";
                //    //echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";
                //    //echo "<input type='hidden' name='plugin_processmaker_delIndex' value='".$pmCaseUser->delIndex."'>";
                //    //echo "<input type='hidden' name='plugin_processmaker_userId' value='".$pmCaseUser->userId."'>";
                //    echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['pausedtask']."</th><th>";
                //    echo "<input type='submit' name='unpausecase' value='".$LANG['processmaker']['item']['unpause']."' class='submit'>";
                //    echo "</th></tr>";
                            
                case "DRAFT" :
                case "TO_DO" : 
                    
                    $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus ;   
                    $locDelIndex = $pmCaseUser->delIndex ; 
                    if( $pmCaseUser->userId != '' ) {
                        echo "<tr class='tab_bg_1'>" ;
                        
                        if( $caseInfo->caseStatus == "DRAFT" ) {
                            // then propose a button to delete case
                            echo "<td class='tab_bg_2'>";
                            echo $LANG['processmaker']['item']['deletecase'] ;
                            echo "</td><td class='tab_bg_2'>";
                            echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                            echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";
                            
                            echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttondeletecaseconfirmation']."\"); cancelMyMask = !ret ; return ret;'  type='submit' name='delete' value='".$LANG['processmaker']['item']['buttondeletecase']."' class='submit' >";
                            
                            echo "</td>";
                            
                        } elseif( $GLPICurrentPMUserId == $pmCaseUser->userId) {
                            // then propose a button to cancel case only when assigned user is == to glpi  current user
                            echo "<td class='tab_bg_2' >";
                            echo $LANG['processmaker']['item']['cancelcase'] ;
                            echo "</td><td class='tab_bg_2'>";
                            echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                            echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";
                            //echo "<input type='hidden' name='plugin_processmaker_delIndex' value='".$pmCaseUser->delIndex."'>";
                            //echo "<input type='hidden' name='plugin_processmaker_userId' value='".$pmCaseUser->userId."'>";
                            echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttoncancelcaseconfirmation']."\") ;  cancelMyMask = !ret ;  return ret;'   type='submit' name='cancel' value='".$LANG['processmaker']['item']['buttoncancelcase']."' class='submit'>";
                            echo "</td>";
                        }
                            
                        echo "<td class='tab_bg_2'  colspan='1'>";
                        echo "</form>";
                        
                        echo "</td></tr>";
                    }
                    
                    
                    break ;
                case "COMPLETED" :
                    echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['completedcase']."</th></tr>";
                    $paramsURL = "DEL_INDEX=" ; // DEL_INDEX is not set to tell PM to show the current task i.e.: the last one
                    break ;
            }
                        
            // then propose a button to view case history 
            echo "<tr class='tab_bg_1' >" ; 
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='button' onclick=\"javascript:Actions.tabFrame('caseMap');\" value='".$LANG['processmaker']['item']['case']['viewcasemap']."'>";
            echo "</td>";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='button' onclick=\"javascript:Actions.tabFrame('caseHistory');\" value='".$LANG['processmaker']['item']['case']['viewcasehistory']."'>";
            echo "</td>";
            echo "<td class='tab_bg_2' colspan='1'>";
            echo "<input type='button' class='button' onclick=\"javascript:Actions.tabFrame('historyDynaformPage');\" value='".$LANG['processmaker']['item']['case']['viewdynaforms']."'>";
            echo "</td>";
            echo "</tr>";
            
            echo "<tr class='tab_bg_1' ><td class='tab_bg_2' colspan=4>" ; 
            echo "<div id=processmakertabpanel></div>" ;
            echo "</td></tr>";
            echo "<tr class='tab_bg_1' ><td class='tab_bg_2' colspan=4 >" ; 

            
            echo "<script type='text/javascript' src='".GLPI_ROOT."/plugins/processmaker/js/cases.js'></script>" ; //?rand=$rand'

            echo "<script>
                var historyGridListChangeLogGlobal = { viewIdHistory: '', viewIdDin: '', viewDynaformName: '', idHistory: '' } ;
                var ActionTabFrameGlobal = { tabData: '', tabName: '', tabTitle: '' } ;
                
                var Actions = { tabFrame: function( actionToDo ) { 
                           if( actionToDo == 'caseMap' ) {
                               if( !tabs.items.containsKey( 'caseMap' ) ) {
                                    tabs.add( { 
                                        title: '".$LANG['processmaker']['item']['case']['casemap']."',   
                                        id: 'caseMap',
                                        closable: true,
                                        listeners: { activate: function () {
                                                       // debugger ;
                                                        onOtherFrameLoad( 'caseMap', 'caseMapFrame', 'body' ) ;
                                                        }
                                                    },
                                        html:    \"<iframe id='caseMapFrame' \" + 
                                                    \"style='border:none;' \" + 
                                                    \"onload='onOtherFrameLoad( \\\"caseMap\\\", \\\"caseMapFrame\\\", \\\"body\\\" );' \" +
                                                    \"width='100%' \" +
                                                    \"src='".$myProcessMaker->serverURL."/cases/ajaxListener?action=processMap&rand=$rand' >\" +
                                           \"</iframe>\" 
                                        } ) ; 
                                    tabs.doLayout( ) ;
                               } 
                               tabs.setActiveTab( 'caseMap' ) ; 
                           }
                           else
                           if( actionToDo == 'caseHistory' ) {
                               if( !tabs.items.containsKey( 'caseHistory' ) ) {
                                    tabs.add( { 
                                        title: '".$LANG['processmaker']['item']['case']['casehistory']."',   
                                        id: 'caseHistory',
                                        closable: true,
                                        listeners: { activate: function () {
                                                        //debugger ;
                                                        onOtherFrameLoad( 'caseHistory', 'caseHistoryFrame', 'body' ) ;
                                                        }
                                                    },
                                        html:    \"<iframe id='caseHistoryFrame' \" + 
                                                    \"height='500px' \" +
                                                    \"style='border:none;' \" + 
                                                    \"onload='onOtherFrameLoad( \\\"caseHistory\\\", \\\"caseHistoryFrame\\\", \\\"body\\\" );' \" +
                                                    \"width='100%' \" +
                                                    \"src='".$myProcessMaker->serverURL."/cases/ajaxListener?action=caseHistory&rand=$rand' >\" +
                                           \"</iframe>\" 
                                        } ) ; 
                                    tabs.doLayout( ) ;
                               }
                               tabs.setActiveTab( 'caseHistory' ) ; 
                           }
                           else
                           if( actionToDo == 'dynaformViewFromHistory' ) {
                               //debugger;                               
                               actionToDo = 'dynaformChangeLogViewHistory' + historyGridListChangeLogGlobal.viewIdDin + historyGridListChangeLogGlobal.dynDate ;
                               if( !tabs.items.containsKey( actionToDo ) ) {
                                    ajaxResponse = Ext.util.JSON.decode(historyGridListChangeLogGlobal.viewDynaformName); 
                                    tabs.add( { 
                                        title: ajaxResponse.dynTitle + ' <sup>(' + historyGridListChangeLogGlobal.dynDate + ')</sup>',   
                                        id: actionToDo,
                                        closable: true,
                                        listeners: { activate: function () {
                                                       // debugger ;
                                                        onOtherFrameLoad( actionToDo, actionToDo+'Frame', 'body' ) ;
                                                        }
                                                    },
                                        html: \"<iframe id='\"+actionToDo+\"Frame' \" + 
                                                \"style='border:none;' \" + 
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad( \\\"\"+actionToDo+\"\\\", \\\"\"+actionToDo+\"Frame\\\", \\\"body\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/ajaxListener?action=dynaformViewFromHistory&DYN_UID=\" + historyGridListChangeLogGlobal.viewIdDin + \"&HISTORY_ID=\" + historyGridListChangeLogGlobal.viewIdHistory + \"&rand=$rand' >\" +
                                        \"</iframe>\"
                                        } ) ; 
                                    tabs.doLayout( ) ;
                               }
                               tabs.setActiveTab( actionToDo ) ; 
                           }
                           else   
                           if( actionToDo == 'historyDynaformPage' ) {
                               if( !tabs.items.containsKey( 'historyDynaformPage' ) ) {
                                    tabs.add( { 
                                        title: '".$LANG['processmaker']['item']['case']['dynaforms']."',   
                                        id: 'historyDynaformPage',
                                        closable: true,
                                        listeners: { activate: function () {
                                                        //debugger ;
                                                        onOtherFrameLoad( 'historyDynaformPage', 'historyDynaformPageFrame', 'body' ) ;
                                                        }
                                                    },                                        
                                        html:    \"<iframe id='historyDynaformPageFrame' \" + 
                                                    \"onload='onOtherFrameLoad( \\\"\"+actionToDo+\"\\\", \\\"\"+actionToDo+\"Frame\\\", \\\"body\\\" );' \" +
                                                    \"style='border:none;' \" + 
                                                    \"width='100%' \" +
                                                    \"src='".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand' >\" +
                                           \"</iframe>\" 
                                        } ) ; 
                                    tabs.doLayout( ) ;
                                }
                               tabs.setActiveTab( 'historyDynaformPage' ) ; 
                           } 
                           else 
                            if( actionToDo.search( '^changeLog' ) == 0 ) {
                                if( !tabs.items.containsKey( 'changeLog' ) ) {
                                    tabs.add( { 
                                        title: '".$LANG['processmaker']['item']['case']['changelog']."',   
                                        id: 'changeLog',
                                        closable: true,
                                        listeners: { activate: function () {
                                                        //debugger ;
                                                        onOtherFrameLoad( 'changeLog', 'changeLogFrame', 'body' ) ;
                                                        }
                                                    },       html: \"<iframe id='changeLogFrame' \" + 
                                                \"style='border:none;' \" + 
                                                \"height='500px' \" +
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad( \\\"changeLog\\\", \\\"changeLogFrame\\\", \\\"body\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/ajaxListener?action=changeLogTab&idHistory=\" + historyGridListChangeLogGlobal.idHistory + \"&rand=$rand' >\" +
                                        \"</iframe>\"
                                        } ) ; 
                                    tabs.doLayout( ) ;
                                }
                                tabs.setActiveTab( 'changeLog' ) ; 
                           } 
                           else 
                            if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) { 
                                actionToDo = actionToDo.replace('_', '$') ;
                                if( !tabs.items.containsKey( actionToDo ) ) {
                                    var act = actionToDo.replace( '$', '&DYN_UID=') ;
                                    tabs.add( { 
                                        title: ActionTabFrameGlobal.tabTitle,   
                                        id: actionToDo,
                                        closable: true,
                                        listeners: { activate: function () {
                                                       // debugger ;
                                                        onOtherFrameLoad( actionToDo, actionToDo+'Frame', 'form' ) ;
                                                        }
                                                    },
                                        html: \"<iframe id='\"+actionToDo+\"Frame' \" + 
                                                \"style='border:none;' \" + 
                                                \"height='500px' \" +
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad( \\\"\"+actionToDo+\"\\\", \\\"\"+actionToDo+\"Frame\\\", \\\"form\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=\" + act + \"&rand=$rand' >\" +
                                        \"</iframe>\"
                                        } ) ; 
                                    tabs.doLayout( ) ;
                                }
                               tabs.setActiveTab( actionToDo ) ; 
                           }
                           else 
                            if( actionToDo.search( '^historyDynaformGridHistory' ) == 0) { 
                                if( !tabs.items.containsKey( actionToDo ) ) {
                                    var ajaxResponse = Ext.util.JSON.decode(ActionTabFrameGlobal.tabData); 
                                    var act = 'showDynaformListHistory&PRO_UID=' + ajaxResponse.PRO_UID + '&APP_UID=' + ajaxResponse.APP_UID + '&TAS_UID=-1&DYN_UID=' + ajaxResponse.DYN_UID;
                                    tabs.add( { 
                                        title: ActionTabFrameGlobal.tabTitle,   
                                        id: actionToDo,
                                        closable: true,
                                        listeners: { activate: function () {
                                                       // debugger ;
                                                        onOtherFrameLoad( actionToDo, actionToDo+'Frame', 'body' ) ;
                                                        }
                                                    },
                                        html: \"<iframe id='\"+actionToDo+\"Frame' \" + 
                                                \"style='border:none;' \" + 
                                                \"height='500px' \" +
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad(  \\\"\"+actionToDo+\"\\\", \\\"\"+actionToDo+\"Frame\\\", \\\"body\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=\" + act + \"&rand=$rand' >\" +
                                        \"</iframe>\"
                                        } ) ; 
                                    tabs.doLayout( ) ;
                                }
                               tabs.setActiveTab( actionToDo ) ; 
                           }
                           else 
                            if( actionToDo.search( '^dynaformChangeLogViewHistory' ) == 0) { 
                                var ajaxResponse = Ext.util.JSON.decode(ActionTabFrameGlobal.tabData); 
                                actionToDo='dynaformChangeLogViewHistory' + ajaxResponse.dynUID + ajaxResponse.dynDate ;
                                if( !tabs.items.containsKey( actionToDo ) ) {
                                    var act = 'dynaformChangeLogViewHistory&DYN_UID=' + ajaxResponse.dynUID + '&HISTORY_ID=' + ajaxResponse.tablename;
                                    tabs.add( { 
                                        title: ajaxResponse.dynTitle + ' <sup>(' + ajaxResponse.dynDate + ')</sup>',   
                                        id: actionToDo,
                                        closable: true,
                                        listeners: { activate: function () {
                                                       // debugger ;
                                                        onOtherFrameLoad( actionToDo, actionToDo+'Frame', 'form' ) ;
                                                        }
                                                    },
                                        html: \"<iframe id='\"+actionToDo+\"Frame' \" + 
                                                \"style='border:none;' \" + 
                                                \"height='500px' \" +
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad(  \\\"\"+actionToDo+\"\\\", \\\"\"+actionToDo+\"Frame\\\", \\\"form\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=\" + act + \"&rand=$rand' >\" +
                                        \"</iframe>\"
                                        } ) ; 
                                    tabs.doLayout( ) ;
                                }
                               tabs.setActiveTab( actionToDo ) ; 
                           }
                        }
                    } ;
                //debugger;  

                var tabs = new Ext.TabPanel({
                        renderTo: 'processmakertabpanel',
                        width: 930,
                        deferredRender: false,
                        enableTabScroll: true,
                        items: [";
            
            if( $pmCaseUser ) {
                $first = true ;
                foreach($caseInfo->currentUsers as $caseUser) {
                    if( !$first ) echo "," ;
                    echo "{                            
                                title: ".($caseUser->userId != $GLPICurrentPMUserId?"'<i><sub>Task: ".$caseUser->taskName."</sub></i>'":"'Task: ".$caseUser->taskName."'").",
                                id: 'task-".$caseUser->delIndex."',
                                listeners: { activate: function () {
                                                            try {
                                                                //debugger;
                                                                if( typeof onOtherFrameLoad == 'function' ) 
                                                                    onOtherFrameLoad( 'task-".$caseUser->delIndex."', 'caseiframe-".$caseUser->delIndex."', 'table' ) ;
                                                                // to load users for re-assign only when task is not to be 'claimed'
                                                                if( ".($caseUser->userId?"true":"false")." && Ext.get('divUsers-".$caseUser->delIndex."').dom.innerHTML == '' ) {
                                                                    Ext.get('divUsers-".$caseUser->delIndex."').load({
                                                                        url : '".GLPI_ROOT."/plugins/processmaker/ajax/task_users.php',
                                                                        scripts: true,
                                                                        params: 'caseId=".$caseInfo->caseId."&itemId=".$item_id."&itemType=".$item_type."&userId=".$caseUser->userId."&taskId=".$caseUser->taskId."&delIndex=".$caseUser->delIndex."&delThread=".$caseUser->delThread."&rand=$rand',
                                                                        });
                                                                }
                                                            } catch( evt ) {
                                                                //debugger;
                                                            }
                                                        } 
                                            } ,
                                html: \"<div class='tab_bg_2' id='divUsers-".$caseUser->delIndex."' >\" + 
                                      \"</div>\" + 
                                      \"<iframe id='caseiframe-".$caseUser->delIndex."' \" + 
                                                \"onload='onTaskFrameLoad( ".$caseUser->delIndex." );' \" +
                                                \"style='border:none;' \" + 
                                                \"class='tab_bg_2' \" +
                                                \"width='100%' \" +
                                                \"src='".($caseUser->userId == $GLPICurrentPMUserId || $caseUser->userId == ''?
                                                            $myProcessMaker->serverURL."/cases/cases_open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&DEL_INDEX=".$caseUser->delIndex."&action=TO_DO"
                                                            :
                                                            GLPI_ROOT."/plugins/processmaker/ajax/task_resume.php?username=".urlencode( $caseUser->userName )."&taskname=".urlencode( $caseUser->taskName )."&url=".urlencode( $myProcessMaker->serverURL."/cases/cases_open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&DEL_INDEX=".$caseUser->delIndex."&action=TO_DO" )
                                                            )."&rand=$rand'>\" +
                                       \"</iframe>\"
                            }";
                    $first = false ;
                }
            } else {
                // no user means CANCELLED or COMPLETED
                // then create artificial panel to host case infos
                echo "{                            
                                title: '".$LANG['processmaker']['item']['case']['caseinfo']."',
                                id: 'caseInfo',
                                listeners: { activate: function () {
                                                // debugger ;
                                                if( typeof onOtherFrameLoad == 'function' ) 
                                                    onOtherFrameLoad( 'caseInfo', 'caseInfoFrame', 'body' ) ;
                                                }
                                            },
                                html:  \"<iframe \" +
                                                \"id='caseInfoFrame' \" + 
                                                \"style='border:none;' \" + 
                                                \"width='100%' \" +
                                                \"onload='onOtherFrameLoad( \\\"caseInfo\\\", \\\"caseInfoFrame\\\", \\\"body\\\" );' \" +
                                                \"src='".$myProcessMaker->serverURL."/cases/cases_open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&action=TO_DO&rand=$rand'>\" +
                                       \"</iframe>\"
                            }";
            }
            echo "       ]
                    });";
                    
            if( $pmCaseUser ) 
                echo " tabs.setActiveTab( 'task-".$pmCaseUser->delIndex."') ;" ;
            else
                echo " tabs.setActiveTab( 'caseInfo') ;" ;
            echo    "</script>";
            
            //////echo "<iframe id='caseiframe' onload='onGLPILoadFrame( event ) ;' height='1080px' style='border:none;' width='100%' src='".$myProcessMaker->serverURL."/cases/open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&rand=$rand' >" ; 
            //////echo "</iframe>";                   
            echo "</td></tr>";
        } else {
                    
            // no running case for this ticket
            // propose to start one
            echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['nocase'] ;
                    
            // check if item is not solved nor closed
            if( $item->fields['status'] != 'solved' && $item->fields['status'] != 'closed' && $_SESSION['glpiactiveprofile']['interface'] != 'helpdesk' ) {                    
                // propose case start
                echo "&nbsp;-&nbsp;".$LANG['processmaker']['item']['startone'];
                echo "</th></tr>";
                    
                echo "<tr class='tab_bg_2'><td class='tab_bg_2' colspan='1'>";
                echo $LANG['processmaker']['item']['selectprocess']."&nbsp;";
                echo "<input type='hidden' name='action' value='newcase'>";
                echo "<input type='hidden' name='id' value='$item_id'>";
                echo "<input type='hidden' name='itemtype' value='$item_type'>";
//                Dropdown::show('PluginProcessmakerProcessmaker', array( 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_process_id', 'condition' => "is_active=1")); // condition is used to prevent start of none-active cases 
                PluginProcessmakerProcess::dropdown(array( 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_process_id'));
                echo "</td><td class='tab_bg_2'>";
                echo "<input type='submit' name='additem' value='".$LANG['processmaker']['item']['start']."' class='submit'>";
                echo "</td></tr>";
            }
            else echo "</th></tr>";
        }

        echo "</table>";
        echo "</form>";
        
        return true ; 
    }

    /**
     * Summary of deleteTasks
     * will delete all tasks associated with this case from the item
     * BEWARE that this will only be done when case is in DRAFT status
     * @return true if tasks have been deleted from associated item and from case table
     */
    private function deleteTasks( ) {
        global $DB ;
        $ret = false ;
        
        if( isset($this->fields['case_status']) && $this->fields['case_status'] == "DRAFT" ) {
            $query = "DELETE from glpi_".$this->fields['itemtype']."tasks where id in (select items_id from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."')";
            if( $DB->query( $query ) ) {
                $query = "DELETE from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."'";
                if( $DB->query( $query ) )               
                    $ret = true ;
            }
        }        
        return $ret ;         
    }
    
    
    /**
     * Summary of deleteCase
     * will delete case and all tasks associated with this case from the item
     * BEWARE that this will only be done when case is in DRAFT status
     * @return true if case and tasks have been deleted from associated item and from case table
     */
    function deleteCase( ) {
        global $DB ;
        $ret = false ;
        
        if( isset($this->fields['case_status']) && $this->fields['case_status'] == "DRAFT" ) {
            if( $this->deleteTasks() )
                if( $this->deleteFromDB( ) )
                    $ret = true ;
        }        
        return $ret ;         
    }
    

    
    /**
     * Summary of cancelTasks
     * will mark as information all to_do tasks 
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if tasks have been deleted from associated item and from case table
     */
    private function cancelTasks( ) {
        global $DB ;
        $ret = false ;
        
        if( isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO" ) {
            $query = "UPDATE glpi_".$this->fields['itemtype']."tasks SET state=0,users_id_tech=0,begin=NULL,end=NULL  WHERE state=1 AND id in (select items_id from glpi_plugin_processmaker_tasks where case_id='".$this->fields['id']."')";
            if( $DB->query( $query ) ) {
                $ret = true ;
            }
        }        
        return $ret ;         
    }

    
    
    /**
     * Summary of cancelCase
     * will cancel case and mark 'to_do' tasks associated with this case from the item as information
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if case and tasks have been cancelled or marked from associated item and from case table
     */
    function cancelCase( ) {
        global $DB ;
        $ret = false ;
        
        if( isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO" ) 
            if( $this->cancelTasks() ) 
                if( $this->update( array( 'id' => $this->getID(), 'case_status' => 'CANCELLED' ) ) )
                    $ret=true;
                
        return $ret ;         
    }
 
    /**
     * Summary of canSolve
     * To know if a Ticket (Problem or Change) can be solved
     * i.e. the case permits solving of item
     * @param mixed $parm 
     * @return bool
     */
    public static function canSolve ($parm) {
        $myCase = new self;
        if( $myCase->getCaseFromItemTypeAndItemId( $parm->getType(), $parm->getID() )  ) {                        
            $pmVar = $myCase->getVariables( array( 'GLPI_ITEM_CAN_BE_SOLVED' ) ) ;
            if( $myCase->fields['case_status'] != 'COMPLETED' && $myCase->fields['case_status'] != 'CANCELLED' && (!isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) || $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1) ) { 
                // then item can't be solved
                return false ;
            }
        }
        return true ;                            
    }
    
    /**
     * Summary of getToDoTasks
     * @param mixed $parm is a Ticket, a Problem or a Change
     * @return array list of tasks with status 'to do' for case associated with item
     */
    public static function getToDoTasks($parm) {
        $myCase = new self;
        
        if( $myCase->getCaseFromItemTypeAndItemId( $parm->getType(), $parm->getID() )  ) {
            return PluginProcessmakerTasks::getToDoTasks( $myCase->getID(), $parm->getType()."Task" ) ;
        }
        return array();
    }
}