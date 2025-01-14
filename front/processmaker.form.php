<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */
include_once ("../../../inc/includes.php");

switch ($_REQUEST["action"]) {
   case 'newcase':
      if (isset($_REQUEST['items_id']) && $_REQUEST['items_id'] > 0) {

         // then this case will be bound to an item
         if ($_REQUEST['plugin_processmaker_processes_id'] > 0) {

            $resultCase = $PM_SOAP->startNewCase($_REQUEST['plugin_processmaker_processes_id'], $_REQUEST['itemtype'], $_REQUEST['items_id'], Session::getLoginUserID());

            if ($resultCase->status_code == 0) {
               $case = new PluginProcessmakerCase;
               if ($case->getFromGUID($resultCase->caseId)) {
                  $link         = $case->getLinkURL();
                  $task = new PluginProcessmakerTask($_REQUEST['itemtype'].'Task');

                  $task->getFromDBByRequest([
                                  'WHERE'  => [
                                  'plugin_processmaker_cases_id'  => $case->getID()
                                  ],
                              ]);


                  //$task->getFromDBByQuery(" WHERE `plugin_processmaker_cases_id`=".$case->getID()); // normally there is only one and only one first task
                  //$link .= '&forcetab=PluginProcessmakerTask$'.$task->getID();
                  if (!isset($_REQUEST['timeline'])) {
                       Session::setActiveTab('PluginProcessmakerCase', 'PluginProcessmakerTask$'.$task->fields['id']);
                       $item = new $_REQUEST['itemtype'];
                       $item->getFromDB($_REQUEST['items_id']);
                       unset($_SERVER['REQUEST_URI']); // to prevent use of processmaker.form.php in NavigateList
                       Session::initNavigateListItems('PluginProcessmakerCase',
                             //TRANS : %1$s is the itemtype name,
                        //        %2$s is the name of the item (used for headings of a list)
                                       sprintf('%1$s = %2$s',
                                               $_REQUEST['itemtype']::getTypeName(1), $item->fields["name"]));
                       Html::redirect($link);
                  } else if ($_REQUEST['timeline']) {
                      Html::back();
                  }
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
         $resultCase = $PM_SOAP->newCase( $_REQUEST['plugin_processmaker_processes_id'],
                                          ['GLPI_ITEM_CAN_BE_SOLVED'     => 0,
                                           'GLPI_SELFSERVICE_CREATED'    => '1',
                                           'GLPI_ITEM_TYPE'              => 'Ticket',
                                           'GLPI_URL'                    => $CFG_GLPI['url_base'],
                                           // Specific to Tickets
                                           // GLPI_TICKET_TYPE will contains 1 (= incident) or 2 (= request)
                                           'GLPI_TICKET_TYPE'            => $_REQUEST['type'],
                                           'GLPI_ITEM_REQUESTER_GLPI_ID' => Session::getLoginUserID(),
                                           'GLPI_ITEM_REQUESTER_PM_ID'   => $pm_user_guid
                                          ] );
         if ($resultCase->status_code == 0) {
            // case is created
              // Must show it...
              //
            $rand = rand( );
            Html::redirect(
               Plugin::getWebDir('processmaker') . "/front/processmaker.helpdesk.form.php?processes_id=" . $_REQUEST['plugin_processmaker_processes_id'] .
               "&case_guid=".$resultCase->caseId .
               "&rand=$rand" .
               "&itilcategories_id=" . $_REQUEST["itilcategories_id"] .
               "&type=" . $_REQUEST["type"] .
               "&entities_id=" . $_REQUEST['entities_id']
            );

         } else {
            Session::addMessageAfterRedirect( PluginProcessmakerProcessmaker::getPMErrorMessage($resultCase->status_code)."<br>$resultCase->message ($resultCase->status_code)", true, ERROR);
            Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
         }

      }
      break;

   case 'reassign_reminder' :
      if (isset($_REQUEST['reassign'])) {
         // here we should re-assign the current task to $_REQUEST['users_id_recipient']
         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_REQUEST['cases_id']);
         if ($_REQUEST['users_id_recipient'] != 0) {
            // we are assigning a new tech to a task
            $pmResponse = $locCase->reassignCase($_REQUEST['delIndex'],
                                                 $_REQUEST['taskGuid'],
                                                 $_REQUEST['delThread'],
                                                 $_REQUEST['users_id'],
                                                 $_REQUEST['users_id_recipient'],
                                                 ['comment' => $_REQUEST['comment']]);
            if ($pmResponse->status_code == 0) {
               Session::addMessageAfterRedirect(__('Task re-assigned!', 'processmaker'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Error re-assigning task: ', 'processmaker') . $pmResponse->message, true, ERROR);
            }
         } elseif ($_REQUEST['users_id_recipient'] == 0) {
            // we are unassigning a task, i.e.: task un-claim
            $pmResponse = $locCase->unassignCase($_REQUEST['delIndex'],
                                                 $_REQUEST['taskGuid'],
                                                 $_REQUEST['tasktype'],
                                                 $_REQUEST['tasks_id'],
                                                 $_REQUEST['itemtype'],
                                                 ['comment' => $_REQUEST['comment']]);
            if ($pmResponse) {
               Session::addMessageAfterRedirect(__('Task un-claimed!', 'processmaker'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__("Can't un-claim task! Verify 'Assignement Rules' in the process definition.", 'processmaker'), true, ERROR);
            }
         }
      } elseif (isset($_REQUEST['reminder'])) {
         // send notification reminder as requested for this task

         $locCase = new PluginProcessmakerCase;
         $locCase->getFromDB($_REQUEST['cases_id']);
         $glpi_item = new $_REQUEST['itemtype'];
         $glpi_item->getFromDB($_REQUEST['items_id']);
         $pm_task = new PluginProcessmakerTask($_REQUEST['tasktype']);
         $pm_task->getFromDB($_REQUEST['tasks_id']);
         $glpi_task = new $_REQUEST['tasktype'];
         $glpi_task->getFromDB($_REQUEST['tasks_id']);

         // send notification now!
         $pm_task->sendNotification('task_reminder', $glpi_task, $glpi_item, $locCase);

         // Add a follow-up in the hosting item to indicate the sending of the reminder
         $fu = new ITILFollowup();
         $input = $fu->fields;

         $fucontent = sprintf(
             __("Case: '%s',<br>Task: '%s',<br>A reminder has been sent to:<br>", 'processmaker'),
             $locCase->fields['name'],
             Dropdown::getDropdownName("glpi_taskcategories", $glpi_task->fields["taskcategories_id"])
             );

         if (isset($glpi_task->fields['users_id_tech']) && $glpi_task->fields['users_id_tech'] > 0) {
             // get infos for user
             $dbu = new DbUtils;
             $userinfos = $dbu->getUserName($glpi_task->fields['users_id_tech'], 2);
             $fucontent .= "-> " . $userinfos['name'] . "<br>";
         }

         if (isset($glpi_task->fields['groups_id_tech']) && $glpi_task->fields['groups_id_tech'] > 0) {
             // get infos for group
             $grp = new Group();
             $grp->getFromDB($glpi_task->fields['groups_id_tech']);
             $fucontent .= "-> " . $grp->fields['name'] . "<br>";
         }

         $input['content'] = $DB->escape($fucontent);
         $input['is_private'] = 0;
         //$input['requesttypes_id'] = ;
         $input['items_id'] = $_REQUEST['items_id'];
         $input['users_id'] = Session::getLoginUserID(true);
         $input['itemtype'] = $_REQUEST['itemtype'];

         $fu->add($input);

      } elseif (isset($_REQUEST['reminder_settings'])) {
          // here we must add/update the taskrecalls
          $recall = new PluginProcessmakerTaskrecall();
          // if the new_when is less than glpi_currenttime, then set it to glpi_currenttime 
          // to prevent send of many "after reminders" in case of late cron that missed several "after reminders"
          // if the new_when is less than glpi_currenttime, then set it to glpi_currenttime
          $new_when = -1; // by default will delete any records
          if (isset($_REQUEST['actual_before_time']) && $_REQUEST['actual_before_time'] >= 0) {
              $new_when = $_REQUEST['next_reminder_before'];
          } elseif ($_REQUEST['actual_after_time'] >= 0) {
              $new_when = $_REQUEST['next_reminder_after'];
          }
          if ($new_when !== -1 && $_REQUEST['plugin_processmaker_taskrecalls_id'] > 0) {
              $recall->update([
                  'id'          => $_REQUEST['plugin_processmaker_taskrecalls_id'],
                  'before_time' => $_REQUEST['actual_before_time'],
                  'after_time'  => $_REQUEST['actual_after_time'],
                  'when'        => $new_when,
                  'users_id'    => $_REQUEST['actual_users_id']
                  ]);
          } elseif ($new_when !== -1) {
              $recall->add([
                  'plugin_processmaker_tasks_id' => $_REQUEST['plugin_processmaker_tasks_id'],
                  'before_time'                  => $_REQUEST['actual_before_time'],
                  'after_time'                   => $_REQUEST['actual_after_time'],
                  'when'                         => $new_when,
                  'users_id'                     => $_REQUEST['actual_users_id']
                  ]);
          } elseif ($_REQUEST['plugin_processmaker_taskrecalls_id'] > 0) {
              $recall->delete(['id' => $_REQUEST['plugin_processmaker_taskrecalls_id']]);
          }
      }
}

// to return to item
Html::back();

