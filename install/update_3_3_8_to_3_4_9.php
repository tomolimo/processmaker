<?php

function update_3_3_8_to_3_4_9() {
   global $DB;

   // Alter table glpi_plugin_processmaker_configs
   if (!$DB->fieldExists("glpi_plugin_processmaker_configs", "max_cases_per_item" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	             ADD COLUMN `max_cases_per_item` INT(11) NOT NULL DEFAULT '0' AFTER `db_version`;";

      $DB->query($query) or die("error adding max_cases_per_item to glpi_plugin_processmaker_configs table" . $DB->error());
   }

   // Alter table glpi_plugin_processmaker_processes
   if (!$DB->fieldExists("glpi_plugin_processmaker_processes", "maintenance" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	             ADD COLUMN `maintenance` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_request`;";

      $DB->query($query) or die("error adding maintenance to glpi_plugin_processmaker_processes table" . $DB->error());
   }
   // Alter table glpi_plugin_processmaker_processes
   if (!$DB->fieldExists("glpi_plugin_processmaker_processes", "max_cases_per_item" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	             ADD COLUMN `max_cases_per_item` INT(11) NOT NULL DEFAULT '0' AFTER `maintenance`;";

      $DB->query($query) or die("error adding max_cases_per_item to glpi_plugin_processmaker_processes table" . $DB->error());
   }

   return '3.4.9';
}