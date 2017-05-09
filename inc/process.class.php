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

   static function getMenuName() {
      return 'ProcessMaker';
   }

   static function canCreate() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }


   static function canView() {
      return Session::haveRight('plugin_processmaker_config', READ);
   }

   static function canUpdate( ) {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   function canUpdateItem() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }


   function maybeDeleted() {
      return false;
   }

   /**
   * Summary of refreshTasks
   * will refresh (re-synch) all process task list
   * @param array $post is the $_POST
   * @return void
   */
   function refreshTasks( $post ) {
      global $PM_DB, $CFG_GLPI;

      if ($this->getFromDB( $post['id'] )) {
         // here we are in the right process
         // we need to get the tasks + content from PM db
         //$config = PluginProcessmakerConfig::getInstance() ;
         //$database = $config->fields['pm_workspace'] ;
         $translates = false;
         $mapLangs = array( );
         if (TableExists( 'glpi_dropdowntranslations' ) && class_exists('DropdownTranslation')) {
            // to force rigths to add translations
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['name'] = 'name';
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['completename'] = 'completename';
            $_SESSION['glpi_dropdowntranslations']['TaskCategory']['comment'] = 'comment';
            $translates = true;
            // create a reversed map for languages
            foreach ($CFG_GLPI['languages'] as $key => $valArray) {
               $mapLangs[ locale_get_primary_language( $key ) ][] = $key;
            }
         }
         $lang = locale_get_primary_language( $CFG_GLPI['language'] );
         $query = "SELECT TASK.TAS_UID, TASK.TAS_START, CONTENT.CON_LANG, CONTENT.CON_CATEGORY, CONTENT.CON_VALUE FROM TASK
                        INNER JOIN CONTENT ON CONTENT.CON_ID=TASK.TAS_UID
                        WHERE TASK.PRO_UID = '".$this->fields['process_guid']."' AND CONTENT.CON_CATEGORY IN ('TAS_TITLE', 'TAS_DESCRIPTION') ".($translates ? "" : " AND CONTENT.CON_LANG='$lang'")." ;";
         $taskArray = array();
         $defaultLangTaskArray=array();
         foreach ($PM_DB->request( $query ) as $task) {
            if ($task['CON_LANG'] == $lang) {
               $defaultLangTaskArray[ $task['TAS_UID'] ][ $task['CON_CATEGORY'] ]  = $task['CON_VALUE'];
               $defaultLangTaskArray[ $task['TAS_UID'] ]['start']=($task['TAS_START']=='TRUE'?true:false);
            } else {
               foreach ($mapLangs[ $task['CON_LANG'] ] as $valL) {
                  $taskArray[ $task['TAS_UID'] ][ $valL ][ $task['CON_CATEGORY'] ]  = $task['CON_VALUE'];
               }
            }
         }

         foreach ($defaultLangTaskArray as $taskGUID => $task) {
            $pmTaskCat = new PluginProcessmakerTaskCategory;
            $taskCat = new TaskCategory;
            if ($pmTaskCat->getFromDBbyExternalID( $taskGUID )) {
               // got it then check names, and if != update
               if ($taskCat->getFromDB( $pmTaskCat->fields['taskcategories_id'] )) {
                  // found it must test if should be updated
                  if ($taskCat->fields['name'] != $task['TAS_TITLE'] || $taskCat->fields['comment'] != $task['TAS_DESCRIPTION']) {
                     $taskCat->update( array( 'id' => $taskCat->getID(), 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) );
                  }
                  if ($pmTaskCat->fields['start'] != $task['start']) {
                         $pmTaskCat->update( array( 'id' =>  $pmTaskCat->getID(), 'start' => $task['start'] ) );
                  }
               } else {
                  // taskcat must be created
                  $taskCat->add( array( 'is_recursive' => true, 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) );
                  // update pmTaskCat
                  $pmTaskCat->update( array( 'id' => $pmTaskCat->getID(), 'taskcategories_id' => $taskCat->getID(), 'start' => $task['start'] ) );
               }
            } else {
               // should create a new one
               // taskcat must be created
               $taskCat->add( array( 'is_recursive' => true, 'name' => $task['TAS_TITLE'], 'comment' => $task['TAS_DESCRIPTION'], 'taskcategories_id' => $this->fields['taskcategories_id'] ) );
               // pmTaskCat must be created too
               $pmTaskCat->add( array( 'processes_id' => $this->getID(), 'pm_task_guid' => $taskGUID, 'taskcategories_id' => $taskCat->getID(), 'start' => $task['start'] ) );
            }
            // here we should take into account translations if any
            if ($translates && isset($taskArray[ $taskGUID ])) {
               foreach ($taskArray[ $taskGUID ] as $langTask => $taskL) {
                  // look for 'name' field
                  if ($loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'name', $langTask )) {
                     if (DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'name', $langTask ) != $taskL[ 'TAS_TITLE' ]) {
                        // must be updated
                        $trans = new DropdownTranslation;
                        $trans->update( array( 'id' => $loc_id, 'field' => 'name', 'value' => $PM_DB->escape($taskL[ 'TAS_TITLE' ]), 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ) );
                        $trans->generateCompletename( array( 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask ) );
                     }
                  } else {
                     // must be added
                     // must be updated
                     $trans = new DropdownTranslation;
                     $trans->add( array( 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'name', 'value' => $PM_DB->escape($taskL[ 'TAS_TITLE' ]) ) );
                     $trans->generateCompletename( array( 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(),'language' => $langTask ) );
                  }

                  // look for 'comment' field
                  if ($loc_id = DropdownTranslation::getTranslationID( $taskCat->getID(), 'TaskCategory', 'comment', $langTask )) {
                     if (DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $langTask ) != $taskL[ 'TAS_DESCRIPTION' ]) {
                        // must be updated
                        $trans = new DropdownTranslation;
                        $trans->update( array( 'id' => $loc_id, 'field' => 'comment', 'value' => $PM_DB->escape($taskL[ 'TAS_DESCRIPTION' ]) , 'itemtype' => 'TaskCategory', 'items_id' => $taskCat->getID(), 'language' => $langTask) );
                     }
                  } else {
                     // must be added
                     $trans = new DropdownTranslation;
                     $trans->add( array( 'items_id' => $taskCat->getID(), 'itemtype' => 'TaskCategory', 'language' => $langTask, 'field' => 'comment', 'value' => $PM_DB->escape($taskL[ 'TAS_DESCRIPTION' ]) ) );
                  }

               }
            }
         }

      }

   }

   /**
   * Summary of refresh
   * used to refresh process list and task category list
   * @return void
   */
   function refresh( ) {
      // then refresh list of available process from PM to inner table
      $pm = new PluginProcessmakerProcessmaker;
      $pm->login( true );
      $pmProcessList = $pm->processList();

      $config = PluginProcessmakerConfig::getInstance();
      $pmMainTaskCat = $config->fields['taskcategories_id'];

      // and get processlist from GLPI
      if ($pmProcessList) {
         foreach ($pmProcessList as $process) {
            $glpiprocess = new PluginProcessmakerProcess;
            if ($glpiprocess->getFromDBbyExternalID($process->guid)) {
               // then update it only if name has changed
               if ($glpiprocess->fields['name'] != $process->name) {
                  $glpiprocess->update( array( 'id' => $glpiprocess->getID(), 'name' => $process->name ) );
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

               if ($glpiprocess->add( array( 'process_guid' => $process->guid, 'name' => $process->name, 'project_type' => $project_type ))) {
                  // and add main task category for this process
                  $glpiprocess->addTaskCategory( $pmMainTaskCat );
               }
            }
         }
      }

      // should de-activate other

   }

   /**
   * Summary of updateTaskCategory
   * Updates TaskCategory for current process, only if needed (i.e. name has changed)
   * @param integer $pmMainTaskCat is the id of the main task category
   * @return boolean true if update is done, false otherwise
   */
   function updateTaskCategory( $pmMainTaskCat ) {
      $taskCat = new TaskCategory;
      if ($taskCat->getFromDB( $this->fields['taskcategories_id'] ) && $taskCat->fields['name'] != $this->fields['name']) {
         return $taskCat->update( array( 'id' => $taskCat->getID(), 'taskcategories_id' => $pmMainTaskCat, 'name' => $this->fields['name'] ) );
      }
      return false;
   }

   /**
   * Summary of addTaskCategory
   * Adds a new TaskCategory for $this process
   * @param int $pmMainTaskCat is the main TaskCategory from PM configuration
   * @return boolean true if TaskCategory has been created and updated into $this process, else otherwise
   */
   function addTaskCategory( $pmMainTaskCat ) {
      $taskCat = new TaskCategory;
      if ($taskCat->add( array( 'is_recursive' => true, 'taskcategories_id' => $pmMainTaskCat, 'name' => $this->fields['name']) )) {
         return $this->update( array( 'id' => $this->getID(), 'taskcategories_id' => $taskCat->getID() ) );
      }
      return false;
   }


   /**
   * Print a good title for process pages
   * add button for re-synchro of process list (only if rigths are w)
   * @return void (display)
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
   * @param string $extid guid of the process
   * @return bool true if succeed else false
   **/
   public function getFromDBbyExternalID($extid) {
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
      $tab[1]['name']          = __('Name');
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = $this->getType();

      //$tab[7]['table']         = 'glpi_plugin_processmaker_processes';
      //$tab[7]['field']         = 'is_helpdeskvisible';
      //$tab[7]['name']          = $LANG['tracking'][39];
      //$tab[7]['massiveaction'] = true;
      //$tab[7]['datatype']      = 'bool';

      $tab[8]['table']         = 'glpi_plugin_processmaker_processes';
      $tab[8]['field']         = 'is_active';
      $tab[8]['name']          = __('Active');
      $tab[8]['massiveaction'] = true;
      $tab[8]['datatype']      = 'bool';

      $tab[4]['table']        = 'glpi_plugin_processmaker_processes';
      $tab[4]['field']        =  'comment';
      $tab[4]['name']         =  __('Comments');
      $tab[4]['massiveaction'] = true;
      $tab[4]['datatype']     =  'text';

      $tab[9]['table']         = 'glpi_plugin_processmaker_processes';
      $tab[9]['field']         = 'date_mod';
      $tab[9]['name']          = __('Last update');
      $tab[9]['massiveaction'] = false;
      $tab[9]['datatype']      = 'datetime';

      $tab[10]['table']        = 'glpi_plugin_processmaker_processes';
      $tab[10]['field']        =  'process_guid';
      $tab[10]['name']         =  $LANG['processmaker']['process']['process_guid'];
      $tab[10]['massiveaction'] = false;
      $tab[10]['datatype']     =  'text';

      $tab[11]['table']        = 'glpi_plugin_processmaker_processes';
      $tab[11]['field']        =  'project_type';
      $tab[11]['name']         =  $LANG['processmaker']['process']['project_type_short'];
      $tab[11]['massiveaction'] = false;
      $tab[11]['datatype']     =  'specific';

      $tab[12]['table']         = 'glpi_plugin_processmaker_processes';
      $tab[12]['field']         = 'hide_case_num_title';
      $tab[12]['name']          = $LANG['processmaker']['process']['hide_case_num_title_short'];
      $tab[12]['massiveaction'] = true;
      $tab[12]['datatype']      = 'bool';

      $tab[13]['table']         = 'glpi_plugin_processmaker_processes';
      $tab[13]['field']         = 'insert_task_comment';
      $tab[13]['name']          = $LANG['processmaker']['process']['insert_task_comment_short'];
      $tab[13]['massiveaction'] = true;
      $tab[13]['datatype']      = 'bool';

      $tab[14]['table']         = 'glpi_itilcategories';
      $tab[14]['field']         = 'completename';
      $tab[14]['name']          = __('Category');
      $tab[14]['datatype']      = 'dropdown';
      $tab[14]['massiveaction'] = false;

      $tab[15]['table']         = 'glpi_plugin_processmaker_processes';
      $tab[15]['field']         = 'type';
      $tab[15]['name']          = $LANG['processmaker']['process']['type'];
      $tab[15]['searchtype']    = 'equals';
      $tab[15]['datatype']      = 'specific';
      $tab[15]['massiveaction'] = false;

      return $tab;
   }


   /**
   * @since version 0.84
   *
   * @param $field
   * @param $values
   * @param $options   array
   **/
   static function getSpecificValueToDisplay($field, $values, array $options=array()) {
      global $LANG;

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {

         case 'project_type':
             return $LANG['processmaker']['process']['project_type_'.$values[$field]];

         case 'type':
             return Ticket::getTicketTypeName($values[$field]);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   static function getTypeName($nb=0) {
      global $LANG;

      if ($nb>1) {
         return $LANG['processmaker']['title'][1];
      }
      return $LANG['processmaker']['title'][2];
   }

   function defineTabs($options=array()) {

      //        $ong = array('empty' => $this->getTypeName(1));
      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);

      $this->addStandardTab('PluginProcessmakerTaskCategory', $ong, $options);
      $this->addStandardTab('PluginProcessmakerProcess_Profile', $ong, $options);
      //$this->addStandardTab('Ticket', $ong, $options);
      //$this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   function showForm ($ID, $options=array('candel'=>false)) {
      global $DB, $CFG_GLPI, $LANG;

      //if ($ID > 0) {
      //   $this->check($ID,READ);
      //}

      //$canedit = $this->can($ID,UPDATE);
      //$options['canedit'] = $canedit ;

      $this->initForm($ID, $options);
      //$this->showTabs($options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__("Name")."&nbsp;:</td><td>";
      //Html::autocompletionTextField($this, "name");
      echo $this->fields["name"];
      echo "</td>";
      echo "<td rowspan='5' class='middle right'>".__("Comments")."&nbsp;:</td>";
      echo "<td class='center middle' rowspan='5'><textarea cols='45' rows='6' name='comment' >".
           $this->fields["comment"]."</textarea></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['process_guid']."&nbsp;:</td><td>";
      echo $this->fields["process_guid"];
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__("Active")."&nbsp;:</td><td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".$LANG['tracking'][39]."&nbsp;:</td><td>";
      //Dropdown::showYesNo("is_helpdeskvisible",$this->fields["is_helpdeskvisible"]);
      //echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['hide_case_num_title']."&nbsp;:</td><td>";
      Dropdown::showYesNo("hide_case_num_title", $this->fields["hide_case_num_title"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['insert_task_comment']."&nbsp;:</td><td>";
      Dropdown::showYesNo("insert_task_comment", $this->fields["insert_task_comment"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['type']."&nbsp;:</td><td>";
      if (true) { // $canupdate || !$ID
            $idticketcategorysearch = mt_rand(); $opt = array('value' => $this->fields["type"]);
            $rand = $idtype = Ticket::dropdownType('type', $opt, array(), array('toupdate' => "search_".$idticketcategorysearch ));
            $opt = array('value' => $this->fields["type"]);
            $params = array('type'            => '__VALUE__',
                            //'entity_restrict' => -1, //$this->fields['entities_id'],
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
      if (true) { // $canupdate || !$ID || $canupdate_descr
          $opt = array('value'  => $this->fields["itilcategories_id"]);

         switch ($this->fields['type']) {
            case Ticket::INCIDENT_TYPE :
               $opt['condition'] = "`is_incident`='1'";
                break;

            case Ticket::DEMAND_TYPE :
               $opt['condition'] = "`is_request`='1'";
                break;

            default :
                break;
         }

          echo "<span id='show_category_by_type'>";
         if (isset($idticketcategorysearch)) {
            $opt['rand'] = $idticketcategorysearch;
         }
          Dropdown::show('ITILCategory', $opt);
          echo "</span>";
      } else {
          echo Dropdown::getDropdownName("glpi_itilcategories", $this->fields["itilcategories_id"]);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['processmaker']['process']['project_type']."&nbsp;:</td><td>";
      Dropdown::showFromArray( 'project_type', array( 'classic' => $LANG['processmaker']['process']['project_type_classic'], 'bpmn' => $LANG['processmaker']['process']['project_type_bpmn'] ), array( 'value' => $this->fields["project_type"] ) );
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__("Last update")."&nbsp;:</td><td>";
      echo Html::convDateTime($this->fields["date_mod"]);
      echo "</td></tr>";

      $this->showFormButtons($options );
      //$this->addDivForTabs();

   }



   /**
   * Execute the query to select box with all glpi users where select key = name
   *
   * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
   *
   * @param $count true if execute an count(*),
   * @param $search pattern
   *
   * @return mysql result set.
   **/
   static function getSqlSearchResult ($count=true, $search='') {
      global $DB, $CFG_GLPI;

      $orderby = '';

      $where = ' WHERE glpi_plugin_processmaker_processes.is_active=1 ';

      if ($count) {
         $fields = " COUNT(DISTINCT glpi_plugin_processmaker_processes.id) AS cpt ";
      } else {
         $fields = " DISTINCT glpi_plugin_processmaker_processes.* ";
         $orderby = " ORDER BY glpi_plugin_processmaker_processes.name ASC";
      }

      if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
         $where .= " AND (glpi_plugin_processmaker_processes.name $search
                        OR glpi_plugin_processmaker_processes.comment $search) ";
      }

      $query = "SELECT $fields FROM glpi_plugin_processmaker_processes ".$where." ".$orderby.";";

      return $DB->query($query);
   }

   /**
   * Summary of getProcessName
   * @param mixed $pid
   * @param mixed $link
   * @return mixed
   */
   static function getProcessName( $pid, $link=0 ) {
      global $DB;
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
         $processname = $data["name"];
         if ($link==2) {
            $process["name"]    = $processname;
            $process["link"]    = $CFG_GLPI["root_doc"]."/plugins/processmaker/front/process.form.php?id=".$pid;
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
      global $CFG_GLPI;
      $options['url'] = $CFG_GLPI["root_doc"].'/plugins/processmaker/ajax/dropdownProcesses.php';
      return Dropdown::show( __CLASS__, $options );

   }

}

