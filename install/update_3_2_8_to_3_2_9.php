<?php

function update_3_2_8_to_3_2_9(){
   global $DB;

   if (!arFieldExists("glpi_plugin_processmaker_configs", "db_version")) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
                  ADD COLUMN `db_version` VARCHAR(10) NULL;";
      $DB->query($query) or die("error adding db_version field to glpi_plugin_processmaker_configs" . $DB->error());
   }

   return '3.2.9';

}
