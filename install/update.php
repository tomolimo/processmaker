<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2022 by Raynet SAS a company of A.Raymond Network.

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
function processmaker_update() {
   global $DB;

   // update from older versions
   // load config to get current version
   $config_table = 'glpi_plugin_processmaker_configs';
   $config_field = 'db_version';
   $current_version = '2.4.1'; //by default
   $config = Config::getConfigurationValues('plugin:processmaker');
   if (count($config) > 0) {
      $current_version = $config[$config_field];
   }
   if ($DB->tableExists($config_table) && $DB->fieldExists($config_table, $config_field)) {
      $config = getAllDataFromTable($config_table);
      $config = $config[1];
      $current_version = $config[$config_field];
   }

   switch ($current_version) {
      case '2.4.1' :
         // will upgrade any old versions (< 3.2.8) to 3.2.8
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_to_3_2_8.php");
         $new_version = update_to_3_2_8();

      case '3.2.8' :
         // will upgrade 3.2.8 to 3.2.9
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_2_8_to_3_2_9.php");
         $new_version = update_3_2_8_to_3_2_9();

      case '3.2.9' :
         // will upgrade 3.2.9 to 3.3.0
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_2_9_to_3_3_0.php");
         $new_version = update_3_2_9_to_3_3_0($config);

      case '3.3.0' :
         // will upgrade 3.3.0 to 3.3.1
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_3_0_to_3_3_1.php");
         $new_version = update_3_3_0_to_3_3_1();

      case '3.3.1' :
         // will upgrade 3.3.1 to 3.3.8
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_3_1_to_3_3_8.php");
         $new_version = update_3_3_1_to_3_3_8();

      case '3.3.8' :
         // will upgrade 3.3.8 to 3.4.9
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_3_8_to_3_4_9.php");
         $new_version = update_3_3_8_to_3_4_9();

      case '3.4.9' :
         // will upgrade 3.4.9 to 3.4.10
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_4_9_to_3_4_10.php");
         $new_version = update_3_4_9_to_3_4_10();

      case '3.4.10' :
         // will upgrade 3.4.10 to 4.0.0
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_3_4_10_to_4_0_0.php");
         $new_version = update_3_4_10_to_4_0_0();

      case '4.0.0' :
         // will upgrade 4.0.0 to 4.0.1
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_4_0_0_to_4_0_1.php");
         $new_version = update_4_0_0_to_4_0_1();

      case '4.0.1' :
         // will upgrade 4.0.1 to 4.0.2
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_4_0_1_to_4_0_2.php");
         $new_version = update_4_0_1_to_4_0_2($config);

         if (isset($new_version) && $new_version !== false) {
             // end update by updating the db version number
             $query = "UPDATE `glpi_plugin_processmaker_configs` SET `db_version` = '$new_version' WHERE `id` = 1;";
       
             $DB->query($query) or die("error when updating db_version field in glpi_plugin_processmaker_configs" . $DB->error());
         }

       case '4.0.2' :
         // will upgrade 4.0.2 to 4.4.0
         include_once(PLUGIN_PROCESSMAKER_ROOT . "/install/update_4_0_2_to_4_4_0.php");
         $new_version = update_4_0_2_to_4_4_0();

   }

    if (isset($new_version) && $new_version !== false) {
        // end update by updating the db version number
        Config::setConfigurationValues('plugin:processmaker', [$config_field => $new_version]);
    }


}
