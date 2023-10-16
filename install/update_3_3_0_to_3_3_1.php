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
function update_3_3_0_to_3_3_1() {
   global $DB;

   // Alter table glpi_plugin_processmaker_processes
   if (!$DB->fieldExists("glpi_plugin_processmaker_processes", "is_change" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
                  ADD COLUMN `is_change` TINYINT(1) NOT NULL DEFAULT '0' AFTER `project_type`,
                  ADD COLUMN `is_problem` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_change`,
                  ADD COLUMN `is_incident` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_problem`,
                  ADD COLUMN `is_request` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_incident`;";

      $DB->query($query) or die("error adding is_change, etc... to glpi_plugin_processmaker_processes table" . $DB->error());

   }

   // Alter table glpi_plugin_processmaker_caselinks
   if (!$DB->fieldExists("glpi_plugin_processmaker_caselinks", "is_targettoreassign" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_caselinks`
	               ADD COLUMN `is_targettoreassign` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_targettoclaim`,
	               ADD COLUMN `is_targettoimpersonate` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_targettoreassign`,
	               ADD COLUMN `is_synchronous` TINYINT(1) NOT NULL DEFAULT '0' AFTER `externalapplication`;";

      $DB->query($query) or die("error adding is_targettoreassign, etc... to glpi_plugin_processmaker_caselinks table" . $DB->error());

   }

   return '3.3.1';
}
