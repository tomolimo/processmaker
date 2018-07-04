<?php

function update_3_2_9_to_3_3_0(){
   global $DB, $PM_DB; //, $PM_SOAP;


   // to be sure
   $PM_DB = new PluginProcessmakerDB;

   // Alter table plugin_processmaker_cases
   if (!arFieldExists("glpi_plugin_processmaker_cases", "plugin_processmaker_processes_id" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
               	ALTER `id` DROP DEFAULT;";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_cases table step 1" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	              CHANGE COLUMN `id` `case_guid` VARCHAR(32) NOT NULL AFTER `items_id`;";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_cases table step 2" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	               CHANGE COLUMN `case_num` `id` INT(11) NOT NULL FIRST,
	               CHANGE COLUMN `itemtype` `itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket' AFTER `id`,
	               ADD COLUMN `entities_id` INT(11) NOT NULL DEFAULT '0' AFTER `items_id`,
	               ADD COLUMN `name` MEDIUMTEXT NOT NULL DEFAULT '' AFTER `entities_id`,
                  CHANGE COLUMN `processes_id` `plugin_processmaker_processes_id` INT(11) NULL DEFAULT NULL AFTER `case_status`,
                  ADD COLUMN `plugin_processmaker_cases_id` INT(11) NOT NULL DEFAULT '0' AFTER `plugin_processmaker_processes_id`,
	               DROP INDEX `items`,
	               ADD INDEX `items` (`itemtype`, `items_id`),
	               ADD PRIMARY KEY (`id`),
	               ADD UNIQUE INDEX `case_guid` (`case_guid`),
	               ADD INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`),
	               ADD INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`);";

      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_cases table step 3 " . $DB->error());

      // needs to set entities_id and name fields
      // for this needs to browse all cases and do a getCaseInfo for each and to get entities_id from itemtype(items_id)
      foreach($DB->request(PluginProcessmakerCase::getTable()) as $row) {
         $tmp = new $row['itemtype'];
         $entities_id = 0;
         if ($tmp->getFromDB($row['items_id'])) {
            $entities_id = $tmp->fields['entities_id'];
         }
         foreach($PM_DB->request("SELECT CON_VALUE FROM CONTENT WHERE CON_CATEGORY='APP_TITLE' AND CON_LANG='en' AND CON_ID='{$row['case_guid']}'") as $name) {
            // there is only one record :)
            $name = $PM_DB->escape($name['CON_VALUE']);
            $query = "UPDATE ".PluginProcessmakerCase::getTable()." SET `name` = '{$name}', `entities_id` = $entities_id WHERE `id` = {$row['id']};";
            $DB->query($query) or die("error normalizing glpi_plugin_processmaker_cases table step 4 " . $DB->error());
         }
      }
   }

   if (!arFieldExists("glpi_plugin_processmaker_processes_profiles", "plugin_processmaker_processes_id")) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes_profiles`
	               CHANGE COLUMN `processes_id` `plugin_processmaker_processes_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`,
                  DROP INDEX `processes_id`,
	               ADD INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`);";
      $DB->query($query) or die("error on glpi_plugin_processmaker_processes_profiles table when renaming processes_id into plugin_processmaker_processes_id " . $DB->error());

      // must clean the table in case there would be duplicate entries for a process
      $query = "SELECT gpp.id, gpp.plugin_processmaker_processes_id, gpp.profiles_id, gpp.entities_id, MAX(gpp.is_recursive) AS is_recursive
                  FROM glpi_plugin_processmaker_processes_profiles AS gpp
                  GROUP BY gpp.plugin_processmaker_processes_id, gpp.profiles_id, gpp.entities_id
                  HAVING COUNT(id) > 1;";

      foreach($DB->request($query) as $rec){
         // there we have one rec per duplicates
         // so we may delete all records in the table, and a new one
         $del_query = "DELETE FROM glpi_plugin_processmaker_processes_profiles WHERE plugin_processmaker_processes_id=".$rec['plugin_processmaker_processes_id']."
                        AND profiles_id = ".$rec['profiles_id']."
                        AND entities_id = ".$rec['entities_id'].";";
         $DB->query($del_query) or die("error when deleting duplicated process_profiles in glpi_plugin_processmaker_processes_profiles table ". $DB->error());

         $add_query = "INSERT INTO `glpi_plugin_processmaker_processes_profiles` (`id`, `plugin_processmaker_processes_id`, `profiles_id`, `entities_id`, `is_recursive`)
                           VALUES (".$rec['id'].", ".$rec['plugin_processmaker_processes_id'].", ".$rec['profiles_id'].", ".$rec['entities_id'].", ".$rec['is_recursive'].");";
         $DB->query($add_query) or die("error when inserting singletons of duplicated process_profiles in glpi_plugin_processmaker_processes_profiles table ". $DB->error());
      }

      $query = "ALTER TABLE `glpi_plugin_processmaker_processes_profiles`
                     ADD UNIQUE INDEX `plugin_processmaker_processes_id_profiles_id_entities_id` (`plugin_processmaker_processes_id`, `profiles_id`, `entities_id`);";
      $DB->query($query) or die("error when adding new index on glpi_plugin_processmaker_processes_profiles table " . $DB->error());
   }

   if (!arFieldExists("glpi_plugin_processmaker_tasks", "plugin_processmaker_cases_id" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_tasks`
	               ALTER `itemtype` DROP DEFAULT;";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_tasks table step 1" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_tasks`
	               CHANGE COLUMN `itemtype` `itemtype` VARCHAR(32) NOT NULL AFTER `id`,
	               ADD COLUMN `plugin_processmaker_cases_id` INT(11) NULL AFTER `case_id`,
	               ADD COLUMN `plugin_processmaker_taskcategories_id` INT(11) NULL AFTER `plugin_processmaker_cases_id`,
	               ADD COLUMN `del_thread` INT(11) NOT NULL AFTER `del_index`,
               	ADD COLUMN `del_thread_status` VARCHAR(32) NOT NULL DEFAULT 'OPEN' AFTER `del_thread`,
	               DROP INDEX `case_id`,
	               ADD UNIQUE INDEX `tasks` (`plugin_processmaker_cases_id`, `del_index`),
                  ADD INDEX `del_thread_status` (`del_thread_status`);";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_tasks table step 2" . $DB->error());

      // transform case_id (=GUID) into plugin_processmaker_cases_id
      $query = "UPDATE `glpi_plugin_processmaker_tasks`
                LEFT JOIN `glpi_plugin_processmaker_cases` ON `glpi_plugin_processmaker_cases`.`case_guid` = `glpi_plugin_processmaker_tasks`.`case_id`
                SET `glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id` = `glpi_plugin_processmaker_cases`.`id`;";
      $DB->query($query) or die("error transforming case_id into plugin_processmaker_cases_id in glpi_plugin_processmaker_tasks table" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_tasks`
	               DROP COLUMN `case_id`;";
      $DB->query($query) or die("error deleting case_id column in glpi_plugin_processmaker_tasks table" . $DB->error());

      // set real thread status get it from APP_DELEGATION
      $query = "SELECT APP_UID, DEL_INDEX, DEL_THREAD, DEL_THREAD_STATUS FROM APP_DELEGATION WHERE DEL_THREAD_STATUS = 'CLOSED';";
      $locThreads = [];
      foreach($PM_DB->request($query) as $thread){
         $locThreads[$thread['APP_UID']][] = $thread;
      }
      $locCase = new PluginProcessmakerCase;
      foreach($locThreads as $key => $threads){
         // get GLPI case id
         $locCase->getFromGUID($key);
         $del_indexes = [];
         foreach($threads as $thread){
            $del_indexes[] = $thread['DEL_INDEX'];
         }
         $del_indexes = implode(", ", $del_indexes);
         $query = "UPDATE glpi_plugin_processmaker_tasks SET del_thread_status = 'CLOSED' WHERE plugin_processmaker_cases_id = {$locCase->getID()} AND del_index IN ($del_indexes)";
         $DB->query($query) or die("error updating del_thread_status in glpi_plugin_processmaker_tasks table" . $DB->error());
      }

      // set the plugin_processmaker_taskcategories_id
      $app_delegation = [];
      $query = "SELECT CONCAT(APPLICATION.APP_NUMBER, '-', APP_DELEGATION.DEL_INDEX) AS 'key', APP_DELEGATION.TAS_UID FROM APP_DELEGATION
               LEFT JOIN APPLICATION ON APPLICATION.APP_UID=APP_DELEGATION.APP_UID";
      foreach($PM_DB->request($query) as $row) {
         $app_delegation[$row['key']]=$row['TAS_UID'];
      }

      $taskcats = [];
      $query = "SELECT * FROM glpi_plugin_processmaker_taskcategories";
      foreach($DB->request($query) as $row) {
         $taskcats[$row['pm_task_guid']] = $row['id'];
      }

      $query = "SELECT * FROM glpi_plugin_processmaker_tasks";
      foreach($DB->request($query) as $row) {
         $key = $row['plugin_processmaker_cases_id']."-".$row['del_index'];
         if (isset($app_delegation[$key]) && isset($taskcats[$app_delegation[$key]])) {
            $DB->query("UPDATE glpi_plugin_processmaker_tasks SET plugin_processmaker_taskcategories_id={$taskcats[$app_delegation[$key]]} WHERE id={$row['id']}") or
               die("error updating plugin_processmaker_taskcategories_id in glpi_plugin_processmaker_tasks table" . $DB->error());
         }
      }
   }

   if (!arFieldExists("glpi_plugin_processmaker_taskcategories", "is_subprocess" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_taskcategories`
	               ALTER `processes_id` DROP DEFAULT;";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_taskcategories step 1" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_taskcategories`
                  CHANGE COLUMN `processes_id` `plugin_processmaker_processes_id` INT(11) NOT NULL AFTER `id`,
                  CHANGE COLUMN `start` `is_start` TINYINT(1) NOT NULL DEFAULT '0' AFTER `taskcategories_id`,
	               ADD COLUMN `is_subprocess` TINYINT(1) NOT NULL DEFAULT '0' AFTER `is_active`,
	               DROP INDEX `processes_id`,
	               ADD INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`);";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_taskcategories step 2" . $DB->error());
   }


   if (arFieldExists("glpi_plugin_processmaker_users", "password" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_users`
                	DROP COLUMN `password`;
               ";
      $DB->query($query) or die("error deleting password col from glpi_plugin_processmaker_users" . $DB->error());
   }

   if (!arFieldExists("glpi_plugin_processmaker_crontaskactions", "plugin_processmaker_cases_id" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
	               ADD COLUMN `plugin_processmaker_cases_id` INT(11) DEFAULT '0' AFTER `plugin_processmaker_caselinks_id`;" ;
      $DB->query($query) or die("error adding plugin_processmaker_cases_id col into glpi_plugin_processmaker_crontaskactions" . $DB->error());

      // data migration
      // before the 3.3.0 release there was one and only one case per item
      $query ="UPDATE `glpi_plugin_processmaker_crontaskactions`
                  LEFT JOIN `glpi_plugin_processmaker_cases` ON `glpi_plugin_processmaker_cases`.`itemtype` = `glpi_plugin_processmaker_crontaskactions`.`itemtype`
                                                               AND `glpi_plugin_processmaker_cases`.`items_id` = `glpi_plugin_processmaker_crontaskactions`.`items_id`
                  SET `glpi_plugin_processmaker_crontaskactions`.`plugin_processmaker_cases_id` = `glpi_plugin_processmaker_cases`.`id`;";
      $DB->query($query) or die("error migrating itemtype and items_id into a plugin_processmaker_cases_id col in glpi_plugin_processmaker_crontaskactions" . $DB->error());
      // end of migration

      $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
	               DROP COLUMN `itemtype`,
	               DROP COLUMN `items_id`;";
      $DB->query($query) or die("error deleting adding itemtype and items_id cols from glpi_plugin_processmaker_crontaskactions" . $DB->error());
   }

   return '3.3.0';
}
