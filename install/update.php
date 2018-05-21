<?php

function processmaker_update(){
   global $DB;

   // update from older versions
   // load config to get current version
   if (!arFieldExists("glpi_plugin_processmaker_configs", "db_version" )) {
      $current_version = '2.4.1';
   } else {
      include_once(GLPI_ROOT."/plugins/processmaker/inc/config.class.php");
      $config = PluginProcessmakerConfig::getInstance();
      $current_version = $config->fields['db_version'];
   }

   switch($current_version){
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
   }

   // end update by updating the db version number
   $query = "UPDATE `glpi_plugin_processmaker_configs` SET `db_version` = '$new_version' WHERE `id` = 1;";

   $DB->query($query) or die("error when updating db_version field in glpi_plugin_processmaker_configs" . $DB->error());

}
