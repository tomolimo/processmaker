<?php

function processmaker_update() {
   global $DB;

   // update from older versions
   // load config to get current version
   if (!$DB->fieldExists("glpi_plugin_processmaker_configs", "db_version" )) {
      $current_version = '2.4.1';
   } else {
      include_once(GLPI_ROOT."/plugins/processmaker/inc/config.class.php");
      $config = PluginProcessmakerConfig::getInstance();
      $current_version = $config->fields['db_version'];
      if (empty($current_version)) {
         $current_version = '2.4.1';
      }
   }

   switch ($current_version) {
      case '2.4.1' :
         // will upgrade any old versions (< 3.2.8) to 3.2.8
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_to_3_2_8.php");
         $new_version = update_to_3_2_8();

      case '3.2.8' :
         // will upgrade 3.2.8 to 3.2.9
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_2_8_to_3_2_9.php");
         $new_version = update_3_2_8_to_3_2_9();

      case '3.2.9' :
         // will upgrade 3.2.9 to 3.3.0
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_2_9_to_3_3_0.php");
         $new_version = update_3_2_9_to_3_3_0();

      case '3.3.0' :
         // will upgrade 3.3.0 to 3.3.1
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_3_0_to_3_3_1.php");
         $new_version = update_3_3_0_to_3_3_1();
      case '3.3.1' :
         // will upgrade 3.3.1 to 3.3.8
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_3_1_to_3_3_8.php");
         $new_version = update_3_3_1_to_3_3_8();
      case '3.3.8' :
         // will upgrade 3.3.8 to 3.4.9
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_3_8_to_3_4_9.php");
         $new_version = update_3_3_8_to_3_4_9();
      case '3.4.9' :
         // will upgrade 3.4.9 to 3.4.10
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_4_9_to_3_4_10.php");
         $new_version = update_3_4_9_to_3_4_10();
      case '3.4.10' :
         // will upgrade 3.4.10 to 4.0.0
         include_once(GLPI_ROOT."/plugins/processmaker/install/update_3_4_10_to_4_0_0.php");
         $new_version = update_3_4_10_to_4_0_0();
   }

   if (isset($new_version) && $new_version !== false) {
      // end update by updating the db version number
      $query = "UPDATE `glpi_plugin_processmaker_configs` SET `db_version` = '$new_version' WHERE `id` = 1;";

      $DB->query($query) or die("error when updating db_version field in glpi_plugin_processmaker_configs" . $DB->error());
   }

}
