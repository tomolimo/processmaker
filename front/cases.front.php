<?php
define('DO_NOT_CHECK_HTTP_REFERER', 1);
include_once '../../../inc/includes.php';
//include_once '../inc/processmaker.class.php' ;
//include_once '../inc/case.class.php' ;

// check if it is from PM pages
if( isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] ) ) {
    // then get item id from DB
    $myCase = new PluginProcessmakerCase ;
    if( $myCase->getFromDB( $_REQUEST['APP_UID'] ) ) {
        $myProcessMaker = new PluginProcessmakerProcessmaker() ;
        $myProcessMaker->login( ) ;

        if( isset( $_REQUEST['form'] ) ) {
            // save the case variables
            //$resultSave = $myProcessMaker->sendVariables( $myCase->getID() , $_REQUEST['form'] ) ;
            $resultSave = $myProcessMaker->saveForm( $_REQUEST, $_SERVER['HTTP_COOKIE'] ) ;
            //$myCase->sendVariables( $_REQUEST['form']  ) ;

            // now derivate the case !!!
            $pmRouteCaseResponse = $myProcessMaker->routeCase( $myCase->getID(), $_REQUEST['DEL_INDEX']) ;

            // now tries to get some variables to setup content for new task and to append text to solved task
            $infoForTasks = $myProcessMaker->getVariables( $myCase->getID(), array(  "GLPI_ITEM_TASK_CONTENT",
                                                                                     "GLPI_ITEM_APPEND_TO_TASK",
                                                                                     "GLPI_NEXT_GROUP_TO_BE_ASSIGNED",
                                                                                     "GLPI_ITEM_TITLE",
                                                                                     "GLPI_TICKET_FOLLOWUP_CONTENT",
                                                                                     "GLPI_TICKET_FOLLOWUP_IS_PRIVATE",
                                                                                     "GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID",
                                                                                     "GLPI_ITEM_TASK_ENDDATE",
                                                                                     "GLPI_ITEM_TASK_STARTDATE",
                                                                                     "GLPI_ITEM_SET_STATUS"
                                                                                    ) );
            $itemSetStatus = '';
            if( array_key_exists( 'GLPI_ITEM_SET_STATUS', $infoForTasks ) ) {
               $itemSetStatus = $infoForTasks[ 'GLPI_ITEM_SET_STATUS' ]  ;
            }

            $txtItemTitle  = '' ;
            if( array_key_exists( 'GLPI_ITEM_TITLE', $infoForTasks ) ) {
               $txtItemTitle = $infoForTasks[ 'GLPI_ITEM_TITLE' ] ;
            }

            $txtToAppendToTask  = '' ;
            if( array_key_exists( 'GLPI_ITEM_APPEND_TO_TASK', $infoForTasks ) ) {
               $txtToAppendToTask = $infoForTasks[ 'GLPI_ITEM_APPEND_TO_TASK' ] ;
            }

            $txtTaskContent = '' ;
            if( array_key_exists( 'GLPI_ITEM_TASK_CONTENT', $infoForTasks ) ) {
               $txtTaskContent = $infoForTasks[ 'GLPI_ITEM_TASK_CONTENT' ] ;
            }

            $groupId = 0 ;
            if( array_key_exists( 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED', $infoForTasks ) ) {
               $groupId = $infoForTasks[ 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED' ] ;
            }

            $taskStartDate = '' ;
            $taskEndDate = '' ;
            if( array_key_exists( 'GLPI_ITEM_TASK_ENDDATE', $infoForTasks ) ) {
               $taskEndDate = $infoForTasks[ 'GLPI_ITEM_TASK_ENDDATE' ] ;
            }
            if( array_key_exists( 'GLPI_ITEM_TASK_STARTDATE', $infoForTasks ) ) {
               $taskStartDate = $infoForTasks[ 'GLPI_ITEM_TASK_STARTDATE' ] ;
               if( $taskEndDate == '' ) {
                  // at least
                  $taskEndDate = $taskStartDate ;
               }
            }

            $createFollowup = false ; // by default
            if( array_key_exists( 'GLPI_TICKET_FOLLOWUP_CONTENT', $infoForTasks ) && $infoForTasks[ 'GLPI_TICKET_FOLLOWUP_CONTENT' ] != '') {
                  //&& array_key_exists( 'GLPI_TICKET_FOLLOWUP_IS_PRIVATE', $infoForTasks )
                  //&& array_key_exists( 'GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID', $infoForTasks )
                  $createFollowup = true ;
            }
            // reset those variables
            $resultSave = $myProcessMaker->sendVariables( $myCase->getID() , array( "GLPI_ITEM_APPEND_TO_TASK" => '',
                                                                                    "GLPI_ITEM_TASK_CONTENT" => '',
                                                                                    "GLPI_NEXT_GROUP_TO_BE_ASSIGNED" => '',
                                                                                    "GLPI_TICKET_FOLLOWUP_CONTENT" => '',
                                                                                    "GLPI_TICKET_FOLLOWUP_IS_PRIVATE" => '',
                                                                                    "GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID" => '',
                                                                                    "GLPI_ITEM_TASK_ENDDATE" => '',
                                                                                    "GLPI_ITEM_TASK_STARTDATE" => '',
                                                                                    'GLPI_ITEM_TITLE' => '',
                                                                                    "GLPI_ITEM_SET_STATUS" => ''
                                                                                    )  ) ;

           // print_r( $pmRouteCaseResponse ) ;
           // die() ;

            // now manage tasks associated with item
            $itemType = $myCase->getField('itemtype');
            $itemId = $myCase->getField('items_id');

            // switch own task to 'done' and create a new one
            $myProcessMaker->solveTask(  $myCase->getID(), $_REQUEST['DEL_INDEX'], $txtToAppendToTask ) ;

            // create a followup if requested
            if( $createFollowup && $itemType == 'Ticket' ) {
               $myProcessMaker->addTicketFollowup( $itemId, $infoForTasks ) ;
            }
            $caseInfo = $myProcessMaker->getCaseInfo(  $myCase->getID(), $_REQUEST['DEL_INDEX']) ;
            if( property_exists( $pmRouteCaseResponse, 'routing' ) ) {
                foreach( $pmRouteCaseResponse->routing as $route ) {
                    $myProcessMaker->addTask( $itemType, $itemId, $caseInfo, $route->delIndex, PluginProcessmakerUser::getGLPIUserId( $route->userId ), $groupId, $route->taskId, $txtTaskContent, $taskStartDate, $taskEndDate ) ;
                    // if end date was specicied, then must change due date of the PM task
                    if( $taskEndDate != '' ) {
                       $PM_DB->query( "UPDATE APP_DELEGATION SET DEL_TASK_DUE_DATE='$taskEndDate' WHERE APP_UID='".$caseInfo->caseId."' AND DEL_INDEX=".$route->delIndex);
                    }
                }
            }
            if( $txtItemTitle != '') {
                // we are going to change the title of current GLPI Item
                $item = new $itemType ;
                $item->getFromDB( $itemId ) ;
                $item->update( array('id' => $itemId, 'name' => $txtItemTitle) ) ;
            }

            if( $itemSetStatus != '' ) {
               $myProcessMaker->setItemStatus($itemType, $itemId, $itemSetStatus ) ;
            }
            // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
            $myCase->update( array( 'id' => $myCase->getID(), 'case_status' => $caseInfo->caseStatus ) ) ;

        }
    }
}
// Claim task management
elseif( isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID']) ){
    // here we are in a Claim request
    $myCase = new PluginProcessmakerCase ;
    if( $myCase->getFromDB( $_REQUEST['form']['APP_UID'] ) ) {
        $myProcessMaker = new PluginProcessmakerProcessmaker() ;
        $myProcessMaker->login( ) ;

        $pmClaimCase = $myProcessMaker->claimCase( $myCase->getID(), $_REQUEST['DEL_INDEX'] ) ;

        // now manage tasks associated with item
        $myProcessMaker->claimTask(  $myCase->getID(), $_REQUEST['DEL_INDEX'] ) ;
    }

}

// now redirect to item form page
//Html::redirect( Toolbox::getItemTypeFormURL($myCase->getField('itemtype')));
echo "<html><body><script></script><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>" ;


