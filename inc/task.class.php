<?php

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
   function __construct($itemtype='TicketTask') {
      parent::__construct();
      $this->itemtype=$itemtype;
   }

   /**
    * Name of the type
    *
    * @param $nb : number of item in the type (default 0)
    **/
   static function getTypeName($nb=0) {
      return _n('Process case task', 'Process case tasks', $nb);

   }

   function getItilObjectItemType() {
      return str_replace('Task', '', $this->itemtype);
   }
    /**
     * Summary of getFromDB
     * @param mixed $items_id
     * @param mixed $itemtype
     * @return bool
     */
   function getFromDB($items_id) {
      global $DB;

      if ($this->getFromDBByQuery(" WHERE itemtype='".$this->itemtype."' AND items_id=$items_id;" )) {
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
     * Summary of getToDoTasks
     * returns all 'to do' tasks associated with this case
     * @param mixed $case_id
     */
   public static function getToDoTasks( $case_id, $itemtype ) {
      global $DB;
      $ret = array();
      $selfTable = getTableForItemType( __CLASS__);
      $itemTypeTaskTable = getTableForItemType( $itemtype );

      $query = "SELECT glpi_tickettasks.id as taskID from $itemTypeTaskTable
                  INNER JOIN $selfTable on $selfTable.items_id=$itemTypeTaskTable.id
                  WHERE $itemTypeTaskTable.state=1 and $selfTable.case_id='$case_id';";
      foreach ($DB->request($query) as $row) {
         $ret[$row['taskID']]=$row['taskID'];
      }
      return $ret;
   }

   static function canView( ) {
      return true;
   }

   static function populatePlanning($params) {
      global $CFG_GLPI;

      $ret = array();
      $events = array();
      if (isset($params['start'])) {
         $params['begin'] = '2000-01-01 00:00:00';
         if ($params['type'] == 'group') {
            $params['who_group'] = $params['who'];
            $params['whogroup'] = $params['who'];
            $params['who'] = 0;
         }
         $ret = CommonITILTask::genericPopulatePlanning( 'TicketTask', $params );

         foreach ($ret as $key => $event) {
            if ($event['state'] == 1 || ($params['display_done_events'] == 1 && $event['state'] == 2)) { // if todo or done but need to show them (=planning)
               // check if task is one within a case
               $pmTask = new self('TicketTask');
               if ($pmTask->getFromDB( $event['tickettasks_id'] )) { // $pmTask->getFromDBByQuery( " WHERE itemtype = 'TicketTask' AND items_id = ". $event['tickettasks_id'] ) ) {
                  $event['editable'] = false;
                  //$event['url'] .= '&forcetab=PluginProcessmakerCase$processmakercases';
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
                  $event['content'] = str_replace( '##processmakercase.url##', "", $event['content'] ); //<a href=\"".$event['url']."\">"."Click to manage task"."</a>
                  //if( $event['state'] == 1 && $event['end'] < $params['start'] ) { // if todo and late
                  //   $event['name'] = $event['end'].' '.$event['name'] ; //$event['begin'].' to '.$event['end'].' '.$event['name'] ;
                  //   $event['end'] = $params['start'].' 24:00:00'; //.$CFG_GLPI['planning_end'];
                  //}
                  $events[$key] = $event;
               }
            }
         }
      }
      return $events;
   }


   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      global $DB, $LANG;

      $caseInfo = $case->getCaseInfo();

      if (property_exists($caseInfo, 'currentUsers')) {

         $GLPICurrentPMUserId = PluginProcessmakerUser::getPMUserId(Session::getLoginUserID());

         // get all tasks that are OPEN for this case
         $tasks = [];
         $query = "SELECT * FROM `glpi_plugin_processmaker_tasks` WHERE `plugin_processmaker_cases_id`={$case->fields['id']} AND `del_thread_status`='OPEN'";
         foreach($DB->request($query) as $task) {
            $tasks[$task['del_index']] = $task;
         }

         // get all tasks that are OPEN for any sub-case of this case
         $sub_tasks = [];
         $query = "SELECT `glpi_plugin_processmaker_tasks`.*  FROM `glpi_plugin_processmaker_tasks`
                  JOIN `glpi_plugin_processmaker_cases` on `glpi_plugin_processmaker_cases`.`id`=`glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id`
                  WHERE `glpi_plugin_processmaker_cases`.`plugin_processmaker_cases_id`={$case->fields['id']} AND `del_thread_status`='OPEN'";
         foreach($DB->request($query) as $task) {
            $sub_tasks[$task['plugin_processmaker_cases_id']][$task['del_index']] = $task;
         }

         $caseInfo->currentUsers = $case->sortTasks($caseInfo->currentUsers, $GLPICurrentPMUserId);

         $tab = [];
         foreach ($caseInfo->currentUsers as $key => $caseUser) {
            $title = $caseUser->taskName;
            if (isset($tasks[$caseUser->delIndex])) {
               $hide_claim_button = false;
               if ($caseUser->userId == '') { // task to be claimed
                  $itemtask = getItemForItemtype($tasks[$caseUser->delIndex]['itemtype']);
                  $itemtask->getFromDB($tasks[$caseUser->delIndex]['items_id']);
                  // check if this group can be found in the current user's groups
                  if (!isset($_SESSION['glpigroups']) || !in_array( $itemtask->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
                     $hide_claim_button = true;
                  }
               }
               $tab[$tasks[$caseUser->delIndex]['id']] = ($caseUser->userId != '' && $caseUser->userId != $GLPICurrentPMUserId) || $hide_claim_button ? "<i><sub>$title</sub></i>" : $title;
            }
         }
      }

      //// TODO manage of sub-tasks.
      //foreach ($sub_tasks as $scases_id => $scase) {
      //   foreach (
      //}


   return $tab;

   }


   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $case the PluginProcessmakerCase
    * @param integer $tabnum contains the PluginProcessmakerTask id
    * @param mixed $withtemplate
    */
   static function displayTabContentForItem(CommonGLPI $case, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI, $PM_SOAP;

      $hide_claim_button = false;
      $config = $PM_SOAP->config;
      $rand = rand();

      // get infos for the current task
      $task = getAllDatasFromTable('glpi_plugin_processmaker_tasks', "id = $tabnum");

      // TODO manage sub-tasks

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
         if ($currentUser->userId && $task[$tabnum]['del_index']) {
            // to load users for task re-assign only when task is not to be 'claimed' and if task is not a sub-case

            echo "<div class='tab_bg_2' id='divUsers-".$currentUser->delIndex."'><div class='loadingindicator'>".__('Loading...')."</div></div>";
            echo "<script>$('#divUsers-{$task[$tabnum]['del_index']}').load( '".$CFG_GLPI["root_doc"]."/plugins/processmaker/ajax/task_users.php?cases_id="
                     .$case->getID()
                     ."&items_id="
                     .$case->fields['items_id']
                     ."&itemtype="
                     .$case->fields['itemtype']
                     ."&users_id="
                     .PluginProcessmakerUser::getGLPIUserId($currentUser->userId)
                     ."&taskGuid="
                     .$currentUser->taskId
                     ."&delIndex={$task[$tabnum]['del_index']}&delThread={$currentUser->delThread}&rand=$rand' ); </script>";
         } else {
            // manages the claim
            // current task is to be claimed
            // get the assigned group to the item task
            $itemtask = getItemForItemtype( $task[$tabnum]['itemtype'] );
            $itemtask->getFromDB( $task[$tabnum]['items_id'] );
            // check if this group can be found in the current user's groups
            if (!isset($_SESSION['glpigroups']) || !in_array( $itemtask->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
               $hide_claim_button=true;
            }
         }
      }

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>";

      $csrf = Session::getNewCSRFToken();

      echo "<iframe id='caseiframe-task-{$task[$tabnum]['del_index']}' onload=\"onTaskFrameLoad( event, {$task[$tabnum]['del_index']}, "
         .($hide_claim_button?"true":"false")
         .", '$csrf' );\" style='border:none;' class='tab_bg_2' width='100%' src='";
      echo $PM_SOAP->serverURL
         ."/cases/cases_Open?sid="
         .$PM_SOAP->getPMSessionID()
         ."&APP_UID="
         .$case->fields['case_guid']
         ."&DEL_INDEX="
         .$task[$tabnum]['del_index']
         ."&action=TO_DO";
      echo "&rand=$rand&glpi_domain={$config->fields['domain']}'></iframe></div>";

   }


}
