<?php

function update_3_2_9_to_3_3_0(){
   global $DB;


   // Alter table plugin_processmaker_cases
   if (!arFieldExists("glpi_plugin_processmaker_cases", "plugin_processmaker_processes_id" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	               ALTER `id` DROP DEFAULT;
               ALTER TABLE `glpi_plugin_processmaker_cases`
	               ADD COLUMN `id` INT(11) NOT NULL AUTO_INCREMENT FIRST,
	               CHANGE COLUMN `itemtype` `itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket' AFTER `id`,
	               CHANGE COLUMN `id` `case_guid` VARCHAR(32) NOT NULL AFTER `items_id`,
	               CHANGE COLUMN `processes_id` `plugin_processmaker_processes_id` INT(11) NULL DEFAULT NULL AFTER `case_status`,
	               DROP INDEX `items`,
	               ADD INDEX `items` (`itemtype`, `items_id`),
	               ADD PRIMARY KEY (`id`),
	               ADD UNIQUE INDEX `case_guid` (`case_guid`),
	               ADD UNIQUE INDEX `case_num` (`case_num`),
	               ADD INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`);";

      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_cases table" . $DB->error());
   }

   if (!arTableExists("glpi_plugin_processmaker_profiles")) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes_profiles`
	               CHANGE COLUMN `processes_id` `plugin_processmaker_processes_id` INT(11) NOT NULL DEFAULT '0' AFTER `id`;
                RENAME TABLE `glpi_plugin_processmaker_processes_profiles` TO `glpi_plugin_processmaker_profiles`;";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_processes_profiles table" . $DB->error());
   }   

   if (!arFieldExists("glpi_plugin_processmaker_tasks", "plugin_processmaker_cases_id" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_tasks`
	               ALTER `itemtype` DROP DEFAULT;
               ALTER TABLE `glpi_plugin_processmaker_tasks`
	               CHANGE COLUMN `itemtype` `itemtype` VARCHAR(32) NOT NULL AFTER `id`,
	               ADD COLUMN `plugin_processmaker_cases_id` INT(11) NULL AFTER `case_id`,
	               DROP INDEX `case_id`,
	               ADD INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`, `del_index`);";
      $DB->query($query) or die("error normalizing glpi_plugin_processmaker_tasks table" . $DB->error());

      // transform case_id (=GUID) into plugin_processmaker_cases_id
      $query = "UPDATE `glpi_plugin_processmaker_tasks`
                LEFT JOIN `glpi_plugin_processmaker_cases` ON `glpi_plugin_processmaker_cases`.`case_guid` = `glpi_plugin_processmaker_tasks`.`case_id`
                SET `glpi_plugin_processmaker_tasks`.`plugin_processmaker_cases_id` = `glpi_plugin_processmaker_cases`.`id`;";
      $DB->query($query) or die("error transforming case_id into plugin_processmaker_cases_id in glpi_plugin_processmaker_tasks table" . $DB->error());

      $query = "ALTER TABLE `glpi_plugin_processmaker_tasks`
	               DROP COLUMN `case_id`;";
      $DB->query($query) or die("error deleting case_id column in glpi_plugin_processmaker_tasks table" . $DB->error());
   }

}
