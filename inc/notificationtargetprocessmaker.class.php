<?php

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
class PluginProcessmakerNotificationTargetProcessmaker extends NotificationTarget {

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
               'case.url'                => __('URL'),
               'var.XXX'                 => __('Case variable \'XXX\'', 'processmaker'),
               'array.YYY'               => __('List of values in \'YYY\' array', 'processmaker'),
               'array.numberofYYY'       => __('Number of rows in \'YYY\' array', 'processmaker'),
               'array.YYY.colname'       => __('Value for colname in case array \'YYY\'', 'processamker')
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
         $this->addTagToList($elt);
      }

      asort($this->tag_descriptions);
   }


   /**
    * Get all data needed for template processing
    **/
   public function addDataForTemplate($event, $options = []) {
      global $PM_DB, $CFG_GLPI;

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

      // set defaults to all
      foreach ($this->tags as $key => $val) {
         $this->data["##$key##"] = "-";
      }

      // get case variable values
      $res = $PM_DB->query("SELECT APP_DATA, APP_TITLE, APP_DESCRIPTION FROM APPLICATION WHERE APP_NUMBER = ".$options['case']->fields['id']);
      if ($res && $PM_DB->numrows($res) == 1) {
         // get all the case variables from $PM_DB
         $caserow = $PM_DB->fetch_assoc($res);
         $case_variables = unserialize($caserow['APP_DATA']);
         $excluded_re = '/^(' . implode('|', $excluded) . ')$/u';
         foreach ($case_variables as $key => $val) {
            if (!preg_match($excluded_re, $key)) {
               if (is_array($val)) {
                  // add numberof for count of rows
                  $this->data["##array.numberof$key##"] = count($val);
                  // get the keys/vals of the sub-array
                  foreach ($val as $row) {
                     foreach ($row as $col_name => $col_val) {
                        $this->data["array.$key"][]["##array.$key.$col_name##"] = $col_val;
                        $this->data["##lang.array.$key.$col_name##"] = $col_name;
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
      $this->data['##case.url##'] = $CFG_GLPI["url_base"]."/index.php?redirect=".urlencode("/plugins/processmaker/front/case.form.php?id=".$options['case']->fields['id']);

      // parent task information: meta data on process
      // will get parent of task which is the process task category
      $tmp_taskcatinfo['name'] = DropdownTranslation::getTranslatedValue( $taskcat_id, 'TaskCategory', 'name');
      $tmp_taskcatinfo['comment'] = DropdownTranslation::getTranslatedValue( $taskcat_id, 'TaskCategory', 'comment');
      // process title
      $this->data['##process.categoryid##'] = $taskcat_id;
      $this->data['##process.category##'] = $tmp_taskcatinfo['name'];
      $this->data['##process.categorycomment##'] = $tmp_taskcatinfo['comment'];

      // add labels
      $this->getTags();
      foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
         if (!isset($this->data[$tag])) {
            $this->data[$tag] = $values['label'];
         }
      }
   }

}