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

// to restore case state

// Ensure current directory when run from crontab
chdir(dirname($_SERVER["SCRIPT_FILENAME"]));

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");


function runSQLFile($locdbs, $path) {
   $line = 1;

   $script = fopen($path, 'r');
   if (!$script) {
      return false;
   }
   $sql_query = @fread(
      $script,
      @filesize($path)
   ) . "\n";

   $queries = preg_split('/;\s*$/m', $sql_query);

   foreach ($queries as $query) {
      $query = trim($query);

      if ($query != '') {
         $re = '/INSERT INTO `.*`\.`glpi_/';
         $locdb = $locdbs['PM'];
         if (preg_match($re, $query)) {
            $locdb = $locdbs['GLPI'];
         }
         $locdb->queryOrDie($query, "ERROR: Can't exec line $line\n");
         $line++;
      }
   }

   return true;
}


if ($argc == 3) {

   // $argv[1] must be the cases_id
   // and
   // argv[2] must be a SQL file to restore

   /////////////////////////////
   // Delete the case in GLPI and PM
   /////////////////////////////

   $case = new PluginProcessmakerCase();
   if ($case->getFromDB($argv[1]) && $case->deleteCase()) {
      echo "Case has been deleted\n";
   } else {
      echo "Can't delete case " . $argv[1] . "\n";
   }

   $DB->beginTransaction();
   $PM_DB->beginTransaction();

   runSQLFile(['GLPI' => $DB, 'PM' => $PM_DB], $argv[2]);

   $PM_DB->commit();
   $DB->commit();


   echo "Case has been restored\n";

} else {
   echo "Can't restore case, a cases_id is missing and/or sql file name, syntax: php.exe -f caseRestore.php cases_id SQLfilename\n";
}


