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
function update_4_0_2_to_4_4_0() {
   global $DB;

   if ($DB->tableExists('glpi_plugin_processmaker_configs')) {
       // needs to move all the data in this table to the glpi_configs table.
       $row = getAllDataFromTable('glpi_plugin_processmaker_configs');
       $row = $row[1];
       unset($row['id']);
       unset($row['name']);
       unset($row['comment']);
       unset($row['domain']);
       $row['is_reassignreason_mandatory'] = 0;
       Config::setConfigurationValues('plugin:processmaker', $row);

       // then now delete the glpi_plugin_processmaker_configs table
       // needs to add fields into glpi_plugin_processmaker_case table
       $query = "DROP TABLE `glpi_plugin_processmaker_configs`;";
       $DB->query($query) or die("error when deleting glpi_plugin_processmaker_configs table" . $DB->error());
   }

   if (!$DB->fieldExists('glpi_plugin_processmaker_processes', 'is_reassignreason_mandatory')) {
       // add the field into table
       $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	       ADD COLUMN `is_reassignreason_mandatory` TINYINT(1) NOT NULL DEFAULT '-2';";

       $DB->query($query) or die("error when adding is_reassignreason_mandatory field to glpi_plugin_processmaker_processes table" . $DB->error());
   }

   if (!$DB->fieldExists('glpi_plugin_processmaker_taskcategories', 'is_reassignreason_mandatory')) {
       // add the field into table
       $query = "ALTER TABLE `glpi_plugin_processmaker_taskcategories`
	       ADD COLUMN `is_reassignreason_mandatory` TINYINT(1) NOT NULL DEFAULT '-2';";

       $DB->query($query) or die("error when adding is_reassignreason_mandatory field to glpi_plugin_processmaker_taskcategories table" . $DB->error());
   }

   if (!$DB->tableExists('glpi_plugin_processmaker_reassignreasontranslations')) {
        $query = "CREATE TABLE `glpi_plugin_processmaker_reassignreasontranslations` (
	            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	            `plugin_processmaker_taskcategories_id` INT UNSIGNED NOT NULL,
	            `language` VARCHAR(10) NOT NULL,
	            `label` TEXT NULL DEFAULT NULL,
	            PRIMARY KEY (`id`) USING BTREE,
	            UNIQUE INDEX `plugin_processmaker_taskcategories_id_language` (`plugin_processmaker_taskcategories_id`, `language`) USING BTREE,
	            INDEX `language` (`language`) USING BTREE,
	            INDEX `plugin_processmaker_taskcategories_id` (`plugin_processmaker_taskcategories_id`) USING BTREE
            )
            ENGINE=InnoDB
            ;";

       $DB->query($query) or die("error when creating glpi_plugin_processmaker_reassignreasontranslations table" . $DB->error());
   }

   return '4.4.0';
}