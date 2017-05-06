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
   function __construct($itemtype) {
      parent::__construct();
      $this->itemtype=$itemtype;
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

      //$query = "SELECT * FROM ".self::getTable()." WHERE itemtype='".$this->itemtype."' AND items_id=$items_id;" ;

      //$ret = $DB->query( $query ) ;
      //if( $ret && $DB->numrows( $ret ) == 1 ) {
      //    $row = $DB->fetch_assoc( $ret ) ;
      //    $task = new $this->itemtype;
      //    if( $task->getFromDB( $row['items_id'] ) ) {
      //        // then we should add our own fields
      //        unset( $row['id'] ) ;
      //        unset( $row['items_id'] ) ;
      //        unset( $row['itemtype'] ) ;
      //        foreach( $row as $field => $val) {
      //            $task->fields[ $field ] = $val ;
      //        }
      //        $this->fields = $task->fields ;
      //        return true ;
      //    }
      //}
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
                  $event['url'] .= '&forcetab=PluginProcessmakerCase$processmakercases';

                  $taskCat = new TaskCategory;
                  $taskCat->getFromDB( $pmTask->fields['taskcategories_id'] );
                  $taskComment = isset($taskCat->fields['comment']) ? $taskCat->fields['comment'] : '';
                  if (Session::haveTranslations('TaskCategory', 'comment')) {
                     $taskComment = DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskComment );
                  }

                  $event['content'] = str_replace( '##processmaker.taskcomment##', $taskComment, $event['content'] );
                  $event['content'] = str_replace( '##ticket.url##_PluginProcessmakerCase$processmakercases', "", $event['content'] ); //<a href=\"".$event['url']."\">"."Click to manage task"."</a>
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


}
