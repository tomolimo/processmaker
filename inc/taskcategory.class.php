<?php

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


   static function displayTabContentForProcess(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $DB, $CFG_GLPI;

      self::title($item);

      echo "<div class='center'><br><table class='tab_cadre_fixehov'>";
      echo "<tr><th colspan='7'>".__('Task list', 'processmaker')."</th></tr>";
      echo "<tr><th>".__('Task name', 'processmaker')."</th>".
      "<th>".__('Complete name')."</th>" .
      "<th>".__('Start', 'processmaker')."</th>" .
      "<th>".__('Task guid', 'processmaker')."</th>" .
      "<th>".__('Comments')."</th>" .
      "<th>".__('Active')."</th>" .
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
                        'pm.is_subprocess'
                     ],
                     'FROM'      => 'glpi_plugin_processmaker_taskcategories AS pm',
                     'LEFT JOIN' => [
                        'glpi_taskcategories AS gl' => [
                           'FKEY' => [
                              'gl' => 'id',
                              'pm' => 'taskcategories_id'
                           ]
                        ]
                     ],
                     'WHERE'     => [
                        'pm.plugin_processmaker_processes_id' => $item->getId()
                     ]
         ]);
      //$query = "SELECT pm.pm_task_guid, pm.taskcategories_id, pm.`is_start`, gl.name, gl.completename, gl.`comment`, pm.is_active, pm.is_subprocess FROM glpi_plugin_processmaker_taskcategories AS pm
      //              LEFT JOIN glpi_taskcategories AS gl ON pm.taskcategories_id=gl.id
      //              WHERE pm.plugin_processmaker_processes_id=".$item->getID().";";

      //foreach ($DB->request($query) as $taskCat) {
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


   /**
    * Summary of displayTabContentForTaskCategory
    * @param CommonGLPI $item 
    * @param mixed $tabnum 
    * @param mixed $withtemplate 
    * @return boolean
    */
   static function displayTabContentForTaskCategory(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $DB, $CFG_GLPI;

      $is_taskcat = false;
      $processes_id = 0;
      $pmtaskcat = new PluginProcessmakerTaskCategory;
      $is_taskcat = $pmtaskcat->getFromDBbyCategory($item->fields['id']);
      $processes_id = $pmtaskcat->fields['plugin_processmaker_processes_id'];

      echo "<div class='center'><br><table class='tab_cadre_fixehov'>";

      echo "<tr><th colspan='8'>".__('Process task', 'processmaker')."</th></tr>";
      echo "<tr><th>".__('Process name', 'processmaker')."</th>";
      echo "<th>".__('Task name', 'processmaker')."</th>";

      echo "<th>".__('Complete name')."</th>" .
      "<th>".__('Start', 'processmaker')."</th>" .
      "<th>".__('Task guid', 'processmaker')."</th>" .
      "<th>".__('Comments')."</th>" .
      "<th>".__('Active')."</th>" .
      "<th>".__('Sub-process', 'processmaker')."</th>" .
      "</tr>";

      $query = "SELECT pm.pm_task_guid, pm.taskcategories_id, pm.`is_start`, glp.name as 'pname', gl.name, gl.completename, gl.`comment`, pm.is_active, pm.is_subprocess FROM glpi_plugin_processmaker_taskcategories AS pm
                  LEFT JOIN glpi_taskcategories AS gl ON pm.taskcategories_id=gl.id
                  LEFT JOIN glpi_taskcategories AS glp ON glp.id=gl.taskcategories_id
                  WHERE pm.taskcategories_id=".$item->getID().";";

      foreach ($DB->request($query) as $taskCat) {
         echo "<tr class='tab_bg_1'>";

         echo "<td class='b'><a href='";
         echo Toolbox::getItemTypeFormURL('PluginProcessmakerProcess') . "?id=" . $processes_id . "'>" . $taskCat['pname'];
         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (" . $processes_id . ")";
         }
         echo "</a></td>";
         echo "<td class='b'>";
         echo  $taskCat['name'];

         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (" . $taskCat['taskcategories_id'] . ")";
         }
         echo "</td>";

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
         $pic = $CFG_GLPI["root_doc"] . "/plugins/processmaker/pics/gears.png";
         if ($item->fields['maintenance']) {
            $pic = $CFG_GLPI["root_doc"] . "/plugins/processmaker/pics/verysmall-under_maintenance.png";
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
      global $DB;

      $res = $DB->request(
                     $this->getTable(),
                     [
                     'pm_task_guid' => $task_guid
                     ]
      );
      if ($res) {
         if ($res->numrows() != 1) {
            return false;
         }
         $this->fields = $res->next();
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         }
      }

      //$query = "SELECT *
      //          FROM `".$this->getTable()."`
      //          WHERE `pm_task_guid` = '$task_guid'";

      //if ($result = $DB->query($query)) {
      //   if ($DB->numrows($result) != 1) {
      //      return false;
      //   }
      //   $this->fields = $DB->fetch_assoc($result);
      //   if (is_array($this->fields) && count($this->fields)) {
      //      return true;
      //   }
      //}
      return false;
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
      //$query = "SELECT *
      //          FROM `".$this->getTable()."`
      //          WHERE `taskcategories_id` = $catid";
      if ($res) {
         if ($res->numrows() != 1) {
            return false;
         }
         $this->fields = $res->next();
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         }
      }
      //if ($result = $DB->query($query)) {
      //   if ($DB->numrows($result) != 1) {
      //      return false;
      //   }
      //   $this->fields = $DB->fetch_assoc($result);
      //   if (is_array($this->fields) && count($this->fields)) {
      //      return true;
      //   }
      //}
      return false;
   }

   ///**
   // * Summary of dropdown
   // * @param mixed $options
   // * @return mixed
   // */
   //static function dropdown($options=array()) {
   //   global $CFG_GLPI;
   //   if (isset($options['value'])) {
   //      $that = new self;
   //      $that->getFromDB($options['value']);
   //      $options['value'] = $that->fields['taskcategories_id'];
   //   }

   //   $options['url'] = $CFG_GLPI["root_doc"].'/plugins/processmaker/ajax/dropdownTaskcategories.php';
   //   return Dropdown::show( 'TaskCategory', $options );

   //}

   ///**
   // * Execute the query to select ProcesssmakerTaskcategories
   // *
   // * @param $count true if execute an count(*),
   // * @param $search pattern
   // *
   // * @return mysql result set.
   // **/
   //static function getSqlSearchResult ($count=true, $search='') {
   //   global $DB, $CFG_GLPI;

   //   $orderby = '';

   //   $where = ' WHERE glpi_plugin_processmaker_taskcategories.is_active=1 ';

   //   $join = ' LEFT JOIN glpi_taskcategories ON glpi_taskcategories.id = glpi_plugin_processmaker_taskcategories.taskcategories_id';

   //   if ($count) {
   //      $fields = " COUNT(DISTINCT glpi_plugin_processmaker_taskcategories.id) AS cpt ";
   //   } else {
   //      $fields = " DISTINCT glpi_taskcategories.id, glpi_taskcategories.completename AS name ";
   //      $orderby = " ORDER BY glpi_taskcategories.completename ASC";
   //   }

   //   if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
   //      $where .= " AND (glpi_taskcategories.completename $search
   //                     OR glpi_taskcategories.comment $search) ";
   //   }

   //   $query = "SELECT $fields FROM glpi_plugin_processmaker_taskcategories $join ".$where." ".$orderby.";";

   //   return $DB->query($query);
   //}


}
