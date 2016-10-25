<?php
/**
 */

class PluginProcessmakerConfig extends CommonDBTM {

    static private $_instance = NULL;
    //static private $db = NULL ;

    static function canCreate() {
        return Session::haveRight('config', UPDATE);
    }

    static function canView() {
        return Session::haveRight('config', READ);
    }

    static function canUpdate() {
        return Session::haveRight('config', UPDATE);
    }

    static function getTypeName($nb=0) {
        global $LANG;

        return $LANG['processmaker']['config']['setup'];
    }

    function getName($with_comment=0) {
        global $LANG;

        return $LANG['processmaker']['title'][1];
    }

    /**
     * Singleton for the unique config record
     */
    static function getInstance() {

        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

   /**
   * Prepare input datas for updating the item
   *
   * @param $input array used to update the item
   *
   * @return array the modified $input array
   **/
   function prepareInputForUpdate($input) {

      if( !isset($input["maintenance"]) ) {
         $input["maintenance"] = 0 ;
      }

      if (isset($input["pm_dbserver_passwd"])) {
         if (empty($input["pm_dbserver_passwd"])) {
            unset($input["pm_dbserver_passwd"]);
         } else {
            $input["pm_dbserver_passwd"] = Toolbox::encrypt(stripslashes($input["pm_dbserver_passwd"]), GLPIKEY);
         }
      }

      if (isset($input["_blank_pm_dbserver_passwd"]) && $input["_blank_pm_dbserver_passwd"]) {
         $input['pm_dbserver_passwd'] = '';
      }

      if (isset($input["pm_admin_passwd"])) {
         if (empty($input["pm_admin_passwd"])) {
            unset($input["pm_admin_passwd"]);
         } else {
            $input["pm_admin_passwd"] = Toolbox::encrypt(stripslashes($input["pm_admin_passwd"]), GLPIKEY);
         }
      }

      if (isset($input["_blank_pm_admin_passwd"]) && $input["_blank_pm_admin_passwd"]) {
         $input['pm_admin_passwd'] = '';
      }

      return $input;
   }
    static function showConfigForm($item) {
        global $LANG, $PM_DB;

        $ui_theme = array(
          'glpi_classic' => 'glpi_classic',
          'glpi_neoclassic' => 'glpi_neoclassic'
        );

        $config = self::getInstance();

        $config->showFormHeader();

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['URL']."</td><td >";
        echo "<input size='50' type='text' name='pm_server_URL' value='".$config->fields['pm_server_URL']."'>" ;
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['workspace']."</td><td >";
        echo "<input type='text' name='pm_workspace' value='".$config->fields['pm_workspace']."'>" ;
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >" . $LANG['processmaker']['config']['admin']['user'] . "</td>";
        echo "<td ><input type='text' name='pm_admin_user' value='".$config->fields["pm_admin_user"]."'>";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >" . $LANG['processmaker']['config']['admin']['password'] . "</td>";
        echo "<td ><input type='password' name='pm_admin_passwd' value='' autocomplete='off'>";
        echo "&nbsp;<input type='checkbox' name='_blank_pm_admin_passwd'>&nbsp;".__('Clear');
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['connectionstatus']."</td><td >";
        $pm = new PluginProcessmakerProcessmaker ;
        $ret = $pm->login(true);
        if( $ret ) {
           echo "<font color='green'>".__('Test successful');
        } else {
           echo "<font color='red'>".__('Test failed')."<br>".print_r($pm->lasterror,true);
        }
        echo "</font></span></td></tr>\n";

        echo "<tr><td  colspan='4' class='center b'>".$LANG['processmaker']['config']['mysql']."</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td >" . __('SQL server (MariaDB or MySQL)') . "</td>";
        echo "<td ><input type='text' size=50 name='pm_dbserver_name' value='".$config->fields["pm_dbserver_name"]."'>";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >" . __('SQL user') . "</td>";
        echo "<td ><input type='text' name='pm_dbserver_user' value='".$config->fields["pm_dbserver_user"]."'>";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >" . __('SQL password') . "</td>";
        echo "<td ><input type='password' name='pm_dbserver_passwd' value='' autocomplete='off'>";
        echo "&nbsp;<input type='checkbox' name='_blank_pm_dbserver_passwd'>&nbsp;".__('Clear');
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['connectionstatus']."</td><td >";
        if( $PM_DB->connected ) {
           echo "<font color='green'>".__('Test successful');
        } else {
           echo "<font color='red'>".__('Test failed');
        }
        echo "</font></span></td></tr>\n";

        echo "<tr><td  colspan='4' class='center b'>".__('Settings')."</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['theme']."</td><td >";
        Dropdown::showFromArray('pm_theme', $ui_theme,
                        array('value' => $config->fields['pm_theme']));
        echo "</td></tr>";

        $taskCatogrie = new TaskCategory;
        $taskCatogrie->getFromDB( $config->fields['taskcategories_id'] ) ;
        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['main_task_category']."</td><td >";
        echo "<a href='".Toolbox::getItemTypeFormURL( 'TaskCategory' )."?id=". $config->fields['taskcategories_id']."'>".str_replace(" ", "&nbsp;", $taskCatogrie->fields['name']);
        if ($_SESSION["glpiis_ids_visible"]) {
            echo " (".$config->fields['taskcategories_id'].")";
        }
        echo "</a>" ;
        echo "</td></tr>\n";

        $taskUser = new User;
        $taskUser->getFromDB( $config->fields['users_id'] ) ;
        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['taskwriter']."</td><td >";
        echo "<a href='".Toolbox::getItemTypeFormURL( 'User' )."?id=". $config->fields['users_id']."'>".str_replace(" ", "&nbsp;", $taskUser->getName());
        if ($_SESSION["glpiis_ids_visible"]) {
            echo " (".$config->fields['users_id'].")";
        }
        echo "</a>" ;
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td >".$LANG['processmaker']['config']['pm_group_name']."</td><td >";

        $pmGroups = array( 0 => Dropdown::EMPTY_VALUE ) ;
        $query = "SELECT DISTINCT CON_ID, CON_VALUE FROM content WHERE CON_CATEGORY='GRP_TITLE' AND CON_LANG='".$pm->lang."' ORDER BY CON_VALUE;" ;
        if( $PM_DB->connected ) {
           foreach( $PM_DB->request( $query ) as $row ) {
              $pmGroups[ $row['CON_ID'] ] = $row['CON_VALUE'] ;
           }
           Dropdown::showFromArray( 'pm_group_guid', $pmGroups, array('value' => $config->fields['pm_group_guid']) ) ;
        } else {
           echo "<font color='red'>".__('Not connected');
        }

        echo "</td></tr>\n";

         //echo "<tr class='tab_bg_1'>";
         //echo "<td >".$LANG['processmaker']['config']['comments']."";
         //echo "</td><td rowspan='5'  >";
         //echo "<textarea cols='60' rows='5' name='comment' >".$config->fields['comment']."</textarea>";
         //echo "</td></tr>\n";

         //echo "<tr></tr>";
         //echo "<tr></tr>";
         //echo "<tr></tr>";
         //echo "<tr></tr>";

         echo "<tr><td  colspan='4' class='center b'>".__('Maintenance')."</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".$LANG['processmaker']['config']['maintenance']."</td><td >";
         Dropdown::showYesNo("maintenance", $config->fields['maintenance']);
         echo "</td></tr>";

        $config->showFormButtons(array('candel'=>false));

        return false;
    }


    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
        global $LANG;

        if ($item->getType()=='Config') {
            return $LANG['processmaker']['title'][1];
        }
        return '';
    }


    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

        if ($item->getType()=='Config') {
            self::showConfigForm($item);
        }
        return true;
    }


}
