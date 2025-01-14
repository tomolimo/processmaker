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
include_once 'inc/processmaker.class.php';

function plugin_processmaker_MassiveActions($type) {
   switch ($type) {
      case 'PluginProcessmakerProcess' :
         if (plugin_processmaker_haveRight('config', UPDATE)) {
            return ['PluginProcessmakerProcess:taskrefresh' => __('Synchronize Task List', 'processmaker')];
         }
      break;
      case 'PluginProcessmakerProcess_Profile' :
         if (plugin_processmaker_haveRight('config', UPDATE)) {
            return ['purge' => __('Delete permanently')];
         }
      break;
      case 'PluginProcessmakerCase' :
         if (plugin_processmaker_haveRight("case", CANCEL)) {
            return ['PluginProcessmakerCase:casecancel' => __('Cancel', 'processmaker')];
         }
      break;
   }
   return [];
}


/**
 * Summary of plugin_processmaker_install
 *      Creates tables and initializes tasks, "GLPI Requesters" group
 *      and so on
 * @return true or die!
 */
function plugin_processmaker_install() {
   global $DB;
   if (!$DB->tableExists("glpi_plugin_processmaker_cases")) {
      // new installation
      include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/install.php");
      processmaker_install();
   } else {
      // upgrade
      include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update.php");
      processmaker_update();
   }

   // To be called for each task managed by the plugin
   // task in class
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmusers', DAY_TIMESTAMP, ['state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL]);
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmorphancases', DAY_TIMESTAMP, ['param' => 10, 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL]);
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmtaskactions', MINUTE_TIMESTAMP, ['state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL]);
   CronTask::Register('PluginProcessmakerTaskcategory', 'pmreminders', 5*MINUTE_TIMESTAMP, ['state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL]);

   // required because autoload doesn't work for inactive plugin'
   include_once(PLUGIN_PROCESSMAKER_ROOT . "/inc/profile.class.php");
   PluginProcessmakerProfile::createAdminAccess($_SESSION['glpiactiveprofile']['id']);

   return true;
}

function plugin_processmaker_uninstall() {

   CronTask::Unregister('PluginProcessmakerProcessmaker');

   return true;
}


function plugin_processmaker_getAddSearchOptionsNew($itemtype) {

    $tab = [];

   if (in_array($itemtype, ['Ticket', 'Change', 'Problem'])) {
      $tab[] = [
          'id'                 => 'processmaker',
          'name'               => _n('Process Case', 'Process Cases', Session::getPluralNumber(), 'processmaker')
      ];

      $tab[] = [
         'id'                  => 10001,
         'table'               => PluginProcessmakerCase::getTable(),
         'field'               => 'case_status',
         'massiveaction'       => false,
         'name'                => __('Status', 'processmaker'),
         'datatype'            => 'specific',
         'searchequalsonfield' => true,
         'usehaving'           => true,
         'searchtype'          => [
            '1'                  => 'equals',
            '2'                  => 'notequals'
         ],
         'forcegroupby'  => true,
         //'splititems'    => true,
         'joinparams'    => [
            'jointype' => 'itemtype_item'
         ]
      ];
      $tab[] = [
         'id'                  => 10002,
         'table'               => PluginProcessmakerCase::getTable(),
         'field'               => 'name',
         'massiveaction'       => false,
         'name'                => __('Title', 'processmaker'),
         'datatype'            => 'itemlink',
         'searchtype'          => [
            '0'                  => 'contains'
         ],
         'forcegroupby'  => true,
         //'splititems'    => true,
         'joinparams'    => [
            'jointype' => 'itemtype_item'
         ]
      ];
      $tab[] = [
         'id'                  => 10004,
         'table'               => PluginProcessmakerCase::getTable(),
         'field'               => 'date_creation',
         'massiveaction'       => false,
         'name'                => __('Opening date'),
         'datatype'            => 'datetime',
         //'searchtype'          => [
         //   '0'                  => 'contains'
         //],
         //'forcegroupby'  => true,
         //'splititems'    => true,
         'joinparams'    => [
            'jointype' => 'itemtype_item'
         ]
      ];
      $tab[] = [
         'id'                  => 10005,
         'table'               => PluginProcessmakerCase::getTable(),
         'field'               => 'date_mod',
         'massiveaction'       => false,
         'name'                => __('Last update'),
         'datatype'            => 'datetime',
         //'searchtype'          => [
         //   '0'                  => 'contains'
         //],
         //'forcegroupby'  => true,
         //'splititems'    => true,
         'joinparams'    => [
            'jointype' => 'itemtype_item'
         ]
      ];
      $tab[] = [
         'id'                 => '10003',
         'table'              => PluginProcessmakerProcess::getTable(),
         'field'              => 'name',
         'name'               => __('Process name', 'processmaker'),
         'datatype'           => 'dropdown',
         'massiveaction'      => false,
         //'usehaving'           => true,
         //'searchequalsonfield' => true,
         //'forcegroupby'  => true,
         //'splititems'    => true,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => PluginProcessmakerCase::getTable(),
               'joinparams'         => [
                  'jointype'           => 'itemtype_item'
               ]
            ]
         ]

      ];
   }

   if ($itemtype == 'TaskCategory') {
       $tab[] = [
          'id'                 => 'processmaker',
          'name'               => _n('Process task category', 'Process task categories', Session::getPluralNumber(), 'processmaker')
      ];

       $tab[] = [
         'id'                  => 20000,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'taskcategories_id',
         'massiveaction'       => false,
         'name'                => __('Process task', 'processmaker'),
         'datatype'            => 'itemlink',
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20001,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'is_start',
         'massiveaction'       => false,
         'name'                => __('Start', 'processmaker'),
         'datatype'            => 'bool',
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20002,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'is_active',
         'massiveaction'       => false,
         'name'                => __('Active', 'processmaker'),
         'datatype'            => 'bool',
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20003,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'is_subprocess',
         'massiveaction'       => false,
         'name'                => __('Sub-process', 'processmaker'),
         'datatype'            => 'bool',
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20004,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'is_reassignreason_mandatory',
         'massiveaction'       => false,
         'name'                => __('Force re-assign reason', 'processmaker'),
         'datatype'            => 'specific',
         'nosearch'            => true,
         'nodisplay'           => true,
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20005,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'before_time',
         'massiveaction'       => false,
         'name'                => __('Reminder (before task begin)', 'processmaker'),
         'datatype'            => 'specific',
         'searchequalsonfield' => true,
         'searchtype'          => [
            'equals',
            'notequals',
            'lessthan',
            'morethan'
         ],
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

       $tab[] = [
         'id'                  => 20006,
         'table'               => User::getTable(),
         'field'               => 'name',
         'massiveaction'       => false,
         'name'                => __('Reminder sender', 'processmaker'),
         'datatype'            => 'dropdown',
         'linkfield'          => 'users_id',
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => PluginProcessmakerTaskCategory::getTable(),
               'joinparams'         => [
                  'jointype'           => 'child',
               ]
            ]
         ]
      ];

       $tab[] = [
         'id'                  => 20007,
         'table'               => PluginProcessmakerTaskCategory::getTable(),
         'field'               => 'after_time',
         'massiveaction'       => false,
         'name'                => __('Reminder (after task end)', 'processmaker'),
         'datatype'            => 'specific',
         'searchequalsonfield' => true,
         'searchtype'          => [
            'equals',
            'notequals',
            'lessthan',
            'morethan'
         ],
         'joinparams'    => [
            'jointype' => 'child'
         ]
      ];

      // $tab[] = [
      //   'id'                  => 20008,
      //   'table'               => PluginProcessmakerTaskCategory::getTable(),
      //   'field'               => 'reminder_overdue_frequency',
      //   'massiveaction'       => false,
      //   'name'                => __('Reminder frequency', 'processmaker'),
      //   'datatype'            => 'specific',
      //   'searchequalsonfield' => true,
      //   'searchtype'          => [
      //      'equals',
      //      'notequals',
      //   ],
      //  'joinparams'         => [
      //      'jointype'           => 'child',
      //  ]
      //];
   }

   return $tab;
}

function plugin_processmaker_addWhere($link, $nott, $itemtype, $ID, $val, $searchtype) {
    if ($itemtype == 'TaskCategory' && $ID == 20005) { // 20005 == before_time
        switch ($searchtype) {
            case 'lessthan':
                if ($nott) {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`before_time` <= $val)";
                } else {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`before_time` > $val)";
                }
                break;
            case 'morethan':
                if ($nott) {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`before_time` >= $val)";
                } else {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`before_time` < $val)";
                }
                break;
        }
    }
    if ($itemtype == 'TaskCategory' && $ID == 20007) { // 20007 == after_time
        switch ($searchtype) {
            case 'lessthan':
                if ($nott) {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`after_time` >= $val)";
                } else {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`after_time` < $val)";
                }
                break;
            case 'morethan':
                if ($nott) {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`after_time` <= $val)";
                } else {
                    return "$link (`glpi_plugin_processmaker_taskcategories`.`after_time` > $val)";
                }
                break;
        }
    }

    return '';
}


//function plugin_processmaker_giveItem($itemtype, $ID, $data, $num) {
//    echo '';
//}


/**
 * Summary of plugin_pre_item_update_processmaker
 * @param CommonITILObject $parm is an object
 * @return void
 */
function plugin_pre_item_update_processmaker(CommonITILObject $parm) {
   //global $DB;//, $PM_SOAP;

   // look at previous status
   if (isset($parm->input['status'])
      && $parm->input['status'] == CommonITILObject::SOLVED
      && !in_array($parm->fields['status'], [CommonITILObject::SOLVED, CommonITILObject::CLOSED])
      && !PluginProcessmakerCase::canSolve(['item' => $parm])) {
      $parm->input = []; // empty array... to prevent item update
      Session::addMessageAfterRedirect(__('At least one \'Process case\' is running!<br/>Solving is currently disabled!', 'processmaker'), false, ERROR);
      return;
   }


   if (isset($_SESSION['glpiname'])) {
      $locVar = [ ];
      foreach ($parm->input as $key => $val) {
         switch ($key) {
            case 'global_validation' :
               $locVar[ 'GLPI_TICKET_GLOBAL_VALIDATION' ] = $val;
               $locVar[ 'GLPI_ITEM_GLOBAL_VALIDATION' ] = $val;
               break;
            case 'itilcategories_id' :
               $locVar[ 'GLPI_ITEM_ITIL_CATEGORY_ID' ] = $val;
               break;
            case 'date' :
               $locVar[ 'GLPI_ITEM_OPENING_DATE' ] = $val;
               break;
            case 'time_to_resolve' :
               $locVar[ 'GLPI_TICKET_DUE_DATE' ] = $val;
               $locVar[ 'GLPI_ITEM_DUE_DATE' ] = $val;
               break;
            case 'urgency' :
               $locVar[ 'GLPI_TICKET_URGENCY' ] = $val;
               $locVar[ 'GLPI_ITEM_URGENCY' ] = $val;
               break;
            case 'impact' :
               $locVar[ 'GLPI_ITEM_IMPACT' ] = $val;
               break;
            case 'priority' :
               $locVar[ 'GLPI_ITEM_PRIORITY' ] = $val;
               break;
            case 'requesttypes_id' :
               $locVar[ 'GLPI_TICKET_REQUESTTYPES_ID' ] = $val;
               break;
         }
      }

      if (count($locVar)) {
         $itemId = $parm->getID();
         $itemType = $parm->getType();

         $locCase = new PluginProcessmakerCase;
         foreach (PluginProcessmakerCase::getIDsFromItem($itemType, $itemId) as $cases_id) {
            $locCase->getFromDB($cases_id);
            $locCase->sendVariables($locVar);

            // if entities_id of item has been changed, then must update case
            if (isset($parm->input['entities_id']) && $parm->input['entities_id'] != $parm->fields['entities_id']) {
               $locCase->update(['id' => $cases_id, 'entities_id' => $parm->input['entities_id']]);
            }
         }
      }
   }

}

/**
 * Summary of plugin_item_update_processmaker_satisfaction
 * inject satisfaction survey into case
 * @param mixed $parm is the object
 */
function plugin_item_update_processmaker_satisfaction($parm) {
   global $PM_SOAP;
   if (Session::isCron()) { // Task cron log with user admin
      $PM_SOAP->login(true);
   }
   $cases = PluginProcessmakerCase::getIDsFromItem('Ticket', $parm->fields['tickets_id']);
   foreach ($cases as $cases_id) {
      $locCase = new PluginProcessmakerCase;
      if ($locCase->getFromDB($cases_id)) {
         // case is existing for this item
         $locCase->sendVariables( ['GLPI_SATISFACTION_QUALITY' => $parm->fields['satisfaction']] );
      }
   }
}


/**
 * Summary of plugin_item_update_processmaker_user
 * When a user login is changed, then must change it in the PM tables
 * @param User $param is the user being changed
 */
function plugin_item_update_processmaker_user(User $param) {
   // Must test if user login has been changed
   // if yes, must change the login in the PM tables:
   // USERS and RBAC_USERS, othewise the link in the processmaker_users table will be invalid
   if (in_array('name', $param->updates)) {
      // check if user is in the processmaker_user table
      $pm_user = PluginProcessmakerUser::getPMUserId($param->getID());
      if ($pm_user) {
         // must update the user in PM tables
         global $PM_SOAP;
         if ($param->fields['is_active'] == 0 || $param->fields['is_deleted'] == 1) {
               $status = "INACTIVE";
            } else {
               $status = "ACTIVE";
            }
         $PM_SOAP->login(true);
         $pmResult = $PM_SOAP->updateUser( $pm_user, $param->fields['name'], $param->fields['firstname'], $param->fields['realname'], $status );
      }
   }
}


function plugin_processmaker_post_init() {
   global $PM_DB, $PM_SOAP;
   if (!isset($PM_DB)) {
      $PM_DB = new PluginProcessmakerDB;
   }
   if (!isset($PM_SOAP)) {
      $PM_SOAP = new PluginProcessmakerProcessmaker;
      // and default login is current running user if any
      if (Session::getLoginUserID()) {
         $PM_SOAP->login();
      }
   }
}


function plugin_processmaker_change_profile() {
   if ($_SESSION['glpiactiveprofile']['interface'] == "helpdesk") {
      // must add the rights for simplified interface
      $_SESSION['glpiactiveprofile']['plugin_processmaker_case'] = READ;
   }
}

/**
   * Summary of plugin_item_add_update_processmaker_tasks
   * @param mixed $parm
   */
function plugin_item_update_processmaker_tasks($parm) {
   global $DB, $CFG_GLPI, $PM_SOAP;

   $pmTaskCat = new PluginProcessmakerTaskCategory;
   if ($pmTaskCat->getFromDBbyCategory( $parm->fields['taskcategories_id'] )
            && in_array( 'state', $parm->updates )
            && $parm->input['state'] == Planning::DONE
            && $parm->oldvalues['state'] == Planning::TODO) {  // the task has just been set to DONE state

      //$itemtype = str_replace( 'Task', '', $parm->getType() );

      $pmTask = new PluginProcessmakerTask($parm->getType());
      $pmTask->getFromDB($parm->fields['id']);

      $locCase = new PluginProcessmakerCase;
      $locCase->getFromDB($pmTask->fields['plugin_processmaker_cases_id']);
      $srccase_guid = $locCase->fields['case_guid'];
      //$msg  =  Toolbox::backtrace(false);
      //$msg .= ' $locCase: '.str_replace("\n", "\n  ", print_r($locCase, true))."\n";
      //$msg .= ' $task: '.str_replace("\n", "\n  ", print_r($parm, true))."\n";
      //$msg .= ' $pmTask: '.str_replace("\n", "\n  ", print_r($pmTask, true))."\n";
      //$msg .= "\n";

      foreach ($DB->request( 'glpi_plugin_processmaker_caselinks', "is_active = 1 AND sourcetask_guid='".$pmTaskCat->fields['pm_task_guid']."'") as $targetTask) {

         // Must check the condition
         $casevariables = [];

         $matches = [];
         if (preg_match_all( "/@@(\w+)/u", $targetTask['sourcecondition'], $matches  )) {
            $casevariables = $matches[1];
         }

         $targetTask['targetactions'] = []; // empty array by default
         foreach ($DB->request( 'glpi_plugin_processmaker_caselinkactions', 'plugin_processmaker_caselinks_id = '.$targetTask['id']) as $actionvalue) {
            $targetTask['targetactions'][$actionvalue['name']] = $actionvalue['value'];
            if (preg_match_all( "/@@(\w+)/u", $actionvalue['value'], $matches  )) {
               $casevariables = array_merge( $casevariables, $matches[1] );
            }
         }
         $externalapplication = false; // by default
         if ($targetTask['is_externaldata'] && isset($targetTask['externalapplication'])) {
            // must read some values
            $externalapplication = json_decode( $targetTask['externalapplication'], true );
            // must be of the form
            // {"method":"POST","url":"urloftheservice","params":json_object}
            // Where method is the POST, GET, ... method
            // url is the URL to be called
            // params is a list of parameters to get from running case
            foreach ($externalapplication['params'] as $paramname => $variable) {
               if (preg_match_all( "/@@(\w+)/u", $variable, $matches  )) {
                  $casevariables = array_merge( $casevariables, $matches[1] );
               }
            }
            if (preg_match_all( "/@@(\w+)/u", $externalapplication['url'], $matches  )) {
                $casevariables = array_merge( $casevariables, $matches[1] );
            }
            if(isset($externalapplication['headers']) && $externalapplication['headers'] != "") {
                if (preg_match_all( "/@@(\w+)/u", $externalapplication['headers'], $matches  )) {
                    $casevariables = array_merge( $casevariables, $matches[1] );
                }
            }

         }

         // ask for those case variables
         //$PM_SOAP = new PluginProcessmakerProcessmaker();
         //$PM_SOAP->login( );
         // now tries to get the variables to check condition
         $infoForTasks = $locCase->getVariables($casevariables);
         $infoForURL = [];
         foreach ($infoForTasks as $casevar => $varval) {
            $infoForTasks[ "@@$casevar" ] = "'$varval'";
            $infoForURL[ "@@$casevar" ] = $varval;
            unset( $infoForTasks[ $casevar ] );
         }

         $targetTask['sourcecondition'] = str_replace( array_keys($infoForTasks), $infoForTasks, $targetTask['sourcecondition'] );
         $eval = eval( "return (".$targetTask['sourcecondition']." ? 1 : 0);" );

         if ($eval) {
            // look at each linked ticket if a case is attached and then if a task like $val is TO_DO
            // then will try to routeCase for each tasks in $val

            $formdata = [];
            foreach ($targetTask['targetactions'] as $action => $actionvalue) {
               $formdata['form'][$action] = eval( "return ".str_replace( array_keys($infoForTasks), $infoForTasks, $actionvalue)." ;" );
            }
            $formdata['UID']                        = $targetTask['targetdynaform_guid'];
            $formdata['__DynaformName__']           = $targetTask['targetprocess_guid']."_".$targetTask['targetdynaform_guid'];
            $formdata['__notValidateThisFields__']  = '[]';
            $formdata['DynaformRequiredFields']     = '[]';
            $formdata['form']['btnGLPISendRequest'] = 'submit';

            $externalapplicationparams = [];
            if ($externalapplication) {
               // must call curl
               foreach ($externalapplication['params'] as $paramname => $variable) {
                  $externalapplicationparams[$paramname] = eval( "return ".str_replace( array_keys($infoForTasks), $infoForTasks, $variable)." ;" );
               }
               $externalapplicationparams['callback'] = Plugin::getWebDir('processmaker', true, true) . "/ajax/asynchronousdatas.php";
               $ch = curl_init();

               $externalapplication['url'] = str_replace( array_keys($infoForURL), $infoForURL, $externalapplication['url']);
               curl_setopt($ch, CURLOPT_URL, $externalapplication['url'] );
               if (isset($externalapplication['method']) && $externalapplication['method'] == 'POST') {
                  curl_setopt($ch, CURLOPT_POST, 1);
               }
            }

            if ($targetTask['is_self']) {
               $PM_SOAP->login(true);
               $taskCase = $PM_SOAP->taskCase( $srccase_guid );
               foreach ($taskCase as $task) {
                  // search for target task guid
                  if ($task->guid == $targetTask['targettask_guid']) {
                     break;
                  }
               }
               $PM_SOAP->login();

               $formdata['APP_UID']                    = $srccase_guid;
               $formdata['DEL_INDEX']                  = $task->delegate;

               //need to get the 'ProcessMaker' user
               //$config = $PM_SOAP->config; //PluginProcessmakerConfig::getInstance();

               $cronaction = new PluginProcessmakerCrontaskaction;
               $cronactionid = $cronaction->add([
                     'plugin_processmaker_caselinks_id' => $targetTask['id'],
                     'plugin_processmaker_cases_id'     => $locCase->getID(),
                     //'itemtype'                       => $itemtype,
                     //'items_id'                       => $parm->fields['tickets_id'],
                     'users_id'                         => $PM_SOAP->config['users_id'],
                     'is_targettoclaim'                 => $targetTask['is_targettoclaim'],
                     'state'                            => ($targetTask['is_externaldata'] ? PluginProcessmakerCrontaskaction::WAITING_DATA : PluginProcessmakerCrontaskaction::DATA_READY),
                     'formdata'                         => json_encode($formdata, JSON_HEX_APOS | JSON_HEX_QUOT)
                  ], [], false);

               $externalapplicationparams['id'] = $cronactionid;
              if ($externalapplication) {
                  $externalapplicationparams['callback'] .= "?id=$cronactionid";
              }
               $externalapplicationparams = json_encode( $externalapplicationparams, JSON_HEX_APOS | JSON_HEX_QUOT);
               // add to the crontaskcation the id of the crontaskaction itself in the postdata
               $cronaction->update([
                  'id'       => $cronactionid,
                  'postdata' => $externalapplicationparams
                  ], false);


               if ($externalapplication) {
                  // must call external application in order to get the needed data asynchroneously
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $externalapplicationparams);
                  $headers = [
                      'Content-Type: application/json',
                      'Content-Length: ' . strlen($externalapplicationparams),
                      'Expect:'];
                  if(isset($externalapplication['headers']) && $externalapplication['headers'] != "") {
                      $externalapplication['headers'] = eval("return ".str_replace(array_keys($infoForTasks), $infoForTasks, $externalapplication['headers'])." ;"); // '???
                      //Can't add an associative array in curlopt_httpheader
                      foreach($externalapplication['headers'] as $key => $h) {
                          array_push($headers, $key.": ".$h);
                      }
                  }
                  //$headers = array_merge($headers, $externalapplication['headers']);

                  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
                  curl_setopt($ch, CURLOPT_VERBOSE, 1);

                  if (isset($externalapplication['ssl_verify'])) {
                     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($externalapplication['ssl_verify'] == 0 ? 0 : 1));
                     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $externalapplication['ssl_verify']);
                  }

                  if (isset($externalapplication['proxy'])) {
                     curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1 ) ;
                     curl_setopt($ch, CURLOPT_PROXY, $externalapplication['proxy']);
                  }

                  $error = '';
                  $response = curl_exec ($ch);
                  if ($response === false) {
                     //throw new Exception(curl_error($ch), curl_errno($ch));
                     $error = curl_error($ch) . ":" . curl_errno($ch);
                     Toolbox::logDebug($error);
                     // Set 0 to the crontask action status
                     $cronaction->update([
                        'id ' => $cronactionid,
                        'state' => PluginProcessmakerCrontaskaction::CURL_ERROR
                        ]);
                  }

                  // add to the crontaskaction the response of the remote API
                  $cronaction->update([
                     'id'      => $cronactionid,
                     'retcode' => ($response === false ? $error : $DB->escape($response))
                     ], false);

                  curl_close ($ch);
               }
            } else {
               // TODO to review this part of code as it is no longer usable like this !!!
               foreach (Ticket_Ticket::getLinkedTicketsTo( $parm->fields['tickets_id'] ) as $tlink) {
                  if ($tlink['link'] == Ticket_Ticket::LINK_TO) {
                     $query = "SELECT glpi_plugin_processmaker_cases.id, MAX(glpi_plugin_processmaker_tasks.del_index) AS del_index FROM glpi_tickettasks
                           JOIN glpi_plugin_processmaker_taskcategories ON glpi_plugin_processmaker_taskcategories.taskcategories_id=glpi_tickettasks.taskcategories_id
                           JOIN glpi_plugin_processmaker_cases ON glpi_plugin_processmaker_cases.processes_id=glpi_plugin_processmaker_taskcategories.processes_id
                           RIGHT JOIN glpi_plugin_processmaker_tasks ON glpi_plugin_processmaker_tasks.items_id=glpi_tickettasks.id AND glpi_plugin_processmaker_tasks.case_id=glpi_plugin_processmaker_cases.id
                           WHERE glpi_plugin_processmaker_taskcategories.pm_task_guid = '".$targetTask['targettask_guid']."' AND glpi_tickettasks.state = 1 AND glpi_tickettasks.tickets_id=".$tlink['tickets_id'];
                     foreach ($DB->request($query) as $case) {
                        // must be only one row

                        $formdata['APP_UID']                    = $case['id'];
                        $formdata['DEL_INDEX']                  = $case['del_index'];

                        $cronaction = new PluginProcessmakerCrontaskaction;
                        $cronaction->add( [ 'plugin_processmaker_caselinks_id' => $targetTask['id'],
                                                 'plugin_processmaker_cases_id' => $locCase->getID(),
                                                   //'itemtype'         => $itemtype,
                                                   //'items_id'         => $parm->fields['tickets_id'],
                                                   'users_id'         => Session::getLoginUserID(),
                                                   'is_targettoclaim' => $targetTask['is_targettoclaim'],
                                                   'state'            => ($targetTask['is_externaldata'] ? PluginProcessmakerCrontaskaction::WAITING_DATA : PluginProcessmakerCrontaskaction::DATA_READY),
                                                   'formdata'         => json_encode($formdata, JSON_HEX_APOS | JSON_HEX_QUOT),
                                                   'postdata'         => json_encode($externalapplicationparams, JSON_HEX_APOS | JSON_HEX_QUOT)
                                                   ],
                                          [],
                                          false);
                     }
                  }
               }
            }

            if ($targetTask['is_synchronous']) {
               // must call PluginProcessmakerProcessmaker::cronPMTaskActions()
               PluginProcessmakerProcessmaker::cronPMTaskActions();
            }
         }

      }

      //$msg .= "================\n";
      //Toolbox::logInFile("processmaker", $msg);

   }
}


/**
 * Summary of plugin_processmaker_redefine_menus
 * @param mixed $menu
 * @return mixed
 */
function plugin_processmaker_redefine_menus($menu) {
   global $PM_SOAP;

   //$config = PluginProcessmakerConfig::getInstance();
   $plugin_data["version"]       = PROCESSMAKER_VERSION;
   $plugin_data["pm_server_URL"] = $PM_SOAP->config['pm_server_URL'];

   // inject them into javascript
   $plugin_data = 'var GLPI_PROCESSMAKER_PLUGIN_DATA = ' . json_encode($plugin_data) . ';';

   echo Html::scriptBlock("
         $plugin_data
      ");

   return $menu;
}

