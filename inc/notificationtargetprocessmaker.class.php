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
 * PluginProcessmakerNotificationTargetProcessmaker short summary.
 *
 * PluginProcessmakerNotificationTargetProcessmaker description.
 *
 * Common notificationtarget class for cases and tasks
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerNotificationTargetProcessmaker extends NotificationTargetCommonITILObject {

   const PM_USER_TYPE = 1000;

   const OLD_TASK_TECH_IN_CHARGE = 1;


   /**
    * Summary of saveNotificationState
    * @param mixed $donotif
    * @return mixed
    */
   static function saveNotificationState($donotif) {
      global $CFG_GLPI;
      $savenotif = $CFG_GLPI["use_notifications"];
      if (!$donotif) {
         $CFG_GLPI["use_notifications"] = false;
      }
      return $savenotif;
   }


   /**
    * Summary of restoreNotificationState
    * @param mixed $savenotif
    */
   static function restoreNotificationState($savenotif) {
      global $CFG_GLPI;
      $CFG_GLPI["use_notifications"] = $savenotif;
   }


   /**
    * Summary of getSubjectPrefix
    * @param mixed $event
    * @return string
    */
   function getSubjectPrefix($event = '') {
      return '';
   }


   /**
    * Summary of getTags
    */
   public function getTags() {

      $tags = ['process.category'        => __('Process category', 'processmaker'),
               'process.categoryid'      => __('Process category id', 'processmaker'),
               'process.categorycomment' => __('Process category comment', 'processmaker'),
               'case.id'                 => __('Case id', 'processmaker'),
               'case.title'              => __('Case title', 'processmaker'),
               'case.description'        => __('Case description', 'processmaker'),
               'case.url'                => __('Case URL'),
               'var.XXX'                 => __('Case variable \'XXX\'', 'processmaker'),
               'array.YYY'               => __('List of values in \'YYY\' array', 'processmaker'),
               'array.numberofYYY'       => __('Number of rows in \'YYY\' array', 'processmaker'),
               'array.YYY.colname'       => __('Value for colname in \'YYY\' array', 'processmaker'),
               '1darray.ZZZ.key'         => __('Value for key in \'ZZZ\' assoc array (1-dimension array)', 'processmaker'),
               'item.type'               => __('Item type', 'processmaker'),
               'item.id'                 => __('Item id', 'processmaker'),
               'item.url'                => __('Item URL', 'processmaker'),
               'item.title'              => __('Item title', 'processmaker')
             ];

      foreach ($tags as $tag => $label) {
         $elt= ['tag'   => $tag,
                'label' => $label,
                'value' => true];
         if ($tag == 'var.XXX') {
            $elt['allowed_values'] = [__('XXX is to be replaced by any case variable names', 'processmaker')];
         }
         if ($tag == 'array.YYY') {
            $elt['allowed_values'] = [__('YYY is to be replaced by any array variables', 'processmaker')];
            $elt['foreach'] = true;
         }
         if ($tag == '1darray.ZZZ.key') {
            $elt['allowed_values'] = [__('ZZZ is to be replaced by any assoc array variables (1-dimension array with key/value pairs)', 'processmaker')];
         }
         $this->addTagToList($elt);
      }

      asort($this->tag_descriptions);
   }


   /**
    * Get all data needed for template processing
    **/
   public function addDataForTemplate($event, $options = []) {
      global $CFG_GLPI, $PM_DB;

      $excluded = ['_VAR_CHANGED_',
                   'PIN',
                   'APPLICATION',
                   'PROCESS',
                   'TASK',
                   'INDEX',
                   'USER_LOGGED',
                   'USR_USERNAME',
                   'APP_NUMBER',
                   'GLPI_.*',
                   'SYS_.*'
                  ];

      $process = new PluginProcessmakerProcess;

      $process->getFromDB($options['case']->fields['plugin_processmaker_processes_id']);
      $taskcat_id = $process->fields['taskcategories_id'];

      // get case variable values
      $res = $PM_DB->query("SELECT APP_DATA, APP_TITLE, APP_DESCRIPTION FROM APPLICATION WHERE APP_NUMBER = ".$options['case']->fields['id']);
      if ($res && $PM_DB->numrows($res) == 1) {
         // get all the case variables from $PM_DB
         $caserow = $PM_DB->fetchAssoc($res);
         $case_variables = unserialize($caserow['APP_DATA']);
         $excluded_re = '/^(' . implode('|', $excluded) . ')$/u';
         foreach ($case_variables as $key => $val) {
            if (!preg_match($excluded_re, $key)) {
               if (is_array($val)) {
                  // add numberof for count of rows
                  $this->data["##array.numberof$key##"] = count($val);
                  // get the keys/vals of the sub-array
                  foreach ($val as $attribute => $row) {
                     if (is_array($row)) {
                        $index = isset($this->data["array.$key"]) ? count($this->data["array.$key"]) : 0;
                        foreach ($row as $col_name => $col_val) {
                           $this->data["array.$key"][$index]["##array.$key.$col_name##"] = $col_val;
                           $this->data["##lang.array.$key.$col_name##"] = $col_name;
                        }
                     } else {
                        $this->data["1darray.$key"]["##array.$key.$attribute##"] = $row;
                        $this->data["##lang.1darray.$key.$attribute##"] = $attribute;
                     }
                  }
               } else {
                  $this->data["##var.$key##"] = $val;
                  $this->data["##lang.var.$key##"] = $key;
               }
            }
         }
         $this->data['##case.title##'] = $caserow['APP_TITLE'];
         $this->data['##case.description##'] = $caserow['APP_DESCRIPTION'];
      }

      // case id
      $this->data['##case.id##'] = $options['case']->fields['id'];

      // case URL
      $this->data['##case.url##'] = $this->formatURL($options['additionnaloption']['usertype'],
                                                     urlencode(urlencode($CFG_GLPI["url_base"] .
                                                               PluginProcessmakerCase::getFormURLWithID($options['case']->fields['id'], false))));
      // parent task information: meta data on process
      // will get parent of task which is the process task category
      $tmp_taskcatinfo['name'] = DropdownTranslation::getTranslatedValue($taskcat_id, 'TaskCategory', 'name');
      $tmp_taskcatinfo['comment'] = DropdownTranslation::getTranslatedValue($taskcat_id, 'TaskCategory', 'comment');
      // process title
      $this->data['##process.categoryid##'] = $taskcat_id;
      $this->data['##process.category##'] = $tmp_taskcatinfo['name'];
      $this->data['##process.categorycomment##'] = $tmp_taskcatinfo['comment'];

      // add information about item that hosts the case
      $item = new $options['case']->fields['itemtype'];
      $item->getFromDB($options['case']->fields['items_id']);
      $this->data['##item.type##']  = $item->getTypeName(1);
      $this->data['##item.id##']    = sprintf("%07d", $options['case']->fields['items_id']);  // to have items_id with 7 digits with leading 0
      $this->data['##item.url##']   = $this->formatURL($options['additionnaloption']['usertype'],
                                                       urlencode(urlencode($CFG_GLPI["url_base"] .
                                                                 $item::getFormURLWithID($options['case']->fields['items_id'], false))));
      $this->data['##item.title##'] = HTML::entities_deep($item->fields['name']);

      // add labels to tags that are not set
      $this->getTags();
      foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
         if (!isset($this->data[$tag])) {
            $this->data[$tag] = $values['label'];
         }
      }
   }


   /**
    * Get header to add to content
    **/
   function getContentHeader() {

      if ($this->getMode() == \Notification_NotificationTemplate::MODE_MAIL
         && MailCollector::countActiveCollectors()
         && $this->allowResponse()
      ) {
         return NotificationTargetTicket::HEADERTAG.' '.__('To answer by email, write above this line').' '.
                NotificationTargetTicket::HEADERTAG;
      }

      return '';
   }


   /**
    * Get footer to add to content
    **/
   function getContentFooter() {

      if ($this->getMode() == \Notification_NotificationTemplate::MODE_MAIL
         && MailCollector::countActiveCollectors()
         && $this->allowResponse()
      ) {
         return NotificationTargetTicket::FOOTERTAG.' '.__('To answer by email, write under this line').' '.
                NotificationTargetTicket::FOOTERTAG;
      }

      return '';
   }

}