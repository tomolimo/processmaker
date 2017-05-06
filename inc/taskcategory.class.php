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

   function getTabNameForItem( CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      return $LANG['processmaker']['title'][3];
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      global $LANG, $DB;

      self::title($item);

      echo "<div class='center'><br><table class='tab_cadre_fixehov'>";
      echo "<tr><th colspan='5'>".$LANG['processmaker']['title'][3]."</th></tr>";
      echo "<tr><th>".$LANG['processmaker']['process']['taskcategories']['name']."</th>".
      "<th>".$LANG['processmaker']['process']['taskcategories']['completename']."</th>" .
      "<th>".$LANG['processmaker']['process']['taskcategories']['guid']."</th>" .
      "<th>".$LANG['processmaker']['process']['taskcategories']['start']."</th>" .
      "<th>".$LANG['processmaker']['process']['taskcategories']['comment']."</th></tr>";

      $query = "SELECT pm.pm_task_guid, pm.taskcategories_id, pm.`start`, gl.name, gl.completename, gl.`comment` FROM glpi_plugin_processmaker_taskcategories AS pm
                    LEFT JOIN glpi_taskcategories AS gl ON pm.taskcategories_id=gl.id
                    WHERE pm.processes_id=".$item->getID().";";

      foreach ($DB->request($query) as $taskCat) {
         echo "<tr class='tab_bg_1'><td class='b'><a href='".
         Toolbox::getItemTypeFormURL( 'TaskCategory' )."?id=".
         $taskCat['taskcategories_id']."'>".str_replace(" ", "&nbsp;", $taskCat['name']);
         if ($_SESSION["glpiis_ids_visible"]) {
            echo " (".$taskCat['taskcategories_id'].")";
         }
         echo "</a></td><td >".str_replace(" ", "&nbsp;", $taskCat['completename'])."</td>
             <td >".$taskCat['pm_task_guid']."</td>".
            "<td>".($taskCat['start']?'x':'')."</td><td >".
         $taskCat['comment']."</td></tr>";
      }
      echo "</table></div>";

      return true;
   }

    /**
     * Print a good title for task categories tab
     * add button for re-synchro of taskcategory list (only if rigths are w)
     * @return nothing (display)
     **/
   static function title(CommonGLPI $item) {
      global $LANG, $CFG_GLPI;

      $buttons = array();
      $title = $LANG['processmaker']['config']['refreshtasklist'];

      if (Session::haveRight('plugin_processmaker_config', UPDATE)) {
         $buttons["process.form.php?refreshtask=1&id=".$item->getID()] = $LANG['processmaker']['config']['refreshtasklist'];
         $title = "";
         Html::displayTitle($CFG_GLPI["root_doc"] . "/plugins/processmaker/pics/gears.png", $LANG['processmaker']['config']['refreshtasklist'], $title,
                            $buttons);
      }
   }

    //function getLinkItemFromExternalID($extId) {
    //    if( $this->getFromDBbyExternalID( $extId ) ) {
    //        $taskcat = new TaskCategory ;
    //        return $taskcat->getFromDB( $this->fields['items_id'] ) ;
    //    }
    //}


    /**
     * Retrieve a TaskCat from the database using its external id (unique index): pm_task_guid
     *
     * @param $extid string externalid
     *
     * @return true if succeed else false
     **/
   function getFromDBbyExternalID($extid) {
      global $DB;

      $query = "SELECT *
                FROM `".$this->getTable()."`
                WHERE `pm_task_guid` = '$extid'";

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
     * Retrieve a TaskCat from the database using its category id (unique index): taskcategories_id
     *
     * @param $catid string task category id
     *
     * @return true if succeed else false
     **/
   function getFromDBbyCategory($catid) {
      global $DB;

      $query = "SELECT *
                FROM `".$this->getTable()."`
                WHERE `taskcategories_id` = $catid";

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

}
