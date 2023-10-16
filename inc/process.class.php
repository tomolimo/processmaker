<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2022 by Raynet SAS a company of A.Raymond Network.

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
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * process short summary.
 *
 * process description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerProcess extends CommonDBTM {

   const CLASSIC = 'classic';
   const BPMN    = 'bpmn';

   static $rightname                           = 'plugin_processmaker_config';


   static function canCreate() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }


   static function canView() {
      return Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE]);
   }


   static function canUpdate() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }


   function canUpdateItem() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }


   function maybeDeleted() {
      return false;
   }


//   static function getIcon() {
////      return "fas fa-code-branch fa-rotate-90";
//      return "fas fa-blog fa-flip-vertical";
////      return "fas fa-cogs fa-flip-vertical";
//   }


   /**
    * Get default values to search engine to override
    **/
   static function getDefaultSearchRequest() {

      $search = ['sort'     => 1,
                 'order'    => 'ASC'];

      return $search;
   }

   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      $action = $ma->getAction();

      switch ($action) {
         case 'taskrefresh' :
            foreach ($ids as $pid) {
               if (!$item->canUpdateItem()) {
                  $ma->itemDone(__CLASS__, $pid, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                  continue;
               }

               $item->refreshTasks($pid);
               $ma->itemDone(__CLASS__, $pid, MassiveAction::ACTION_OK);
            }
            break;
      }
   }

   /**
   * Summary of refreshTasks
   * will refresh (re-synch) all process task list
   * @param integer $ID is the id of the process
   * @return void
   */
   function refreshTasks($ID) {
      global $PM_DB, $CFG_GLPI;

      if ($this->getFromDB($ID)) {
         // here we are in the right process
         // we need to get the tasks + content from PM db
         $mapLangs = [];
         $dbu = new DbUtils;
         //         if (class_exists('DropdownTranslation')) {
            // to force rights to add translations
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['name'] = 'name';
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['completename'] = 'completename';
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['comment'] = 'comment';
            //$translates = true;
            // create a reversed map for languages
         foreach ($CFG_GLPI['languages'] as $key => $valArray) {
            $lg = locale_get_primary_language( $key );
            $mapLangs[$lg][] = $key;
            $mapLangs[$key][] = $key; // also add complete lang
         }
         //}
         $lang = locale_get_primary_language( $CFG_GLPI['language'] );
         $query = [
                     'SELECT'     => ['TASK.TAS_UID', 'TASK.TAS_START', 'TASK.TAS_TYPE', 'CONTENT.CON_LANG', 'CONTENT.CON_CATEGORY', 'CONTENT.CON_VALUE'],
                     'FROM'       => 'TASK',
                     'INNER JOIN' => [
                        'CONTENT'   => [
                           'FKEY'   => [
                              'CONTENT' => 'CON_ID',
                              'TASK'    => 'TAS_UID'
                           ]
                        ]
                     ],
                     'WHERE'      => [
                        'AND' => [
                           'TASK.TAS_TYPE'         => ['NORMAL', 'SUBPROCESS'],
                           'TASK.PRO_UID'          => $this->fields['process_guid'],
                           'CONTENT.CON_CATEGORY'  => ['TAS_TITLE', 'TAS_DESCRIPTION']
                        ]
                     ]
                  ];
         //if (!$translates) {
         //   $query['WHERE']['AND']['CONTENT.CON_LANG'] = $lang;
         //}
         //$query = "SELECT TASK.TAS_UID, TASK.TAS_START, TASK.TAS_TYPE, CONTENT.CON_LANG, CONTENT.CON_CATEGORY, CONTENT.CON_VALUE FROM TASK
         //               INNER JOIN CONTENT ON CONTENT.CON_ID=TASK.TAS_UID
         //               WHERE (TASK.TAS_TYPE = 'NORMAL' OR TASK.TAS_TYPE = 'SUBPROCESS') AND TASK.PRO_UID = '".$this->fields['process_guid']."' AND CONTENT.CON_CATEGORY IN ('TAS_TITLE', 'TAS_DESCRIPTION') ".($translates ? "" : " AND CONTENT.CON_LANG='$lang'")." ;";
         $taskArray = [];
         $defaultLangTaskArray = [];
         foreach ($PM_DB->request( $query ) as $task) {
            if ($task['CON_LANG'] == $lang) {
               $defaultLangTaskArray[$task['TAS_UID']][$task['CON_CATEGORY']] = $task['CON_VALUE'];
               $defaultLangTaskArray[$task['TAS_UID']]['is_start'] = ($task['TAS_START'] == 'TRUE' ? 1 : 0);
               $defaultLangTaskArray[$task['TAS_UID']]['is_subprocess'] = ($task['TAS_TYPE'] == 'SUBPROCESS' ? 1 : 0);
            } else {
               foreach ($mapLangs[ $task['CON_LANG'] ] as $valL) {
                  $taskArray[ $task['TAS_UID'] ][ $valL ][ $task['CON_CATEGORY'] ]  = $task['CON_VALUE'];
               }
            }
         }

         $pmtask = new PluginProcessmakerTaskCategory;
         $restrict = ["is_active" => '1', 'plugin_processmaker_processes_id' => $this->getID()];
         $currentasksinprocess = $dbu->getAllDataFromTable($pmtask->getTable(), $restrict);
         $tasks=[];
         foreach ($currentasksinprocess as $task) {
            $tasks[$task['pm_task_guid']] = $task;
         }
         $inactivetasks = array_diff_key($tasks, $defaultLangTaskArray);
         foreach ($inactivetasks as $task) {
            // must verify if this taskcategory are used in a task somewhere
            $objs = ['TicketTask', 'ProblemTask', 'ChangeTask'];
            $countElt = 0;
            foreach ($objs as $obj) {
               $countElt += $dbu->countElementsInTable( $dbu->getTableForItemType($obj), ["taskcategories_id" => $task['taskcategories_id']] );
               if ($countElt != 0) {
                  // just set 'is_active' to 0
                  $pmtask->Update(['id' => $task['id'], 'is_start' => 0, 'is_active' => 0]);
                  break;
               }
            }
            if ($countElt == 0) {
               // purge this category as it is not used anywhere
               $taskCat = new TaskCategory;
               $taskCat->delete(['id' => $task['taskcategories_id']], 1);
               $pmTaskCat = new PluginProcessmakerTaskCategory;
               $pmTaskCat->delete(['id' => $task['id']], 1);
            }
         }

         foreach ($defaultLangTaskArray as $taskGUID => $task) {
            $pmTaskCat = new PluginProcessmakerTaskCategory;
            $taskCat = new TaskCategory;
            if ($pmTaskCat->getFromGUID( $taskGUID )) {
               // got it then check names, and if != update
               if ($taskCat->getFromDB($pmTaskCat->fields['taskcategories_id'])) {
                  // found it must test if should be updated
                  if ($taskCat->fields['name'] != $task['TAS_TITLE']
                     || $taskCat->fields['comment'] != $task['TAS_DESCRIPTION']) {
                     $taskCat->update([
                        'id'                => $taskCat->getID(),
                        'name'              => $PM_DB->escape($task['TAS_TITLE']),
                        'comment'           => $PM_DB->escape($task['TAS_DESCRIPTION']),
                        'taskcategories_id' => $this->fields['taskcategories_id'],
                        'is_active'         => 0 // to prevent use of this task cat in manual tasks
                        ] );
                  }
                  if ($pmTaskCat->fields['is_start'] != $task['is_start']) {
                         $pmTaskCat->update(['id' =>  $pmTaskCat->getID(), 'is_start' => $task['is_start']]);
                  }
               } else {
                  // taskcat must be created
                  $taskCat->add([
                     'is_recursive'      => true,
                     'name'              => $PM_DB->escape($task['TAS_TITLE']),
                     'comment'           => $PM_DB->escape($task['TAS_DESCRIPTION']),
                     'taskcategories_id' => $this->fields['taskcategories_id'],
                     'is_active'         => 0 // to prevent use of this task cat in manual tasks
                     ] );
                  // update pmTaskCat
                  $pmTaskCat->update(['id' => $pmTaskCat->getID(), 'taskcategories_id' => $taskCat->getID(), 'is_start' => $task['is_start']]);
               }
            } else {
               // should create a new one
               // taskcat must be created
               $taskCat->add([
                  'is_recursive'      => true,
                  'name'              => $PM_DB->escape($task['TAS_TITLE']),
                  'comment'           => $PM_DB->escape($task['TAS_DESCRIPTION']),
                  'taskcategories_id' => $this->fields['taskcategories_id'],
                  'is_active'         => 0 // to prevent use of this task cat in manual tasks
                  ] );
               // pmTaskCat must be created too
               $pmTaskCat->add([
                  'plugin_processmaker_processes_id' => $this->getID(),
                  'pm_task_guid'                     => $taskGUID,
                  'taskcategories_id'                => $taskCat->getID(),
                  'is_start'                         => $task['is_start'],
                  'is_active'                        => 1,
                  'is_subprocess'                    => $task['is_subprocess']
                  ]);
            }
            // here we should take into account translations if any
            if (isset($taskArray[ $taskGUID ])) {
               foreach ($taskArray[ $taskGUID ] as $langTask => $taskL) {
                  // look for 'name' field
                  if ($loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'name', $langTask )) {
                     if (DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'name', $langTask ) != $taskL[ 'TAS_TITLE' ]) {
                        // must be updated
                        $trans = new DropdownTranslation;
                        $trans->update( [ 'id' => $loc_id, 'field' => 'name', 'value' => $PM_DB->escape($taskL[ 'TAS_TITLE' ]), 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ] );
                        $trans->generateCompletename( [ 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ] );
                     }
                  } else {
                     // must be added
                     // must be updated
                     $trans = new DropdownTranslation;
                     $trans->add( [ 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'name', 'value' => $PM_DB->escape($taskL[ 'TAS_TITLE' ]) ] );
                     $trans->generateCompletename( [ 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(),'language' => $langTask ] );
                  }

                  // look for 'comment' field
                  if ($loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'comment', $langTask )) {
                     if (DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $langTask ) != $taskL[ 'TAS_DESCRIPTION' ]) {
                        // must be updated
                        $trans = new DropdownTranslation;
                        $trans->update( [ 'id' => $loc_id, 'field' => 'comment', 'value' => $PM_DB->escape($taskL[ 'TAS_DESCRIPTION' ]) , 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask] );
                     }
                  } else {
                     // must be added
                     $trans = new DropdownTranslation;
                     $trans->add( [ 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'comment', 'value' => $PM_DB->escape($taskL[ 'TAS_DESCRIPTION' ]) ] );
                  }

               }
            }
         }

      }

   }

   function prepareInputForAdd($input) {
      global $PM_DB;
      if (isset($input['name'])) {
         $input['name'] = $PM_DB->escape($input['name']);
      }
      return $input;
   }

   function prepareInputForUpdate($input) {
      global $PM_DB;
      if (isset($input['name'])) {
         $input['name'] = $PM_DB->escape($input['name']);
      }
      return $input;
   }

   function post_addItem() {
      $this->getFromDB($this->getID());
   }

   function post_updateItem($history = 1) {
      $this->getFromDB($this->getID());
   }

   /**
   * Summary of refresh
   * used to refresh process list and task category list
   * @return void
   */
   function refresh() {
      global $DB, $PM_SOAP;
      $dbu = new DbUtils;
      $pmCurrentProcesses = [];

      // then refresh list of available process from PM to inner table
      $PM_SOAP->login( true );
      $pmProcessList = $PM_SOAP->processList();

      //$config = $PM_SOAP->config; // $PM_PluginProcessmakerConfig::getInstance();
      $pmMainTaskCat = $PM_SOAP->config['taskcategories_id'];

      // and get processlist from GLPI
      if ($pmProcessList) {
         foreach ($pmProcessList as $process) {
            $glpiprocess = new PluginProcessmakerProcess;
            if ($glpiprocess->getFromGUID($process->guid)) {
               // then update it only if name has changed
               if ($glpiprocess->fields['name'] != $process->name) {
                  $glpiprocess->update( [ 'id' => $glpiprocess->getID(), 'name' => $process->name] );
               }
               // and check if main task category needs update
               if (!$glpiprocess->fields['taskcategories_id']) {
                  // then needs to be added
                  $glpiprocess->addTaskCategory( $pmMainTaskCat );
               } else {
                  $glpiprocess->updateTaskCategory( $pmMainTaskCat );
               }
            } else {
               // create it
               if (isset( $process->project_type )) {
                  $project_type = $process->project_type;
               } else {
                  $project_type = 'classic';
               }

               if ($glpiprocess->add( [ 'process_guid' => $process->guid, 'name' => $process->name, 'project_type' => $project_type ])) {
                  // and add main task category for this process
                  $glpiprocess->addTaskCategory( $pmMainTaskCat );
               }
            }
            $pmCurrentProcesses[$glpiprocess->getID()] = $glpiprocess->getID();
         }
      }

      // should de-activate other
      $glpiCurrentProcesses = $dbu->getAllDataFromTable(self::getTable());
      // get difference between PM and GLPI
      foreach (array_diff_key($glpiCurrentProcesses, $pmCurrentProcesses) as $key => $process) {
         $proc = new PluginProcessmakerProcess;
         $proc->getFromDB($key);

         // check if at least one case is existing for this process
         $res = $DB->request(
                        PluginProcessmakerCase::getTable(), [
                        'plugin_processmaker_processes_id' => $key
                        ]
               );
         //$query = "SELECT * FROM `".PluginProcessmakerCase::getTable()."` WHERE `plugin_processmaker_processes_id` = ".$key;
         //$res = $DB->query($query);
         if ($res->numrows() === 0) {
            // and if no will delete the process
            $proc->delete(['id' => $key]);
            // delete main taskcat
            $tmp = new TaskCategory;
            $tmp->delete(['id' => $proc->fields['taskcategories_id']]);

            // must delete processes_profiles if any
            $tmp = new PluginProcessmakerProcess_Profile;
            $tmp->deleteByCriteria(['plugin_processmaker_processes_id' => $key]);

            // must delete any taskcategory and translations
            $restrict = ["plugin_processmaker_processes_id" => $key];
            //$pmtaskcategories = $dbu->getAllDataFromTable( PluginProcessmakerTaskCategory::getTable(), "plugin_processmaker_processes_id = $key");
            $pmtaskcategories = $dbu->getAllDataFromTable( PluginProcessmakerTaskCategory::getTable(), $restrict );
            foreach ($pmtaskcategories as $pmcat) {
               // delete taskcat
               $tmp = new TaskCategory;
               $tmp->delete(['id' => $pmcat['taskcategories_id']]);

               // delete pmtaskcat
               $tmp = new PluginProcessmakerTaskCategory;
               $tmp->delete(['id' => $pmcat['id']]);

               // delete any translations
               $tmp = new DropdownTranslation;
               $tmp->deleteByCriteria(['itemtype' => 'TaskCategory', 'items_id' => $pmcat['taskcategories_id']]);
            }
         } else {
            // set it as inactive
            $proc->update(['id' => $key, 'is_active' => 0]);
         }
      }

   }

   /**
   * Summary of updateTaskCategory
   * Updates TaskCategory for current process, only if needed (i.e. name has changed)
   * @param integer $pmMainTaskCat is the id of the main task category
   * @return boolean true if update is done, false otherwise
   */
   function updateTaskCategory($pmMainTaskCat) {
      global $PM_DB;
      $taskCat = new TaskCategory;
      if ($taskCat->getFromDB( $this->fields['taskcategories_id'] ) && $taskCat->fields['name'] != $this->fields['name']) {
         return $taskCat->update([
            'id'                => $taskCat->getID(),
            'taskcategories_id' => $pmMainTaskCat,
            'name'              => $PM_DB->escape($this->fields['name']),
            'is_active'         => 0 // to prevent use of this task cat in a manual task
            ] );
      }
      return false;
   }

   /**
   * Summary of addTaskCategory
   * Adds a new TaskCategory for $this process
   * @param int $pmMainTaskCat is the main TaskCategory from PM configuration
   * @return boolean true if TaskCategory has been created and updated into $this process, else otherwise
   */
   function addTaskCategory($pmMainTaskCat) {
      global $PM_DB;
      $taskCat = new TaskCategory;
      if ($taskCat->add([
            'is_recursive'      => true,
            'taskcategories_id' => $pmMainTaskCat,
            'name'              => $PM_DB->escape($this->fields['name']),
            'is_active'         => 0 // to prevent use of this task cat in a manual task
            ])) {
         return $this->update( [ 'id' => $this->getID(), 'taskcategories_id' => $taskCat->getID() ] );
      }
      return false;
   }


   /**
   * Print a good title for process pages
   * add button for re-synchro of process list (only if rigths are w)
   * @return void (display)
   **/
   function title() {
      global $CFG_GLPI;

      $buttons = [];
      $title = __('Synchronize Process List', 'processmaker');

      if ($this->canCreate()) {
         $buttons["process.php?refresh=1"] = $title;
         $title = "";
         Html::displayTitle(Plugin::getWebDir('processmaker')."/pics/gears.png", $title, '',
                                    $buttons);
      }

   }

   /**
   * Retrieve a Process from the database using its external id (unique index): process_guid
   * @param string $process_guid guid of the process
   * @return bool true if succeed else false
   **/
   public function getFromGUID($process_guid) {
      global $DB;

      $res = $DB->request(
                     $this->getTable(), [
                     'process_guid' => $process_guid
                     ]
             );
      //$query = "SELECT *
      //          FROM `".$this->getTable()."`
      //          WHERE `process_guid` = '$process_guid'";

      //if ($result = $DB->query($query)) {
      if ($res) {
         if ($res->numrows() != 1) {//if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $res->next(); //$DB->fetch_assoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         }
      }
      return false;
   }


   /**
     * Summary of rawSearchOptions
   * @return mixed
   */
   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
              'id'                 => 'common',
              'name'               => __('ProcessMaker', 'processmaker')
           ];

      $tab[] = [
         'id'                 => '1',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => __('Name'),
         'datatype'           => 'itemlink',
         'itemlink_type'      => 'PluginProcessmakerProcess',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this->getTable(),
         'field'              => 'is_active',
         'name'               => __('Active'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '4',
         'table'              => $this->getTable(),
         'field'              => 'comment',
         'name'               => __('Comments'),
         'massiveaction'      => true,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '9',
         'table'              => $this->getTable(),
         'field'              => 'date_mod',
         'name'               => __('Last update'),
         'massiveaction'      => false,
         'datatype'           => 'datetime'
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'process_guid',
         'name'               => __('Process GUID', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '11',
         'table'              => $this->getTable(),
         'field'              => 'project_type',
         'name'               => __('Process type', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'specific'
      ];

      $tab[] = [
         'id'                 => '12',
         'table'              => $this->getTable(),
         'field'              => 'hide_case_num_title',
         'name'               => __('Hide case num. & title', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '13',
         'table'              => $this->getTable(),
         'field'              => 'insert_task_comment',
         'name'               => __('Insert Task Category', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '14',
         'table'              => 'glpi_itilcategories',
         'field'              => 'completename',
         'name'               => __('Category (self-service)', 'processmaker'),
         'datatype'           => 'dropdown',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '15',
         'table'              => $this->getTable(),
         'field'              => 'type',
         'name'               => __('Ticket type (self-service)', 'processmaker'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'is_incident',
         'name'               => __('Visible in Incident for Central interface', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '17',
         'table'              => $this->getTable(),
         'field'              => 'is_request',
         'name'               => __('Visible in Request for Central interface', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '18',
         'table'              => $this->getTable(),
         'field'              => 'is_change',
         'name'               => __('Visible in Change', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '19',
         'table'              => $this->getTable(),
         'field'              => 'is_problem',
         'name'               => __('Visible in Problem', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '20',
         'table'              => 'glpi_plugin_processmaker_processes',
         'field'              => 'maintenance',
         'name'               => __('Maintenance'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '21',
         'table'              => 'glpi_plugin_processmaker_processes',
         'field'              => 'max_cases_per_item',
         'name'               => __('Max cases per item (0=unlimited)', 'processmaker'),
         'massiveaction'      => true,
         'datatype'           => 'number'
      ];

      return $tab;
   }


   /**
   * @since version 0.84
   *
   * @param $field
   * @param $values
   * @param $options   array
   **/
   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {

         case 'project_type':
             return self::getProcessTypeName($values[$field]);

         case 'type':
             return Ticket::getTicketTypeName($values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * Summary of getAllTypeArray
    * @return string[]
    */
   static function getAllTypeArray() {

      $tab = [self::CLASSIC => _x('process_type', 'Classic', 'processmaker'),
                   self::BPMN    => _x('process_type', 'BPMN', 'processmaker')];

      return $tab;
   }


   /**
    * Summary of getProcessTypeName
    * @param mixed $value
    * @return mixed
    */
   static function getProcessTypeName($value) {

      $tab  = static::getAllTypeArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }


   /**
    * Summary of getTypeName
    * @param mixed $nb
    * @return mixed
    */
   static function getTypeName($nb = 0) {
      if ($nb>1) {
         return __('Processes', 'processmaker');
      }
      return __('Process', 'processmaker');
   }

   function defineTabs($options = []) {

      //        $ong = array('empty' => $this->getTypeName(1));
      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);

      $this->addStandardTab('PluginProcessmakerTaskCategory', $ong, $options);
      $this->addStandardTab('PluginProcessmakerProcess_Profile', $ong, $options);
      //$this->addStandardTab('Ticket', $ong, $options);
      //$this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   function showForm ($ID, $options = ['candel'=>false]) {
      global $DB, $CFG_GLPI, $PM_SOAP;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__("Name")."</td><td>";
      //Html::autocompletionTextField($this, "name");
      echo $this->fields["name"];
      echo "</td>";
      echo "<td rowspan='6' class='middle right'>".__("Comments")."</td>";
      echo "<td class='center middle' rowspan='6'><textarea cols='45' rows='10' name='comment' >".
           $this->fields["comment"]."</textarea></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Process GUID', 'processmaker')."</td><td>";
      echo $this->fields["process_guid"];
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Active')."</td><td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Hide case number and title in task descriptions', 'processmaker')."</td><td>";
      Dropdown::showYesNo("hide_case_num_title", $this->fields["hide_case_num_title"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Insert Task Category comments in Task Description', 'processmaker')."</td><td>";
      Dropdown::showYesNo("insert_task_comment", $this->fields["insert_task_comment"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >" . __('Max cases per item (0=unlimited)', 'processmaker') . "</td>";
      echo "<td ><input type='text' name='max_cases_per_item' value='".$this->fields["max_cases_per_item"]."'>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Re-assign reason is mandatory (can be changed in task category settings)', 'processmaker') . "</td>";
      echo "<td nowrap>";
      $elements = [
          Entity::CONFIG_PARENT => __('Inheritance of the plugin settings', 'processmaker'),
          0                     => Dropdown::getYesNo(0),
          1                     => Dropdown::getYesNo(1)
          ];
      Dropdown::showFromArray('is_reassignreason_mandatory', $elements, [
          'value' => $this->fields['is_reassignreason_mandatory'],
          ]);

      if ($this->fields['is_reassignreason_mandatory'] == Entity::CONFIG_PARENT) {
         echo "<div class='inherited inline' title='"
             .__('Value inherited from plugin settings', 'processmaker')
             ."'><i class='fas fa-level-down-alt'></i>"
             .$elements[$PM_SOAP->config['is_reassignreason_mandatory']]
            ."</div>";
      }

      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Visible in Incident for Central interface', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_incident", $this->fields["is_incident"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Visible in Request for Central interface', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_request", $this->fields["is_request"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td rowspan=2>".__('Visible for Self-service interface (change ITIL Category for Self-service to enable / disable)', 'processmaker')."</td>";
      echo "<td rowspan=2>".Dropdown::getYesNo($this->fields["itilcategories_id"])."</td>";
      echo "<td >".__('Type for Self-service interface', 'processmaker')."</td><td>";
      if (true) { // $canupdate || !$ID

         $rand = Ticket::dropdownType('type',
                              ['value' => $this->fields["type"],
                              ]);
         $params = ['type'            => '__VALUE__',
                    'value'           => $this->fields['itilcategories_id'],
                    'currenttype'     => $this->fields['type'],
                    'condition'       => ['is_helpdeskvisible' => '1']
                    ];

         Ajax::updateItemOnSelectEvent("dropdown_type$rand", "show_category_by_type",
                                         Plugin::getWebDir('processmaker')."/ajax/dropdownTicketCategories.php",
                                         $params);
      } else {
         echo Ticket::getTicketTypeName($this->fields["type"]);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('ITIL Category for Self-service interface (left empty to disable)', 'processmaker')."</td><td>";
      if (true) { // $canupdate || !$ID || $canupdate_descr
         $opt['value'] = $this->fields["itilcategories_id"];
         $opt['condition']['is_helpdeskvisible'] = '1';
         switch ($this->fields['type']) {
            case Ticket::INCIDENT_TYPE :
               $opt['condition']['is_incident'] = '1';
               break;

            case Ticket::DEMAND_TYPE :
               $opt['condition']['is_request'] = '1';
               break;

            default :
               break;
         }

         echo "<span id='show_category_by_type'>";
         Dropdown::show('ITILCategory', $opt);
         echo "</span>";
      } else {
          echo Dropdown::getDropdownName("glpi_itilcategories", $this->fields["itilcategories_id"]);
      }

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Visible in Change', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_change", $this->fields["is_change"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Visible in Problem', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_problem", $this->fields["is_problem"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Process type (to be changed only if not up-to-date)', 'processmaker')."</td><td>";
      Dropdown::showFromArray( 'project_type', self::getAllTypeArray(), [ 'value' => $this->fields["project_type"] ] );
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Maintenance mode')."</td><td>";
      Dropdown::showYesNo("maintenance", $this->fields["maintenance"]);
      if ($this->fields["maintenance"]) {
         echo "</td><td>";
         echo "<img src='".Plugin::getWebDir('processmaker')."/pics/verysmall-under_maintenance.png' alt='Synchronize Task List' title='Synchronize Task List'>";
      }
      echo "</td></tr>";

      $this->showFormButtons($options);
   }



   /**
   * Execute the query to select box with all glpi users where select key = name
   *
   * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
   *
   * @param $count true if execute an count(*),
   * @param $search pattern
   *
   * @return DBmysqlIterator.
   **/
   static function getSqlSearchResult ($count = true, $search = []) {
      global $DB, $CFG_GLPI;
      $query = [];
      //$where = '';
      //$orderby = '';

      if (isset($_REQUEST['condition']) && isset($_SESSION['glpicondition'][$_REQUEST['condition']])) {
         //$where = ' WHERE '.$_SESSION['glpicondition'][$_REQUEST['condition']]; //glpi_plugin_processmaker_processes.is_active=1 ';
         $query['WHERE']['AND'] = $_SESSION['glpicondition'][$_REQUEST['condition']];
      }

      if ($count) {
         //$fields = " COUNT(DISTINCT glpi_plugin_processmaker_processes.id) AS cpt ";
         $query['SELECT'] = ['COUNT' => 'glpi_plugin_processmaker_processes.id AS cpt'];
      } else {
         //$fields = " DISTINCT glpi_plugin_processmaker_processes.* ";
         $query['SELECT'] = ['glpi_plugin_processmaker_processes.*'];
         $query['ORDER']  ='glpi_plugin_processmaker_processes.name ASC';
         //$orderby = " ORDER BY glpi_plugin_processmaker_processes.name ASC";
      }

      if (!empty($search) && $search != $CFG_GLPI["ajax_wildcard"]) {
         $query['WHERE']['AND']['OR']['glpi_plugin_processmaker_processes.name'] = $search;
         $query['WHERE']['AND']['OR']['glpi_plugin_processmaker_processes.comment'] = $search;
         //$where .= " AND (glpi_plugin_processmaker_processes.name $search
         //            OR glpi_plugin_processmaker_processes.comment $search) ";
      }
      $query['FROM'] = 'glpi_plugin_processmaker_processes';
      //$query = "SELECT $fields FROM glpi_plugin_processmaker_processes ".$where." ".$orderby.";";
      //return $DB->query($query);
      //$r= $DB->request($query);
      return $DB->request($query);
   }

   /**
   * Summary of getProcessName
   * @param mixed $pid
   * @param mixed $link
   * @return mixed
   */
   static function getProcessName($pid, $link = 0) {
      global $DB, $CFG_GLPI;
      $process='';
      if ($link==2) {
         $process = ["name"    => "",
                       "link"    => "",
                       "comment" => ""];
      }

      $res = $DB->request('glpi_plugin_processmaker_processes', ['id' => $pid]);
      //$query="SELECT * FROM glpi_plugin_processmaker_processes WHERE id=$pid";
      //$result = $DB->query($query);
      //if ($result && $DB->numrows($result)==1) {
      //   $data     = $DB->fetch_assoc($result);
      if ($res && $res->numrows() == 1) {
//         $processname = $res['name'];//$data["name"];
         $data = $res->next();
         $processname = $data["name"];
         if ($link == 2) {
            $process["name"]    = $processname;
            $process["link"]    = Plugin::getWebDir('processmaker')."/front/process.form.php?id=".$pid;
            $process["comment"] = __('Name')."&nbsp;: ".$processname."<br>".__('Comments').
                               "&nbsp;: ".$data["comment"]."<br>";
         } else {
            $process = $processname;
         }

      }
      return $process;
   }

   /**
   * retrieve the entities allowed to a process for a profile
   *
   * @param $processes_id     Integer  ID of the process
   * @param $profiles_id  Integer  ID of the profile
   * @param $child        Boolean  when true, include child entity when recursive right
   *
   * @return Array of entity ID
   */
   static function getEntitiesForProfileByProcess($processes_id, $profiles_id, $child = false) {
      global $DB;
      $dbu = new DbUtils;
      $res = $DB->request([
                     'SELECT' => ['entities_id', 'is_recursive'],
                     'FROM'   => 'glpi_plugin_processmaker_processes_profiles',
                     'WHERE'  => [
                        'AND' => [
                           'plugin_processmaker_processes_id' => $processes_id,
                           'profiles_id'                      => $profiles_id
                        ]
                     ]
                  ]);
      //$query = "SELECT `entities_id`, `is_recursive`
      //          FROM `glpi_plugin_processmaker_processes_profiles`
      //          WHERE `plugin_processmaker_processes_id` = '$processes_id'
      //                AND `profiles_id` = '$profiles_id'";

      $entities = [];
      //foreach ($DB->request($query) as $data) {
      foreach ($res as $data) {
         if ($child && $data['is_recursive']) {
            foreach ($dbu->getSonsOf('glpi_entities', $data['entities_id']) as $id) {
               $entities[$id] = $id;
            }
         } else {
            $entities[$data['entities_id']] = $data['entities_id'];
         }
      }
      return $entities;
   }

   /**
   * Summary of dropdown
   * @param mixed $options
   * @return mixed
   */
   static function dropdown($options = []) {
      global $CFG_GLPI;

      if (!isset($options['specific_tags']['process_restrict'])) {
         $options['specific_tags']['process_restrict'] = 0;
      }
      $options['url'] = Plugin::getWebDir('processmaker').'/ajax/dropdownProcesses.php';
      return Dropdown::show( __CLASS__, $options );

   }


   /**
    * Summary of underMaintenance
    * Shows a nice(?) under maintenance message
    */
   static function showUnderMaintenance($ptitle, $size = '') {
      global $CFG_GLPI;
      if ($size != '') {
         $size .= '-';
      }
      echo "<div class='center'>";

      echo Html::image(Plugin::getWebDir('processmaker')."/pics/{$size}under_maintenance.png");
      echo "<p style='font-weight: bold;'>";
      echo sprintf(__('Process \'%s\' is under maintenance, please retry later, thank you.', 'processmaker'), $ptitle);
      echo "</p>";
      echo "</div>";
   }
}

