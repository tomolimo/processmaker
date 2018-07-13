<?php

/**
 * PluginProcessmakerCase short summary.
 *
 * PluginProcessmakerCase description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCase extends CommonDBTM {

   //static public $itemtype = 'itemtype'; // Class name or field name (start with itemtype) for link to Parent
   //static public $items_id = 'items_id'; // Field name
   static $rightname                           = 'plugin_processmaker_case';

   const DRAFT     = 'DRAFT';
   const TO_DO     = 'TO_DO';
   const COMPLETED = 'COMPLETED';
   const CANCELLED = 'CANCELLED';

   static function getTypeName($nb=0) {
      return _n('Process case', 'Process cases', $nb);
   }

   //static function canCreate() {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}


   //static function canView() {
   //   return Session::haveRightsOr('plugin_processmaker_case', [READ, UPDATE]);
   //}

   //static function canUpdate( ) {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}

   //function canUpdateItem() {
   //   return Session::haveRight('plugin_processmaker_config', UPDATE);
   //}


   function maybeDeleted() {
      return false;
   }

   static function canDelete() {
      return parent::canDelete();
   }

   function canDeleteItem() {
      return parent::canDeleteItem();
   }

   static function canPurge() {
      return self::canDelete();
   }

   function canPurgeItem() {
      return $this->canDeleteItem();
   }

   /**
    * Summary of getTabNameForItem
    * @param CommonGLPI $item         is the item
    * @param mixed      $withtemplate has template
    * @return array os strings
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      if ($item->getType() == __CLASS__) {
         // get tab name for a case itself
         return [ __CLASS__ => __('Case')."<sup class='tab_nb'> ".$LANG['processmaker']['case']['statuses'][$item->fields['case_status']]."</sup>"];
      } else {
         $items_id = $item->getID();
         $itemtype = $item->getType();

         // count how many cases are on this item
         $cnt = count(self::getIDsFromItem($itemtype, $items_id));
         if ($cnt == 0) {
            return ['processmakercases' => __('Process case', 'processmaker')];
         }
         return ['processmakercases' => _n('Process case', 'Process cases', $cnt, 'processmaker')."<sup class='tab_nb'>$cnt</sup>"];
      }
   }


   /**
    * Summary of getName
    * @param mixed $options
    * @return mixed
    */
   function getName($options = array()){
      return $this->fields['name'];
   }


   /**
    * Summary of getIDFromItem
    * @param string $itemtype is the item type
    * @param integer $items_id   is the item id
    * @return integer cases_id
    */
   static function getIDFromItem($itemtype, $items_id) {
      $tmp = New self;
      if ($tmp->getFromDBByQuery(" WHERE items_id=$items_id and itemtype='$itemtype'")) {
         return $tmp->getID();
      }
      return false;
   }


   /**
    * Summary of getIDsFromItem
    * returns an array of the case ids linked to the item
    * @param string $itemtype is the item type of the item (Ticket, Problem, Change)
    * @param mixed $items_id is the GLPI id of the item in the type
    * @return array
    */
   static function getIDsFromItem($itemtype, $items_id) {
      $ret = [];
      foreach(getAllDatasFromTable( self::getTable(), "items_id=$items_id AND itemtype='$itemtype'") as $case) {
         $ret[] = $case['id'];
      }
      return $ret;
   }

   /**
    * Summary of getFromItem
    * @param mixed $itemtype is the item type
    * @param mixed $items_id   is the item id
    * @return mixed: returns false when there is no case associated with the item, else fills in the item fields from DB, and returns true
    */
   function getFromItem($itemtype, $items_id) {
      return $this->getFromDBByQuery(" WHERE items_id=$items_id and itemtype='$itemtype'");
   }


   /**
    * Summary of getFromGUID
    * @param mixed $case_guid
    * @return boolean
    */
   function getFromGUID($case_guid) {
      return $this->getFromDBByQuery(" WHERE case_guid='$case_guid'");
   }


   /**
    * Summary of getVariables
    * @param mixed $vars
    * @return mixed
    */
   function getVariables($vars=array()) {
      global $PM_SOAP;
      return $PM_SOAP->getVariables($this->fields['case_guid'], $vars);
   }


   /**
    * Summary of sendVariables
    * @param mixed $vars
    * @return A
    */
   function sendVariables($vars = array()) {
      global $PM_SOAP;
      return $PM_SOAP->sendVariables($this->fields['case_guid'], $vars);
   }


   /**
    * Summary of getCaseInfo
    * @param mixed $delIndex
    * @return stdClass, a getCaseInfoResponse object, or false exception occured
    */
   function getCaseInfo($delIndex='') {
      global $PM_SOAP;
      return $PM_SOAP->getCaseInfo($this->fields['case_guid'], $delIndex);
   }


   /**
    * Summary of unpauseCase
    * @param mixed $delIndex
    * @param mixed $userGuid
    * @return an
    */
   function unpauseCase($delIndex, $userGuid) {
      global $PM_SOAP;
      return $PM_SOAP->unpauseCase($this->fields['case_guid'], $delIndex, $userGuid);
   }


   /**
    * Summary of reassignCase
    * @param mixed $delIndex
    * @param mixed $taskGuid
    * @param mixed $delThread
    * @param mixed $users_id_source
    * @param mixed $users_id_target
    * @return mixed
    */
   function reassignCase($delIndex, $taskGuid, $delThread, $users_id_source, $users_id_target) {
      global $PM_SOAP;
      $users_guid_source = PluginProcessmakerUser::getPMUserId($users_id_source);
      $users_guid_target = PluginProcessmakerUser::getPMUserId($users_id_target);
      $pmResponse = $PM_SOAP->reassignCase($this->fields['case_guid'], $delIndex, $users_guid_source, $users_guid_target);
      // now should managed GLPI Tasks previously assigned to the $users_guid_source
      if ($pmResponse->status_code == 0) {
         // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks
         // we need the delindex of the current glpi task, and the delindex of the new one
         // search for new delIndex and new delThread
         $newCaseInfo = $this->getCaseInfo( );
         $newDelIndex = 0;
         $newDelThread = 0;
         foreach ($newCaseInfo->currentUsers as $newCaseUser) {
            if ($newCaseUser->taskId == $taskGuid && $newCaseUser->delThread == $delThread) {
               $newDelIndex = $newCaseUser->delIndex;
               $newDelThread = $newCaseUser->delThread;
               break;
            }
         }
         $this->reassignTask($delIndex, $newDelIndex, $delThread, $newDelThread, $users_id_target );
         return true;
      }
      return false;
   }


   /**
    * Summary of reassignTask
    * @param mixed $delIndex
    * @param mixed $newDelIndex
    * @param mixed $newTech
    */
   public function reassignTask ($delIndex, $newDelIndex, $delThread, $newDelThread, $newTech) {
      global $DB;

      $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id={$this->getID()} AND del_index=$delIndex AND del_thread=$delThread; ";
      $res = $DB->query($query);
      if ($DB->numrows($res) > 0) {
         $row = $DB->fetch_array( $res );
         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB( $row['items_id'] );

         $itilobject_itemtype = $this->fields['itemtype']; //str_replace( 'Task', '', $row['itemtype'] );
         $foreignkey = getForeignKeyFieldForItemType( $itilobject_itemtype );

         PluginProcessmakerProcessmaker::addWatcher( $itilobject_itemtype, $glpi_task->fields[ $foreignkey ], $newTech );

         $glpi_task->update( array( 'id' => $row['items_id'], $foreignkey => $glpi_task->fields[ $foreignkey ],  'users_id_tech' => $newTech ));

         // then update the delIndex and delThread
         $query = "UPDATE glpi_plugin_processmaker_tasks SET del_index = $newDelIndex, del_thread = $newDelThread WHERE id={$row['id']}; ";
         $res = $DB->query($query);
      }
   }


   /**
    * Summary of showCaseInfoTab
    * Will show information about the current case
    * @param CommonGLPI $item is a PluginProcessmakerCase object
    * @param mixed $tabnum
    * @param mixed $withtemplate
    */
   static function showCaseInfoTab(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      echo 'The idea is to show here the GLPI ITIL item to which it is linked, and to give a resume of the current case status, and to give possibility to delete or cancel the case.';

      $rand = rand();
      // will not show delete button if case is a sub-process
      // will show it if it is also a draft or if current glpi user has the right to delete cases and session is central
      if ($item->fields['plugin_processmaker_cases_id'] == 0
         && ($item->fields['case_status'] == self::DRAFT
            || (plugin_processmaker_haveRight("case", DELETE)
               && $_SESSION['glpiactiveprofile']['interface'] == 'central'))) {
         // then propose a button to delete case
         echo "<form style='margin-bottom: 0px' name='processmaker_case_form$rand' id='processmaker_case_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerCase")."'>";
         echo "<div class='center'>";
         echo "<table style='margin-bottom: 0px' class='tab_cadre_fixe'>";

         echo "<tr><th colspan='4'>".__('Case Deletion', 'processmaker')."</th></tr>";
         echo "<td class='tab_bg_2' colspan=3>&nbsp;</td>";
         echo "<td class='tab_bg_2'>";
         echo "<input type='hidden' name='action' value='delete'>";
         echo "<input type='hidden' name='cases_id' value='".$item->getID()."'>";
         echo "<input onclick='ret = confirm(\"".__('Confirm expunge?')."\"); cancelMyMask = !ret ; return ret;'  type='submit' name='delete' value='".__('Delete permanently')."' class='submit' >";
         echo "</td></tr></table>";

         Html::closeForm();
      }

      return;
   }


   /**
    * Summary of showForItem
    * Shows list of cases attached to an item
    * @param CommonITILObject $item
    */
   static function showForItem(CommonITILObject $item) {
      global $DB, $CFG_GLPI, $LANG;

      $items_id = $item->getField('id');
      $itemtype = $item->getType();
      //if (!Session::haveRight("problem", Problem::READALL)
      //    || !$item->can($ID, READ)) {

      //   return false;
      //}

      $canupdate = $item->can($items_id, UPDATE);

      $rand = mt_rand();

      $query = "SELECT gppc.`id` AS assocID, gppc.`id` as id, gppp.name as pname, gppc.`case_status`, gppc.`plugin_processmaker_cases_id`
                FROM `glpi_plugin_processmaker_cases` as gppc
                LEFT JOIN `glpi_plugin_processmaker_processes` AS gppp ON gppp.`id`=gppc.`plugin_processmaker_processes_id`
                WHERE gppc.`itemtype` = '$itemtype'
                  AND gppc.`items_id` = $items_id
                ";
      $result = $DB->query($query);

      $cases = array();
      $used     = array();
      if ($numrows = $DB->numrows($result)) {
         while ($data = $DB->fetch_assoc($result)) {
            $cases[$data['id']] = $data;
            $used[$data['id']]     = $data['id'];
         }
      }

      $columns = array('pname'  => 'Process',
                       'name'   => 'Name',
                       'status' => 'Status',
                       'sub'    => 'Subcase of'
           );

      // check if item is not solved nor closed
      if ($canupdate
            && $item->fields['status'] != CommonITILObject::SOLVED
            && $item->fields['status'] != CommonITILObject::CLOSED
            && $_SESSION['glpiactiveprofile']['interface'] != 'helpdesk') {
         echo "<div class='firstbloc'>";
         echo "<form style='margin-bottom: 0px' name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
         echo "<input type='hidden' name='action' value='newcase'>";
         echo "<input type='hidden' name='items_id' value='$items_id'>";
         echo "<input type='hidden' name='itemtype' value='$itemtype'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th colspan='3'>".__('Add a new case')."</th></tr>";

         echo "<tr class='tab_bg_2'><td class='tab_bg_2'>";
         echo $LANG['processmaker']['item']['selectprocess']."&nbsp;";
         echo "</td><td class='tab_bg_2'>";
         PluginProcessmakerProcess::dropdown(array( 'value' => 0, 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_processes_id', 'condition' => "is_active=1"));
         echo "</td><td class='tab_bg_2'>";
         echo "<input type='submit' name='additem' value='"._sx('button','Add')."' class='submit'>";
         echo "</td></tr></table>";
         Html::closeForm();
         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canupdate && $numrows) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = array('num_displayed'  => $numrows,
                                      'container'      => 'mass'.__CLASS__.$rand);
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='noHover'><th colspan='12'>".PluginProcessmakerCase::getTypeName($numrows)."</th>";
      echo "</tr>";
      if ($numrows) {
         $header_begin  = "<tr>";
         $header_top    = '';
         $header_bottom = '';
         $header_end    = '';
         if ($canupdate
             && $numrows) {
            $header_top    .= "<th width='11'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_top    .= "</th>";
            $header_bottom .= "<th width='11'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_bottom .= "</th>";
         }

         foreach ($columns as $key => $val) {
            $header_end .= "<th>$val</th>";
         }

         $header_end .= "</tr>";
         echo $header_begin.$header_top.$header_end;

         //Session::initNavigateListItems('PluginProcessmakerCase',
         //                     //TRANS : %1$s is the itemtype name,
         //                     //        %2$s is the name of the item (used for headings of a list)
         //                               sprintf(__('%1$s = %2$s'),
         //                                       $itemtype::getTypeName(1), $item->fields["name"]));

         $i = 0;
         foreach ($cases as $data) {
            Session::addToNavigateListItems('PluginProcessmakerCase', $data["id"]);
            $link         = NOT_AVAILABLE;
            $case = new self;
            if ($case->getFromDB($data["id"])) {
               $link         = $case->getLink();
            }

            echo "<tr class='tab_bg_1'>";
            if ($canupdate) {
               echo "<td width='10'>";
               // prevent massiveaction on subprocess
               if ($data['plugin_processmaker_cases_id'] == 0) {
                  Html::showMassiveActionCheckBox(__CLASS__, $data["assocID"]);
               }
               echo "</td>";
            }
            echo "<td class='center'>".$data['pname']."</td>";
            echo "<td class='center'>".$link."</td>";
            echo "<td class='center'>".self::getStatus($data['case_status'])."</td>";
            echo "<td class='center'>";
            if ($data['plugin_processmaker_cases_id'] > 0) {
               // then this is a subprocess of
               $maincase = new self;
               if ($maincase->getFromDB($data['plugin_processmaker_cases_id'])) {
                  echo $maincase->getLink();
               }
            } else {
               echo '-';
            }
            echo "</td>";
            //echo "<td class='center'>".Html::convDateTime($data["date_creation"])."</td>";
            echo "</tr>";

            $i++;
         }
         echo $header_begin.$header_top.$header_end;

      }

      echo "</table>";
      if ($canupdate && $numrows) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "</div>";
   }

   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $item
    * @param mixed $tabnum
    * @param mixed $withtemplate
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $LANG, $DB, $CFG_GLPI, $PM_SOAP;

      if ($item->getType() == __CLASS__) {
         // we are in a case viewing the main tab
         // the 'Case infos' tab
         self::showCaseInfoTab($item, $tabnum, $withtemplate);

      } else {

         // the idea is to show a list of cases attached to the $item ITIL object
         // TODO give possibility to start a new case if needed
         self::showForItem($item);
      }
   }

    /**
     * Summary of displayTabContentForItem
     * @param CommonGLPI $item         is the item
     * @param mixed      $tabnum       is the tab num
     * @param mixed      $withtemplate has template
     * @return mixed
     */
   static function displayTabContentForItem_old(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $LANG, $DB, $CFG_GLPI, $PM_SOAP;

      $config = $PM_SOAP->config; //PluginProcessmakerConfig::getInstance();

      if ($config->fields['maintenance'] == 0) {

         $items_id = $item->getID();
         $itemtype = $item->getType();

         $rand = rand();
         echo "<form style='margin-bottom: 0px' name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
         echo "<div class='center'> <table id='processmakercasemenu' style='margin-bottom: 0px' class='tab_cadre_fixe'>";
         echo Html::scriptBlock("$('#processmakercasemenu').css('max-width', 'none');");
         echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['tab']."</th></tr>";

         $pmCaseUser = false; // initial value: no user
         // first search for the case
         $locCase = new self;
         if ($locCase->getFromItem($itemtype, $items_id)) {
            $GLPICurrentPMUserId=0;
            $paramsURL='';
            $caseInfo = $locCase->getCaseInfo();
            if ($caseInfo->caseStatus != 'CANCELLED' && $caseInfo->caseStatus != 'COMPLETED') {
               // need to get info on the thread of the GLPI current user
               // we must retreive currentGLPI user from this array
               $GLPICurrentPMUserId = PluginProcessmakerUser::getPMUserId(Session::getLoginUserID());
               $pmCaseUser = $caseInfo->currentUsers[0]; // by default currently manage only one task at a time, must define tab management for several tasks
               foreach ($caseInfo->currentUsers as $caseUser) {
                  if ($caseUser->userId == $GLPICurrentPMUserId) {
                     $pmCaseUser = $caseUser;
                     break;
                  }
               }
            }
            $locDelIndex = 1; // by default
            switch ($caseInfo->caseStatus) {
               case "CANCELLED"  :
                  echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['cancelledcase']."</th></tr>";
                  $paramsURL = "DEL_INDEX=1";
                  //                    echo "<tr class='tab_bg_1' ><td id='GLPI-PM-DEL_INDEX' ><script>var GLPI_DEL_INDEX = 1; </script></td></tr>" ;
                  break;

               case "DRAFT" :
               case "TO_DO" :

                  $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus;
                  $locDelIndex = $pmCaseUser->delIndex;
                  if ($pmCaseUser->userId != '') {
                     echo "<tr class='tab_bg_1'>";

                     if ($GLPICurrentPMUserId == $pmCaseUser->userId) {
                        // then propose a button to cancel case only when assigned user is == to glpi current user
                        echo "<td class='tab_bg_2' >";
                        echo $LANG['processmaker']['item']['cancelcase'];
                        echo "</td><td class='tab_bg_2'>";
                        echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                        echo "<input type='hidden' name='cases_id' value='".$locCase->getID()."'>";
                        //echo "<input type='hidden' name='plugin_processmaker_cases_guid' value='".$caseInfo->caseId."'>";
                        //echo "<input type='hidden' name='plugin_processmaker_del_index' value='".$pmCaseUser->delIndex."'>";
                        //echo "<input type='hidden' name='plugin_processmaker_users_id' value='".$pmCaseUser->userId."'>";
                        echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttoncancelcaseconfirmation']."\") ;  cancelMyMask = !ret ;  return ret;'   type='submit' name='cancel' value='".$LANG['processmaker']['item']['buttoncancelcase']."' class='submit'>";
                        echo "</td>";
                     }

                     if ($caseInfo->caseStatus == "DRAFT" || (plugin_processmaker_haveRight("case", DELETE) && $_SESSION['glpiactiveprofile']['interface'] == 'central')) {
                        // then propose a button to delete case
                        echo "<td class='tab_bg_2'>";
                        echo $LANG['processmaker']['item']['deletecase'];
                        echo "</td><td class='tab_bg_2'>";
                        echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
                        //echo "<input type='hidden' name='plugin_processmaker_cases_guid' value='".$caseInfo->caseId."'>";
                        echo "<input type='hidden' name='cases_id' value='".$locCase->getID()."'>";

                        echo "<input onclick='ret = confirm(\"".$LANG['processmaker']['item']['buttondeletecaseconfirmation']."\"); cancelMyMask = !ret ; return ret;'  type='submit' name='delete' value='".$LANG['processmaker']['item']['buttondeletecase']."' class='submit' >";

                        echo "</td>";

                     }

                     echo "</form>";

                     echo "</td></tr>";
                  }

                  break;
               case "COMPLETED" :
                  echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['completedcase']."</th></tr>";
                  $paramsURL = "DEL_INDEX="; // DEL_INDEX is not set to tell PM to show the current task i.e.: the last one
                  break;
            }

            $proj = new PluginProcessmakerProcess;
            $proj->getFromGUID( $caseInfo->processId );
            $project_type = $proj->fields['project_type'];

            echo "</table>";
            echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

            // processmakertabpaneltable  is used to align the tabs
            echo "<table id=processmakertabpaneltable style='margin-bottom: 0px; width:100%;' class='tab_cadre_fixe'>";
            echo Html::scriptBlock("$('#processmakertabpaneltable').css('max-width', 'none');");
            echo "<tr><td>";

            //////////////////////////
            // Processmaker tab panels
            // need to have a global variable which contains tab id
            // used only one time for activated panel
            //////////////////////////
            $arrayProcessmakerTabPanel = array();
            echo "<div id=processmakertabpanel >";

            //////////////
            // Define tabs
            //////////////
            echo "    <ul>";
            //echo "            <li><a href='#tabs-1'>Nunc tincidunt</a></li>";
            //$arrayProcessmakerTabPanel[] = "tabs-1";
            $arrayProcessmakerTabPanel = [];
            if ($pmCaseUser) {
               foreach ($caseInfo->currentUsers as $caseUser) {
                  $title = $LANG['processmaker']['item']['task']['task'].$caseUser->taskName;
                  echo "<li><a href='#task-".$caseUser->delIndex."' title='$title'>". ($caseUser->userId != $GLPICurrentPMUserId ? "<i><sub>$title</sub></i>" : $title) ."</a></li>";
                  $arrayProcessmakerTabPanel[] = "task-".$caseUser->delIndex;
               }
            } else {
               // no user means CANCELLED or COMPLETED
               // then create artificial panel to host case infos
               echo "<li><a href='#caseInfo' title='".$LANG['processmaker']['item']['case']['caseinfo']."'>".$LANG['processmaker']['item']['case']['caseinfo']."</a></li>";
               $arrayProcessmakerTabPanel[] = "caseInfo";
            }
            // add default panels: map, history, log and dynaforms
            $defaultTabs = ['caseMap' => 'viewcasemap', 'caseHistory' => 'viewcasehistory', 'caseChangeLogHistory' => 'viewcasechangeloghistory', 'historyDynaformPage' => 'viewdynaforms' ];
            foreach ($defaultTabs as $tab => $tabText) {
               echo "<li><a href='#$tab' onclick=\"javascript:Actions.tabFrame('$tab');return false;\" title='".$LANG['processmaker']['item']['case'][$tabText]."'>".$LANG['processmaker']['item']['case'][$tabText]."</a></li>";
            }

            echo "</ul>";

            ////////////////
            // Define panels
            ////////////////
            if ($pmCaseUser) {
               $csrf = Session::getNewCSRFToken();
               foreach ($caseInfo->currentUsers as $caseUser) {
                  // for each task, if task is to be claimed, we need to verify that current user can claim it by checking if he/she is in the group assigned to the task
                  $hide_claim_button=false; // by default
                  if (!$caseUser->userId) {
                     // current task is to claimed
                     // get task user list
                     $query = "SELECT items_id, itemtype FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id = '".$locCase->getID()."' AND del_index =".$caseUser->delIndex;
                     foreach ($DB->request($query) as $row) {
                        // normally there is only one task
                        $task = getItemForItemtype( $row['itemtype'] );
                        $task->getFromDB( $row['items_id'] );
                        // check if this group can be found in the current user's groups
                        if (!isset($_SESSION['glpigroups']) || !in_array( $task->fields['groups_id_tech'], $_SESSION['glpigroups'] )) {
                           $hide_claim_button=true;
                        }
                     }
                  }
                  echo "<div id='task-".$caseUser->delIndex."'>";
                  // to load users for task re-assign only when task is not to be 'claimed'
                  if ($caseUser->userId) {
                     echo "<div class='tab_bg_2' id='divUsers-".$caseUser->delIndex."' >Loading...</div>";
                     echo "<script>$('#divUsers-".$caseUser->delIndex."').load( '".$CFG_GLPI["root_doc"]."/plugins/processmaker/ajax/task_users.php?cases_id=".$locCase->getID()."&items_id=".$items_id."&itemtype=".$itemtype."&users_id=".PluginProcessmakerUser::getGLPIUserId($caseUser->userId)."&taskGuid=".$caseUser->taskId."&delIndex=".$caseUser->delIndex."&delThread=".$caseUser->delThread."&rand=$rand' ); </script>";
                  }
                  echo "<iframe id='caseiframe-task-".$caseUser->delIndex."' onload='onTaskFrameLoad( event, ".$caseUser->delIndex.", ".($hide_claim_button?"true":"false").", \"$csrf\" );' style='border:none;' class='tab_bg_2' width='100%' src='";
                  echo $PM_SOAP->serverURL."/cases/cases_Open?sid=".$PM_SOAP->getPMSessionID()."&APP_UID=".$caseInfo->caseId."&DEL_INDEX=".$caseUser->delIndex."&action=TO_DO";
                  echo "&rand=$rand&glpi_domain={$config->fields['domain']}'></iframe></div>";
               }
            } else {
                // no user means CANCELLED or COMPLETED
                // then create artificial panel to host case infos
                echo "<div id='caseInfo'>";
                $url = $PM_SOAP->serverURL."/cases/cases_Open?sid=".$PM_SOAP->getPMSessionID()."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&action=TO_DO";
                echo "<iframe id=\"caseiframe-caseInfo\" onload=\"onOtherFrameLoad( 'caseInfo', 'caseiframe-caseInfo', 'body' );\" style=\"border:none;\" class=\"tab_bg_2\" width=\"100%\" src=\"$url&rand=$rand&glpi_domain={$config->fields['domain']}\"></iframe></div>";
            }
            // default panels
            // map, history, log and dynaforms
            // will be added dynamically by the addTabPanel function


            echo "</div>";
            // end of tabs/panels

            echo "</td></tr>";
            echo "<tr class='tab_bg_1' ><td  colspan=4 >";
            if ($pmCaseUser) {
                $activePanel = 'task-'.$pmCaseUser->delIndex;
            } else {
                $activePanel = 'caseInfo';
            }
            $caseMapUrl = $PM_SOAP->serverURL.($project_type=='bpmn' ? "/designer?prj_uid=".$caseInfo->processId."&prj_readonly=true&app_uid=".$caseInfo->caseId : "/cases/ajaxListener?action=processMap&rand=$rand")."&glpi_domain={$config->fields['domain']}";
            echo "<script>
                function addTabPanel( name, title, html ){
                    //debugger ;
                    if( !$('#processmakertabpanel')[0].children[name] ) { // panel is not yet existing, create one
                        //var num_tabs = $('#processmakertabpanel ul li').length ;
                        if( $('#processmakertabpanel a[href=\"#'+name+'\"]').length == 0 ) {
                           $('#processmakertabpanel ul').append( '<li><a href=\'#' + name + '\'>' + title + '</a></li>' );
                        }
                        //debugger ;
                        $('#processmakertabpanel').append( '<div id=\'' + name + '\'>' + html + '</div>');
                        $('#processmakertabpanel').tabs('refresh'); // to show the panel
                    }
                    var tabIndex = $('#processmakertabpanel a[href=\"#'+name+'\"]').parent().index();
                    $('#processmakertabpanel').tabs( 'option', 'active', tabIndex) ; // to activate it
                    //$('#processmakertabpanel').tabs( 'option', 'collapsible', true );
                }
                var historyGridListChangeLogGlobal = { viewIdHistory: '', viewIdDin: '', viewDynaformName: '', idHistory: '' } ;
                var ActionTabFrameGlobal = { tabData: '', tabName: '', tabTitle: '' } ;

                var Actions = { tabFrame: function( actionToDo ) {
                                                       // debugger ;
                            if( actionToDo == 'caseMap' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casemap']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", ".($project_type=='bpmn' ? "true" : "false" )." );\' width=\'100%\' src=\'$caseMapUrl\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'caseHistory' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casehistory']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/ajaxListener?action=caseHistory&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'caseChangeLogHistory' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['casechangeloghistory']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/ajaxListener?action=changeLogHistory&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'dynaformViewFromHistory' ) {
                                actionToDo = 'dynaformChangeLogViewHistory_' + historyGridListChangeLogGlobal.viewIdDin + historyGridListChangeLogGlobal.dynDate.replace(/ /g, '_').replace(/:/g, '-') ;
                                ajaxResponse = $.parseJSON(historyGridListChangeLogGlobal.viewDynaformName);
                                addTabPanel( actionToDo,
                                        ajaxResponse.dynTitle + ' <sup>(' + historyGridListChangeLogGlobal.dynDate + ')</sup>',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/ajaxListener?action=dynaformViewFromHistory&DYN_UID=' + historyGridListChangeLogGlobal.viewIdDin + \"&HISTORY_ID=\" + historyGridListChangeLogGlobal.viewIdHistory + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                           if( actionToDo == 'historyDynaformPage' ) {
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['dynaforms']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=historyDynaformPage&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^changeLog' ) == 0 ) {
                                actionToDo = 'changeLog' ;
                                addTabPanel( actionToDo,
                                        '".$LANG['processmaker']['item']['case']['changelog']."',
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/ajaxListener?action=changeLogTab&idHistory=' + historyGridListChangeLogGlobal.idHistory + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^historyDynaformGridPreview' ) == 0 ) {
                                actionToDo = actionToDo.replace('_', '$') ;
                                    var act = actionToDo.replace( '$', '&DYN_UID=') ;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^historyDynaformGridHistory' ) == 0) {
                                var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                                    var act = 'showDynaformListHistory&PRO_UID=' + ajaxResponse.PRO_UID + '&APP_UID=' + ajaxResponse.APP_UID + '&TAS_UID=-1&DYN_UID=' + ajaxResponse.DYN_UID;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"body\", 0 );\' height=\'600px\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                            } else
                            if( actionToDo.search( '^dynaformChangeLogViewHistory' ) == 0) {
                                var ajaxResponse = $.parseJSON(ActionTabFrameGlobal.tabData);
                                actionToDo='dynaformChangeLogViewHistory' + ajaxResponse.dynUID + ajaxResponse.dynDate ;
                                //actionToDo = actionToDo.replace(' ', '_').replace(':', '-');
                                    var act = 'dynaformChangeLogViewHistory&DYN_UID=' + ajaxResponse.dynUID + '&HISTORY_ID=' + ajaxResponse.tablename;
                                addTabPanel( actionToDo,
                                        ActionTabFrameGlobal.tabTitle,
                                        '<iframe id=\'caseiframe-' + actionToDo + '\' style=\'border: none;\' onload=\'onOtherFrameLoad( \"'+actionToDo+'\", \"caseiframe-' + actionToDo + '\", \"form\", 0 );\' width=\'100%\' src=\'".$PM_SOAP->serverURL."/cases/casesHistoryDynaformPage_Ajax?actionAjax=' + act + '&rand=$rand&glpi_domain={$config->fields['domain']}\' ></iframe>'
                                        );
                           }
                        }
                    } ;

                $(function() {
//debugger;
                    $('#processmakertabpanel').tabs( {active: ".array_search( $activePanel, $arrayProcessmakerTabPanel )."});
                    //$('#processmakertabpanel').scrollabletabs();
                    //$('#processmakertabpanel').position({
                    //  my: 'left top',
                    //  at: 'left top',
                    //  of: '#processmakertabpaneltable'
                    //});
                    $('#processmakertabpanel').removeClass( 'ui-tabs' ) ;
                    //debugger ;
                    $('#processmakertabpanel').tabs({activate: function (event, ui) {
                                                            try {
                                                                //debugger;
                                                                if( typeof onOtherFrameLoad == 'function' )
                                            var newPanel = ui.newPanel.selector.replace('#', '') ;
                                            var panelType = newPanel.split( '-' )[ 0 ].split( '$' )[ 0 ].split( '_' ) ;
                                            var searchTag = '' ;
                                            switch( panelType[0] ) {
                                                case 'task' :
                                                    searchTag = 'table' ;
                                                    break ;

                                                case 'historyDynaformGridPreview' :
                                                case 'dynaformChangeLogViewHistory' :
                                                    searchTag = 'form' ;
                                                    break ;

                                                case 'caseInfo' :
                                                case 'caseMap' :
                                                case 'caseHistory' :
                                                case 'changeLog' :
                                                case 'historyDynaformPage' :
                                                case 'dynaformChangeLogViewHistory' :
                                                case 'historyDynaformGridHistory' :
                                                default :
                                                    searchTag = 'body' ;
                                                    break ;
                                                                }
                                            onOtherFrameLoad( newPanel, 'caseiframe-' + newPanel, searchTag, ".($project_type=='bpmn' ? "true" : "false" )."  ) ;
                                                            } catch( evt ) {
                                                                //debugger;
                                                            }
                                                        }
                    });

            ";

            echo "});

            ";

            echo    "</script>";

            echo "</td></tr>";

         } else {

            //********************************
            // no running case for this ticket
            // propose to start one
            //********************************
            echo "<tr><th colspan='4'>".$LANG['processmaker']['item']['nocase'];

            // check if item is not solved nor closed
            if ($item->fields['status'] != 'solved' && $item->fields['status'] != 'closed' && $_SESSION['glpiactiveprofile']['interface'] != 'helpdesk') {
               // propose case start
               echo "&nbsp;-&nbsp;".$LANG['processmaker']['item']['startone'];
               echo "</th></tr>";

               echo "<tr class='tab_bg_2'><td class='tab_bg_2' colspan='1'>";
               echo $LANG['processmaker']['item']['selectprocess']."&nbsp;";
               echo "<input type='hidden' name='action' value='newcase'>";
               echo "<input type='hidden' name='items_id' value='$items_id'>";
               echo "<input type='hidden' name='itemtype' value='$itemtype'>";
               PluginProcessmakerProcess::dropdown(array( 'value' => 0, 'entity' => $item->fields['entities_id'], 'name' => 'plugin_processmaker_processes_id', 'condition' => "is_active=1"));
               echo "</td><td class='tab_bg_2'>";
               echo "<input type='submit' name='additem' value='".$LANG['processmaker']['item']['start']."' class='submit'>";
               echo "</td></tr>";
            } else {
               echo "</th></tr>";
            }
         }

         echo "</table>";
         Html::closeForm(true );
         //echo "</form>";

      } else {
         // under maintenance
         echo $LANG['processmaker']['config']['undermaintenance'];
      }

      return true;
   }

   /**
   * Summary of deleteTasks
   * will delete all tasks associated with this case from the item
   * @return true if tasks have been deleted from associated item and from case table
   */
   private function deleteTasks( ) {
      global $DB;
      $ret = false;

      $query = "DELETE FROM glpi_".strtolower($this->fields['itemtype'])."tasks WHERE id IN (SELECT items_id FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id='".$this->fields['id']."')";
      if ($DB->query( $query )) {
         $query = "DELETE FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id='".$this->fields['id']."'";
         if ($DB->query( $query )) {
            $ret = true;
         }
      }
      return $ret;
   }


    /**
     * Summary of deleteCase
     * will delete case and all tasks associated with this case from the item
     * @return true if case and tasks have been deleted from associated item and from case table
     */
   function deleteCase( ) {
      return $this->delete(['id' => $this->getID()]);
   }



    /**
     * Summary of cancelTasks
     * will mark as information all to_do tasks
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if tasks have been deleted from associated item and from case table
     */
   private function cancelTasks( ) {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         $query = "UPDATE glpi_".$this->fields['itemtype']."tasks SET state=0,users_id_tech=0,begin=NULL,end=NULL  WHERE state=1 AND id in (select items_id from glpi_plugin_processmaker_tasks where plugin_processmaker_cases_id='".$this->fields['id']."')";
         if ($DB->query( $query )) {
            $ret = true;
         }
      }
      return $ret;
   }



    /**
     * Summary of cancelCase
     * will cancel case and mark 'to_do' tasks associated with this case from the item as information
     * BEWARE that this will only be done when case is in TO_DO status
     * @return true if case and tasks have been cancelled or marked from associated item and from case table
     */
   function cancelCase( ) {
      global $DB;
      $ret = false;

      if (isset($this->fields['case_status']) && $this->fields['case_status'] == "TO_DO") {
         if ($this->cancelTasks()) {
            if ($this->update( array( 'id' => $this->getID(), 'case_status' => 'CANCELLED' ) )) {
                $ret=true;
            }
         }
      }

      return $ret;
   }

    /**
     * Summary of canSolve
     * To know if a Ticket (Problem or Change) can be solved
     * i.e. the case permits solving of item
     * @param mixed $item is the item
     * @return bool true to permit solve, false otherwise
     */
   public static function canSolve ($item) {
      $myCase = new self;
      if ($myCase->getFromItem( $item['item']->getType(), $item['item']->getID() )) {
         $pmVar = $myCase->getVariables(['GLPI_ITEM_CAN_BE_SOLVED']);
         // TODO also manage sub-cases
         if ($myCase->fields['case_status'] != 'COMPLETED' && $myCase->fields['case_status'] != 'CANCELLED' && (!isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) || $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1)) {
            // then item can't be solved
            return false;
         }
      }
      return true;
   }

    /**
     * Summary of getToDoTasks
     * @param mixed $parm is a Ticket, a Problem or a Change
     * @return array list of tasks with status 'to do' for case associated with item
     */
   public static function getToDoTasks($parm) {
      $myCase = new self;

      if ($myCase->getFromItem( $parm->getType(), $parm->getID() )) {
         return PluginProcessmakerTask::getToDoTasks( $myCase->getID(), $parm->getType()."Task" );
      }
      return array();
   }


   /**
    * Summary of getMenuContent
    * @return array
    */
   static function getMenuContent() {

      //if (!Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
      //   return;
      //}

      $front_page = "/plugins/processmaker/front";
      $menu = array();
      $menu['title'] = self::getTypeName(Session::getPluralNumber());
      $menu['page']  = "$front_page/case.php";
      $menu['links']['search'] = PluginProcessmakerCase::getSearchURL(false);
      //if (Session::haveRightsOr("config", [READ, UPDATE])) {
      //   $menu['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
      //}

      $itemtypes = [
                 'PluginProcessmakerCase' => 'cases'
            ];

      foreach ($itemtypes as $itemtype => $option) {
         //$menu['options'][$option]['title']           = $itemtype::getTypeName(Session::getPluralNumber());
         $menu['options'][$option]['page']            = $itemtype::getSearchURL(false);
         $menu['options'][$option]['links']['search'] = $itemtype::getSearchURL(false);
         //if (Session::haveRightsOr("config", [READ, UPDATE])) {
         //   $menu['options'][$option]['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
         //}
         switch( $itemtype ) {
            case 'PluginProcessmakerCase':
               //if ($itemtype::canCreate()) {
               //$menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
               //}
               break ;
            default :
               $menu['options'][$option]['page']            = PluginProcessmakerProcess::getSearchURL(false);
               break ;
         }

      }
      return $menu;
   }

   static function getSpecificValueToDisplay($field, $values, array $options=array()) {
      global $LANG;

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'id':
            $locCase = new self;

            //$ret = $locCase->add(['id' => 300, 'itemtype' => 'Ticket', 'items_id' => 252108, 'case_guid' => 'azertyuiop', 'case_num' => -12] );
            $locCase->getFromDB($values['id']);
            return $locCase->getLink();

         case 'items_id':
            switch ($field) {
               case 8:
               default:
                  // show an item link
                  $item = new $values['itemtype'];
                  $item->getFromDB($values['items_id']);
                  return $item->getLink(['forceid' => 1]);
               case 9:
                  // show item entity
                  $item = new $values['itemtype'];
                  $item->getFromDB($values['items_id']);
                  $entity = new Entity;
                  $entity->getFromDB($item->fields['entities_id']);
                  return $entity->getLink(['complete' => 1]);

            }
         case 'case_status':
            return $LANG['processmaker']['case']['statuses'][$values['case_status']];

         default:
            return parent::getSpecificValueToDisplay($field, $values, $options);
      }
   }


   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;

      switch ($field) {
         case 'case_status':
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownStatus($options);

         default:
            return parent::getSpecificValueToSelect($field, $name, $values, $options);
      }
   }


   static function dropdownStatus(array $options=array()) {

      $p['name']      = 'case_status';
      $p['value']     = self::TO_DO;
      $p['showtype']  = 'normal';
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      switch ($p['showtype']) {
         //case 'allowed' :
         //   $tab = static::getAllowedStatusArray($p['value']);
         //   break;

         case 'search' :
            $tab = static::getAllStatusArray(true);
            break;

         default :
            $tab = static::getAllStatusArray(false);
            break;
      }

      return Dropdown::showFromArray($p['name'], $tab, $p);
   }


   static function getAllStatusArray($withmetaforsearch=false) {

      $tab = array(self::DRAFT => _x('case_status', 'Draft'),
                   self::TO_DO => _x('case_status', 'To do'),
                   self::COMPLETED => _x('case_status', 'Completed'),
                   self::CANCELLED  => _x('case_status', 'Cancelled'));

      //if ($withmetaforsearch) {
      //   $tab['notold']    = _x('status', 'Not solved');
      //   $tab['notclosed'] = _x('status', 'Not closed');
      //   $tab['process']   = __('Processing');
      //   $tab['old']       = _x('status', 'Solved + Closed');
      //   $tab['all']       = __('All');
      //}
      return $tab;
   }


   static function getStatus($value) {

      $tab  = static::getAllStatusArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }

   /**
    * Summary of getSearchOptions
    * @return mixed
    */
   function getSearchOptions() {
      global $LANG;

      $tab = array();

      $tab['common'] = __('Process cases', 'processmaker'); //$LANG['processmaker']['title'][1];

      $tab[1]['table']         = self::getTable();
      $tab[1]['field']         = 'id';
      $tab[1]['name']          = __('ID', 'processmaker');
      $tab[1]['datatype']      = 'number';
      $tab[1]['searchtype']    = 'contains';
      $tab[1]['massiveaction'] = false;

      $tab[2]['table']         = self::getTable();
      $tab[2]['field']         = 'name';
      $tab[2]['name']          = __('Title', 'processmaker');
      $tab[2]['datatype']      = 'itemlink';
      $tab[2]['searchtype']    = 'contains';
      $tab[2]['massiveaction'] = false;

      $tab[3]['table']         = PluginProcessmakerProcess::getTable();
      $tab[3]['field']         = 'name';
      $tab[3]['name']          = __('Process', 'processmaker');
      $tab[3]['datatype']      = 'itemlink';
      $tab[3]['massiveaction'] = false;


      //$tab[7]['table']         = self::getTable();
      //$tab[7]['field']         = 'itemtype';
      //$tab[7]['name']          = __('Item type', 'processmaker');
      //$tab[7]['massiveaction'] = false;
      //$tab[7]['datatype']      = 'text';

      $tab[8]['table']         = self::getTable();
      $tab[8]['field']         = 'items_id';
      $tab[8]['name']          = __('Item', 'processmaker');
      $tab[8]['massiveaction'] = false;
      $tab[8]['datatype']      = 'specific';
      $tab[8]['additionalfields'] = ['itemtype'];

      $tab[9]['table']         = Entity::getTable();
      $tab[9]['field']         = 'name';
      $tab[9]['name']          = __('Item entity', 'processmaker');
      $tab[9]['massiveaction'] = false;
      $tab[9]['datatype']      = 'itemlink';

      $tab[10]['table']         = self::getTable();
      $tab[10]['field']         = 'case_status';
      $tab[10]['name']          = __('Status', 'processmaker');
      $tab[10]['datatype']      = 'specific';
      $tab[10]['searchtype']    = ['contains', 'equals', 'notequals'];
      $tab[10]['massiveaction'] = false;


      $tab[14]['table']         = self::getTable();
      $tab[14]['field']         = 'plugin_processmaker_cases_id';
      $tab[14]['name']          = __('Subcase of', 'processmaker');
      $tab[14]['datatype']      = 'itemlink';
      $tab[14]['massiveaction'] = false;


      return $tab;
   }


   function showForm ($ID, $options=array('candel'=>false)) {
      global $DB, $CFG_GLPI, $LANG;

      $options['candel'] = true;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      //echo "<tr class='tab_bg_1'>";
      //echo "<td>".__("Name")."</td><td>";
      //echo "<input size='100' type='text' name='name' value='".Html::cleanInputText($this->fields["name"])."'>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Active")."</td><td>";
      //Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("External data")."</td><td>";
      //Dropdown::showYesNo("is_externaldata", $this->fields["is_externaldata"]);
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Self")."</td><td>";
      //Dropdown::showYesNo("is_self", $this->fields["is_self"]);
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Source task GUID")."</td><td>";
      ////PluginProcessmakerTaskCategory::dropdown(array('name'                => 'plugin_processmaker_taskcategories_id_source',
      ////                                               'display_emptychoice' => false,
      ////                                               'value'               => $this->fields['plugin_processmaker_taskcategories_id_source']));
      //echo "<input size='100' type='text' name='sourcetask_guid' value='".$this->fields["sourcetask_guid"]."'>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Target task GUID")."</td><td>";
      ////PluginProcessmakerTaskCategory::dropdown(array('name'                => 'plugin_processmaker_taskcategories_id_target',
      ////                                               'display_emptychoice' => false,
      ////                                               'value'               => $this->fields['plugin_processmaker_taskcategories_id_target']));
      //echo "<input size='100' type='text' name='targettask_guid' value='".$this->fields["targettask_guid"]."'>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Target process GUID")."</td><td>";
      ////Dropdown::show( 'PluginProcessmakerProcess', array('name'                => 'plugin_processmaker_processes_id',
      ////                                          'display_emptychoice' => true,
      ////                                          'value'               => $this->fields['plugin_processmaker_processes_id'],
      ////                                          'condition' => 'is_active = 1'));
      //echo "<input size='100' type='text' name='targetprocess_guid' value='".$this->fields["targetprocess_guid"]."'>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td>".__("Target dynaform GUID")."</td><td>";
      //echo "<input size='100' type='text' name='targetdynaform_guid' value='".$this->fields["targetdynaform_guid"]."'>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td>".__("Source condition")."</td><td>";
      ////echo "<input size='100' type='text' name='sourcecondition' value='".$this->fields["sourcecondition"]."'>";
      //echo "<textarea cols='100' rows='3' name='sourcecondition' >".$this->fields["sourcecondition"]."</textarea>";
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__("Claim target task")."</td><td>";
      //Dropdown::showYesNo("is_targettoclaim", $this->fields["is_targettoclaim"]);
      //echo "</td></tr>";

      //echo "<tr class='tab_bg_1'>";
      //echo "<td>".__("External application JSON config")."</td><td>";
      //echo "<textarea cols='100' rows='6' name='externalapplication' >".$this->fields["externalapplication"]."</textarea>";
      //echo "</td></tr>";


      $this->showFormButtons($options );

   }

   function defineTabs($options=array()) {

      //        $ong = array('empty' => $this->getTypeName(1));
      $ong = array();
      //$this->addDefaultFormTab($ong);

      $this->addStandardTab('PluginProcessmakerTask', $ong, $options);

      $this->addStandardTab(__CLASS__, $ong, $options);

      $this->addStandardTab('PluginProcessmakerCasemap', $ong, $options);

      $this->addStandardTab('PluginProcessmakerCasehistory', $ong, $options);

      $this->addStandardTab('PluginProcessmakerCasechangelog', $ong, $options);

      $this->addStandardTab('PluginProcessmakerCasedynaform', $ong, $options);

      //$this->addStandardTab('Ticket', $ong, $options);
      //$this->addStandardTab('Log', $ong, $options);

      //TODO we are going to add tabs like tasks, map, history, dynaform...

      return $ong;
   }

   /**
    * Actions done after the PURGE of the item in the database
    *
    * @return nothing
    **/

   /**
    * Summary of post_purgeItem
    * Actions done after the PURGE of the item in the database
    * Will purge the tasks and the PM case and recursively the sub-cases if any
    * @return boolean|integer
    */
   function post_purgeItem() {
      global $PM_SOAP;
      $ret = false;

      $PM_SOAP->login(true);
      if ($this->deleteTasks() && $this->deleteCronTaskActions() && $PM_SOAP->deleteCase($this->fields['case_guid'])->status_code == 0) {
         $ret = true;
         // then must delete any sub-processes (sub-cases)
         foreach(getAllDatasFromTable(self::getTable(), "`plugin_processmaker_cases_id` = ".$this->getID()) as $row){
            $tmp = new self;
            $tmp->fields = $row;
            $ret &= $tmp->delete(['id' => $row['id']]);
         }
      }
      return $ret;
   }


   /**
    * Summary of deleteCronTaskActions
    * Will delete any cron task actions taht are linked to current case
    */
   function deleteCronTaskActions( ) {
      global $DB;

      $query = "DELETE FROM `glpi_plugin_processmaker_crontaskactions` WHERE `plugin_processmaker_cases_id` = ".$this->getID();

      return $DB->query($query);
   }
}