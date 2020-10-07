<?php
/*
 * @version $Id: notificationtargettaskcategory.class.php tomolimo $
-------------------------------------------------------------------------

 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class PluginProcessmakerNotificationTargetTask extends PluginProcessmakerNotificationTargetProcessmaker {


   /**
    * Summary of getDefaultEvents
    * @return array[]
    */
   private static function getDefaultEvents() {
      return ['task_add'      => ['event' => 'task_add_',      'label' => __('Task add'),       'glpi' => 'add_task'],
              'task_reassign' => ['event' => 'task_reassign_', 'label' => __('Task re-assign'), 'glpi' => 'update_task'],
              'task_unclaim'  => ['event' => 'task_unclaim_',  'label' => __('Task un-claim'),  'glpi' => 'update_task'],
              'task_done'     => ['event' => 'task_done_',     'label' => __('Task done'),      'glpi' => 'update_task'],
              'task_reminder' => ['event' => 'task_reminder_', 'label' => __('Task reminder'),  'glpi' => 'update_task']
             ];
   }


   /**
    * Summary of getDefaultGLPIEvents
    * Will return the GLPI default task event matching a self type
    * @param string $type
    * @return string
    */
   static function getDefaultGLPIEvents($type) {
      $events = self::getDefaultEvents();
      return $events[$type]['glpi'];
   }


   /**
    * Summary of getNotification
    * @param mixed $evt
    * @param mixed $taskcat
    * @param mixed $entity
    * @return array
    */
   static function getNotifications($evt, $taskcat, $entity) {
      // search if at least one active notification is existing for that pm task with that event 'task_update_'.$glpi_task->fields['taskcategories_id']
      $defaultEvents = self::getDefaultEvents();
      $event = $defaultEvents[$evt]['event'].$taskcat;
      $dbu = new DbUtils;
      $crit = $dbu->getEntitiesRestrictCriteria(Notification::getTable(), 'entities_id', $entity, true);
      return ['event' => $event, 'notifications' => $dbu->getAllDataFromTable(Notification::getTable(), ['itemtype' => 'PluginProcessmakerTask', 'event' => $event, 'is_active' => 1, $crit])];
   }


   /**
    * Summary of getEvents
    * @return string[]
    */
   public function getEvents() {
      global $DB;
      $actions = [];

      $defaultEvents = self::getDefaultEvents();

      $table = PluginProcessmakerTaskCategory::getTable();
      $ptable = PluginProcessmakerProcess::getTable();
      $query = "SELECT $table.taskcategories_id AS taskcat, $ptable.taskcategories_id AS ptaskcat FROM $table
                LEFT JOIN $ptable ON $ptable.id=$table.plugin_processmaker_processes_id";

      $ptaskcats = [];
      $temp = new TaskCategory;
      foreach ($DB->request($query) as $row) {
         if (!isset($ptaskcats[$row['ptaskcat']])) {
            $temp->getFromDB($row['ptaskcat']);
            $ptaskcats[$row['ptaskcat']] = $temp->fields['name'];
         }
         $temp->getFromDB($row['taskcat']);

         foreach($defaultEvents as $events) {
            $actions[$events['event'].$row['taskcat']] = $ptaskcats[$row['ptaskcat']]." > ".$temp->fields['name'].": " . $events['label'];
         }
      }

      return $actions;
   }


   /**
    * Get all data needed for template processing
    **/
   public function addDataForTemplate($event, $options = []) {
      global $PM_DB, $CFG_GLPI;

      if (!isset($options['case']) || $options['case'] == null) {
         $mycase = new PluginProcessmakerCase;
         $mycase->getFromDB($options['plugin_processmaker_cases_id']);
         $options['case'] = $mycase;
      }
      parent::addDataForTemplate($event, $options);

      $events = self::getDefaultEvents();
      $locevent = explode('_', $event);
      $baseevent = $locevent[0].'_'.$locevent[1];
      $taskcat_id = $locevent[2];

      // task action: add, update or done
      $this->data['##task.action##'] = $events[$baseevent]['label'];

      // task category information: meta data on task
      $tmp_taskcatinfo['name'] = DropdownTranslation::getTranslatedValue($taskcat_id, 'TaskCategory', 'name');
      $tmp_taskcatinfo['comment'] = DropdownTranslation::getTranslatedValue($taskcat_id, 'TaskCategory', 'comment');
      $this->data['##task.categoryid##'] = $taskcat_id;
      $this->data['##task.category##'] = $tmp_taskcatinfo['name'];
      $this->data['##task.categorycomment##'] = $tmp_taskcatinfo['comment'];

      // task information
      $taskobj = $this->obj;

      // del index
      $this->data['##task.delindex##'] = $taskobj->fields['del_index'];

      // is private?
      $this->data['##task.isprivate##'] = Dropdown::getYesNo(false);
      if ($taskobj->maybePrivate()) {
         $this->data['##task.isprivate##'] = Dropdown::getYesNo($taskobj->fields['is_private']);
      }
      // status
      $this->data['##task.status##'] = Planning::getState($taskobj->fields['state']);
      // creation date
      $this->data['##task.date##'] = Html::convDateTime($taskobj->fields['date_creation']);
      // update date
      $this->data['##task.update##'] = Html::convDateTime($taskobj->fields['date_mod']);
      // content: don't know if this could be interesting
      $this->data['##task.description##'] = $taskobj->fields['content'];

      // task creator
      // should always be Process Maker user
      $dbu = new DbUtils();
      $this->data['##task.author##'] = Html::clean($dbu->getUserName($taskobj->fields['users_id']));

      // task editor
      $this->data['##task.lastupdater##'] = Html::clean($dbu->getUserName($taskobj->fields['users_id_editor']));

      // task technician
      $this->data['##task.user##'] = '';
      $this->data['##task.user.login##'] = ''; // by default
      $tech = new User;
      if ($taskobj->fields['users_id_tech'] > 0 
          && $tech->getFromDB($taskobj->fields['users_id_tech'])) {
         $this->data['##task.user##'] = Html::clean($dbu->getUserName($taskobj->fields['users_id_tech']));
         $this->data['##task.user.login##'] = $tech->fields['name'];
      }
      $oldtech = new User;
      if (isset($options['old_users_id_tech']) 
          && $options['old_users_id_tech'] > 0 
          && $oldtech->getFromDB($options['old_users_id_tech'])) {
         $this->data['##task.former.user##'] = Html::clean($dbu->getUserName($options['old_users_id_tech']));
         $this->data['##task.former.user.login##'] = $oldtech->fields['name'];
      }

      // task group technician
      $this->data['##task.group##'] = Html::clean(Toolbox::clean_cross_side_scripting_deep(Dropdown::getDropdownName("glpi_groups", $taskobj->fields['groups_id_tech'])), true, 2, false);

      // task planning
      $this->data['##task.begin##'] = '';
      $this->data['##task.end##'] = '';
      if (!is_null($taskobj->fields['begin'])) {
         $this->data['##task.begin##'] = Html::convDateTime($taskobj->fields['begin']);
         $this->data['##task.end##'] = Html::convDateTime($taskobj->fields['end']);
      }
      // task duration
      $this->data['##task.time##'] = Html::timestampToString($taskobj->fields['actiontime'], false);

      // add labels to tags that are not set
      $this->getTags();
      foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
         if (!isset($this->data[$tag])) {
            $this->data[$tag] = $values['label'];
         }
      }
   }


   /**
    * Summary of getTags
    */
   public function getTags() {

      parent::getTags();

      $tags = ['task.action'             => __('Task action', 'processmaker'),
               'task.author'             => __('Writer'),
               'task.isprivate'          => __('Private'),
               'task.date'               => __('Opening date'),
               'task.description'        => __('Description'),
               'task.categoryid'         => __('Category id'),
               'task.category'           => __('Category'),
               'task.categorycomment'    => __('Category comment'),
               'task.time'               => __('Total duration'),
               'task.user'               => __('User assigned to task'),
               'task.user.login'         => __('User login assigned to task'),
               'task.former.user'        => __('Former user assigned to task'),
               'task.former.user.login'  => __('Former user login assigned to task'),
               'task.group'              => __('Group assigned to task'),
               'task.begin'              => __('Start date'),
               'task.end'                => __('End date'),
               'task.status'             => __('Status'),
               'task.lastupdater'        => __('Last updater'),
               'task.update'             => __('Last update'),
               'task.delindex'           => __('Delegation index')
             ];
      foreach ($tags as $tag => $label) {
         $elt= ['tag'   => $tag,
                'label' => $label,
                'value' => true];

         $this->addTagToList($elt);
      }
      asort($this->tag_descriptions);
   }


   /**
    * Summary of addAdditionalTargets
    * @param mixed $event
    */
   function addAdditionalTargets($event = '') {

      $this->addTarget(Notification::TASK_ASSIGN_TECH, __('Technician in charge of the task'));
      $this->addTarget(Notification::TASK_ASSIGN_GROUP, __('Group in charge of the task'));

      $this->addTarget(Notification::OBSERVER_GROUP, __('Watcher group'));
      $this->addTarget(Notification::OBSERVER, __('Watcher'));

      $this->addTarget(Notification::AUTHOR, __('Requester'), PluginProcessmakerNotificationTargetProcessmaker::PM_USER_TYPE);

      if (strpos($event, 'task_update_') === 0) {
         $this->addTarget(Notification::OLD_TECH_IN_CHARGE,
                          __('Former technician in charge of the task'));
      }

   }


   /**
    * Summary of addSpecificTargets
    * @param mixed $data
    * @param mixed $options
    */
   function addSpecificTargets($data, $options) {

      $obj = $this->obj;
      $this->obj = $options['obj'];

      switch ($data['type']) {
         case PluginProcessmakerNotificationTargetProcessmaker::PM_USER_TYPE :

            switch ($data['items_id']) {

               //Send to the requester of the ITIL object
               case Notification::AUTHOR :
                  $this->addItemAuthor();
                  break;
            }
            break;

         case Notification::USER_TYPE :

            switch ($data['items_id']) {

               //Notification to the ITIL object's observer group
               case Notification::OBSERVER_GROUP :
                  $this->addLinkedGroupByType(CommonITILActor::OBSERVER);
                  break;

               //Notification to the ITIL object's observer user
               case Notification::OBSERVER :
                  $this->addLinkedUserByType(CommonITILActor::OBSERVER);
                  break;

               //Send to the ITIL object followup author
               case Notification::TASK_ASSIGN_TECH :
                  $this->addTaskAssignUser($options);
                  break;

               //Send to the ITIL object task group assigned
               case Notification::TASK_ASSIGN_GROUP :
                  $this->addTaskAssignGroup($options);
                  break;

               //Send to the technician previously in charge of the task (before re-assignment)
               case Notification::OLD_TECH_IN_CHARGE :
                  $this->addOldAssignTechnician($options);
                  break;

            }
      }

      $this->obj = $obj;
   }


   /**
    * Summary of addOldAssignTechnician
    * @param  $options
    */
   function addOldAssignTechnician($options = []) {
      global $DB;

      // In case of delete task pass user id
      if (isset($options['old_users_id_tech'])) {
         $query = $this->getDistinctUserSql()."
                  FROM `glpi_users` ".
                  $this->getProfileJoinSql()."
                  WHERE `glpi_users`.`id` = '".$options['old_users_id_tech']."'";

         foreach ($DB->request($query) as $data) {
            $this->addToRecipientsList($data);
         }
      }
   }

}
