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
function update_4_0_0_to_4_0_1() {
   global $DB;

   // needs to create glpi_plugin_processmaker_documents table
   $query = "CREATE TABLE `glpi_plugin_processmaker_documents` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `plugin_processmaker_cases_id` INT UNSIGNED NOT NULL,
      `documents_id` INT UNSIGNED NULL,
      `guid` VARCHAR(32) NOT NULL COMMENT 'PM document GUID',
      `version` INT UNSIGNED NOT NULL COMMENT 'PM document version',
      `link` VARCHAR(512) NOT NULL COMMENT 'PM document link',
      `mime` VARCHAR(50) NOT NULL,
      `is_output` TINYINT(1) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`) USING BTREE,
      UNIQUE INDEX `documents_id` (`documents_id`),
      UNIQUE INDEX `guid_version` (`guid`, `version`),
      INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`),
      INDEX `is_output` (`is_output`)
      ) ENGINE=InnoDB DEFAULT;";
   $DB->query($query) or die("error when creating glpi_plugin_processmaker_documents table" . $DB->error());

   $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
      CHANGE COLUMN `logs_out` `postdata` MEDIUMTEXT NULL DEFAULT NULL AFTER `is_targettoclaim`,
      ADD COLUMN `retcode` MEDIUMTEXT NULL DEFAULT NULL AFTER `postdata`,
      CHANGE COLUMN `postdata` `formdata` MEDIUMTEXT NULL DEFAULT NULL AFTER `state`;";
   $DB->query($query) or die("error when altering glpi_plugin_processmaker_crontaskactions table" . $DB->error());

   return '4.0.1';
}