<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

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
function update_3_3_8_to_3_4_9() {
   global $DB;

   // Alter table glpi_plugin_processmaker_configs
   if (!$DB->fieldExists("glpi_plugin_processmaker_configs", "max_cases_per_item" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	             ADD COLUMN `max_cases_per_item` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `db_version`;";

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
	             ADD COLUMN `max_cases_per_item` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `maintenance`;";

      $DB->query($query) or die("error adding max_cases_per_item to glpi_plugin_processmaker_processes table" . $DB->error());
   }

   return '3.4.9';
}