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
class PluginProcessmakerDB extends DBmysql {

   var $dbhost;

   var $dbuser;

   var $dbpassword;

   var $dbdefault;

   function __construct($config = []) {
    global $DB;
      if (count($config) == 0) {
          $config = Config::getConfigurationValues('plugin:processmaker');
      }
      if ($config['pm_dbserver_name'] != ''
         && $config['pm_dbserver_user'] != ''
         && $config['pm_workspace'] != '' ) {
         $this->dbhost = $config['pm_dbserver_name'];
         $this->dbuser = $config['pm_dbserver_user'];
         $this->dbpassword = Toolbox::sodiumDecrypt($config['pm_dbserver_passwd']);
         $this->dbdefault = isset($config['pm_dbname']) ? $config['pm_dbname'] : '';
         parent::__construct();
      }
   }

}
