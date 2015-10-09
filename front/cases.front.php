<?php

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");
include_once '../inc/processmaker.class.php' ;
include_once '../inc/cases.class.php' ;

// check if it is from PM pages
if( isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] ) ) {
    // then get item id from DB
    $myCase = new PluginProcessmakerCases ;
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
            $txtForTasks = $myProcessMaker->getVariables( $myCase->getID(), array( "GLPI_ITEM_TASK_CONTENT", "GLPI_ITEM_APPEND_TO_TASK", "GLPI_NEXT_GROUP_TO_BE_ASSIGNED" ) );
            if( array_key_exists( 'GLPI_ITEM_APPEND_TO_TASK', $txtForTasks ) ) 
                $txtToAppendToTask = $txtForTasks[ 'GLPI_ITEM_APPEND_TO_TASK' ] ;
            else
                $txtToAppendToTask  = '' ;
            if( array_key_exists( 'GLPI_ITEM_TASK_CONTENT', $txtForTasks ) ) 
                $txtTaskContent = $txtForTasks[ 'GLPI_ITEM_TASK_CONTENT' ] ;
            else
                $txtTaskContent = '' ;
            if( array_key_exists( 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED', $txtForTasks ) ) 
                $groupId = $txtForTasks[ 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED' ] ;
            else 
                $groupId = 0 ;
                
            // reset those variables
            $resultSave = $myProcessMaker->sendVariables( $myCase->getID() , array( "GLPI_ITEM_APPEND_TO_TASK" => '', 'GLPI_ITEM_TASK_CONTENT' => '', 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED' => '' )  ) ;                
            
           // print_r( $pmRouteCaseResponse ) ;
           // die() ;
            
            // now manage tasks associated with item
            $itemType = $myCase->getField('itemtype');
            $itemId = $myCase->getField('items_id');
            
            // switch own task to 'done' and create a new one
            $myProcessMaker->solveTask(  $myCase->getID(), $_REQUEST['DEL_INDEX'], $txtToAppendToTask ) ;
            $caseInfo = $myProcessMaker->getCaseInfo(  $myCase->getID(), $_REQUEST['DEL_INDEX']) ;
            if( property_exists( $pmRouteCaseResponse, 'routing' ) ) {
                foreach( $pmRouteCaseResponse->routing as $route ) {                    
                    $myProcessMaker->addTask( $itemType, $itemId, $caseInfo, $route->delIndex, PluginProcessmakerProcessmaker::getGLPIUserId( $route->userId ), $groupId, $route->taskId, $txtTaskContent ) ; 
                }
            }
            
            // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
            $myCase->update( array( 'id' => $myCase->getID(), 'case_status' => $caseInfo->caseStatus ) ) ;            
            
        }   
    }   
}
// Claim task management
elseif( isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID']) ){
    // here we are in a Claim request
    $myCase = new PluginProcessmakerCases ;
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
echo "<html><body><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>" ;


?>