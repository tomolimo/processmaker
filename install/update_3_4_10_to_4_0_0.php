<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2022 by Raynet SAS a company of A.Raymond Network.

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
function update_3_4_10_to_4_0_0() {
   global $PLUGIN_HOOKS;

   function migratePasswords() {
      global $DB;

      $glpikey = new GLPIKey;
      // Fetch old key and migrate
      $sodium_key = null;
      $old_key = $glpikey->getLegacyKey();

      if ($DB instanceof DBmysql) {
         return $glpikey->migrateFieldsInDb($sodium_key, $old_key);
      }

      return false;
   }

   // needs to change password encryption
    $PLUGIN_HOOKS['secured_fields']['processmaker'] = [
        'glpi_plugin_processmaker_configs.pm_admin_passwd',
        'glpi_plugin_processmaker_configs.pm_dbserver_passwd',
        ];
    
   if (migratePasswords()) {
      return '4.0.0';
   }

   return false;
}