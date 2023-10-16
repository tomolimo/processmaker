<?php
// to dump case records, to be able to restore case state

// Ensure current directory when run from crontab
chdir(dirname($_SERVER["SCRIPT_FILENAME"]));

define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");

/**
 * Summary of exportRows
 * @param mixed $locDB mysql db ressource
 * @param mixed $table mysql table
 * @param mixed $where mysql where clause
 * @param mixed $fd file descriptor used to write
 * @return array found rows in the table
 */
function exportRows($locDB, $table, $where, $fd) {
   $content = '';
   $ret    = [];

   $table = "`".$locDB->dbdefault."`.`".$table."`";
   $results = $locDB->query('SELECT * FROM '.$table.' WHERE '.$where);
   foreach ($results as $row) {
      $line = '';
      foreach (array_values($row) as $val) {
         if (strlen($line) > 0) {
            $line .= ",";
         }
         if (is_string($val)) {
            $line .= "'".$locDB->escape($val)."'";
         } else {
            $line .= $val ?? 'NULL';
         }
      }
      $line = "INSERT INTO ".$table." VALUES ($line);";
      $content .= $line. "\n";
      $ret[] = $row;
   }

   fputs($fd, $content);

   return $ret;
}


if ($argc == 3) {

   // $argv[1] must be the plugin_processmaker_cases_id
   // $argv[2] must be the filename to dump

   /////////////////////////////
   // GLPI
   /////////////////////////////
   $fd = fopen($argv[2], 'w');

   // first get the case info
   $case = exportRows($DB, 'glpi_plugin_processmaker_cases', 'id = '. $argv[1], $fd);
   if (count($case) > 0) {
      // then get crontaskactions
      $crontasks = exportRows($DB, 'glpi_plugin_processmaker_crontaskactions', 'plugin_processmaker_cases_id = '. $argv[1], $fd);

      // then get the tasks todo and done
      $pmtasks = exportRows($DB, 'glpi_plugin_processmaker_tasks', 'plugin_processmaker_cases_id = '. $argv[1], $fd);
      $tasktable = getTableForItemType($pmtasks[0]['itemtype']); // there should be at least one task
      $tasksid = [];
      foreach ($pmtasks as $pmtask) {
         exportRows($DB, $tasktable, 'id = '.$pmtask['items_id'], $fd);
         $tasksid[] = $pmtask['items_id'];
      }


      // then get the tasks of type information
      $re = "<input name=\'caseid\' type=\'hidden\' value=\'".$argv[1]."\'><input name=\'taskid\' type=\'hidden\' value=\'[1-9][0-9]*\'>";
      exportRows($DB, $tasktable, "content REGEXP '".$re."' AND id NOT IN (".implode(',', $tasksid).")", $fd);

      // then export documents
      $docs_id = exportRows($DB, 'glpi_plugin_processmaker_documents', "plugin_processmaker_cases_id = ".$argv[1], $fd);
      $docs_id = array_column($docs_id, 'documentsid');
      if (count($docs_id)) {
         exportRows($DB, 'glpi_documents_items', "documents_id IN (".implode(',', $docs_id).")", $fd);
         exportRows($DB, 'glpi_documents', "id IN (".implode(',', $docs_id).")", $fd);
      }



      /////////////////////////////
      // PM
      /////////////////////////////

      // next get $PM_DB dump
      $pmcases = exportRows($PM_DB, 'application', 'APP_NUMBER = '.$argv[1], $fd);
      $APP_UID = $pmcases[0]['APP_UID'];

      //exportRows($PM_DB, 'app_cache_view', "APP_UID = '$APP_UID'", $fd); DO NOT EXPORT
      exportRows($PM_DB, 'app_delay', "APP_UID = '$APP_UID'", $fd);
      exportRows($PM_DB, 'app_delegation', "APP_UID = '$APP_UID'", $fd);

      // add a delete, otherwise the app_document table may contain "DELETED" documents during restore
      fputs($fd, "DELETE FROM `".$PM_DB->dbdefault."`.`app_document` WHERE APP_UID = '$APP_UID';\n");
      exportRows($PM_DB, 'app_document', "APP_UID = '$APP_UID'", $fd);

      exportRows($PM_DB, 'app_history', "APP_UID = '$APP_UID'", $fd);
      exportRows($PM_DB, 'app_message', "APP_UID = '$APP_UID'", $fd);
      exportRows($PM_DB, 'app_notes', "APP_UID = '$APP_UID'", $fd);
      exportRows($PM_DB, 'app_thread', "APP_UID = '$APP_UID'", $fd);

      echo "Case has been dumped\n";

   } else {
      echo "Can't find case: " . $argv[1] . "\n";
   }

   fclose($fd);

} else {
   echo "Can't dump case, syntax error: php.exe -f caseDump.php caseid filename\n";
}

