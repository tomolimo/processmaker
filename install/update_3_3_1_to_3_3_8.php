<?php

function update_3_3_1_to_3_3_8(){
   global $DB;

   // Alter table glpi_plugin_processmaker_configs
   if (!arFieldExists("glpi_plugin_processmaker_configs", "ssl_verify" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	               ADD COLUMN `ssl_verify` TINYINT(1) NOT NULL DEFAULT '0' AFTER `maintenance`;";

      $DB->query($query) or die("error adding ssl_verify to glpi_plugin_processmaker_configs table" . $DB->error());
   }

   return '3.3.8';
}
