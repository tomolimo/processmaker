<?php

/**
 * PluginProcessmakerCase short summary.
 *
 * PluginProcessmakerCase description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCase extends CommonDBTM {

   //static public $itemtype = 'itemtype'; // Class name or field name (start with itemtype) for link to Parent
   //static public $items_id = 'items_id'; // Field name
   static $rightname                           = 'plugin_processmaker_case';

   private $process = null;

   const DRAFT     = 'DRAFT';
   const TO_DO     = 'TO_DO';
   const COMPLETED = 'COMPLETED';
   const CANCELLED = 'CANCELLED';

   static function getTypeName($nb = 0) {
      return _n('Process case', 'Process cases', $nb, 'processmaker');
   }

   //static function canCreate() {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}


   static function canView() {
      return Session::haveRightsOr('plugin_processmaker_case', [READ, UPDATE]);
   }

   function canViewItem() {
      return Session::haveRight('plugin_processmaker_case', READ);
   }

   //static function canUpdate( ) {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}

   //function canUpdateItem() {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}

   //function canEdit($ID) {
   //   return parent::canDelete();
   //}

   function maybeDeleted() {
      return false;
   }

   //static function canDelete() {
   //   return parent::canDelete();
   //}

   //function canDeleteItem() {
   //   return parent::canDeleteItem();
   //}

   static function canPurge() {
      return true; //self::canDelete();
   }

   function canPurgeItem() {
      return $_SESSION['glpiactiveprofile']['interface'] == 'central'
         && $this->fields['plugin_processmaker_cases_id'] == 0
         &&  $this->canDeleteItem()
         && (self::canDelete() || $this->fields['case_status'] == PluginProcessmakerCase::DRAFT);
   }

   static function canCancel() {
      return plugin_processmaker_haveRight('case', CANCEL);
   }

   /**
    * Summary of getTabNameForItem
    * @param CommonGLPI $item         is the item
    * @param mixed      $withtemplate has template
    * @return array os strings
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == __CLASS__) {
         // get tab name for a case itself
         $tabname = __('Case', 'processmaker');
         if ($item->fields['plugin_processmaker_cases_id'] > 0) {
            // case is a sub-case
            $tabname = __('Sub-case', 'processmaker');
         }
         return [ 'main' => $tabname."<sup class='tab_nb'> ".self::getStatus($item->fields['case_status'])."</sup>"];
      } else {
         $items_id = $item->getID();
         $itemtype = $item->getType();

         // count how many cases are on this item
         $cnt = count(self::getIDsFromItem($itemtype, $items_id));
         if ($cnt == 0) {
            return ['processmakercases' => __('Process case', 'processmaker')];
         }
         return ['processmakercases' => _n('Process case', 'Process cases', $cnt, 'processmaker')."<sup class='tab_nb'>$cnt</sup>"];
      }
   }


   /**
    * Summary of getName
    * @param mixed $options
    * @return mixed
    */
   function getName($options = []) {
      return $this->fields['name'];
   }


   /**
    * Summary of getIDFromItem
    * @param string $itemtype is the item type
    * @param integer $items_id   is the item id
    * @return integer cases_id
    */
   //static function getIDFromItem($itemtype, $items_id) {
   //   $tmp = New self;
   //   if ($tmp->getFromDBByQuery(" WHERE items_id=$items_id and itemtype='$itemtype'")) {
   //      return $tmp->getID();
   //   }
   //   return false;
   //}


   /**
    * Summary of getIDsFromItem
    * returns an array of the case ids linked to the item
    * @param string $itemtype is the item type of the item (Ticket, Problem, Change)
    * @param mixed $items_id is the GLPI id of the item in the type
    * @return array
    */
   static function getIDsFromItem($itemtype, $items_id) {
      $ret = [];
      $dbu = new DbUtils;
      $restrict = [
          "items_id" => $items_id,
          "itemtype" => $itemtype,];

      //foreach ($dbu->getAllDataFromTable( self::getTable(), "items_id=$items_id AND itemtype='$itemtype'") as $case)
      foreach ($dbu->getAllDataFromTable( self::getTable(), $restrict) as $case) {
         $ret[] = $case['id'];
      }
      return $ret;
   }

   /**
    * Summary of getFromItem
    * @param mixed $itemtype is the item type
    * @param mixed $items_id   is the item id
    * @return mixed: returns false when there is no case associated with the item, else fills in the item fields from DB, and returns true
    */
   //function getFromItem($itemtype, $items_id) {
   //   return $this->getFromDBByQuery(" WHERE items_id=$items_id and itemtype='$itemtype'");
   //}


   /**
    * Summary of getFromGUID
    * @param mixed $case_guid
    * @return boolean
    */
   function getFromGUID($case_guid) {
       $restrict=[
                              'WHERE'  => [
                              'case_guid'  => $case_guid,
                              ],
                    ];
       return $this->getFromDBByRequest($restrict);
   }


   /**
    * Summary of getVariables
    * @param mixed $vars
    * @return mixed
    */
   function getVariables($vars = []) {
      global $PM_SOAP;
      return $PM_SOAP->getVariables($this->fields['case_guid'], $vars);
   }


   /**
    * Summary of sendVariables
    * @param mixed $vars
    * @return A
    */
   function sendVariables($vars = []) {
      global $PM_SOAP;
      return $PM_SOAP->sendVariables($this->fields['case_guid'], $vars);
   }


   /**
    * Summary of getCaseInfo
    * @param mixed $delIndex
    * @return stdClass, a getCaseInfoResponse object, or false exception occured
    */
   function getCaseInfo($delIndex = '') {
      global $PM_SOAP;
      return $PM_SOAP->getCaseInfo($this->fields['case_guid'], $delIndex);
   }


   /**
    * Summary of unpauseCase
    * @param mixed $delIndex
    * @param mixed $userGuid
    * @return an
    */
   function unpauseCase($delIndex, $userGuid) {
      global $PM_SOAP;
      return $PM_SOAP->unpauseCase($this->fields['case_guid'], $delIndex, $userGuid);
   }


   /**
    * Summary of unassignCase
    * Will unassign the delIndex task, restoring the assigned group
    * @param  $delIndex int the delegation index
    * @param  $taskGuid string the GUID of the task
    * @param  $tasktype string the type of task (TicketTask, ChangeTask, ProblemTask)
    * @param  $tasks_id int the id of the task
    * @param  $itemtype string the type of the ITIL object (Ticket, Change, Problem)
    * @return bool
    */
   function unassignCase($delIndex, $taskGuid, $tasktype, $tasks_id, $itemtype, $options) {
      global $PM_DB, $PM_SOAP, $DB;

      // un-claim task
      // will unclaim the task
      // to unclaim a task, we must un-assign the task in the APP_DELEGATION table
      // and un-assign the task in glpi_itemtypeTask table
      $groupname = '';

      // should get the group that is assigned to the task in SELF_SERVICE assign type
      $query = "SELECT TAS_GROUP_VARIABLE FROM TASK WHERE TAS_UID='".$taskGuid."' AND TAS_ASSIGN_TYPE='SELF_SERVICE' LIMIT 1;";
      foreach($PM_DB->request($query) as $pmGroup) {
         // should have only one record
         if ($pmGroup['TAS_GROUP_VARIABLE'] == '') {
            // then we are in the self-service with a group in the TASK_USER table
            // TU_RELATION=2 is groups and TU_TYPE=1 means normal (= not adhoc)
            // then get the group name from the CONTENT table
            $query = "SELECT CONTENT.CON_VALUE FROM TASK_USER
                           JOIN CONTENT ON CONTENT.CON_ID=TASK_USER.USR_UID AND CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG = 'en'
                           WHERE TASK_USER.TAS_UID='".$taskGuid."' AND TASK_USER.TU_RELATION=2 AND TASK_USER.TU_TYPE=1 LIMIT 1;";

            foreach ($PM_DB->request($query) as $onlyrec) {
               $groupname = $onlyrec['CON_VALUE'];
            }


         } else {
            // then we are in the self-service with a case variable that contains either a group either an array of users
            // array of users is not yet supported by PM plugin, as GLPI tasks have one and only one users_id_tech.
            // group guid (in the case variable) must be retrieved from APP DATA

            // TODO
            ////////////////////////////////////////////////////////////////
            // Currently this case is not manageable by GLPI
            // as GLPI needs at least a user or a group to be assigned to a task
            // and when using the self-service value based assignment, there 
            // is a case variable that contains a list of users. This list of users cannot be mapped to a group in GLI
            // OR may be we may create an artificial group in GLPI that would contains the list of users
            ////////////////////////////////////////////////////////////////

            // and then we get the name of the group from the CONTENT table
            //$query = "SELECT CON_VALUE FROM CONTENT
            //               WHERE CONTENT.CON_ID='$groupId' AND CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG='en' ;";

         }
      }

      $groups_id_tech = PluginProcessmakerProcessmaker::getGLPIGroupId($groupname);

      if ($groups_id_tech) {
         // unclaim the case only when a GLPI group can be found

         $query = "UPDATE APP_DELEGATION SET USR_UID='', DEL_INIT_DATE=NULL, USR_ID=0 WHERE APP_NUMBER=".$this->getID()." AND DEL_INDEX=$delIndex;";
         $PM_DB->query($query);

         $glpi_task = new $tasktype;
         $glpi_task->getFromDB($tasks_id);
         $foreignkey = getForeignKeyFieldForItemType( $itemtype );

         $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false); // do not send notification yet
         $glpi_task->update( ['id'              => $glpi_task->getID(),
                              $foreignkey       => $glpi_task->fields[$foreignkey],
                              'users_id_tech'   => 0,
                              'groups_id_tech'  => $groups_id_tech,
                              'update'          => true] );
         PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);

         // send notification now!
         $pm_task = new PluginProcessmakerTask($tasktype);
         $pm_task->getFromDB($tasks_id);
         $glpi_item = new $itemtype;
         $glpi_item->getFromDB($glpi_task->fields[$foreignkey]);
         $pm_task->sendNotification('task_unclaim', $glpi_task, $glpi_item, $this);

         // create an information task and add comment
         $pm_process = $this->getProcess();
         $dbu = new DbUtils;
         $info = __('<b>Task un-claimed!</b><br/><b>Case: </b>%s<br/><b>Task: </b>"%s" has been un-assigned from "%s" and assigned to "%s" group.<br/><b>Reason: </b>%s', 'processmaker');
         $info .= "<input name='caseid' type='hidden' value='".$this->getID()."'><input name='taskid' type='hidden' value='".$pm_task->getID()."'>";
         $taskCat = new TaskCategory;
         $taskCat->getFromDB( $glpi_task->fields['taskcategories_id'] );
         $info = sprintf($info,
                         $this->getNameID(['forceid' => true]),
                         DropdownTranslation::getTranslatedValue($glpi_task->fields['taskcategories_id'], 'TaskCategory', 'name', $_SESSION['glpilanguage'], $taskCat->fields['name']),
                         Html::clean($dbu->getUserName(isset($glpi_task->oldvalues['users_id_tech']) ? $glpi_task->oldvalues['users_id_tech'] : 0)),
                         Html::clean($groupname),
                         $options['comment']
                        );
         // unescape some chars and replace CRLF, CR or LF by <br/>
         $info = str_replace(["\\'", '\\"', '\r\n', '\r', '\n'], ["'", '"', '<br>', '<br>', '<br>'], $info);

         $glpi_task->add([$foreignkey         => $glpi_task->fields[$foreignkey],
                          'is_private'        => 0, // a post-only user can't create private task
                          'taskcategories_id' => $pm_process->fields['taskcategories_id'],
                          'content'           => $DB->escape($info),
                          'users_id'          => $PM_SOAP->taskWriter,
                          'state'             => Planning::INFO,
                          'users_id_tech'     => Session::getLoginUserID(),
                          ]);

         return true;
      }

      return false;
   }


   /**
    * Summary of reassignCase
    * @param mixed $delIndex
    * @param mixed $taskGuid
    * @param mixed $delThread
    * @param mixed $users_id_source
    * @param mixed $users_id_target
    * @return mixed
    */
   function reassignCase($delIndex, $taskGuid, $delThread, $users_id_source, $users_id_target, $options) {
      global $PM_SOAP;
      $users_guid_source = ''; // by default
      if ($users_id_source !== 0) { // when task is not 'to be claimed'
         $users_guid_source = PluginProcessmakerUser::getPMUserId($users_id_source);
      }
      $users_guid_target = PluginProcessmakerUser::getPMUserId($users_id_target);
      $pmResponse = $PM_SOAP->reassignCase($this->fields['case_guid'], $delIndex, $users_guid_source, $users_guid_target);
      // now should manage GLPI Tasks previously assigned to the $users_guid_source
      if ($pmResponse->status_code == 0) {
         // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks
         // we need the delindex of the current glpi task, and the delindex of the new one
         // search for new delIndex and new delThread
         $newCaseInfo = $this->getCaseInfo( );
         $newDelIndex = 0;
         $newDelThread = 0;
         foreach ($newCaseInfo->currentUsers as $newCaseUser) {
            if ($newCaseUser->taskId == $taskGuid && $newCaseUser->delThread == $delThread) {
               $newDelIndex = $newCaseUser->delIndex;
               $newDelThread = $newCaseUser->delThread;
               break;
            }
         }
         $this->reassignTask($delIndex, $newDelIndex, $delThread, $newDelThread, $users_id_target, $options);
         return true;
      }
      return false;
   }


   /**
    * Summary of reassignTask
    * @param mixed $delIndex
    * @param mixed $newDelIndex
    * @param mixed $newTech
    */
   public function reassignTask ($delIndex, $newDelIndex, $delThread, $newDelThread, $newTech, $options) {
      global $DB, $PM_SOAP;

      $dbu = new DbUtils;
      $pm_task_row = $dbu->getAllDataFromTable(PluginProcessmakerTask::getTable(), ['plugin_processmaker_cases_id' => $this->getID(), 'del_index' => $delIndex, 'del_thread' => $delThread]);
      if ($pm_task_row && count($pm_task_row) == 1) {
         $pm_task_row = array_shift($pm_task_row);
         $glpi_task = new $pm_task_row['itemtype'];
         $glpi_task->getFromDB($pm_task_row['items_id']);

         $itilobject_itemtype = $this->fields['itemtype'];
         $foreignkey = getForeignKeyFieldForItemType( $itilobject_itemtype );

         PluginProcessmakerProcessmaker::addWatcher( $itilobject_itemtype, $glpi_task->fields[ $foreignkey ], $newTech );

         $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false); // do not send notification yet
         $glpi_task->update( ['id'              => $glpi_task->getID(),
                              $foreignkey       => $glpi_task->fields[$foreignkey],
                              'users_id_tech'   => $newTech,
                              'groups_id_tech'  => 0,
                              'update'          => true] );
         PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);

         // then update the delIndex and delThread
         $query = "UPDATE glpi_plugin_processmaker_tasks SET del_index = $newDelIndex, del_thread = $newDelThread WHERE id={$pm_task_row['id']}; ";
         $DB->query($query);

         // send notification now!
         $pm_task = new PluginProcessmakerTask($pm_task_row['itemtype']);
         $pm_task->getFromDB($pm_task_row['items_id']);
         $glpi_item = new $itilobject_itemtype;
         $glpi_item->getFromDB($glpi_task->fields[$foreignkey]);
         $pm_task->sendNotification('task_reassign', $glpi_task, $glpi_item, $this);

         // create an information task and add comment
         $pm_process = $this->getProcess();
         $old_users_tech_id = isset($glpi_task->oldvalues['users_id_tech']) ? $glpi_task->oldvalues['users_id_tech'] : 0;
         $taskCat = new TaskCategory;
         $taskCat->getFromDB( $glpi_task->fields['taskcategories_id'] );
         $task_name = DropdownTranslation::getTranslatedValue($glpi_task->fields['taskcategories_id'], 'TaskCategory', 'name', $_SESSION['glpilanguage'], $taskCat->fields['name']);
         $new_tech_name = Html::clean($dbu->getUserName($newTech));
         if ($old_users_tech_id) {
            $info = __('<b>Task re-assigned!</b><br/><b>Case: </b>%s<br/><b>Task: </b>"%s" has been re-assigned from "%s" to "%s".<br/><b>Reason: </b>%s', 'processmaker');
            $info = sprintf($info,
                            $this->getNameID(['forceid' => true]),
                            $task_name,
                            Html::clean($dbu->getUserName(isset($glpi_task->oldvalues['users_id_tech']) ? $glpi_task->oldvalues['users_id_tech'] : 0)),
                            $new_tech_name,
                            $options['comment']
                           );
         } else {
            $info = __('<b>Task assigned!</b><br/><b>Case: </b>%s<br/><b>Task: </b>"%s" has been assigned to "%s".<br/><b>Reason: </b>%s', 'processmaker');
            $info = sprintf($info,
                            $this->getNameID(['forceid' => true]),
                            $task_name,
                            $new_tech_name,
                            $options['comment']
                           );
         }
         $info .= "<input name='caseid' type='hidden' value='".$this->getID()."'><input name='taskid' type='hidden' value='".$pm_task->getID()."'>";

         // unescape some chars and replace CRLF, CR or LF by <br/>
         $info = str_replace(["\\'", '\\"', '\r\n', '\r', '\n'], ["'", '"', '<br>', '<br>', '<br>'], $info);

         $glpi_task->add([$foreignkey         => $glpi_task->fields[$foreignkey],
                          'is_private'        => 0, // a post-only user can't create private task
                          'taskcategories_id' => $pm_process->fields['taskcategories_id'],
                          'content'           => $DB->escape($info),
                          'users_id'          => $PM_SOAP->taskWriter,
                          'state'             => Planning::INFO,
                          'users_id_tech'     => Session::getLoginUserID(),
                          ]);
      }
   }


   /**
    * Summary of getProcess
    * Returns process object
    * @return bool|PluginProcessmakerProcess
    */
   function getProcess() {
      $pm_process = new PluginProcessmakerProcess;
      if (!$this->process && $pm_process->getFromDB($this->fields['plugin_processmaker_processes_id'])) {
         $this->process = $pm_process;
      }
      return $this->process;
   }

   /**
    * Summary of showCaseProperties
    */
   function showCaseProperties() {
      global $DB, $PM_DB;

      // get all tasks that are OPEN for any sub-case of this case
      $case_tasks = [];
      $query = "SELECT `glpi_plugin_processmaker_tasks`.*  FROM `glpi_plugin_processmaker_tasks`
                  WHERE `glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id`={$this->getID()} AND `del_thread_status`='OPEN'";
      foreach ($DB->request($query) as $task) {
         $case_tasks[$task['del_index']] = $task;
      }

      //// get all tasks that are OPEN for any sub-case of this case
      //$sub_cases = [];
      //$query = "SELECT `glpi_plugin_processmaker_tasks`.*  FROM `glpi_plugin_processmaker_tasks`
      //            JOIN `glpi_plugin_processmaker_cases` on `glpi_plugin_processmaker_cases`.`id`=`glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id`
      //            WHERE `glpi_plugin_processmaker_cases`.`plugin_processmaker_cases_id`={$this->getID()} AND `del_thread_status`='OPEN'";
      //foreach($DB->request($query) as $task) {
      //   $sub_cases[$task['plugin_processmaker_cases_id']][$task['del_index']] = $task;
      //}

      $caseInfo = $this->getCaseInfo();
      if (property_exists($caseInfo, 'currentUsers')) {
         $caseInfo->currentUsers = $this->sortTasks($caseInfo->currentUsers, PluginProcessmakerUser::getPMUserId(Session::getLoginUserID()));
      }
      $query = "SELECT `DEL_INDEX`, `DEL_DELEGATE_DATE` FROM `APP_DELEGATION` WHERE `APP_UID`='{$caseInfo->caseId}'";
      $tasks = [];
      foreach ($PM_DB->request($query) as $row) {
         $tasks[$row['DEL_INDEX']] = $row['DEL_DELEGATE_DATE'];
      }

      echo "<p></p>";
      // show the case properties like given by PM server
      $this->showShort($caseInfo);

      // show current (running) tasks properties
      echo "<p></p>";

      echo "<div class='center'>";
      echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";

      echo "<tr><th colspan=4>".__('Current task(s) properties', 'processmaker')."</th></tr>";

      if (property_exists($caseInfo, 'currentUsers') && count($caseInfo->currentUsers) > 0) {
         echo "<tr style='font-weight: bold;'>
               <th>".__('Task', 'processmaker')."</th>
               <th>".__('Task guid', 'processmaker')."</th>
               <th>".__('Current user', 'processmaker')."</th>
               <th>".__('Task delegation date', 'processmaker')."</th>
            </tr>";
         foreach ($caseInfo->currentUsers as $currentTask) {
            $case_url = $this->getLinkURL().'&forcetab=PluginProcessmakerTask$';
            echo "<tr>";
            if (isset($case_tasks[$currentTask->delIndex])) {
               $case_url .= $case_tasks[$currentTask->delIndex]['id'];
               echo "<td class='tab_bg_2'><a href='$case_url'>".$currentTask->taskName."</a></td>";
            } else {
               $res = $PM_DB->query("SELECT APP_UID FROM SUB_APPLICATION WHERE APP_PARENT='{$this->fields['case_guid']}' AND DEL_INDEX_PARENT={$currentTask->delIndex} AND SA_STATUS='ACTIVE'");
               if ($res && $PM_DB->numrows($res) == 1) {
                  $row = $PM_DB->fetch_assoc($res);
                  $sub_case = new PluginProcessmakerCase;
                  $sub_case->getFromGUID($row['APP_UID']);
                  $case_url .= $sub_case->getID()."-".$currentTask->delIndex;
                  echo "<td class='tab_bg_2'><a href='$case_url'><sub>> ".$currentTask->taskName."</sub></a></td>";
               } else {
                  echo "<td class='tab_bg_2'>".$currentTask->taskName."</td>";
               }
            }
            echo "<td class='tab_bg_2'>".$currentTask->taskId."</td>";
            if ($currentTask->userName == '') {
               echo "<td class='tab_bg_2'>".__('To be claimed', 'processmaker')."</td>";
            } else {
               echo "<td class='tab_bg_2'>".$currentTask->userName."</td>";
            }
            echo "<td class='tab_bg_2'>".Html::convDateTime($tasks[$currentTask->delIndex])."</td>";
            echo "</tr>";
         }
      } else {
         echo "<td colspan=4>".__('None')."</td>";
      }

      echo "</table>";

      echo "</div>";

      // show the parent case if it's a sub-case
      if ($this->fields['plugin_processmaker_cases_id'] > 0) {
         echo "<p></p>";
         $sub_case = new self;
         $sub_case->getFromDB($this->fields['plugin_processmaker_cases_id']);
         $sub_case->showShort($sub_case->getCaseInfo(), true);
      }

   }

   /**
    * Summary of showShort
    * @param mixed $caseInfo
    * @param mixed $showparenturl
    */
   function showShort($caseInfo, $showparenturl = false) {

      echo "<div class='center'>";
      echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";

      if ($this->fields['plugin_processmaker_cases_id'] > 0) {
         echo "<tr><th colspan=8>".__('Sub-case properties', 'processmaker')."</th></tr>";
      } else {
         if ($showparenturl) {
            echo "<tr><th colspan=8>".__('Parent case properties', 'processmaker')."</th></tr>";
         } else {
            echo "<tr><th colspan=8>".__('Case properties', 'processmaker')."</th></tr>";
         }
      }

      echo "<tr>";
      echo "<th class='tab_bg_2'>".__('Process', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Case title', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Case number', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Case status', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Case guid', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Creator', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Creation date', 'processmaker')."</th>";
      echo "<th class='tab_bg_2'>".__('Last update', 'processmaker')."</th>";
      //echo "<th class='tab_bg_2'>".__('Case description', 'processmaker')."</th>";
      echo "</tr>";

      echo "<tr>";
      echo "<td class='tab_bg_2'>".$caseInfo->processName."</td>";
      if ($showparenturl) {
         echo "<td class='tab_bg_2'>".$this->getLink()."</td>";
      } else {
         echo "<td class='tab_bg_2'>".$caseInfo->caseName."</td>";
      }
      echo "<td class='tab_bg_2'>".$caseInfo->caseNumber."</td>";
      echo "<td class='tab_bg_2'>".self::getStatus($caseInfo->caseStatus)."</td>";
      echo "<td class='tab_bg_2'>".$caseInfo->caseId."</td>";
      echo "<td class='tab_bg_2'>".$caseInfo->caseCreatorUserName."</td>";
      echo "<td class='tab_bg_2'>".Html::convDateTime($caseInfo->createDate)."</td>";
      echo "<td class='tab_bg_2'>".Html::convDateTime($caseInfo->updateDate)."</td>";
      //echo "<td class='tab_bg_2'>".$caseInfo->????."</td>";
      echo "</tr>";

      echo "</table>";

      echo "</div>";

   }


   /**
    * Summary of localSortTasks
    * used to sort array of tasks in a currenUsers object
    * @param mixed $a
    * @param mixed $b
    * @return integer
    */
   static private function localSortTasks ($a, $b) {
         return $a->delIndex - $b->delIndex;
   }

   /**
    * Summary of sortTasks
    * @param mixed $tasks is the array of tasks from a getCaseInfo->currentUsers
    * @param mixed $GLPICurrentPMUserId
    * @return array sorted $tasks
    */
   public function sortTasks($tasks, $GLPICurrentPMUserId) {

      $tbctasks = [];
      $utasks = [];
      $infotasks = [];

      foreach ($tasks as $caseUser) {
         if ($caseUser->userId == $GLPICurrentPMUserId) {
            $utasks[] = $caseUser;
         } else {
            if ($caseUser->userId == '') { // task to be claimed
               $tbctasks[] = $caseUser;
            } else {
               $infotasks[] = $caseUser;
            }
         }
      }

      // order task by "current user", then by "to be claimed", and then push to end "tasks assigned to another user"
      // then by delindex ASC in these three parts
      usort($utasks, 'self::localSortTasks');
      usort($tbctasks, 'self::localSortTasks');
      usort($infotasks, 'self::localSortTasks');

      return array_merge($utasks, $tbctasks, $infotasks);
   }


   /**
    * Summary of showCaseInfoTab
    * Will show information about the current case
    * @param CommonGLPI $case is a PluginProcessmakerCase object
    * @param mixed $tabnum
    * @param mixed $withtemplate
    */
   static function showCaseInfoTab(CommonGLPI $case, $tabnum = 1, $withtemplate = 0) {
      // echo 'The idea is to show here the GLPI ITIL item to which it is linked, and to give a resume of the current case status, and to give possibility to delete or cancel the case.';
      $itemtype = $case->fields['itemtype'];

      $maintitle = __('Case is linked to a %1s', 'processmaker');
      if ($case->fields['plugin_processmaker_cases_id'] > 0) {
         $maintitle = __('Sub-case is linked to a %1s', 'processmaker');
      }

      echo "<tr><th colspan=12 >".sprintf($maintitle, $itemtype::getTypeName(1))."</th></tr>";

      Ticket::commonListHeader(Search::HTML_OUTPUT);

      $itemtype::showShort($case->fields['items_id']);

      echo "</table>";

      // show case properties
      $case->showCaseProperties();

      if ($case->fields['plugin_processmaker_cases_id'] == 0 && self::canCancel() && $case->fields['case_status'] == self::TO_DO) {

         // it's a main case, not a sub-case
         // and we have the rights to cancel cases

         echo "<p></p>";
         echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";
         echo "<tr><th colspan='2'>".__('Case cancellation', 'processmaker')."</th></tr>";
         echo "<tr><td class='tab_bg_2' style='width: 10%'>".__('Cancel case', 'processmaker')."</td>";
         echo "<td class='tab_bg_2' >";
         echo "<input type='hidden' name='action' value='cancel'>";
         echo "<input type='hidden' name='cases_id' value='".$case->getID()."'>";
         echo "<input onclick='return confirm(\"".__('Confirm cancellation?', 'processmaker')."\");'  type='submit' name='cancel' value='".__('Cancel', 'processmaker')."' class='submit' >";
         echo "</td></tr></table>";

      }

      // will not show delete button if case is a sub-process
      // and will show it only if it is a draft or if current glpi user has the right to delete cases and session is central
      if ($case->canPurgeItem($case->getID())) {

         // then propose a button to delete case
         // the button will be effectively shown by the showFormButtons()

         echo "<p></p>";
         echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";
         echo "<tr><th colspan='12'>".__('Case deletion', 'processmaker')."</th></tr>";
         //   echo "<tr><td class='tab_bg_2' style='width: 10%'>".__('Delete case', 'processmaker')."</td>";
         //   echo "<td class='tab_bg_2' >";
         //   echo "<input type='hidden' name='action' value='delete'>";
         //   echo "<input type='hidden' name='cases_id' value='".$case->getID()."'>";
         //   echo "<input onclick='return confirm(\"".__('Confirm expunge?')."\");'  type='submit' name='delete' value='".__('Delete permanently')."' class='submit' >";
         //   echo "</td></tr>";
         echo "</table>";
         echo "<p></p>";

      }

      return;
   }


   /**
    * Summary of showForItem
    * Shows list of cases attached to an item
    * @param CommonITILObject $item
    */
   static function showForItem(CommonITILObject $item) {
      global $DB, $CFG_GLPI, $PM_SOAP;

      $items_id = $item->getField('id');
      $itemtype = $item->getType();
      //if (!Session::haveRight("problem", Problem::READALL)
      //    || !$item->can($ID, READ)) {

      //   return false;
      //}

      $canupdate = $item->can($items_id, UPDATE);

      $rand = mt_rand();

      $query = "SELECT gppc.`id` AS assocID, gppc.`id` as id, gppp.id as pid, gppp.name as pname, gppc.`case_status`, gppc.`plugin_processmaker_cases_id`
                FROM `glpi_plugin_processmaker_cases` as gppc
                LEFT JOIN `glpi_plugin_processmaker_processes` AS gppp ON gppp.`id`=gppc.`plugin_processmaker_processes_id`
                WHERE gppc.`itemtype` = '$itemtype'
                  AND gppc.`items_id` = $items_id
                ";
      $result = $DB->query($query);

      $cases = [];
      $used  = [];
      $pid   = [];
      if ($numrows = $DB->numrows($result)) {
         while ($data = $DB->fetch_assoc($result)) {
            $cases[$data['id']] = $data;
            $used[$data['id']]  = $data['id'];
            if (isset($pid[$data['pid']])) {
               $pid[$data['pid']] += 1;
            } else {
               $pid[$data['pid']] = 1;
            }
         }
      }

      $columns = ['pname'  => __('Process', 'processmaker'),
                  'name'   => __('Title', 'processmaker'),
                  'status' => __('Status', 'processmaker'),
                  'sub'    => __('Sub-case of', 'processmaker')
           ];

      // check if item is not solved nor closed
      if ($canupdate
            && $item->fields['status'] != CommonITILObject::SOLVED
            && $item->fields['status'] != CommonITILObject::CLOSED
            && $_SESSION['glpiactiveprofile']['interface'] != 'helpdesk'
            && ($numrows < $PM_SOAP->config->fields['max_cases_per_item']
               || $PM_SOAP->config->fields['max_cases_per_item'] == 0)) {
         echo "<div class='firstbloc'>";
         echo "<form style='margin-bottom: 0px' name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
         echo "<input type='hidden' name='action' value='newcase'>";
         echo "<input type='hidden' name='items_id' value='$items_id'>";
         echo "<input type='hidden' name='itemtype' value='$itemtype'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th colspan='3'>".__('Add a new case', 'processmaker')."</th></tr>";

         echo "<tr class='tab_bg_2'><td class='tab_bg_2'>";
         echo __('Select the process you want to add', 'processmaker');
         echo "</td><td class='tab_bg_2'>";
         if ($itemtype == 'Ticket') {
            $is_itemtype = "AND is_incident=1";
            if ($item->fields['type'] == Ticket::DEMAND_TYPE) {
               $is_itemtype = "AND is_request=1";
            }
         } else {
            $is_itemtype = "AND is_".strtolower($itemtype)."=1";
         }
         PluginProcessmakerProcess::dropdown(['value' => 0,
                                              'entity' => $item->fields['entities_id'],
                                              'name' => 'plugin_processmaker_processes_id',
                                              'condition' => "is_active=1 $is_itemtype",
                                              'specific_tags' => ['count_cases_per_item' => $pid]
                                              ]);
         echo "</td><td class='tab_bg_2'>";
         echo "<input type='submit' name='additem' value='"._sx('button', 'Add')."' class='submit'>";
         echo "</td></tr></table>";
         Html::closeForm();
         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canupdate && $numrows) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = ['num_displayed'  => $numrows,
                                      'container'      => 'mass'.__CLASS__.$rand];
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='noHover'><th colspan='12'>".PluginProcessmakerCase::getTypeName($numrows)."</th>";
      echo "</tr>";
      if ($numrows) {
         $header_begin  = "<tr>";
         $header_top    = '';
         $header_bottom = '';
         $header_end    = '';
         if ($canupdate
             && $numrows) {
            $header_top    .= "<th width='11'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_top    .= "</th>";
            $header_bottom .= "<th width='11'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_bottom .= "</th>";
         }

         foreach ($columns as $key => $val) {
            $header_end .= "<th>$val</th>";
         }

         $header_end .= "</tr>";
         echo $header_begin.$header_top.$header_end;

         //Session::initNavigateListItems('PluginProcessmakerCase',
         //                     //TRANS : %1$s is the itemtype name,
         //                     //        %2$s is the name of the item (used for headings of a list)
         //                               sprintf(__('%1$s = %2$s'),
         //                                       $itemtype::getTypeName(1), $item->fields["name"]));

         $i = 0;
         foreach ($cases as $data) {
            Session::addToNavigateListItems('PluginProcessmakerCase', $data["id"]);
            $link         = NOT_AVAILABLE;
            $case = new self;
            if ($case->getFromDB($data["id"])) {
               $link         = $case->getLink();
            }

            echo "<tr class='tab_bg_1'>";
            if ($canupdate) {
               echo "<td width='10'>";
               // prevent massiveaction on subprocess
               if ($data['plugin_processmaker_cases_id'] == 0) {
                  Html::showMassiveActionCheckBox(__CLASS__, $data["assocID"]);
               }
               echo "</td>";
            }
            echo "<td class='center'>".$data['pname']."</td>";
            echo "<td class='center'>".$link."</td>";
            echo "<td class='center'>".self::getStatus($data['case_status'])."</td>";
            echo "<td class='center'>";
            if ($data['plugin_processmaker_cases_id'] > 0) {
               // then this is a subprocess of
               $maincase = new self;
               if ($maincase->getFromDB($data['plugin_processmaker_cases_id'])) {
                  echo $maincase->getLink();
               }
            } else {
               echo '-';
            }
            echo "</td>";
            //echo "<td class='center'>".Html::convDateTime($data["date_creation"])."</td>";
            echo "</tr>";

            $i++;
         }
         echo $header_begin.$header_top.$header_end;

      }

      echo "</table>";
      if ($canupdate && $numrows) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "</div>";
   }

   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item
    * @param mixed $tabnum
    * @param mixed $withtemplate
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $PM_SOAP;

      if ($item->getType() == __CLASS__) {
         // we are in a case viewing the main tab
         // the 'Case infos' tab
         //self::showCaseInfoTab($item, $tabnum, $withtemplate);

      } else {

         // show the list of cases attached to the $item ITIL object
         if (!$PM_SOAP->config->fields['maintenance']) {
            self::showForItem($item);
         } else {
            PluginProcessmakerProcessmaker::showUnderMaintenance();
         }
      }
   }


   /**
   * Summary of deleteTasks
   * will delete all tasks associated with this case from the item
   * @return true if tasks have been deleted from associated item and from case table
   */
   private function deleteTasks() {
      global $DB;
      $ret = false;

      $query = "DELETE FROM glpi_".strtolower($this->fields['itemtype'])."tasks WHERE id IN (SELECT items_id FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id='".$this->fields['id']."')";
      // also delete all information tasks that are linked to this case
      $query2 = "DELETE FROM glpi_".strtolower($this->fields['itemtype'])."tasks WHERE content REGEXP '<input name=\'caseid\' type=\'hidden\' value=\'".$this->fields['id']."\'>'";
      $query3 = "DELETE FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id='".$this->fields['id']."'";
      if ($DB->query($query) && $DB->query($query2) && $DB->query($query3)) {
         $ret = true;         
      }

      return $ret;
   }


   /**
   * Summary of deleteCase
   * will delete case and all tasks associated with this case from the item
   * @return true if case and tasks have been deleted from associated item and from case table
   */
   function deleteCase() {
      return $this->delete(['id' => $this->getID()]);
   }


   /**
   * Summary of cancelTasks
   * will mark as information all to_do tasks
   * BEWARE that this will only be done when case is in TO_DO status
   * @return true if tasks have been deleted from associated item and from case table
   */
   private function cancelTasks() {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         $query = "UPDATE glpi_".$this->fields['itemtype']."tasks SET state=0,users_id_tech=0,groups_id_tech=0,begin=NULL,end=NULL  WHERE state=1 AND id in (select items_id from glpi_plugin_processmaker_tasks where plugin_processmaker_cases_id='".$this->fields['id']."')";
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
   function cancelCase() {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         if ($this->cancelTasks()) {
            if ($this->update( [ 'id' => $this->getID(), 'case_status' => 'CANCELLED' ] )) {
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
     * @param mixed $param is an array containing the item
     * @return bool true to permit solve, false otherwise
     */
   public static function canSolve ($param) {
      $item = $param['item'];
      $cases = self::getIDsFromItem($item->getType(), $item->getID());
      foreach ($cases as $cases_id) {
         $myCase = new self;
         if ($myCase->getFromDB($cases_id)) {
            $pmVar = $myCase->getVariables(['GLPI_ITEM_CAN_BE_SOLVED']);
            if ($myCase->fields['case_status'] != self::COMPLETED
               && $myCase->fields['case_status'] != self::CANCELLED
               && (!isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) || $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1)) {
               // then item can't be solved
               return false;
            }
         }
      }
      return true;
   }

    /**
     * Summary of getToDoTasks
     * @param mixed $item is a Ticket, a Problem or a Change
     * @return array list of tasks with status 'to do' for case associated with item
     */
   public static function getToDoTasks($item) {
      $ret = [];

      $cases = self::getIDsFromItem($item->getType(), $item->getID());
      foreach ($cases as $cases_id) {
         $ret = $ret + PluginProcessmakerTask::getToDoTasks($cases_id, $item->getType()."Task");
      }

      return $ret;
   }


   /**
    * Summary of getMenuContent
    * @return array
    */
   static function getMenuContent() {

      //if (!Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
      //   return;
      //}

      $front_page = "/plugins/processmaker/front";
      $menu = [];
      $menu['title'] = self::getTypeName(Session::getPluralNumber());
      $menu['page']  = "$front_page/case.php";
      $menu['links']['search'] = PluginProcessmakerCase::getSearchURL(false);
      if (Session::haveRightsOr("config", [READ, UPDATE])) {
         $menu['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
      }

      $itemtypes = [
                 'PluginProcessmakerCase' => 'cases'
            ];

      foreach ($itemtypes as $itemtype => $option) {
         //$menu['options'][$option]['title']           = $itemtype::getTypeName(Session::getPluralNumber());
         $menu['options'][$option]['page']            = $itemtype::getSearchURL(false);
         $menu['options'][$option]['links']['search'] = $itemtype::getSearchURL(false);
         if (Session::haveRightsOr("config", [READ, UPDATE])) {
            $menu['options'][$option]['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
         }
         switch ($itemtype) {
            case 'PluginProcessmakerCase':
               //if ($itemtype::canCreate()) {
               //$menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
               //}
               break;
            default :
               $menu['options'][$option]['page']            = PluginProcessmakerProcess::getSearchURL(false);
               break;
         }

      }
      return $menu;
   }

   /**
    * Summary of getSpecificValueToDisplay
    * @param mixed $field
    * @param mixed $values
    * @param array $options
    * @return mixed
    */
   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      global $PM_DB;
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'id':
            if (isset($options['searchopt']['processmaker_cases'])) {
               switch ($options['searchopt']['processmaker_cases']) {
                  case 'creation_date':
                     $res = $PM_DB->query('SELECT * FROM APPLICATION WHERE APP_NUMBER = '.$values['id']);
                     if ($res->num_rows > 0) {
                        $row = $PM_DB->fetch_assoc($res);
                        return Html::convDateTime($row['APP_CREATE_DATE']);
                     }
                     //$locCase = new self;
                     //$locCase->getFromDB($values['id']);
                     //$caseInfo = $locCase->getCaseInfo();
                     //return Html::convDateTime($caseInfo->createDate);
                     break;
                  case 'update_date':
                     $res = $PM_DB->query('SELECT * FROM APPLICATION WHERE APP_NUMBER = '.$values['id']);
                     if ($res->num_rows > 0) {
                        $row = $PM_DB->fetch_assoc($res);
                        return Html::convDateTime($row['APP_UPDATE_DATE']);
                     }
                     //$locCase = new self;
                     //$locCase->getFromDB($values['id']);
                     //$caseInfo = $locCase->getCaseInfo();
                     //return Html::convDateTime($caseInfo->updateDate);
                     break;
               }
            }
            return '-';
         case 'items_id':
            // show an item link
            $item = new $values['itemtype'];
            $item->getFromDB($values['items_id']);
            return $item->getLink(['forceid' => 1]);

         case 'case_status':
            return self::getStatus($values['case_status']);

         case 'itemtype':
            return self::getItemtype($values['itemtype']);

         case 'plugin_processmaker_processes_id':
            $item = new PluginProcessmakerProcess;
            $item->getFromDB($values['plugin_processmaker_processes_id']);
            return $item->getLink();

         case 'plugin_processmaker_cases_id':
            if ($values['plugin_processmaker_cases_id'] != 0) {
               $locSCase = new self;
               $locSCase->getFromDB($values['plugin_processmaker_cases_id']);
               return $locSCase->getLink(['forceid' => 1]);
            }
            return '-';

         default:
            return parent::getSpecificValueToDisplay($field, $values, $options);
      }
   }


   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'case_status':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownStatus($options);
         case 'itemtype':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownItemtype($options);
         case 'plugin_processmaker_processes_id':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            $options['specific_tags'] = ['process_restrict' => 0];
            return PluginProcessmakerProcess::dropdown($options);

         default:
            return parent::getSpecificValueToSelect($field, $name, $values, $options);
      }
   }


   static function dropdownStatus(array $options = []) {

      $p['name']      = 'case_status';
      $p['value']     = self::TO_DO;
      $p['showtype']  = 'normal';
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      switch ($p['showtype']) {
         //case 'allowed' :
         //   $tab = static::getAllowedStatusArray($p['value']);
         //   break;

         case 'search' :
            $tab = static::getAllStatusArray(true);
            break;

         default :
            $tab = static::getAllStatusArray(false);
            break;
      }

      return Dropdown::showFromArray($p['name'], $tab, $p);
   }


   static function getAllStatusArray($withmetaforsearch = false) {

      $tab = [self::DRAFT     => _x('case_status', 'Draft', 'processmaker'),
                   self::TO_DO     => _x('case_status', 'To do', 'processmaker'),
                   self::COMPLETED => _x('case_status', 'Completed', 'processmaker'),
                   self::CANCELLED => _x('case_status', 'Cancelled', 'processmaker')];

      return $tab;
   }

   static function getStatus($value) {

      $tab  = static::getAllStatusArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }

   static function dropdownItemtype(array $options = []) {

      $p['name']      = 'itemtype';
      $p['value']     = 'Ticket';
      $p['showtype']  = 'normal';
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      switch ($p['showtype']) {
         //case 'allowed' :
         //   $tab = static::getAllowedStatusArray($p['value']);
         //   break;

         case 'search' :
            $tab = static::getAllItemtypeArray(true);
            break;

         default :
            $tab = static::getAllItemtypeArray(false);
            break;
      }

      return Dropdown::showFromArray($p['name'], $tab, $p);
   }


   static function getAllItemtypeArray($withmetaforsearch = false) {

      $tab = ['Change'  => Change::getTypeName(1),
              'Ticket'  => Ticket::getTypeName(1),
              'Problem' => Problem::getTypeName(1)
             ];

      return $tab;
   }


   static function getItemtype($value) {
      $tab  = static::getAllItemtypeArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }


   /**
    * Get default values to search engine to override
    **/
   static function getDefaultSearchRequest() {

      $search = ['sort'     => 1,
                 'order'    => 'DESC'];

      return $search;
   }


   /**
    * Summary of rawSearchOptions
    * @return mixed
    */
   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
             'id'                 => 'common',
             'name'               => __('Process cases', 'processmaker')
          ];

      $tab[] = [
         'id'                 => '1',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'datatype'           => 'number',
         'searchtype'         => 'contains',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => __('Title'),
         'datatype'           => 'itemlink',
         'searchtype'         => 'contains',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '3',
         'table'              => $this->getTable(),
         'field'              => 'plugin_processmaker_processes_id',
         'name'               => __('Process', 'processmaker'),
         'datatype'           => 'specific',
         'searchtype'         => [
            '0'                  => 'contains',
            '1'                  => 'equals',
            '2'                  => 'notequals'
         ],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '7',
         'table'              => $this->getTable(),
         'field'              => 'itemtype',
         'name'               => __('Item type'),
         'massiveaction'      => false,
         'datatype'           => 'specific',
         'searchtype'         => [
            '0'                  => 'contains',
            '1'                  => 'equals',
            '2'                  => 'notequals'
         ]
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this->getTable(),
         'field'              => 'items_id',
         'name'               => __('Item'),
         'massiveaction'      => false,
         'datatype'           => 'specific',
         'additionalfields'   => [
            '0'                  => 'itemtype'
         ]
      ];

      $tab[] = [
         'id'                 => '9',
         'table'              => 'glpi_entities',
         'field'              => 'name',
         'name'               => __('Item entity', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'itemlink'
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'case_status',
         'name'               => __('Status'),
         'datatype'           => 'specific',
         'searchtype'         => [
            '0'                  => 'contains',
            '1'                  => 'equals',
            '2'                  => 'notequals'
         ],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '14',
         'table'              => $this->getTable(),
         'field'              => 'plugin_processmaker_cases_id',
         'name'               => __('Sub-case of', 'processmaker'),
         'datatype'           => 'specific',
         'massiveaction'      => false,
         'nosearch'           => true
      ];

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('Creation date'),
         'datatype'           => 'specific',
         'massiveaction'      => false,
         'nosearch'           => true,
         'processmaker_cases' => 'creation_date'
      ];

      $tab[] = [
         'id'                 => '18',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('Last update'),
         'datatype'           => 'specific',
         'massiveaction'      => false,
         'nosearch'           => true,
         'processmaker_cases' => 'update_date'
      ];

      return $tab;
   }


   function showForm ($ID, $options = ['candel'=>false]) {
      $options['colspan'] = 6;
      $options['formtitle'] = sprintf( __('Case status is \'%s\'', 'processmaker'), self::getStatus($this->fields['case_status']));

      $this->showFormHeader($options);

      $process = new PluginProcessmakerProcess;
      $process->getFromDB($this->fields['plugin_processmaker_processes_id']);

      if ($process->fields['maintenance']) {
         PluginProcessmakerProcess::showUnderMaintenance($process->fields['name'], 'small');
      }
      self::showCaseInfoTab($this);

      //echo '</div>' ;

      Html::closeForm();
      $options['candel'] = true;
      $this->showFormButtons($options);

      echo Html::scriptBlock("
         $('#tabsbody th').css('text-align', 'center');
         $('#tabsbody td').css('text-align', 'center');
         ");
   }


   /**
    * Summary of defineTabs
    * @param mixed $options
    * @return array
    */
   function defineTabs($options = []) {

      $process = new PluginProcessmakerProcess;
      $process->getFromDB($this->fields['plugin_processmaker_processes_id']);

      $ong = [];
      if (self::isLayoutWithMain()) {
         $this->addDefaultFormTab($ong);
      }

      if (!$process->fields['maintenance']) {
         $this->addStandardTab('PluginProcessmakerTask', $ong, $options);
      }

      if (!self::isLayoutWithMain()) {
         $this->addStandardTab(__CLASS__, $ong, $options);
      }

      if (!$process->fields['maintenance']) {
         $this->addStandardTab('PluginProcessmakerCasemap', $ong, $options);

         $this->addStandardTab('PluginProcessmakerCasehistory', $ong, $options);

         $this->addStandardTab('PluginProcessmakerCasechangelog', $ong, $options);

         $this->addStandardTab('PluginProcessmakerCasedynaform', $ong, $options);
      }

      return $ong;
   }

   /**
    * Actions done after the PURGE of the item in the database
    *
    * @return nothing
    **/

   /**
    * Summary of post_purgeItem
    * Actions done after the PURGE of the item in the database
    * Will purge the tasks and the PM case and recursively the sub-cases if any
    * @return boolean|integer
    */
   function post_purgeItem() {
      global $PM_SOAP;
      $ret = false;

      $PM_SOAP->login(true);
      if ($this->deleteTasks() && $this->deleteCronTaskActions() && $PM_SOAP->deleteCase($this->fields['case_guid'])->status_code == 0) {
         $ret = true;
         $dbu = new DbUtils;
         // then must delete any sub-processes (sub-cases)
         $restrict = ["plugin_processmaker_cases_id" => $this->getID()];
         //foreach ($dbu->getAllDataFromTable(self::getTable(), "`plugin_processmaker_cases_id` = ".$this->getID()) as $row) {
         foreach ($dbu->getAllDataFromTable(self::getTable(), $restrict) as $row) {
            $tmp = new self;
            $tmp->fields = $row;
            $ret &= $tmp->delete(['id' => $row['id']]);
         }
      }
      return $ret;
   }


   /**
    * Summary of deleteCronTaskActions
    * Will delete any cron task actions taht are linked to current case
    */
   function deleteCronTaskActions() {
      global $DB;

      $query = "DELETE FROM `glpi_plugin_processmaker_crontaskactions` WHERE `plugin_processmaker_cases_id` = ".$this->getID();

      return $DB->query($query);
   }

}
