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
/**
 * PluginProcessmakerTaskCategory short summary.
 *
 * PluginProcessmakerTaskCategory description.
 *
 * @version 1.0
 * @author MoronO
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


class PluginProcessmakerTaskCategory extends CommonDBTM
{

   const REMINDER_NONE = -10;
   const REMINDER_STOP = -20;

   static $rightname = 'taskcategory';

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'TaskCategory') {
         $pmtaskcat = new PluginProcessmakerTaskCategory;
         if ($pmtaskcat->getFromDBbyCategory($item->fields['id'])) {
            return __('Process task', 'processmaker');
         } else {
            return ''; // means no tab
         }
      }
      return __('Task list', 'processmaker');
   }


   static function getAllAfterReminders() {
        $possible_values                      = [];
        $possible_values[self::REMINDER_NONE] = __('None');

        $min_values = [15, 30];
        foreach ($min_values as $val) {
            $possible_values[$val * MINUTE_TIMESTAMP] = sprintf(_n('%d minute', '%d minutes', $val), $val);
        }

        $h_values = [1, 2, 4, 8, 12];
        foreach ($h_values as $val) {
            $possible_values[$val * HOUR_TIMESTAMP] = sprintf(_n('%d hour', '%d hours', $val), $val);
        }
        $d_values = [1, 2, 4, 6];
        foreach ($d_values as $val) {
            $possible_values[$val * DAY_TIMESTAMP] = sprintf(_n('%d day', '%d days', $val), $val);
        }
        $w_values = [1, 2, 3];
        foreach ($w_values as $val) {
            $possible_values[$val * WEEK_TIMESTAMP] = sprintf(_n('%d week', '%d weeks', $val), $val);
        }
        $m_values = [1, 2];
        foreach ($m_values as $val) {
            $possible_values[$val * MONTH_TIMESTAMP] = sprintf(_n('%d month', '%d months', $val), $val);
        }

        return $possible_values;
   }


   static function getAfterReminder($value) {
       $arr = self::getAllAfterReminders();
       if (array_key_exists($value, $arr)) {
           return $arr[$value];
       } else {
           return null;
       }
   }


   static function displayTabContentForProcess(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $DB, $CFG_GLPI;

      self::title($item);

      echo "<div class='center'><br><table class='tab_cadre_fixehov'>";
      echo "<tr><th colspan='8'>".__('Task list', 'processmaker')."</th></tr>";
      echo "<tr><th>".__('Task name', 'processmaker')."</th>".
      "<th>".__('Complete name')."</th>" .
      "<th>".__('Start', 'processmaker')."</th>" .
      "<th>".__('Task guid', 'processmaker')."</th>" .
      "<th>".__('Comments')."</th>" .
      "<th>".__('Active')."</th>" .
      "<th>".__('Mandatory re-assign reason', 'processmaker')."</th>" .
      "<th>".__('Sub-process', 'processmaker')."</th>" .
      "</tr>";

      $res = $DB->request([
                     'SELECT'    => [
                        'pm.pm_task_guid',
                        'pm.taskcategories_id',
                        'pm.is_start',
                        'gl.name',
                        'gl.completename',
                        'gl.comment',
                        'pm.is_active',
                        'pm.is_reassignreason_mandatory AS pm_is_reassignreason_mandatory',
                        'pm.is_subprocess',
                        'gppp.is_reassignreason_mandatory AS gppp_is_reassignreason_mandatory'
                     ],
                     'FROM'      => 'glpi_plugin_processmaker_taskcategories AS pm',
                     'LEFT JOIN' => [
                        'glpi_taskcategories AS gl' => [
                           'FKEY' => [
                              'gl' => 'id',
                              'pm' => 'taskcategories_id'
                           ]
                        ],
                        'glpi_plugin_processmaker_processes AS gppp' => [
                            'FKEY' => [
                               'gppp' => 'id',
                               'pm'   => 'plugin_processmaker_processes_id'
                            ]
                        ]
                     ],
                     'WHERE'     => [
                        'pm.plugin_processmaker_processes_id' => $item->getId()
                     ]
         ]);
      foreach ($res as $taskCat) {
         echo "<tr class='tab_bg_1'>";

         echo "<td class='b'><a href='".
         Toolbox::getItemTypeFormURL('TaskCategory') . "?id=" . $taskCat['taskcategories_id'] . "'>" . $taskCat['name'];
         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (" . $taskCat['taskcategories_id'] . ")";
         }
         echo "</a></td>";

         echo "<td>" . $taskCat['completename'] . "</td>";

         echo "<td class='center'>";
         if ($taskCat['is_start']) {
            echo "<img src='".$CFG_GLPI["root_doc"]."/pics/ok.png' width='14' height='14' alt=\"".
            __('Start', 'processmaker')."\">";
         }
         echo "</td>";

         echo "<td >".$taskCat['pm_task_guid']."</td>";

         echo "<td>".$taskCat['comment']."</td>";

         echo "<td class='center'>";
         if ($taskCat['is_active']) {
            echo "<img src='".$CFG_GLPI["root_doc"]."/pics/ok.png' width='14' height='14' alt=\"".
            __('Active')."\">";
         }
         echo "</td>";

         echo "<td class='center'>";
         if (self::inheritedReAssignReason($taskCat['pm_is_reassignreason_mandatory'], $taskCat['gppp_is_reassignreason_mandatory']) == 1) {
            echo "<img src='".$CFG_GLPI["root_doc"]."/pics/ok.png' width='14' height='14' alt=\"".
                  __('Active')."\">";
         }
         echo "</td>";


         echo "<td class='center'>";
         if ($taskCat['is_subprocess']) {
            echo "<img src='".$CFG_GLPI["root_doc"]."/pics/ok.png' width='14' height='14' alt=\"".
            __('Sub-process', 'processmaker')."\">";
         }
         echo "</td>";

         echo "</tr>";
      }
      echo "</table></div>";

      return true;
   }


   static function inheritedReAssignReason($taskVal, $processVal) {
       global $PM_SOAP;

       $ret = $taskVal; // by default

       if ($processVal == Entity::CONFIG_PARENT) {
           if (!isset($PM_SOAP->config)) {
               $PM_SOAP->config = Config::getConfigurationValues('plugin:processmaker');
           }
           $processVal = $PM_SOAP->config['is_reassignreason_mandatory'];
       }

       if ($taskVal == Entity::CONFIG_PARENT) {
           $ret = $processVal;
       }

       return $ret;
   }


   static function displayTabContentForTaskCategory($item, $tabnum, $withtemplate) {
      global $DB, $CFG_GLPI;

      $processes_id = 0;
      $pmtaskcat = new self;
      $pmtaskcat->getFromDBbyCategory($item->fields['id']);
      $processes_id = $pmtaskcat->fields['plugin_processmaker_processes_id'];

     $res = $DB->request([
                 'SELECT'    => [
                 'pm.*',
                 'glp.name as pname',
                 'gl.name',
                 'gl.completename',
                 'gl.comment', 
                 'gppp.is_reassignreason_mandatory AS gppp_is_reassignreason_mandatory'
                 ],
                 'FROM'      => 'glpi_plugin_processmaker_taskcategories AS pm',
                 'LEFT JOIN' => [
                 'glpi_taskcategories AS gl' => [
                     'FKEY' => [
                         'gl' => 'id',
                         'pm' => 'taskcategories_id'
                     ]
                 ],
                 'glpi_taskcategories AS glp' => [
                     'FKEY' => [
                         'glp' => 'id',
                         'gl' => 'taskcategories_id'
                     ]
                 ],
                 'glpi_plugin_processmaker_processes AS gppp' => [
                     'FKEY' => [
                         'gppp' => 'id',
                         'pm'   => 'plugin_processmaker_processes_id'
                     ]
                 ]
                 ],
                 'WHERE'     => [
                 'pm.taskcategories_id' => $item->getId()
                 ]
     ]);

      // there is only one row
      $taskCat = $res->current();
      $pmtaskcat->showFormHeader();

      echo "<input type=hidden name=categories_id value='".$item->getID()."'/>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Process name', 'processmaker')."</td><td><a href='";
      echo Toolbox::getItemTypeFormURL('PluginProcessmakerProcess') . "?id=" . $processes_id . "'>" . $taskCat['pname'];
         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (" . $processes_id . ")";
         }
      echo "</a>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".__('Task name', 'processmaker')."</td><td class='b'>";
         echo $taskCat['name'];
         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (" . $taskCat['taskcategories_id'] . ")";
         }
      echo "</td><td>" . __('Complete name') . "</td>";
      echo "<td class='b'>" . $taskCat['completename'] . "</td>";

      echo "</tr>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Start', 'processmaker') . "</td>";
      echo "<td class='b'>";
      echo Dropdown::getYesNo($taskCat['is_start']);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Task guid', 'processmaker') . "</td>";
      echo "<td class='b'>".$taskCat['pm_task_guid']."</td>";
      echo "<td>" . __('Comments') . "</td>";
      echo "<td class='b'>".$taskCat['comment']."</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Active') . "</td>";
      echo "<td class='b'>";
      echo Dropdown::getYesNo($taskCat['is_active']);
      echo "</td>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Sub-process', 'processmaker') . "</td>";
      echo "<td class='b'>";
      echo Dropdown::getYesNo($taskCat['is_subprocess']);
      echo "</td>";

      echo "<tr class='headerRow'><th colspan='2' >" . __('Re-assign reason setting', 'processmaker') . "</th><th colspan='2' ></th></tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Re-assign reason is mandatory', 'processmaker') . "</td>";
      echo "<td class='b' nowrap>";
      $elements = [
                Entity::CONFIG_PARENT => __('Inheritance of the process settings', 'processmaker'),
                0                     => Dropdown::getYesNo(0),
                1                     => Dropdown::getYesNo(1)
                ];
      Dropdown::showFromArray('is_reassignreason_mandatory', $elements, [
          'value' => $taskCat['is_reassignreason_mandatory'],
          ]);

      if ($taskCat['is_reassignreason_mandatory'] == Entity::CONFIG_PARENT) {
         echo "<div class='inherited inline' title='"
             .__('Value inherited from process settings', 'processmaker')
             ."'><i class='fas fa-level-down-alt'></i>"
             .$elements[self::inheritedReAssignReason($taskCat['is_reassignreason_mandatory'], $taskCat['gppp_is_reassignreason_mandatory'])]
            ."</div>";
      }
      echo "</td></tr>";

      echo "<tr class='headerRow'><th colspan='2' >" . __('Reminder settings', 'processmaker') . "</th><th colspan='2' ></th></tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Before task (once)', 'processmaker');
      echo "&nbsp;";
      echo "&nbsp;";
      Html::showToolTip(__('Can be overridden by GLPI_ITEM_TASK_REMINDER', 'processmaker'), [
          'link' => 'https://github.com/tomolimo/processmaker/wiki/Case-Variables#glpi_item_task_reminder',
          'linktarget' => '_blank'
          ]);
      echo "</td>";
      echo "<td class='b'>";
      Dropdown::showFromArray('before_time', self::getAllBeforeReminders(), [
          'value' => $pmtaskcat->fields['before_time']
          ]);
      echo "</td>";

      echo "<td>" . __('Outdated task (every)', 'processmaker') . "</td>";
      echo "<td class='b'>";
      Dropdown::showFromArray('after_time', self::getAllAfterReminders(), [
         'value'   => $pmtaskcat->fields['after_time'],
      ]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Sender (default: GLPI)', 'processmaker') . "</td>";
      echo "<td class='b'>";
      User::dropdown(['name'                 => 'users_id',
                      'display_emptychoice'  => true,
                      'right'                => 'all',
                      'value'                => $pmtaskcat->fields['users_id']]);
      echo "</td></tr>";
      $pmtaskcat->showFormButtons(['candel'=>false]);
   }


   function prepareInputForUpdate($input) {
       if (isset($input['_planningrecall']['before_time'])) {
            $input['reminder_recall_time'] = $input['_planningrecall']['before_time'];
            unset($input['_planningrecall']);
       }
       return $input;
   }


   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item
    * @param mixed $tabnum
    * @param mixed $withtemplate
    * @return boolean
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $ret = false;
      switch ($item->getType()) {
         case 'PluginProcessmakerProcess':
            $ret = self::displayTabContentForProcess($item, $tabnum, $withtemplate);
            break;
         case 'TaskCategory':
            $ret = self::displayTabContentForTaskCategory($item, $tabnum, $withtemplate);
            break;
      }
      return $ret;
   }

    /**
     * Print a good title for task categories tab
     * add button for re-synchro of taskcategory list (only if rigths are w)
     * @return nothing (display)
     **/
   static function title(CommonGLPI $item) {
      global $CFG_GLPI;

      if (Session::haveRight('plugin_processmaker_config', UPDATE)) {
         $title = __('Synchronize Task List', 'processmaker');
         $buttons = ["process.form.php?refreshtask=1&id=".$item->getID() => $title];
         $pic = Plugin::getWebDir('processmaker') . "/pics/gears.png";
         if ($item->fields['maintenance']) {
            $pic = Plugin::getWebDir('processmaker') . "/pics/verysmall-under_maintenance.png";
         }
         Html::displayTitle($pic, $title, "", $buttons);
      }
   }


   /**
   * Retrieve a TaskCat from the database using its external id (unique index): pm_task_guid
   *
   * @param $task_guid string externalid
   *
   * @return true if succeed else false
   **/
   function getFromGUID($task_guid) {
      return $this->getFromDBByCrit(['pm_task_guid' => $task_guid]);
   }

    /**
     * Retrieve a TaskCat from the database using its category id (unique index): taskcategories_id
     *
     * @param $catid string task category id
     *
     * @return true if succeed else false
     **/
   function getFromDBbyCategory($catid) {
      global $DB;

      $res = $DB->request(
                     $this->getTable(),
                     [
                        'taskcategories_id' => $catid
                     ]
         );
      if ($res) {
         if ($res->numrows() != 1) {
            return false;
         }
         $this->fields = $res->current();
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         }
      }
      return false;
   }

   /**
   * summary of cronInfo
   *      Gives localized information about 1 cron task
   * @param $name of the task
   * @return array of strings
   */
   static function cronInfo ($name) {
      switch ($name) {
         case 'pmreminders' :
            return ['description' => __('To send auto-reminders and overdue reminders.', 'processmaker')];
      }
      return [];
   }

   /**
    * Summary of cronPMReminders
    * @param mixed $crontask 
    * @return int
    */
   static function cronPMReminders ($crontask = null) {
      global $DB, $CFG_GLPI, $PM_SOAP;
      
      if (!$CFG_GLPI["use_notifications"]) {
         return 0;
      }

      $pmconfig = $PM_SOAP->config;

      $cron_status = 0;
      $iterator = $DB->request([
         'SELECT'    => ['glpi_plugin_processmaker_taskrecalls.*',
                         'glpi_plugin_processmaker_tasks.itemtype as gppt-itemtype',
                         'glpi_plugin_processmaker_tasks.items_id as gppt-items_id',
                         'glpi_plugin_processmaker_tasks.plugin_processmaker_cases_id as gppt-cases_id',
                         'glpi_plugin_processmaker_cases.id as gppc-id',
                         'glpi_plugin_processmaker_cases.name as gppc-name',
                         'glpi_plugin_processmaker_cases.case_status as gppc-case_status'],
         'DISTINCT'  => true,
         'FROM'      => 'glpi_plugin_processmaker_taskrecalls',
         'LEFT JOIN' => [
            'glpi_plugin_processmaker_taskalerts'  => [
               'ON' => [
                  'glpi_plugin_processmaker_taskrecalls'  => 'id',
                  'glpi_plugin_processmaker_taskalerts'   => 'plugin_processmaker_taskrecalls_id',
               ]
            ],
            'glpi_plugin_processmaker_tasks' => [
               'ON' => [
                    'glpi_plugin_processmaker_tasks'       => 'id',
                    'glpi_plugin_processmaker_taskrecalls' => 'plugin_processmaker_tasks_id'
               ]
            ],
            'glpi_plugin_processmaker_cases' => [
               'ON' => [
                    'glpi_plugin_processmaker_cases' => 'id',
                    'glpi_plugin_processmaker_tasks' => 'plugin_processmaker_cases_id'
               ]
            ],
         ],
         'WHERE'     => [
            'NOT'                                              => ['glpi_plugin_processmaker_taskrecalls.when' => null],
            'glpi_plugin_processmaker_taskrecalls.when'        => ['<', new \QueryExpression('NOW()')],
            'glpi_plugin_processmaker_tasks.del_thread_status' => PluginProcessmakerTask::OPEN,
            'OR' => [
               ['glpi_plugin_processmaker_taskalerts.date'   => null],
               ['glpi_plugin_processmaker_taskalerts.date' => ['<', new \QueryExpression($DB->quoteName('glpi_plugin_processmaker_taskrecalls.when'))]]]
         ]
      ]);

      foreach ($iterator as $data) {
         if ($data['gppc-case_status'] == PluginProcessmakerCase::CANCELLED) {
             PluginProcessmakerCase::deleteReminders($data['gppc-id']);
             continue;
         } 
         $pm_task = new PluginProcessmakerTask($data['gppt-itemtype']);
         $pm_task->getFromDB($data['gppt-items_id']);

         // init sender
         $pm_task->setSender($data['users_id']);

         $glpi_task = new $data['gppt-itemtype'];
         $glpi_task->fields = $pm_task->fields;

         $itemtype = $pm_task->getItilObjectItemType();
         $glpi_item = new $itemtype;
         $glpi_item->getFromDB($pm_task->fields[$glpi_item->getForeignKeyField()]);

         $add_alert = false; // by default
         $new_when = 0; // by default
         if ($data['before_time'] >= 0) {
             // then send task "before reminder"
             if ($pm_task->sendNotification('task_reminder', $glpi_task, $glpi_item)) {
                 // and now add an alert
                 $add_alert = true;
                 // then set the 'before_time' value to self::REMINDER_STOP to permit future "after reminders"
                 $data['before_time'] = self::REMINDER_STOP;
                 $pm_taskrecall = new PluginProcessmakerTaskrecall();
                 $pm_taskrecall->update($data);
             }
             if ($data['after_time'] >= 0) {
                 // if task "after reminder" is set, then compute new when for the first task "after reminder"
                 $new_when = strtotime($pm_task->fields['end']) + $data['after_time'];
             }
         } elseif ($data['after_time'] >= 0) {
             // then task "after reminder"
             if ($pm_task->sendNotification('task_overdue', $glpi_task, $glpi_item)) {
                 // and now add an alert
                 $add_alert = true;
                 // then compute the new when for the next task "after reminder"
                 $new_when = strtotime($data['when']) + $data['after_time'];

                 // Add a follow-up in the hosting item to indicate the sending of the reminder
                 $fu = new ITILFollowup();
                 $input = $fu->fields;

                 $fucontent = sprintf(
                     __("Case: '%s',<br>Task: '%s',<br>A reminder has been sent to:<br>", 'processmaker'),
                     $data['gppc-name'],
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
                 $input['items_id'] = $glpi_item->getID();
                 $input['users_id'] = ($data['users_id'] > 0 ? $data['users_id'] : $pmconfig['users_id']);
                 $input['itemtype'] = $glpi_item->getType();

                 $fu->add($input);
             }
         }

         if ($new_when != 0) {
            // if the new_when is less than glpi_currenttime, then set it to be greater than glpi_currenttime 
            // to prevent send of many "after reminders" in case of late cron that missed one or several "after reminders"
            $glpi_currenttime = strtotime($_SESSION["glpi_currenttime"]);
            if ($new_when < $glpi_currenttime + $data['after_time']) {
                $new_when = $glpi_currenttime + $data['after_time'];
            }
            $data['when'] = date("Y-m-d H:i:s", $new_when);
            $pm_taskrecall = new PluginProcessmakerTaskrecall();
            $pm_taskrecall->update($data);
         }

         if ($add_alert) {
               $cron_status = 1;
               $crontask->addVolume(1);
               $crontask->log(sprintf(__('Reminder for case #%s has been sent!', 'processmaker'), $data['gppt-cases_id']));

               $pm_taskalert = new PluginProcessmakerTaskalert();
               $input["plugin_processmaker_taskrecalls_id"] = $data['id'];
               $pm_taskalert->add($input);
         } else {
            // Clean item
            $pr->delete($data);
         }
      }
      return $cron_status;
      }


   static function getAllBeforeReminders() {
        $possible_values                      = [];
        $possible_values[self::REMINDER_NONE] = __('None');

        $min_values = [0, 15, 30];
        foreach ($min_values as $val) {
            $possible_values[$val * MINUTE_TIMESTAMP] = sprintf(_n('%d minute', '%d minutes', $val), $val);
        }

        $h_values = [1, 2, 4, 8, 12];
        foreach ($h_values as $val) {
            $possible_values[$val * HOUR_TIMESTAMP] = sprintf(_n('%d hour', '%d hours', $val), $val);
        }
        $d_values = [1, 2];
        foreach ($d_values as $val) {
            $possible_values[$val * DAY_TIMESTAMP] = sprintf(_n('%d day', '%d days', $val), $val);
        }
        $w_values = [1];
        foreach ($w_values as $val) {
            $possible_values[$val * WEEK_TIMESTAMP] = sprintf(_n('%d week', '%d weeks', $val), $val);
        }

        return $possible_values;
   }


   static function getBeforeReminder($value) {
       $arr = self::getAllBeforeReminders();
       if (array_key_exists($value, $arr)) {
           return $arr[$value];
       }
       return null;
   }


   static function getSpecificValueToDisplay($field, $values, array $options=[]) {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'before_time':
                return self::getBeforeReminder($values[$field]);
            case 'after_time':
                return self::getAfterReminder($values[$field]);
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   static function getSpecificValueToSelect($field, $name='', $values='', array $options=[]) {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        $options['name']  = $name;
        $options['value'] = $values[$field];
        switch ($field) {
            case 'before_time' :
                return Dropdown::showFromArray($name, self::getAllBeforeReminders(), $options);
            case 'after_time' :
                return Dropdown::showFromArray($name, self::getAllAfterReminders(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }
}
