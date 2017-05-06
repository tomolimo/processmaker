<?php
include_once ("../../../inc/includes.php");

switch ($_POST["action"]) {
   case 'newcase':
      if (isset($_POST['id']) && $_POST['id'] > 0) { // then this case will be bound to an item
         // we must check if a case is not already existing
         // to manage the problem of F5 (Refresh)
         $hasCase = PluginProcessmakerProcessmaker::getCaseIdFromItem( $_POST['itemtype'], $_POST['id'] );
         if ($hasCase === false && $_POST['plugin_processmaker_process_id'] > 0) { //$DB->numrows($res) == 0) {
            $myProcessMaker = new PluginProcessmakerProcessmaker();
            $myProcessMaker->login(); //openSession();

            $resultCase = $myProcessMaker->startNewCase( $_POST['plugin_processmaker_process_id'], $_POST['itemtype'], $_POST['id'], Session::getLoginUserID() );

            if ($resultCase->status_code == 0) {
               Html::back();
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>".$resultCase->message." (".$resultCase->status_code.")", true, ERROR);
            }
         } else {
            Html::back();
         }
      } else { // the case is created before the ticket (used for post-only case creation before ticket creation)
         $myProcessMaker = new PluginProcessmakerProcessmaker();
         $myProcessMaker->login();
         $resultCase = $myProcessMaker->newCase( $_POST['plugin_processmaker_process_id'],
                                                array( 'GLPI_ITEM_CAN_BE_SOLVED'    => 0,
                                                       'GLPI_SELFSERVICE_CREATED'   => '1',
                                                       'GLPI_URL'                   => $CFG_GLPI['url_base'].$CFG_GLPI['root_doc']) );
         if ($resultCase->status_code == 0) {
            // case is created
              // Must show it...
              //
              $rand = rand( );
              Html::redirect($CFG_GLPI['root_doc']."/plugins/processmaker/front/processmaker.helpdesk.form.php?process_id=".$_POST['plugin_processmaker_process_id']."&case_id=".$resultCase->caseId."&rand=$rand&itilcategories_id=".$_POST["itilcategories_id"]."&type=".$_REQUEST["type"]."&entities_id=".$_REQUEST['entities_id']);

         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>$resultCase->message ($resultCase->status_code)", true, ERROR); //echo "Error creating case: $resultCase->message \n";
            Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
         }

      }
        break;

   case 'unpausecase_or_reassign_or_delete' :
      if (isset( $_POST['unpause'] )) {
         $myProcessMaker = new PluginProcessmakerProcessmaker();
         $myProcessMaker->login(); //openSession();
         $pmResultUnpause = $myProcessMaker->unpauseCase( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'] );
         if ($pmResultUnpause->status_code == 0) {
            Html::back();
         } else {
            echo "Error unpausing case: ".$pmResultUnpause->message." \n";
         }
      } else if (isset( $_POST['reassign'] )) {
          // here we should re-assign the current task to $_POST['users_id_recipient']
          $GLPINewPMUserId = PluginProcessmakerUser::getPMUserId( $_POST['users_id_recipient'] );
         if ($_POST['plugin_processmaker_userId'] != $GLPINewPMUserId) {
            $locPM = new PluginProcessmakerProcessmaker();
            $locPM->login( );

            $pmResponse = $locPM->reassignCase( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'], $GLPINewPMUserId );
            if ($pmResponse->status_code == 0) {
               // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks
               // we need the delindex of the current glpi task, and the delindex of the new one
               // search for new delindex
               $newCaseInfo = $locPM->getCaseInfo( $_POST['plugin_processmaker_caseId'] );
               $newDelIndex = 0;
               foreach ($newCaseInfo->currentUsers as $newCaseUser) {
                  if ($newCaseUser->taskId == $_POST['plugin_processmaker_taskId']  && $newCaseUser->delThread == $_POST['plugin_processmaker_delThread']) {
                     $newDelIndex = $newCaseUser->delIndex;
                     break;
                  }
               }
               $locPM->reassignTask( $_POST['plugin_processmaker_caseId'], $_POST['plugin_processmaker_delIndex'], $newDelIndex, $_POST['users_id_recipient'] );
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['reassigned'], true, INFO);
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['notreassigned'].$pmResponse->message, true, ERROR);
            }
         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['assignedtoyou'], true, ERROR); // Html::back();
         }
      } else if (isset($_POST['delete'])) {
         // delete case from case table, this will also delete the tasks
         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB( $_POST['plugin_processmaker_caseId'] );
         if ($locCase->deleteCase()) {
            // request delete from pm itself
            $myProcessMaker = new PluginProcessmakerProcessmaker();
            $myProcessMaker->login(true);
            $resultPM = $myProcessMaker->deleteCase(  $_POST['plugin_processmaker_caseId'] );

            if ($resultPM->status_code == 0) {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['deleted'], true, INFO);
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR);
            }
         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR);
         }
      } else if (isset($_POST['cancel'])) {
         // cancel case from PM
         $myProcessMaker = new PluginProcessmakerProcessmaker();
         $myProcessMaker->login();
         $resultPM = $myProcessMaker->cancelCase(  $_POST['plugin_processmaker_caseId'] ); //, $_POST['plugin_processmaker_delIndex'], $_POST['plugin_processmaker_userId'] ) ;
         if ($resultPM->status_code === 0) {
            $locCase = new PluginProcessmakerCase;
            $locCase->getFromDB( $_POST['plugin_processmaker_caseId'] );
            if ($locCase->cancelCase()) {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['cancelled'], true, INFO);
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled'], true, ERROR);
            }
         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled'], true, ERROR);
         }
      }

      break;

}

// to return to ticket
Html::back();

