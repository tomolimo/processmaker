<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */

class PluginProcessmakerConfig extends CommonDBTM {

   /**
    * Summary of getTypeName
    * @param mixed $nb plural
    * @return mixed
    */
   static function getTypeName($nb = 0) {
      return __('ProcessMaker setup', 'processmaker');
   }

   /**
    * Summary of getName
    * @param mixed $with_comment with comment
    * @return mixed
    */
   function getName($with_comment = 0) {
      return __('ProcessMaker', 'processmaker');
   }


   /**
    * Summary of showConfigForm
    * @param mixed $item is the config
    * @return boolean
    */
   static function showConfigForm($item) {
      global $PM_DB, $CFG_GLPI, $PM_SOAP;

      $setup_ok = false;

      $ui_theme = [
        'glpi_classic' => 'glpi_classic',
        'glpi_neoclassic' => 'glpi_neoclassic'
      ];

      $pmconfig = $PM_SOAP->config;
      echo "<form name='form' action=\"".Toolbox::getItemTypeFormURL('Config')."\" method='post' data-track-changes='true'>";
            echo "<div class='center' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='4'>" . __('ProcessMaker setup') . "</th></tr>";

      if (!$pmconfig['maintenance']) {

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Server URL (if GLPI is using HTTPS, PM server must also use HTTPS)', 'processmaker')."</td><td >";
         echo "<input size='50' type='text' name='pm_server_URL' value='".$pmconfig['pm_server_URL']."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Verify SSL certificate', 'processmaker')."</td><td >";
         Dropdown::showYesNo("ssl_verify", $pmconfig['ssl_verify']);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Workspace Name', 'processmaker')."</td><td >";
         echo "<input type='text' name='pm_workspace' value='".$pmconfig['pm_workspace']."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Server administrator name', 'processmaker')."</td>";
         echo "<td ><input type='text' name='pm_admin_user' value='".$pmconfig["pm_admin_user"]."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Server administrator password', 'processmaker')."</td>";
         echo "<td ><input type='password' name='pm_admin_passwd' id='pm_admin_passwd' value='' autocomplete='new-password'>";
         echo "&nbsp;<input type='checkbox' name='_blank_pm_admin_passwd' id='_blank_pm_admin_passwd'>&nbsp;<label for='_blank_pm_admin_passwd'>".__('Clear')."</label>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Connection status', 'processmaker')."</td><td >";

         if ($pmconfig['pm_server_URL'] != ''
            && $pmconfig['pm_workspace'] != ''
            && $pmconfig["pm_admin_user"] != ''
            && ($PM_SOAP->login(true))) {
            echo "<font color='green'>".__('Test successful');
            $setup_ok = true;
         } else {
            echo "<font color='red'>".__('Test failed')."<br>".print_r($PM_SOAP->lasterror, true);
         }
         echo "</font></td></tr>\n";

         echo "<tr><th colspan='2'>".__('ProcessMaker server information', 'processmaker')."</th></tr>";
         if ($setup_ok) {
             $info = $PM_SOAP->systemInformation( );
             $pm_version = explode('-RE-', $info->version);
             $pm_requested_minversion = explode('-RE-', PLUGIN_PROCESSMAKER_MIN_PM);
             $pm_requested_maxversion = explode('-RE-', PLUGIN_PROCESSMAKER_MAX_PM);


             if (version_compare($pm_version[0], $pm_requested_minversion[0], 'ge')
               && version_compare($pm_version[0], $pm_requested_maxversion[0], 'le')
               && isset($pm_version[1])
               && version_compare($pm_version[1], $pm_requested_minversion[1], 'ge')
               && version_compare($pm_version[1], $pm_requested_maxversion[1], 'le')) {
                 echo '<tr><td>'.__('Version', 'processmaker')."</td><td><font color='green'>".$info->version." -> OK</font></td></tr>";
                 echo '<tr><td>'.__('Web server', 'processmaker').'</td><td>'.$info->webServer.'</td></tr>';
                 echo '<tr><td>'.__('Server name', 'processmaker').'</td><td>'.$info->serverName.'</td></tr>';
                 echo '<tr><td>'.__('PHP version', 'processmaker').'</td><td>'.$info->phpVersion.'</td></tr>';
                 echo '<tr><td>'.__('DB version', 'processmaker').'</td><td>'.$info->databaseVersion.'</td></tr>';
                 echo '<tr><td>'.__('DB server IP', 'processmaker').'</td><td>'.$info->databaseServerIp.'</td></tr>';
                 echo '<tr><td>'.__('DB name', 'processmaker').'</td><td>'.$info->databaseName.'</td></tr>';
                 echo '<tr><td>'.__('User browser', 'processmaker').'</td><td>'.$info->userBrowser.'</td></tr>';
                 echo '<tr><td>'.__('User IP', 'processmaker').'</td><td>'.$info->userIp.'</td></tr>';

             } else {
                echo '<tr><td>'.__('Version', 'processmaker').'</td><td nowrap><font color=red>'.$info->version.' -> NOK<br>'.
                    sprintf(__('This plugin requires PM server >= %s and < %s', 'processmaker'), PLUGIN_PROCESSMAKER_MIN_PM, PLUGIN_PROCESSMAKER_MAX_PM).
                    '</td></font></tr>';
             }
         } else {
             echo '<tr><td>'.__('Version', 'processmaker')."</td><td><font color='red'".__('Not yet!', 'processmaker').'</font></td></tr>';
         }


         echo "<tr><th colspan='2'>".__('SQL server setup', 'processmaker')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td >" . __('SQL server (MariaDB or MySQL)', 'processmaker') . "</td>";
         echo "<td ><input type='text' size=50 name='pm_dbserver_name' value='".$pmconfig["pm_dbserver_name"]."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >" . __('Database name', 'processmaker') . "</td>";
         echo "<td ><input type='text' size=50 name='pm_dbname' value='".$pmconfig['pm_dbname']."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >" . __('SQL user', 'processmaker') . "</td>";
         echo "<td ><input type='text' name='pm_dbserver_user' value='".$pmconfig["pm_dbserver_user"]."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >" . __('SQL password', 'processmaker') . "</td>";
         echo "<td ><input type='password' name='pm_dbserver_passwd' id='pm_dbserver_passwd' value='' autocomplete='new-password'>";
         echo "&nbsp;<input type='checkbox' name='_blank_pm_dbserver_passwd' id='_blank_pm_dbserver_passwd'>&nbsp;<label for='_blank_pm_dbserver_passwd'>".__('Clear')."</label>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Connection status', 'processmaker')."</td><td >";
         if (isset($PM_DB) && $PM_DB->connected && isset($PM_DB->dbdefault) && $PM_DB->dbdefault != '') {
            echo "<font color='green'>".__('Test successful');
         } else {
            echo "<font color='red'>".__('Test failed');
         }
         echo "</font></td></tr>\n";

         echo "<tr><th  colspan='2' >".__('Settings')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Theme Name', 'processmaker')."</td><td >";
         Dropdown::showFromArray('pm_theme', $ui_theme,
                         ['value' => $pmconfig['pm_theme']]);
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Main Task Category (edit to change name)', 'processmaker')."</td><td >";
         TaskCategory::dropdown(['name'              => 'taskcategories_id',
                                  'display_emptychoice'   => true,
                                  'value'                 => $pmconfig['taskcategories_id']]);
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Task writer (edit to change name)', 'processmaker')."</td><td >";
         $rand = mt_rand();
         User::dropdown(['name'                 => 'users_id',
                         'display_emptychoice'  => true,
                         'right'                => 'all',
                         'rand'                 => $rand,
                         'value'                => $pmconfig['users_id']]);

         // this code adds the + sign to the form
         echo "<img alt='' title=\"".__s('Add')."\" src='".$CFG_GLPI["root_doc"].
         "/pics/add_dropdown.png' style='cursor:pointer; margin-left:2px;'
                            onClick=\"".Html::jsGetElementbyID('add_dropdown'.$rand).".dialog('open');\">";
         echo Ajax::createIframeModalWindow('add_dropdown'.$rand,
                                                  User::getFormURL(),
                                                  ['display' => false]);
         // end of + sign

         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >".__('Group in ProcessMaker which will contain all GLPI users', 'processmaker')."</td><td >";

         $pmGroups = [ 0 => Dropdown::EMPTY_VALUE ];
         if ($PM_DB->connected) {
            $res = $PM_DB->request([
                  'DISTINCT' => 'CON_ID',
                  'FIELDS'   => ['CON_ID', 'CON_VALUE'],
                  'FROM'     => 'CONTENT',
                  'WHERE'    => ['CON_CATEGORY' => 'GRP_TITLE'],
                  'ORDER'    => 'CON_VALUE'
               ]);
            foreach ($res as $row) {
               $pmGroups[ $row['CON_ID'] ] = $row['CON_VALUE'];
            }
            Dropdown::showFromArray('pm_group_guid', $pmGroups, ['value' => $pmconfig['pm_group_guid']]);
         } else {
            echo "<font color='red'>".__('Not connected');
         }
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td >" . __('Max cases per item (0=unlimited)', 'processmaker') . "</td>";
         echo "<td ><input type='text' name='max_cases_per_item' value='".$pmconfig["max_cases_per_item"]."'>";
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td>" . __('Re-assign reason is mandatory (can be changed in process settings)', 'processmaker') . "</td>";
         echo "<td >";
         Dropdown::showYesNo('is_reassignreason_mandatory', $pmconfig['is_reassignreason_mandatory']);
         echo "</td></tr>\n";

      } else {
         echo "<tr><td  colspan='2' class='center b'>";
         PluginProcessmakerProcessmaker::showUnderMaintenance();
         echo "</td></tr>";
      }

      echo "<tr><th  colspan='2'>".__('Maintenance')."</th></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Maintenance mode')."</td><td >";
      Dropdown::showYesNo("maintenance", $pmconfig['maintenance']);
      echo "</td></tr>";

      echo "<tr><td colspan='2'></td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td colspan='4' class='center'>";
      echo "<input type='submit' name='update' class='submit' value=\""._sx('button', 'Save')."\">";
      echo "</td></tr>";

      echo "</table></div>";

      echo "<input type='hidden' name='id' value='1'>";
      echo "<input type='hidden' name='config_context' value='plugin:processmaker'>";
      echo "<input type='hidden' name='config_class' value='".__CLASS__."'>";


      Html::closeForm();

      return false;
   }

   
   static function configUpdate($input) {
      if (isset($input["pm_admin_passwd"]) && $input["pm_admin_passwd"] == '') {
         unset($input["pm_admin_passwd"]);
      }
      if (isset($input["_blank_pm_admin_passwd"]) && $input["_blank_pm_admin_passwd"]) {
         $input['pm_admin_passwd'] = '';
      }

      if (isset($input["pm_dbserver_passwd"]) && $input["pm_dbserver_passwd"] == '') {
         unset($input["pm_dbserver_passwd"]);
      }
      if (isset($input["_blank_pm_dbserver_passwd"]) && $input["_blank_pm_dbserver_passwd"]) {
         $input['pm_dbserver_passwd'] = '';
      }
      return $input;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType()=='Config') {
         return __('ProcessMaker', 'processmaker');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType()=='Config') {
         self::showConfigForm($item);
      }
      return true;
   }

}
