<?php

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

    function canCreate() {
        return plugin_processmaker_haveRight('process_config', 'w');
    }

    
    function canView() {
        return plugin_processmaker_haveRight('process_config', 'r');
    }

    
    function maybeDeleted(){
        return false ;
    }

    /**
     * Summary of refreshTasks
     * will refresh (re-synch) all process task list
     */
    function refreshTasks( $post ) {
        global $DB, $CFG_GLPI ;
        
        if( $this->getFromDB( $post['id'] ) ) {
            // here we are in the right process
            // we need to get the tasks + content from PM db
            $config = new PluginProcessmakerConfig ;
            $config->getFromDB( 1 ) ;
            $database = $config->fields['pm_workspace'] ;
            if( TableExists( 'glpi_dropdowntranslations' ) && class_exists('DropdownTranslation') ){ 
                // to force rigths to add translations
                $_SESSION['glpi_dropdowntranslations']['TaskCategory']['name'] = 'name' ;
                $_SESSION['glpi_dropdowntranslations']['TaskCategory']['completename'] = 'completename' ;
                $_SESSION['glpi_dropdowntranslations']['TaskCategory']['comment'] = 'comment' ;
                $translates = true ;
                // create a reversed map for languages
                foreach( $CFG_GLPI['languages'] as $key => $valArray){
                    $mapLangs[ locale_get_primary_language( $key ) ][] = $key ;
                }
            } else {
                $translates = false ;
                $mapLangs = array( ) ;
            }
            $lang = $CFG_GLPI['languages'][ $CFG_GLPI['language'] ][ 2 ] ; 
            $query = "select task.TAS_UID, task.TAS_START, content.CON_LANG, content.CON_CATEGORY, content.CON_VALUE from wf_$database.task
                        inner join wf_$database.content on content.CON_ID=task.TAS_UID
                        where task.PRO_UID = '".$this->fields['process_guid']."' and content.CON_CATEGORY in ('TAS_TITLE', 'TAS_DESCRIPTION') ".($translates?"":"and content.CON_LANG='$lang'")." ;" ;
            $taskArray = array() ;
            $defaultLangTaskArray=array();
            foreach( $DB->request( $query ) as $task ) { 
                if( $task['CON_LANG'] == $lang ) {
                    $defaultLangTaskArray[ $task['TAS_UID'] ][ $task['CON_CATEGORY'] ]  = $task['CON_VALUE'] ; 
                    $defaultLangTaskArray[ $task['TAS_UID'] ]['start']=($task['TAS_START']=='TRUE'?true:false);
                } else {
                    foreach( $mapLangs[ $task['CON_LANG'] ] as $valL ) {
                        $taskArray[ $task['TAS_UID'] ][ $valL ][ $task['CON_CATEGORY'] ]  = $task['CON_VALUE'] ; 
                    }
                }
            }
                        
            foreach( $defaultLangTaskArray as $taskGUID => $task ) {
                $pmTaskCat = new PluginProcessmakerTaskCategory ;
                $taskCat = new TaskCategory ;
                if( $pmTaskCat->getFromDBbyExternalID( $taskGUID ) ){
                    // got it then check names, and if != update
                    if( $taskCat->getFromDB( $pmTaskCat->fields['taskcategories_id'] ) ) {
                        // found it must test if should be updated
                        if( $taskCat->fields['name'] != $task['TAS_TITLE'] || $taskCat->fields['comment'] != $task['TAS_DESCRIPTION'] ) {
                            $taskCat->update( array( 'id' => $taskCat->getID(), 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) ) ;
                        }
                        if(  $pmTaskCat->fields['start'] != $task['start'] ) {
                                $pmTaskCat->update( array( 'id' =>  $pmTaskCat->getID(), 'start' => $task['start'] ) ) ;
                        }
                    } else {
                        // taskcat must be created
                        $taskCat->add( array( 'is_recursive' => true, 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) ) ;
                        // update pmTaskCat
                        $pmTaskCat->update( array( 'id' => $pmTaskCat->getID(), 'taskcategories_id' => $taskCat->getID(), 'start' => $task['start'] ) ) ;
                    }                    
                } else {
                    // should create a new one
                    // taskcat must be created
                    $taskCat->add( array( 'is_recursive' => true, 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) ) ;
                    // pmTaskCat must be created too
                    $pmTaskCat->add( array( 'processes_id' => $this->getID(), 'pm_task_guid' => $taskGUID, 'taskcategories_id' => $taskCat->getID(), 'start' => $task['start'] ) ) ;                    
                }
                // here we should take into account translations if any
                if( $translates && isset($taskArray[ $taskGUID ])  ) {
                    foreach( $taskArray[ $taskGUID ] as $langTask => $taskL ) {
                        // look for 'name' field
                        if( $loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'name', $langTask ) ) {                            
                            if( DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'name', $langTask ) != $taskL[ 'TAS_TITLE' ] ) {
                                // must be updated
                                $trans = new DropdownTranslation ;
                                $trans->update( array( 'id' => $loc_id, 'field' => 'name', 'value' => $taskL[ 'TAS_TITLE' ], 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ) ) ;
                                $trans->generateCompletename( array( 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ) ) ; 
                            }
                        } else {
                            // must be added
                            // must be updated
                            $trans = new DropdownTranslation ;
                            $trans->add( array( 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'name', 'value' => $taskL[ 'TAS_TITLE' ] ) ) ;
                            $trans->generateCompletename( array( 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(),'language' => $langTask ) ) ; 
                        }
                        
                        // look for 'comment' field
                        if( $loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'comment', $langTask ) ) {                            
                            if( DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $langTask ) != $taskL[ 'TAS_DESCRIPTION' ] ) {
                                // must be updated
                                $trans = new DropdownTranslation ;
                                $trans->update( array( 'id' => $loc_id, 'field' => 'comment', 'value' => $taskL[ 'TAS_DESCRIPTION' ] , 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask) ) ;
                            }
                        } else {
                            // must be added
                            $trans = new DropdownTranslation ;
                            $trans->add( array( 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'comment', 'value' => $taskL[ 'TAS_DESCRIPTION' ] ) ) ;
                        }
                        
                    }
                }
            }            

            //if( $translates  ) {
            //    unset( $_SESSION['glpi_dropdowntranslations']['TaskCategory'] ) ;
            //}
        }
        
    }
    
    /**
     * Summary of refresh
     * used to refresh process list and task category list
     */
    function refresh( ) {
        // then refresh list of available process from PM to inner table
        $pm = new PluginProcessmakerProcessmaker ;
        $pm->login( true ) ;
        $pmProcessList = $pm->processList() ;
        
        $config = new PluginProcessmakerConfig ;
        $config->getFromDB( 1 ) ;
        $pmMainTaskCat = $config->fields['taskcategories_id'] ;
        
        // and get processlist from GLPI
        foreach( $pmProcessList as $process ) {
            $glpiprocess = new PluginProcessmakerProcess ;            
            if( $glpiprocess->getFromDBbyExternalID($process->guid) ) {            
                // then update it only if name has changed
                if( $glpiprocess->fields['name'] != $process->name ) {
                    $glpiprocess->update( array( 'id' => $glpiprocess->getID(), 'name' => $process->name ) ) ;
                }
                // and check if main task category needs update
                if( !$glpiprocess->fields['taskcategories_id'] ) {
                    // then needs to be added
                    $glpiprocess->addTaskCategory( $pmMainTaskCat ) ;
                } else {
                    $glpiprocess->updateTaskCategory( ) ;
                }
            } else {
                // create it
                if( $glpiprocess->add( array( 'process_guid' => $process->guid, 'name' => $process->name )) ){
                    // and add main task category for this process
                    $glpiprocess->addTaskCategory( $pmMainTaskCat ) ;
                }
            }                        
        }

    }

    /**
     * Summary of updateTaskCategory
     * Updates TaskCategory for current process, only if needed (i.e. name has changed)
     * returns true if update is done, false otherwise
     */
    function updateTaskCategory( ) {
        $taskCat = new TaskCategory ;
        if( $taskCat->getFromDB( $this->fields['taskcategories_id'] ) && $taskCat->fields['name'] != $this->fields['name'] ) {
            return $taskCat->update( array( 'id' => $taskCat->getID(), 'name' => $this->fields['name'] ) ) ;
        }        
        return false ;
    }
    
    /**
     * Summary of addTaskCategory
     * Adds a new TaskCategory for $this process
     * @param int $pmMainTaskCat is the main TaskCategory from PM configuration
     * returns true if TaskCategory has been created and updated into $this process, else otherwise
     */
    function addTaskCategory( $pmMainTaskCat ) {
        $taskCat = new TaskCategory ;
        if( $taskCat->add( array( 'is_recursive' => true, 'taskcategories_id' => $pmMainTaskCat, 'name' => $this->fields['name']) ) ) {
            return $this->update( array( 'id' => $this->getID(), 'taskcategories_id' => $taskCat->getID() ) ) ;
        }
        return false ;
    }
    
       
    /**
     * Print a good title for process pages
     * add button for re-synchro of process list (only if rigths are w)
     * @return nothing (display)
     **/
    function title() {
        global $LANG, $CFG_GLPI;

        $buttons = array();
        $title = $LANG['processmaker']['config']['refreshprocesslist'];

        if ($this->canCreate()) {
            $buttons["process.php?refresh=1"] = $LANG['processmaker']['config']['refreshprocesslist'];
            $title = "";
            Html::displayTitle($CFG_GLPI["root_doc"] . "/plugins/processmaker/pics/gears.png", $LANG['processmaker']['config']['refreshprocesslist'], $title,
                                       $buttons);
        }
        
    }
    
    /**
     * Retrieve a Process from the database using its external id (unique index): process_guid
     *
     * @param $extid string externalid
     *
     * @return true if succeed else false
     **/
    function getFromDBbyExternalID($extid) {
        global $DB;

        $query = "SELECT *
                FROM `".$this->getTable()."`
                WHERE `process_guid` = '$extid'";

        if ($result = $DB->query($query)) {
            if ($DB->numrows($result) != 1) {
                return false;
            }
            $this->fields = $DB->fetch_assoc($result);
            if (is_array($this->fields) && count($this->fields)) {
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * Summary of getSearchOptions
     * @return mixed
     */
    function getSearchOptions() {
        global $LANG;

        $tab = array();

        $tab['common'] = $LANG['processmaker']['title'][1];

        $tab[1]['table']         = 'glpi_plugin_processmaker_processes';
        $tab[1]['field']         = 'name';
        $tab[1]['name']          = $LANG['common'][16];
        $tab[1]['datatype']      = 'itemlink';
        $tab[1]['itemlink_type'] = $this->getType();

        //$tab[7]['table']         = 'glpi_plugin_processmaker_processes';
        //$tab[7]['field']         = 'is_helpdeskvisible';
        //$tab[7]['name']          = $LANG['tracking'][39];
        //$tab[7]['massiveaction'] = true;
        //$tab[7]['datatype']      = 'bool';
        
        $tab[8]['table']         = 'glpi_plugin_processmaker_processes';
        $tab[8]['field']         = 'is_active';
        $tab[8]['name']          = $LANG['common'][60];
        $tab[8]['massiveaction'] = true;
        $tab[8]['datatype']      = 'bool';

        $tab[4]['table']        = 'glpi_plugin_processmaker_processes';
        $tab[4]['field']        =  'comment';
        $tab[4]['name']         =  $LANG['common'][25];
        $tab[4]['massiveaction'] = true;
        $tab[4]['datatype']     =  'text';       
        
        $tab[9]['table']         = 'glpi_plugin_processmaker_processes';
        $tab[9]['field']         = 'date_mod';
        $tab[9]['name']          = $LANG['common'][26];
        $tab[9]['massiveaction'] = false;
        $tab[9]['datatype']      = 'datetime';
        
        $tab[10]['table']        = 'glpi_plugin_processmaker_processes';
        $tab[10]['field']        =  'process_guid';
        $tab[10]['name']         =  $LANG['processmaker']['process']['process_guid'];
        $tab[10]['massiveaction'] = false;
        $tab[10]['datatype']     =  'text';


        return $tab;
    }

    static function getTypeName($nb=0) {
        global $LANG;

        if ($nb>1) {
            return $LANG['processmaker']['title'][1];
        }
        return $LANG['processmaker']['title'][2];
    }
        
    function defineTabs($options=array()) {

        $ong = array('empty' => $this->getTypeName(1));
        $this->addStandardTab('PluginProcessmakerTaskCategory', $ong, $options);
        $this->addStandardTab('PluginProcessmakerProcess_Profile', $ong, $options);
        //$this->addStandardTab('Ticket', $ong, $options);
        //$this->addStandardTab('Log', $ong, $options);

        return $ong;
    }
    
    function showForm ($ID, $options=array('candel'=>false)) {
      global $DB, $CFG_GLPI, $LANG;

      if ($ID > 0) {
         $this->check($ID,'r');
      } 

      $canedit = $this->can($ID,'w');
      
      $this->showTabs($options);    
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG["common"][16]."&nbsp;:</td><td>";
      //Html::autocompletionTextField($this, "name");
      echo $this->fields["name"];
      echo "</td>";
      echo "<td rowspan='5' class='middle right'>".$LANG["common"][25]."&nbsp;:</td>";
      echo "<td class='center middle' rowspan='5'><textarea cols='45' rows='6' name='comment' >".
             $this->fields["comment"]."</textarea></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['process_guid']."&nbsp;:</td><td>";
      echo $this->fields["process_guid"];
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['common'][60]."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_active",$this->fields["is_active"]);
      echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".$LANG['tracking'][39]."&nbsp;:</td><td>";
      //Dropdown::showYesNo("is_helpdeskvisible",$this->fields["is_helpdeskvisible"]);
      //echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['hide_case_num_title']."&nbsp;:</td><td>";
      Dropdown::showYesNo("hide_case_num_title",$this->fields["hide_case_num_title"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['insert_task_comment']."&nbsp;:</td><td>";
      Dropdown::showYesNo("insert_task_comment",$this->fields["insert_task_comment"]);
      echo "</td></tr>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['type']."&nbsp;:</td><td>";
      if (true) { // $canupdate || !$ID
            $idticketcategorysearch = mt_rand(); $opt = array('value' => $this->fields["type"]);
            $rand = $idtype = Ticket::dropdownType('type', $opt, array(),array('toupdate' => "search_".$idticketcategorysearch ));
            $opt = array('value' => $this->fields["type"]);
            $params = array('type'            => '__VALUE__',
                            'entity_restrict' => $this->fields['entities_id'],
                            'value'           => $this->fields['itilcategories_id'],
                            'currenttype'     => $this->fields['type']);

            Ajax::updateItemOnSelectEvent("dropdown_type$rand", "show_category_by_type",
                                            $CFG_GLPI["root_doc"]."/ajax/dropdownTicketCategories.php",
                                            $params);
      } else {
          echo Ticket::getTicketTypeName($this->fields["type"]);
      }
      echo "</td>";
      
      echo "<td >".$LANG['processmaker']['process']['itilcategory']."&nbsp;:</td><td>";
      if (true ) { // $canupdate || !$ID || $canupdate_descr
          $opt = array('value'  => $this->fields["itilcategories_id"]);

          switch ($this->fields['type']) {
              case Ticket::INCIDENT_TYPE :
                  $opt['condition'] .= "`is_incident`='1'";
                  break;

              case Ticket::DEMAND_TYPE :
                  $opt['condition'] .= "`is_request`='1'";
                  break;

              default :
                  break;
          }

          echo "<span id='show_category_by_type'>";
          if( isset($idticketcategorysearch) ) $opt['rand'] = $idticketcategorysearch;
          Dropdown::show('ITILCategory', $opt);
          echo "</span>";
      } else {
          echo Dropdown::getDropdownName("glpi_itilcategories", $this->fields["itilcategories_id"]);
      }
      echo "</td></tr>";
      
      
      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['common'][26]."&nbsp;:</td><td>";
      echo Html::convDateTime($this->fields["date_mod"]);
      echo "</td></tr>";
      
      $this->showFormButtons($options );
      $this->addDivForTabs();
      
    }
    
    
    
    /**
     * Execute the query to select box with all glpi users where select key = name
     *
     * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
     *
     * @param $count true if execute an count(*),
     * @param $right limit user who have specific right
     * @param $entity_restrict Restrict to a defined entity
     * @param $value default value
     * @param $used Already used items ID: not to display in dropdown
     * @param $search pattern
     *
     * @return mysql result set.
     **/
    static function getSqlSearchResult ($count=true, $right="all", $entity_restrict=-1, $value=0,
                                        $used=array(), $search='') {
        global $DB, $CFG_GLPI;

        $orderby = '' ;
        
        $where = ' WHERE glpi_plugin_processmaker_processes.is_active=1 ' ;
        
        if( $count ) {
            $fields = " COUNT(DISTINCT glpi_plugin_processmaker_processes.id) AS cpt " ;
        } else {
            $fields = " DISTINCT glpi_plugin_processmaker_processes.* " ;
            $orderby = " ORDER BY glpi_plugin_processmaker_processes.name ASC" ; 
        }
        
        if( strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"] ) 
        {
            $where .= " AND (glpi_plugin_processmaker_processes.name LIKE '%$search%' 
                        OR glpi_plugin_processmaker_processes.comment LIKE '%$search%') " ;
        } 
        
        //                    LEFT JOIN glpi_plugin_processmaker_processes_profiles ON glpi_plugin_processmaker_processes_profiles.processes_id=glpi_plugin_processmaker_processes.id
        
        $query = "SELECT $fields FROM glpi_plugin_processmaker_processes ".$where." ".$orderby.";" ;
        
        //// No entity define : use active ones
        //if ($entity_restrict < 0) {
        //    $entity_restrict = $_SESSION["glpiactiveentities"];
        //}

        //$joinprofile = false;
        //switch ($right) {
        //    case "interface" :
        //        $where = " `glpi_profiles`.`interface` = 'central' ";
        //        $joinprofile = true;
        //        $where .= getEntitiesRestrictRequest("AND","glpi_profiles_users",'',$entity_restrict,1);
        //        break;

        //    case "id" :
        //        $where = " `glpi_users`.`id` = '".Session::getLoginUserID()."' ";
        //        break;

        //    case "delegate" :
        //        $groups = self::getDelegateGroupsForUser($entity_restrict);
        //        $users  = array();
        //        if (count($groups)) {
        //            $query = "SELECT `glpi_users`.`id`
        //                 FROM `glpi_groups_users`
        //                 LEFT JOIN `glpi_users`
        //                      ON (`glpi_users`.`id` = `glpi_groups_users`.`users_id`)
        //                 WHERE `glpi_groups_users`.`groups_id` IN ('".implode("','",$groups)."')
        //                       AND `glpi_groups_users`.`users_id` <> '".Session::getLoginUserID()."'";
        //            $result = $DB->query($query);

        //            if ($DB->numrows($result)) {
        //                while ($data=$DB->fetch_array($result)) {
        //                    $users[$data["id"]] = $data["id"];
        //                }
        //            }
        //        }
        //        // Add me to users list for central
        //        if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
        //            $users[Session::getLoginUserID()] = Session::getLoginUserID();
        //        }

        //        if (count($users)) {
        //            $where = " `glpi_users`.`id` IN ('".implode("','",$users)."')";
        //        } else {
        //            $where = '0';
        //        }
        //        break;

        //    case "all" :
        //        $where = " `glpi_users`.`id` > '1' ".
        //                 getEntitiesRestrictRequest("AND","glpi_profiles_users",'',$entity_restrict,1);
        //        break;

        //    default :
        //        $joinprofile = true;
        //        // Check read or active for rights
        //        $where = " (`glpi_profiles`.`".$right."` IN ('1', 'r', 'w') ".
        //                    getEntitiesRestrictRequest("AND", "glpi_profiles_users", '',
        //                                               $entity_restrict, 1)." ";

        //        if (!in_array($right,Profile::$helpdesk_rights)) {
        //            $where .= " AND `glpi_profiles`.`interface` = 'central' ";
        //        }
        //        $where .= ')';
        //}

        //$where .= " AND `glpi_users`.`is_deleted` = '0'
        //          AND `glpi_users`.`is_active` = '1' ";

        //if ((is_numeric($value) && $value)
        //    || count($used)) {

        //    $where .= " AND `glpi_users`.`id` NOT IN (";
        //    if (is_numeric($value)) {
        //        $first = false;
        //        $where .= $value;
        //    } else {
        //        $first = true;
        //    }
        //    foreach ($used as $val) {
        //        if ($first) {
        //            $first = false;
        //        } else {
        //            $where .= ",";
        //        }
        //        $where .= $val;
        //    }
        //    $where .= ")";
        //}

        //if ($count) {
        //    $query = "SELECT COUNT(DISTINCT `glpi_users`.`id` ) AS cpt
        //           FROM `glpi_users` ";
        //} else {
        //    $query = "SELECT DISTINCT `glpi_users`.*
        //           FROM `glpi_users` ";
        //}

        //$query .= " LEFT JOIN `glpi_useremails`
        //             ON (`glpi_users`.`id` = `glpi_useremails`.`users_id`)";
        //$query .= " LEFT JOIN `glpi_profiles_users`
        //             ON (`glpi_users`.`id` = `glpi_profiles_users`.`users_id`)";

        //if ($joinprofile) {
        //    $query .= " LEFT JOIN `glpi_profiles`
        //                ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`) ";
        //}

        //if ($count) {
        //    $query .= " WHERE $where ";
        //} else {
        //    if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
        //        $where .= " AND (`glpi_users`.`name` ".Search::makeTextSearch($search)."
        //                     OR `glpi_users`.`realname` ".Search::makeTextSearch($search)."
        //                     OR `glpi_users`.`firstname` ".Search::makeTextSearch($search)."
        //                     OR `glpi_users`.`phone` ".Search::makeTextSearch($search)."
        //                     OR `glpi_useremails`.`email` ".Search::makeTextSearch($search)."
        //                     OR CONCAT(`glpi_users`.`realname`,' ',`glpi_users`.`firstname`) ".
        //                                   Search::makeTextSearch($search).")";
        //    }
        //    $query .= " WHERE $where ";

        //    if ($_SESSION["glpinames_format"] == FIRSTNAME_BEFORE) {
        //        $query.=" ORDER BY `glpi_users`.`firstname`,
        //                       `glpi_users`.`realname`,
        //                       `glpi_users`.`name` ";
        //    } else {
        //        $query.=" ORDER BY `glpi_users`.`realname`,
        //                       `glpi_users`.`firstname`,
        //                       `glpi_users`.`name` ";
        //    }

        //    if ($search != $CFG_GLPI["ajax_wildcard"]) {
        //        $query .= " LIMIT 0,".$CFG_GLPI["dropdown_max"];
        //    }
        //}

        return $DB->query($query);
    }

    static function getProcessName( $pid, $link=0 ) {
        global $DB, $LANG;
        $process='';
        if ($link==2) {
            $process = array("name"    => "",
                          "link"    => "",
                          "comment" => "");
        }
        
        $query="SELECT * FROM glpi_plugin_processmaker_processes WHERE id=$pid";
        $result = $DB->query($query);
        if ($result && $DB->numrows($result)==1) {
            $data     = $DB->fetch_assoc($result);
            $processname = $data["name"] ;
            if ($link==2) {
                $process["name"]    = $processname ;
                $process["link"]    = $CFG_GLPI["root_doc"]."/plugins/processmaker/front/process.form.php?id=".$pid;
                $process["comment"] = $LANG['common'][16]."&nbsp;: ".$processname."<br>".$LANG["common"][25].
                                   "&nbsp;: ".$data["comment"]."<br>";
            } else {
                $process = $processname ;
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
    static function getEntitiesForProfileByProcess($processes_id, $profiles_id, $child=false) {
        global $DB;

        $query = "SELECT `entities_id`, `is_recursive`
                FROM `glpi_plugin_processmaker_processes_profiles`
                WHERE `processes_id` = '$processes_id'
                      AND `profiles_id` = '$profiles_id'";

        $entities = array();
        foreach ($DB->request($query) as $data) {
            if ($child && $data['is_recursive']) {
                foreach (getSonsOf('glpi_entities', $data['entities_id']) as $id) {
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
    static function dropdown($options=array()) {
        global $DB, $CFG_GLPI, $LANG;

        // Default values
        $p['name']           = 'processes_id';
        $p['value']          = '';
        $p['right']          = 'id';
        $p['all']            = 0;
        $p['on_change']      = '';
        $p['comments']       = 1;
        $p['entity']         = -1;
        $p['entity_sons']    = false;
        $p['used']           = array();
        $p['ldap_import']    = false;
        $p['toupdate']       = '';
        $p['rand']           = mt_rand();

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }


        // Make a select box with all glpi users
        $use_ajax = false;

        if ($CFG_GLPI["use_ajax"]) {
            $res = self::getSqlSearchResult (true, $p['right'], $p['entity'], $p['value'], $p['used']);
            $nb = ($res ? $DB->result($res,0,"cpt") : 0);
            if ($nb > $CFG_GLPI["ajax_limit_count"]) {
                $use_ajax = true;
            }
        }
        $process = self::getProcessName($p['value'],2);

        $default_display  = "<select id='dropdown_".$p['name'].$p['rand']."' name='".$p['name']."'>";
        $default_display .= "<option value='".$p['value']."'>";
        $default_display .= Toolbox::substr($process["name"], 0, $_SESSION["glpidropdown_chars_limit"]);
        $default_display .= "</option></select>";

        //$view_users = (Session::haveRight("user", "r"));
        //TODO: management of rights
        $view_processes = true ;

        $params = array('searchText'       => '__VALUE__',
                        'value'            => $p['value'],
                        'myname'           => $p['name'],
                        'all'              => $p['all'],
                        'right'            => $p['right'],
                        'comment'          => $p['comments'],
                        'rand'             => $p['rand'],
                        'on_change'        => $p['on_change'],
                        'entity_restrict'  => $p['entity'],
                        'used'             => $p['used'],
                        'update_item'      => $p['toupdate'],);
        if ($view_processes) {
            $params['update_link'] = $view_processes;
        }

        $default = "";
        if (!empty($p['value']) && $p['value']>0) {
            $default = $default_display;

        } else {
            $default = "<select name='".$p['name']."' id='dropdown_".$p['name'].$p['rand']."'>";
            if ($p['all']) {
                $default.= "<option value='0'>[ ".$LANG['common'][66]." ]</option></select>";
            } else {
                $default.= "<option value='0'>".Dropdown::EMPTY_VALUE."</option></select>\n";
            }
        }

        Ajax::dropdown($use_ajax, "/plugins/processmaker/ajax/dropdownProcesses.php", $params, $default, $p['rand']);

        // Display comment
        if ($p['comments']) {
            if (!$view_processes) {
                $process["link"] = '';
            } else if (empty($process["link"])) {
                $process["link"] = $CFG_GLPI['root_doc']."/plugins/processmaker/front/process.php";
            }
            Html::showToolTip($process["comment"],
                              array('contentid' => "comment_".$p['name'].$p['rand'],
                                    'link'      => $process["link"],
                                    'linkid'    => "comment_link_".$p["name"].$p['rand']));
        }

        
        return $p['rand'];
    }
    
}

