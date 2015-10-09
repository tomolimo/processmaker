<?php
if( !defined ('GLPI_ROOT' ) )
    define('GLPI_ROOT', '../../..');
include_once (GLPI_ROOT."/inc/includes.php");
include_once '../inc/processmaker.class.php' ;
include_once '../inc/cases.class.php' ;

switch( $_POST["action"] ) {
    case 'newcase':
        if( isset($_POST['id']) && $_POST['id'] > 0 ) { // then this case will be bound to an item
            // we must check if a case is not already existing
            // to manage the problem of F5 (Refresh)
            $hasCase = PluginProcessmakerProcessmaker::getCaseIdFromItem( $_POST['itemtype'], $_POST['id'] ) ;
            if( $hasCase === false && $_POST['plugin_processmaker_process_id'] > 0 ) { //$DB->numrows($res) == 0) {      
                $myProcessMaker = new PluginProcessmakerProcessmaker() ;
                $myProcessMaker->login() ; //openSession();
                            
                $requesters = PluginProcessmakerProcessmaker::getItemUsers( $_POST['itemtype'], $_POST['id'], 1  ) ; // 1 for requesters
                if( !key_exists( 0, $requesters ) ) {
                    $requesters[0]['glpi_id'] = 0 ;
                    $requesters[0]['pm_id'] = 0 ;
                }

                //$technicians = PluginProcessmakerProcessmaker::getItemUsers( $_POST['itemtype'], $_POST['id'], 2 ) ; // 2 for technicians
                //if( !key_exists( 0, $technicians ) ) {
                //    $technicians[0]['glpi_id'] = Session::getLoginUserID() ;
                //    $technicians[0]['pm_id'] = PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() ) ;
                //}
                
                // get item info to retreive title, description and duedate
                $locTicket = new $_POST['itemtype'] ; //Ticket();
                $locTicket->getFromDB( $_POST['id'] ) ;
                
                if($locTicket->countUsers($locTicket::ASSIGN) == 0 
                    || !$locTicket->isUser($locTicket::ASSIGN, Session::getLoginUserID()) ){
                    $locTicket->update( array( 'id' => $_POST['id'], '_itil_assign' => array( '_type' => 'user', 'users_id' => Session::getLoginUserID() )  ) ) ;
                }
                
                //$writer =  PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() );
                if( !isset($locTicket->fields['due_date']) || $locTicket->fields['due_date'] == null ) {
                    $locTicket->fields['due_date'] = "";
                }

                $resultCase = $myProcessMaker->newCase( $_POST['plugin_processmaker_process_id'],
                                                        array(  'GLPI_ITEM_CAN_BE_SOLVED' => 0,
                                                                'GLPI_TICKET_ID' => $_POST['id'], 
                                                                'GLPI_ITEM_TYPE' => $_POST['itemtype'],
                                                                'GLPI_TICKET_REQUESTER_GLPI_ID' => $requesters[0]['glpi_id'], 
                                                                'GLPI_TICKET_REQUESTER_PM_ID' => $requesters[0]['pm_id'], 
                                                                'GLPI_TICKET_TITLE' => $locTicket->fields['name'], 
                                                                'GLPI_TICKET_DESCRIPTION' => $locTicket->fields['content'], 
                                                                'GLPI_TICKET_DUE_DATE' => $locTicket->fields['due_date'],  
																'GLPI_TICKET_URGENCY' => $locTicket->fields['urgency'], 
                                                                'GLPI_ITEM_IMPACT' => $locTicket->fields['impact'], 
                                                                'GLPI_ITEM_PRIORITY' => $locTicket->fields['priority'], 
																'GLPI_TICKET_GLOBAL_VALIDATION' => $locTicket->fields['global_validation'] ,
                                                                'GLPI_TICKET_TECHNICIAN_GLPI_ID' => Session::getLoginUserID(), //$technicians[0]['glpi_id'], 
                                                                'GLPI_TICKET_TECHNICIAN_PM_ID' => PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() ) //$technicians[0]['pm_id']
                                                             ) ) ; 
                
                if ($resultCase->status_code == 0){
                    $caseInfo = $myProcessMaker->getCaseInfo( $resultCase->caseId ); 

                    //$query = "UPDATE APPLICATION SET APP_STATUS='TO_DO' WHERE APP_UID='".$resultCase->caseId."' AND APP_STATUS='DRAFT'" ;
                    //$res = $DB->query($query) ;
                    // save info to DB
                    $query = "INSERT INTO glpi_plugin_processmaker_cases (items_id, itemtype, id, case_num, case_status, processes_id) VALUES (".$_POST['id'].", '".$_POST['itemtype']."', '".$resultCase->caseId."', ".$resultCase->caseNumber.", '".$caseInfo->caseStatus."', '".$caseInfo->processId."');" ;
			        $res = $DB->query($query) ;
                
                    $myProcessMaker->add1stTask($_POST['itemtype'], $_POST['id'], $caseInfo ) ;
                
                    //echo "New case ID: $result->caseId, Case No: $result->caseNumber \n";
                    Html::back();
                }
                else 
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>$resultCase->message ($resultCase->status_code)", true, ERROR); //echo "Error creating case: $resultCase->message \n";
            } else
                Html::back(); 
        }
        else { // the case is created before the ticket (used for user management before ticket creation)
            // list of requesters is needed
            // so read ticket
            //$requesters = array( ) ;
            
            
            //foreach( $DB->request( $query ) as $dbuser ) {
            //    $requesters[] = $dbuser['pm_users_id'] ;
            //}
            //$writer =  PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() );
            //$userGLPI = new User();
            //$userGLPI->getFromDB( Session::getLoginUserID() ) ;
            //if( $userGLPI->fields['language'] != null )
            //    $lang =  substr( $userGLPI->fields['language'], 0, 2)  ;
            //else
            //    $lang = "en" ;
            $myProcessMaker = new PluginProcessmakerProcessmaker() ;
            $myProcessMaker->login() ; //openSession( $userGLPI->fields['name'], "md5:37d442efb43ebb80ec6f9649b375ab72", $lang) ; 
            
            //$resultCase = $myProcessMaker->newCaseImpersonate( $_POST['plugin_processmaker_process_id'], $writer) ;  
            $resultCase = $myProcessMaker->newCase( $_POST['plugin_processmaker_process_id'], array( 'GLPI_ITEM_CAN_BE_SOLVED' => 0 ) ) ;  
            if ($resultCase->status_code == 0){
                // case is created 
                // Must show it...
                // 
                $rand = rand( ) ;
                Html::redirect($CFG_GLPI['root_doc']."/plugins/processmaker/front/processmaker.helpdesk.form.php?process_id=".$_POST['plugin_processmaker_process_id']."&case_id=".$resultCase->caseId."&rand=$rand&itilcategories_id=".$_POST["itilcategories_id"]."&type=".$_REQUEST["type"]); 
                                
            } else {
                //Html::helpHeader($LANG['job'][13], $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);
                //// case is not created show error message
                //echo "Error : ".$resultCase->status_code."</br>" ;
                //echo $resultCase->message."</br>" ;
                //Html::helpFooter();
                Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>$resultCase->message ($resultCase->status_code)", true, ERROR); //echo "Error creating case: $resultCase->message \n";
                Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
            }
            
        }
        break;   
        
    case 'unpausecase_or_reassign_or_delete' :
        if( isset( $_POST['unpause'] ) ) {
            $myProcessMaker = new PluginProcessmakerProcessmaker() ; 
            $myProcessMaker->login() ; //openSession();
            $pmResultUnpause = $myProcessMaker->unpauseCase( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'] ) ;
            if ($pmResultUnpause->status_code == 0){
                Html::back();
            }
            else 
                echo "Error unpausing case: ".$pmResultUnpause->message." \n";
        }
        else if( isset( $_POST['reassign'] ) ) {
            // here we should re-assign the current task to $_POST['users_id_recipient']
            $GLPINewPMUserId = PluginProcessmakerProcessmaker::getPMUserId( $_POST['users_id_recipient'] ) ; 
            if( $_POST['plugin_processmaker_userId'] != $GLPINewPMUserId ) {
                $locPM = new PluginProcessmakerProcessmaker() ;
                $locPM->login( ) ;

                $pmResponse = $locPM->reassignCase( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'], $GLPINewPMUserId )  ;
                if ($pmResponse->status_code == 0){
                    // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks 
                    // we need the delindex of the current glpi task, and the delindex of the new one
                    // search for new delindex
                    $newCaseInfo = $locPM->getCaseInfo( $_POST['plugin_processmaker_caseId'] ) ;
                    $newDelIndex = 0 ;
                    foreach( $newCaseInfo->currentUsers as $newCaseUser ){
                        if( $newCaseUser->taskId == $_POST['plugin_processmaker_taskId']  && $newCaseUser->delThread == $_POST['plugin_processmaker_delThread'] ) {
                            $newDelIndex = $newCaseUser->delIndex ;
                            break ;
                        }
                    }
                    $locPM->reassignTask( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $newDelIndex, $_POST['users_id_recipient'] ) ;
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['reassigned'], true, INFO); 
                   // Html::back();
                }
                else 
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['notreassigned'].$pmResponse->message, true, ERROR); 
            }  else
                Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['assignedtoyou'], true, ERROR); // Html::back();
        }
        else if( isset($_POST['delete']) ) {
            // delete case from case table, this will also delete the tasks
            $locCase = new PluginProcessmakerCases ;
            $locCase->getFromDB( $_POST['plugin_processmaker_caseId'] ) ;
            if( $locCase->deleteCase() ) {
                // request delete from pm itself
                $myProcessMaker = new PluginProcessmakerProcessmaker() ; 
                $myProcessMaker->login() ;
                $resultPM = $myProcessMaker->deleteCase(  $_POST['plugin_processmaker_caseId'] ) ;
                
                if( $resultPM->status_code == 0 ) {
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['deleted'], true, INFO); 
                } else
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR); 
            } else
                Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR); 
        }
        else if( isset($_POST['cancel']) ) {
            // cancel case from PM
            $myProcessMaker = new PluginProcessmakerProcessmaker() ; 
            $myProcessMaker->login() ; 
            $resultPM = $myProcessMaker->cancelCase(  $_POST['plugin_processmaker_caseId'] ) ; //, $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'] ) ;                
            if( $resultPM->status_code === 0 ) {
                $locCase = new PluginProcessmakerCases ;
                $locCase->getFromDB( $_POST['plugin_processmaker_caseId'] ) ;
                if( $locCase->cancelCase() )                
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['cancelled'], true, INFO); 
                else
                    Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled'], true, ERROR); 
            } else
                Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled'], true, ERROR); 
        }
        
        break;   

}

// to return to ticket
Html::back();

?>