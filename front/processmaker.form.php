<?php
include_once ("../../../inc/includes.php");

switch ($_POST["action"]) {
   case 'newcase':
      if (isset($_POST['items_id']) && $_POST['items_id'] > 0) {

         // then this case will be bound to an item
         if ($_POST['plugin_processmaker_processes_id'] > 0) {

            $resultCase = $PM_SOAP->startNewCase($_POST['plugin_processmaker_processes_id'], $_POST['itemtype'], $_POST['items_id'], Session::getLoginUserID());

            if ($resultCase->status_code == 0) {
               $case = new PluginProcessmakerCase;
               if ($case->getFromGUID($resultCase->caseId)) {
                  $link         = $case->getLinkURL();
                  $task = new PluginProcessmakerTask($_POST['itemtype'].'Task');

                  $task->getFromDBByRequest([
                                  'WHERE'  => [
                                  'plugin_processmaker_cases_id'  => $case->getID()
                                  ],
                              ]);


                  //$task->getFromDBByQuery(" WHERE `plugin_processmaker_cases_id`=".$case->getID()); // normally there is only one and only one first task
                  //$link .= '&forcetab=PluginProcessmakerTask$'.$task->getID();

                  Session::setActiveTab('PluginProcessmakerCase', 'PluginProcessmakerTask$'.$task->fields['id']);
                  $item = new $_POST['itemtype'];
                  $item->getFromDB($_POST['items_id']);
                  unset($_SERVER['REQUEST_URI']); // to prevent use of processmaker.form.php in NavigateList
                  Session::initNavigateListItems('PluginProcessmakerCase',
                        //TRANS : %1$s is the itemtype name,
                        //        %2$s is the name of the item (used for headings of a list)
                                  sprintf('%1$s = %2$s',
                                          $_POST['itemtype']::getTypeName(1), $item->fields["name"]));
                  Html::redirect($link);
               }
               Html::back();
            } else {
               Session::addMessageAfterRedirect( PluginProcessmakerProcessmaker::getPMErrorMessage($resultCase->status_code)."<br>".$resultCase->message." (".$resultCase->status_code.")", true, ERROR);
            }
         } else {
            Html::back();
         }
      } else { // the case is created before the ticket (used for post-only case creation before ticket creation)
         $pm_user_guid = PluginProcessmakerUser::getPMUserId( Session::getLoginUserID() );
         $resultCase = $PM_SOAP->newCase( $_POST['plugin_processmaker_processes_id'],
                                          ['GLPI_ITEM_CAN_BE_SOLVED'     => 0,
                                           'GLPI_SELFSERVICE_CREATED'    => '1',
                                           'GLPI_ITEM_TYPE'              => 'Ticket',
                                           'GLPI_URL'                    => $CFG_GLPI['url_base'],
                                           // Specific to Tickets
                                           // GLPI_TICKET_TYPE will contains 1 (= incident) or 2 (= request)
                                           'GLPI_TICKET_TYPE'            => $_POST['type'],
                                           'GLPI_ITEM_REQUESTER_GLPI_ID' => Session::getLoginUserID(),
                                           'GLPI_ITEM_REQUESTER_PM_ID'   => $pm_user_guid
                                          ] );
         if ($resultCase->status_code == 0) {
            // case is created
              // Must show it...
              //
              $rand = rand( );
              Html::redirect($CFG_GLPI['root_doc']."/plugins/processmaker/front/processmaker.helpdesk.form.php?processes_id=".$_POST['plugin_processmaker_processes_id']."&case_guid=".$resultCase->caseId."&rand=$rand&itilcategories_id=".$_POST["itilcategories_id"]."&type=".$_POST["type"]."&entities_id=".$_POST['entities_id']);

         } else {
            Session::addMessageAfterRedirect( PluginProcessmakerProcessmaker::getPMErrorMessage($resultCase->status_code)."<br>$resultCase->message ($resultCase->status_code)", true, ERROR);
            Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
         }

      }
      break;

   case 'reassign_reminder' :
      if (isset($_POST['reassign'])) {
         // here we should re-assign the current task to $_POST['users_id_recipient']
         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_POST['cases_id']);
         if ($_POST['users_id_recipient'] != 0) {
            // we are assigning a new tech to a task
            $pmResponse = $locCase->reassignCase($_POST['delIndex'],
                                                 $_POST['taskGuid'],
                                                 $_POST['delThread'],
                                                 $_POST['users_id'],
                                                 $_POST['users_id_recipient'],
                                                 ['comment' => $_POST['comment']]);
            if ($pmResponse) {
               Session::addMessageAfterRedirect(__('Task re-assigned!', 'processmaker'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Error re-assigning task: ', 'processmaker').$pmResponse->message, true, ERROR);
            }
         } elseif ($_POST['users_id_recipient'] == 0) {
            // we are unassigning a task, i.e.: task un-claim
            $pmResponse = $locCase->unassignCase($_POST['delIndex'],
                                                 $_POST['taskGuid'],
                                                 $_POST['tasktype'],
                                                 $_POST['tasks_id'],
                                                 $_POST['itemtype'],
                                                 ['comment' => $_POST['comment']]);
            if ($pmResponse) {
               Session::addMessageAfterRedirect(__('Task un-claimed!', 'processmaker'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__("Can't un-claim task! Verify 'Assignement Rules' in the process definition.", 'processmaker'), true, ERROR);
            }
         }
      } elseif (isset($_POST['reminder'])) {
         // send notification remider as requested for this task

         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_POST['cases_id']);
         $glpi_item = new $_POST['itemtype'];
         $glpi_item->getFromDB($_POST['items_id']);
         $pm_task = new PluginProcessmakerTask($_POST['tasktype']);
         $pm_task->getFromDB($_POST['tasks_id']);
         $glpi_task = new $_POST['tasktype'];
         $glpi_task->getFromDB($_POST['tasks_id']);

         // send notification now!
         $pm_task->sendNotification('task_reminder', $glpi_task, $glpi_item, $locCase);
      }
}

// to return to item
Html::back();

