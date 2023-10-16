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
function update_4_0_1_to_4_0_2($config) {
   global $DB, $PM_DB;

   if (!isset($PM_DB)) {
      $PM_DB = new PluginProcessmakerDB($config);
   }

   // needs to add fields into glpi_plugin_processmaker_case table
   $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
      ADD COLUMN `date_creation` TIMESTAMP NULL DEFAULT NULL AFTER `plugin_processmaker_cases_id`,
      ADD COLUMN `date_mod` TIMESTAMP NULL DEFAULT NULL AFTER `date_creation`;";
   $DB->query($query) or die("error when altering glpi_plugin_processmaker_case table" . $DB->error());

   // needs to fill in the real dates from the APPLICATION table from PM
   $query = "SELECT APP_NUMBER, APP_CREATE_DATE, APP_UPDATE_DATE FROM APPLICATION;";
   foreach ($PM_DB->request($query) as $row) {
      $DB->update('glpi_plugin_processmaker_cases', ['date_creation' => $row['APP_CREATE_DATE'], 'date_mod' => $row['APP_UPDATE_DATE']], ['id' => $row['APP_NUMBER']]);
   }

   return '4.0.2';
}