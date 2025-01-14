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
function update_4_5_0_to_4_6_0() {
   global $DB;

   if (!$DB->tableExists('glpi_plugin_processmaker_processcategories')) {
        $query = "CREATE TABLE `glpi_plugin_processmaker_processcategories` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`name` VARCHAR(250) NOT NULL DEFAULT '',
					`category_guid` VARCHAR(32) NULL,
					PRIMARY KEY (`id`),
					UNIQUE INDEX `category_guid` (`category_guid`)
					);";

       $DB->query($query) or die("error when creating glpi_plugin_processmaker_processcategories table" . $DB->error());
   }
    if (!$DB->fieldExists('glpi_plugin_processmaker_processes', 'plugin_processmaker_processcategories_id')) {
        $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
					ADD COLUMN `plugin_processmaker_processcategories_id` INT UNSIGNED NULL AFTER `is_reassignreason_mandatory`;
					";

       $DB->query($query) or die("error when creating glpi_plugin_processmaker_processcategories table" . $DB->error());
    }
   return '4.6.0';
}