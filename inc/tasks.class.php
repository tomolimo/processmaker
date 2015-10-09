<?php

/**
 * tasks short summary.
 *
 * tasks description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerTasks extends CommonITILTask
{
    private $itemtype ;
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
        global $DB ;
        
        $query = "SELECT * FROM ".self::getTable()." WHERE itemtype='".$this->itemtype."' AND items_id=$items_id;" ;
        
        $ret = $DB->query( $query ) ;
        if( $ret && $DB->numrows( $ret ) == 1 ) {
            $row = $DB->fetch_assoc( $ret ) ;
            $task = new $this->itemtype;
            if( $task->getFromDB( $row['items_id'] ) ) {
                // then we should add our own fields
                unset( $row['id'] ) ;
                unset( $row['items_id'] ) ;
                unset( $row['itemtype'] ) ;
                foreach( $row as $field => $val) {
                    $task->fields[ $field ] = $val ;
                }
                $this->fields = $task->fields ;
                return true ;
            }                            
        }
        return false ;
    }
    
    /**
     * Summary of getToDoTasks
     * returns all 'to do' tasks associated with this case
     * @param mixed $case_id 
     */
    public static function getToDoTasks( $case_id, $itemtype ) {
        global $DB ;
        $ret = array();
        $selfTable = getTableForItemType( __CLASS__) ;
        $itemTypeTaskTable = getTableForItemType( $itemtype );
        
        $query = "SELECT glpi_tickettasks.id as taskID from $itemTypeTaskTable  
                  INNER JOIN $selfTable on $selfTable.items_id=$itemTypeTaskTable.id 
                  WHERE $itemTypeTaskTable.state=1 and $selfTable.case_id='$case_id';";
        foreach($DB->request($query) as $row){
            $ret[$row['taskID']]=$row['taskID'];
        }
        return $ret ;
    }
}
