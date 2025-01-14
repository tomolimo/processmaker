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
function update_3_2_8_to_3_2_9() {
   global $DB;

   if (!$DB->fieldExists("glpi_plugin_processmaker_configs", "db_version")) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
                  ADD COLUMN `db_version` VARCHAR(10) NULL;";
      $DB->query($query) or die("error adding db_version field to glpi_plugin_processmaker_configs" . $DB->error());
   }

   return '3.2.9';

}
