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
function processmaker_install() {
   global $DB;

   // installation from scratch
   $DB->runFile(PLUGIN_PROCESSMAKER_ROOT . "/install/mysql/processmaker-empty.sql");

   $config = Config::getConfigurationValues('plugin:processmaker');

   $config += [
      'pm_server_URL'              => 'http://localhost/',
      'pm_workspace'               => 'workflow',
      'pm_admin_user'              => 'NULL',
      'pm_admin_passwd'            => 'NULL',
      'pm_theme'                   => 'glpi_classic',
      'date_mod'                   => $_SESSION["glpi_currenttime"],
      'taskcategories_id'          => 'NULL',
      'users_id'                   => 'NULL',
      'pm_group_guid'              => 'NULL',
      'pm_dbserver_name'           => 'NULL',
      'pm_dbname'                  => 'wf_workflow',
      'pm_dbserver_user'           => 'NULL',
      'pm_dbserver_passwd'         => 'NULL',
      'maintenance'                => 0,
      'ssl_verify'                 => 0,
      'db_version'                 => '4.4.0',
      'max_cases_per_item'         => 0,
      'is_reassignreason_mandatory' => 0
      ];

   Config::setConfigurationValues('plugin:processmaker', $config);

}
