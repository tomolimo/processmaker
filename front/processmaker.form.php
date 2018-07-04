<?php
include_once ("../../../inc/includes.php");

switch ($_POST["action"]) {
   case 'newcase':
      if (isset($_POST['items_id']) && $_POST['items_id'] > 0) { // then this case will be bound to an item
         // TODO: we must check if a case is not already existing
         // to manage the problem of F5 (Refresh)

         //$hasCase = PluginProcessmakerCase::getIDFromItem($_POST['itemtype'], $_POST['items_id']);
         //if ($hasCase === false && $_POST['plugin_processmaker_processes_id'] > 0) {
         if ($_POST['plugin_processmaker_processes_id'] > 0) {

            $resultCase = $PM_SOAP->startNewCase($_POST['plugin_processmaker_processes_id'], $_POST['itemtype'], $_POST['items_id'], Session::getLoginUserID());

            if ($resultCase->status_code == 0) {
               $case = new PluginProcessmakerCase;
               if ($case->getFromGUID($resultCase->caseId)) {
                  $link         = $case->getLinkURL();
                  $task = new PluginProcessmakerTask();
                  $task->getFromDBByQuery(" WHERE `plugin_processmaker_cases_id`=".$case->getID()); // normally there is only one and only one first task
                  //$link .= '&forcetab=PluginProcessmakerTask$'.$task->getID();
                  Session::setActiveTab('PluginProcessmakerCase', 'PluginProcessmakerTask$'.$task->getID());
                  $item = new $_POST['itemtype'];
                  $item->getFromDB($_POST['items_id']);
                  unset($_SERVER['REQUEST_URI']); // to prevent use of processmaker.form.php in NavigateList
                  Session::initNavigateListItems('PluginProcessmakerCase', 
                        //TRANS : %1$s is the itemtype name,
                        //        %2$s is the name of the item (used for headings of a list)
                                  sprintf(__('%1$s = %2$s'),
                                          $_POST['itemtype']::getTypeName(1), $item->fields["name"]));
                  Html::redirect($link);
               }
               Html::back();
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>".$resultCase->message." (".$resultCase->status_code.")", true, ERROR);
            }
         } else {
            Html::back();
         }
      } else { // the case is created before the ticket (used for post-only case creation before ticket creation)
         $resultCase = $PM_SOAP->newCase( $_POST['plugin_processmaker_processes_id'],
                                                array( 'GLPI_ITEM_CAN_BE_SOLVED'    => 0,
                                                       'GLPI_SELFSERVICE_CREATED'   => '1',
                                                       'GLPI_ITEM_TYPE'             => 'Ticket',
                                                       'GLPI_URL'                   => $CFG_GLPI['url_base']) );
         if ($resultCase->status_code == 0) {
            // case is created
              // Must show it...
              //
              $rand = rand( );
              Html::redirect($CFG_GLPI['root_doc']."/plugins/processmaker/front/processmaker.helpdesk.form.php?processes_id=".$_POST['plugin_processmaker_processes_id']."&case_guid=".$resultCase->caseId."&rand=$rand&itilcategories_id=".$_POST["itilcategories_id"]."&type=".$_REQUEST["type"]."&entities_id=".$_REQUEST['entities_id']);

         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['error'][$resultCase->status_code]."<br>$resultCase->message ($resultCase->status_code)", true, ERROR); //echo "Error creating case: $resultCase->message \n";
            Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
         }

      }
        break;

   case 'unpausecase_or_reassign_or_delete' :
      if (isset($_POST['unpause'])) {
         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_POST['cases_id']);
         $pmResultUnpause = $locCase->unpauseCase($_POST['delIndex'], $_POST['users_id']);
         if ($pmResultUnpause->status_code == 0) {
            Html::back();
         } else {
            echo "Error unpausing case: ".$pmResultUnpause->message." \n";
         }
      } else if (isset($_POST['reassign'])) {
         // here we should re-assign the current task to $_POST['users_id_recipient']
         //$GLPINewPMUserId = PluginProcessmakerUser::getPMUserId( $_POST['users_id_recipient'] );
         if ($_POST['users_id'] != $_POST['users_id_recipient']) { // normally should be different as of the dropdown prevents already used
            $locCase = new PluginProcessmakerCase;
            $locCase->getFromDB($_POST['cases_id']);

            $pmResponse = $locCase->reassignCase($_POST['delIndex'],
                                                 $_POST['taskGuid'],
                                                 $_POST['delThread'],
                                                 $_POST['users_id'],
                                                 $_POST['users_id_recipient']);
            if ($pmResponse) {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['reassigned'], true, INFO);
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['notreassigned'].$pmResponse->message, true, ERROR);
            }
         } else {
            Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['assignedtoyou'], true, ERROR); // Html::back();
         }
      //} else if (isset($_POST['delete'])) {
      //   // delete case from case table, this will also delete the tasks
      //   $locCase = new PluginProcessmakerCase;
      //   if ($locCase->getFromDB($_POST['cases_id']) && $locCase->deleteCase()) {
      //      // request delete from pm itself
      //      $PM_SOAP->login(true);

      //      $resultPM = $PM_SOAP->deleteCase($locCase->fields['case_guid']);

      //      if ($resultPM->status_code == 0) {
      //         Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['deleted'], true, INFO);
      //      } else {
      //         Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR);
      //      }
      //   } else {
      //      Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR);
      //   }
      } else if (isset($_POST['cancel'])) {
         // cancel case from PM
         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_POST['cases_id']);
         $resultPM = $PM_SOAP->cancelCase($locCase->fields['case_guid']); //, $_POST['plugin_processmaker_del_index'], $_POST['plugin_processmaker_users_id'] ) ;
         if ($resultPM->status_code === 0) {
            //$locCase = new PluginProcessmakerCase;
            //$locCase->getFromDB($_POST['cases_id']);
            if ($locCase->cancelCase()) {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['cancelled'], true, INFO);
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled'], true, ERROR);
            }
         } else {
            if ($resultPM->status_code == 100 && $locCase->deleteCase()) { // case is draft then delete it
               // request delete from pm itself
               $PM_SOAP->login(true);

               $resultPM = $PM_SOAP->deleteCase($locCase->fields['case_guid']);

               if ($resultPM->status_code == 0) {
                  Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['deleted'], true, INFO);
               } else {
                  Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errordeleted'], true, ERROR);
               }
            } else {
               Session::addMessageAfterRedirect($LANG['processmaker']['item']['case']['errorcancelled']. " " . $resultPM->message, true, ERROR);
            }
         }
      }

      break;

}

// to return to item
Html::back();

