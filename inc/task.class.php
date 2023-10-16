<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

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
/**
 * tasks short summary.
 *
 * tasks description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerTask extends CommonITILTask
{
   private $itemtype;
   function __construct($itemtype = 'TicketTask') {
      parent::__construct();
      $this->itemtype = $itemtype;
   }


   const OPEN   = 'OPEN';
   const CLOSED = 'CLOSED';
   const INFO   = 'INFO';

   /**
    * Name of the type
    *
    * @param $nb : number of item in the type (default 0)
    **/
   static function getTypeName($nb = 0) {
      return _n('Process case task', 'Process case tasks', $nb, 'processmaker');
   }

   function getItilObjectItemType() {
      return str_replace('Task', '', $this->itemtype);
   }
    /**
     * Summary of getFromDB
     * @param mixed $items_id
     * @return bool
     */
   function getFromDB($items_id) {
      global $DB;

      //if ($this->getFromDBByQuery(" WHERE itemtype='".$this->itemtype."' AND items_id=$items_id;" )) {
      if ($this->getFromDBByRequest([
                     'WHERE'  => [
                        'itemtype'  => $this->itemtype,
                        'items_id'  => $items_id
                     ],
                 ])) {
         $task = new $this->itemtype;
         if ($task->getFromDB( $items_id )) {
            // then we should add our own fields
            $task->fields['items_id'] = $this->fields['id'];
            $task->fields['itemtype'] = $this->fields['itemtype'];
            unset( $this->fields['id'] );
            unset( $this->fields['items_id'] );
            unset( $this->fields['itemtype'] );
            foreach ($this->fields as $field => $val) {
               $task->fields[ $field ] = $val;
            }
            $this->fields = $task->fields;
            return true;
         }
      }

      return false;
   }


   /**
    * Summary of getPMTaskID
    * @return mixed
    */
   function getPMTaskID() {
      return $this->fields['items_id'];
   }

    /**
     * Summary of getToDoTasks
     * returns all 'to do' tasks associated with this case
     * @param mixed $case_id
     */
   public static function getToDoTasks($case_id) {
      global $DB;
      $ret = [];
      $dbu = new DbUtils;
      $selfTable = $dbu->getTableForItemType(__CLASS__);

      $res = $DB->request([
                     'SELECT' => 'items_id AS taskID',
                     'FROM'   => $selfTable,
                     'WHERE'  => [
                        'AND' => [
                           'del_thread_status'            => self::OPEN,
                           'plugin_processmaker_cases_id' => $case_id
                        ]
                     ]
         ]);
      foreach ($res as $row) {
         $ret[$row['taskID']] = $row['taskID'];
      }
      return $ret;
   }

   static function canView() {
      return true;
   }

   static function populatePlanning($params = []) :array {

      $events = [];

      if (isset($params['start'])) {
         $params['begin'] = $params['start']; //'2000-01-01 00:00:00';
         if ($params['type'] == 'group') {
            $params['who_group'] = $params['who'];
            $params['whogroup'] = $params['who'];
            $params['who'] = 0;
         }

         $objects = ['TicketTask', 'ChangeTask', 'ProblemTask'];
         foreach ($_SESSION['glpi_plannings']['filters'] as $tasktype => $iteminfo) {
            if (!$iteminfo['display'] || !in_array($tasktype, $objects)) {
               continue;
            }
            $ret = CommonITILTask::genericPopulatePlanning($tasktype, $params);

            foreach ($ret as $key => $event) {
               // if todo or done but need to show them (=planning)
               if ($event['state'] == Planning::TODO || $event['state'] == Planning::INFO || ($params['display_done_events'] == 1 && $event['state'] == Planning::DONE)) {
                  // check if task is one within a case
                  $pmTask = new PluginProcessmakerTask($tasktype);
                  if ($pmTask->getFromDB($event[strtolower($tasktype).'s_id'])) {
                     $event['editable'] = false;
                     $tmpCase = new PluginProcessmakerCase;
                     $tmpCase->getFromDB($pmTask->fields['plugin_processmaker_cases_id']);
                     $event['url'] = $tmpCase->getLinkURL().'&forcetab=PluginProcessmakerTask$'.$pmTask->fields['items_id'];

                     $taskCat = new TaskCategory;
                     $taskCat->getFromDB( $pmTask->fields['taskcategories_id'] );
                     $taskComment = isset($taskCat->fields['comment']) ? $taskCat->fields['comment'] : '';
                     if (Session::haveTranslations('TaskCategory', 'comment')) {
                        $taskComment = DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskComment );
                     }

                     $event['content'] = str_replace( '##processmaker.taskcomment##', $taskComment, $event['content'] );
                     $event['content'] = str_replace( ['\n##processmakercase.url##', '##processmakercase.url##'], "", $event['content'] );
                     $events[$key]     = $event;
                  }
               }
            }
         }
      }
      return $events;
   }


   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0) {
      global $DB, $PM_DB;

      $tab = [];

      $caseInfo = $case->getCaseInfo();

      if (property_exists($caseInfo, 'currentUsers')) {
         $dbu = new DbUtils;
         $GLPICurrentPMUserId = PluginProcessmakerUser::getPMUserId(Session::getLoginUserID());

         // get all tasks that are OPEN for this case
         $tasks = [];
         $res = $DB->request(
                        'glpi_plugin_processmaker_tasks', [
                           'AND' => [
                              'plugin_processmaker_cases_id' => $case->fields['id'],
                              'del_thread_status'            => self::OPEN
                           ]
                        ]
            );
         foreach ($res as $task) {
            $tasks[$task['del_index']] = $task;
         }

         $caseInfo->currentUsers = $case->sortTasks($caseInfo->currentUsers, $GLPICurrentPMUserId);

         $main_tasks = []; //will contains the tasks that are main-processes
         foreach ($caseInfo->currentUsers as $caseUser) {
            $title = $caseUser->taskName;
            if (isset($tasks[$caseUser->delIndex])) {
               $hide_claim_button = false;
               if ($caseUser->userId == '') { // task to be claimed
                  $itemtask = $dbu->getItemForItemtype($tasks[$caseUser->delIndex]['itemtype']);
                  $itemtask->getFromDB($tasks[$caseUser->delIndex]['items_id']);
                  // check if this group can be found in the current user's groups
                  if (!isset($_SESSION['glpigroups']) || !in_array( $itemtask->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
                     $hide_claim_button = true;
                  }
               }
               $tab[$tasks[$caseUser->delIndex]['id']] = ($caseUser->userId != '' && $caseUser->userId != $GLPICurrentPMUserId) || $hide_claim_button ? "<i><sub>$title</sub></i>" : $title;
            } else {
               $main_tasks[$caseUser->delIndex] = $caseUser;
            }
         }

         foreach ($main_tasks as $task) {
            $res = $PM_DB->request([
                              'SELECT' => 'APP_UID',
                              'FROM'   => 'SUB_APPLICATION',
                              'WHERE'  => [
                                 'AND' => [
                                    'APP_PARENT'       => $case->fields['case_guid'],
                                    'DEL_INDEX_PARENT' => $task->delIndex,
                                    'SA_STATUS'        => 'ACTIVE'
                                 ]
                              ]
               ]);
            if ($res->numrows() == 1 && $row = $res->next()) {
               //$row = $PM_DB->fetch_assoc($res);
               $loc_case = new PluginProcessmakerCase;
               $loc_case->getFromGUID($row['APP_UID']);
               $tab[$loc_case->getID()."-".$task->delIndex] = "<i><sub>> ".$task->taskName."</sub></i>";
            }
         }

      }

      return $tab;

   }


   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $case the PluginProcessmakerCase
    * @param integer $tabnum contains the PluginProcessmakerTask id
    * @param mixed $withtemplate
    */
   static function displayTabContentForItem(CommonGLPI $case, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $PM_SOAP, $DB, $PM_DB;
      $dbu = new DbUtils;

      $rand = rand();

      // check if we are going to view a sub-task, then redirect to sub-case itself
      if (preg_match('/^(?\'cases_id\'\d+)-(\d+)$/', $tabnum, $matches)) {
         // Show sub-task list

         // get all tasks that are OPEN for any sub-case of this case
         $sub_tasks = [];
         $res = $DB->request(
                        'glpi_plugin_processmaker_tasks AS ppt',
                        ['AND' => [
                           'ppt.plugin_processmaker_cases_id' => $matches['cases_id'],
                           'del_thread_status'  => self::OPEN]
                        ]
                     );
         //$query = "SELECT `glpi_plugin_processmaker_tasks`.*  FROM `glpi_plugin_processmaker_tasks`
         //         WHERE `glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id`={$matches['cases_id']} AND `del_thread_status`='OPEN'";
         //foreach ($DB->request($query) as $task) {
         foreach ($res as $task) {
            $sub_tasks[$task['plugin_processmaker_cases_id']][$task['del_index']] = $task;
         }
         $sub_case = new PluginProcessmakerCase;
         $sub_case->getFromDB($matches['cases_id']);
         $sub_case_url = $sub_case->getLinkURL().'&forcetab=PluginProcessmakerTask$';

         $res = $PM_DB->request([
                           'SELECT' => ['DEL_INDEX', 'DEL_DELEGATE_DATE'],
                           'FROM'   => 'APP_DELEGATION',
                           'WHERE'  => [
                              'APP_UID' => $sub_case->fields['case_guid']
                           ]
            ]);
         $sub_tasks_pm = [];
         foreach ($res as $row) {
            $sub_tasks_pm[$row['DEL_INDEX']] = $row['DEL_DELEGATE_DATE'];
         }

         $sub_case_info = $sub_case->getCaseInfo();
         echo "<div class='center'>";
         echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";

         echo "<tr><th colspan=4>".__('Sub-case task(s)', 'processmaker')."</th></tr>";

         if (property_exists($sub_case_info, 'currentUsers') && count($sub_case_info->currentUsers) > 0) {

            echo "<tr style='font-weight: bold;'>
               <th>".__('Task', 'processmaker')."</th>
               <th>".__('Task guid', 'processmaker')."</th>
               <th>".__('Current user', 'processmaker')."</th>
               <th>".__('Task delegation date', 'processmaker')."</th>
            </tr>";

            foreach ($sub_case_info->currentUsers as $currentTask) {
               echo "<tr>";
               $sub_case_url .= $sub_tasks[$matches['cases_id']][$currentTask->delIndex]['id'];
               echo "<td class='tab_bg_2'><a href='$sub_case_url'>".$currentTask->taskName."</a></td>";
               echo "<td class='tab_bg_2'>".$currentTask->taskId."</td>";
               if ($currentTask->userName == '') {
                  echo "<td class='tab_bg_2'>".__('To be claimed', 'processmaker')."</td>";
               } else {
                  echo "<td class='tab_bg_2'>".$currentTask->userName."</td>";
               }
               echo "<td class='tab_bg_2'>".$sub_tasks_pm[$currentTask->delIndex]."</td>";
               echo "</tr>";
            }
         } else {
            echo "<td colspan=4>".__('None')."</td>";
         }

         echo "</table>";
         echo "</div>";

         return;
      }

      $hide_claim_button = false;
      $task_to_claim = false;

      // get infos for the current task
      $restrict = [
          "id" => $tabnum
          ];
      $task = $dbu->getAllDataFromTable('glpi_plugin_processmaker_tasks', $restrict);

      // shows the re-assign form
      $caseInfo = $case->getCaseInfo();
      $currentUser = null;
      foreach ($caseInfo->currentUsers as $locTask) {
         if ($locTask->delIndex == $task[$tabnum]['del_index']) {
            $currentUser = $locTask;
            break;
         }
      }

      if (isset($currentUser)) {
         if ($task[$tabnum]['del_index']) {
            // to load users for task re-assign only when task is not a sub-case

            echo "<div class='tab_bg_2' id='divUsers-{$currentUser->delIndex}-{$rand}'><div class='loadingindicator'>".__('Loading...')."</div></div>";

            // try to get users whom can't be assigned to this task
            // already assigned user can't be assigned again to this task
            $current_assigned_user = PluginProcessmakerUser::getGLPIUserId($currentUser->userId);

            // and then any forbiden users defined from the case itself
            $casevariablevalues = $case->getVariables(['GLPI_TASK_PREVENT_REASSIGN']);
            $prevent_assign = [];
            if (array_key_exists( 'GLPI_TASK_PREVENT_REASSIGN', $casevariablevalues ) && $casevariablevalues[ 'GLPI_TASK_PREVENT_REASSIGN' ] != '') {
               $prevent_assign = json_decode($casevariablevalues[ 'GLPI_TASK_PREVENT_REASSIGN' ], true);
            }


            $used_users = [];
            $used_users[] = $current_assigned_user;
            if (array_key_exists($currentUser->taskId, $prevent_assign)) {
               if (!is_array($prevent_assign[$currentUser->taskId])) {
                  $prevent_assign[$currentUser->taskId] = [$prevent_assign[$currentUser->taskId]];
               }
               foreach ($prevent_assign[$currentUser->taskId] as $pmuser) {
                  $usr_id = PluginProcessmakerUser::getGlpiIdFromAny($pmuser);
                  if ($usr_id) {
                     $used_users[] = $usr_id;
                  }
               }
            }

            $data = "{
                     cases_id  : {$case->getID()},
                     items_id  : {$case->fields['items_id']},
                     itemtype  : '{$case->fields['itemtype']}',
                     tasktype  : '{$task[$tabnum]['itemtype']}',
                     tasks_id  : {$task[$tabnum]['items_id']},
                     users_id  : {$current_assigned_user},
                     caseGuid  : '{$case->fields['case_guid']}',
                     taskGuid  : '{$currentUser->taskId}',
                     delIndex  : {$task[$tabnum]['del_index']},
                     delThread : {$currentUser->delThread},
//                     used      : [".join(',', array_unique($used_users))."] // not set to be able to alert when trying to re-assigned to the same user
                     }";
            echo html::scriptBlock("$('#divUsers-{$task[$tabnum]['del_index']}-{$rand}').load('".Plugin::getWebDir('processmaker')."/ajax/task_users.php', $data);");
         }

         if (!$currentUser->userId || !$task[$tabnum]['del_index']) {
            // manages the claim
            // current task is to be claimed
            $task_to_claim = true;
            // get the assigned group to the item task
            $itemtask = $dbu->getItemForItemtype( $task[$tabnum]['itemtype'] );
            $itemtask->getFromDB( $task[$tabnum]['items_id'] );
            // check if this group can be found in the current user's groups
            if (!isset($_SESSION['glpigroups']) || !in_array( $itemtask->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
               $hide_claim_button = true;
            }
         }
      }

      $formId = 'caseform-task-' . $task[$tabnum]['del_index'] . '-' . $rand;
      $iframeId = 'caseiframe-task-' . $task[$tabnum]['del_index'] . '-' . $rand;

      $glpi_data = urlencode(json_encode([
          'glpi_url'               => $CFG_GLPI['url_base'],
          'glpi_tabtype'           => 'task',
          'glpi_iframeid'          => $iframeId,
          'glpi_sid'               => $PM_SOAP->getPMSessionID(),
          'glpi_app_uid'           => $case->fields['case_guid'],
          'glpi_del_index'         => $task[$tabnum]['del_index'],
          'glpi_pro_uid'           => $caseInfo->processId,
          'glpi_hide_claim_button' => $hide_claim_button,
          'glpi_task_to_claim'     => $task_to_claim,
          'glpi_task_guid'         => $currentUser->taskId
          ]));

      $url = $PM_SOAP->serverURL
         ."/cases/cases_Open"
         ."?sid=" . $PM_SOAP->getPMSessionID()
         ."&APP_UID=" . $case->fields['case_guid']
         ."&DEL_INDEX=" . $task[$tabnum]['del_index']
         ."&action=TO_DO"
         ."&glpi_data=$glpi_data";

      $formurl = $case->getLinkURL();
      echo "<form id='$formId' name='$formId' methof='post', action='$formurl'>";
      echo "<iframe id='$iframeId' name='$iframeId' style='border:none;' class='tab_bg_2' width='100%' src='$url'></iframe>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('action', ['value' => $task_to_claim ? 'claim' : 'route']);
      echo Html::hidden('APP_UID', ['value' => $case->fields['case_guid']]);
      echo Html::hidden('DEL_INDEX', ['value' => $task[$tabnum]['del_index']]);

      echo "</form>";

   }


   /**
    * Summary of sendNotification
    * Will send either dedicated notification, or standard one
    * @param string $type is 'task_add', 'task_reassign', 'task_done', 'task_reminder'
    * @param CommonITILTask $task is the task (TicketTask,...)
    * @param CommonITILObject $item is the ITIL item (Ticket,...)
    * @param PluginProcessmakerCase $case is the case
    */
   function sendNotification(string $type, CommonITILTask $task, CommonITILObject $item, PluginProcessmakerCase $case = null) {
      // Notification management
      // search if at least one active notification is existing for that pm task with that event 'task_update_'.$glpi_task->fields['taskcategories_id']
      $res = PluginProcessmakerNotificationTargetTask::getNotifications($type, $task->fields['taskcategories_id'], $item->fields['entities_id']);
      if ($res['notifications'] && count($res['notifications']) > 0) {
         NotificationEvent::raiseEvent($res['event'],
                                       $this,
                                       ['plugin_processmaker_cases_id' => $this->fields['plugin_processmaker_cases_id'],
                                        'itemtype'          => $item->getType(),
                                        'task_id'           => $task->getID(),
                                        'old_users_id_tech' => isset($task->oldvalues['users_id_tech']) ? $task->oldvalues['users_id_tech'] : 0,
                                        'is_private'        => isset($task->fields['is_private']) ? $task->fields['is_private'] : 0,
                                        'entities_id'       => $item->fields['entities_id'],
                                        'case'              => $case,
                                        'obj'               => $item
                                       ]);
      } else {
         NotificationEvent::raiseEvent(PluginProcessmakerNotificationTargetTask::getDefaultGLPIEvents($type),
                                       $item,
                                       ['plugin_processmaker_cases_id' => $this->fields['plugin_processmaker_cases_id'],
                                        'itemtype'                     => $item->getType(),
                                        'task_id'                      => $task->getID(),
                                        'is_private'                   => isset($task->fields['is_private']) ? $task->fields['is_private'] : 0
                                       ]);
      }

   }

}
