<?php

function update_to_3_2_8(){
   global $DB;

      if (arTableExists("glpi_plugin_processmaker_config")) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_config`
	                ADD COLUMN `date_mod` DATETIME NULL DEFAULT NULL AFTER `pm_theme`,
	                ADD COLUMN `comment` TEXT NULL AFTER `date_mod`;
                  RENAME TABLE `glpi_plugin_processmaker_config` TO `glpi_plugin_processmaker_configs`;";
         $DB->query($query) or die("error creating glpi_plugin_processmaker_configs" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_configs", "pm_dbserver_name" )) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
                     ADD COLUMN `pm_dbserver_name` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_group_guid`,
                     ADD COLUMN `pm_dbserver_user` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_dbserver_name`,
                     ADD COLUMN `pm_dbserver_passwd` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_dbserver_user`;";
         $DB->query($query) or die("error adding fields pm_dbserver_name, pm_dbserver_user, pm_dbserver_passwd to glpi_plugin_processmaker_configs" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_configs", "domain" )) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	               ADD COLUMN `domain` VARCHAR(50) NULL DEFAULT '' AFTER `pm_dbserver_passwd`;
               ";
         $DB->query($query) or die("error adding field domain to glpi_plugin_processmaker_configs" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_configs", "maintenance" )) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
               	ADD COLUMN `maintenance` TINYINT(1) NOT NULL DEFAULT '0' AFTER `domain`;
               ;";
         $DB->query($query) or die("error adding fields maintenance to glpi_plugin_processmaker_configs" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_configs", "pm_dbname" )) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	               ADD COLUMN `pm_dbname` VARCHAR(50) NULL DEFAULT 'wf_workflow' AFTER `pm_dbserver_name`;
               ;";
         $DB->query($query) or die("error adding field pm_dbname to glpi_plugin_processmaker_configs" . $DB->error());
         
         $DB->query("UPDATE glpi_plugin_processmaker_configs SET `pm_dbname` = CONCAT('wf_', `pm_workspace`) WHERE `id` = 1");
      }

      if (arTableExists("glpi_plugin_processmaker_profiles")) {
         $query = "DROP TABLE `glpi_plugin_processmaker_profiles` ;";
         $DB->query($query) or die("error dropping glpi_plugin_processmaker_profiles" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_cases", "processes_id")) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	               ADD COLUMN `processes_id` INT(11) NULL DEFAULT NULL;
               ";
         $DB->query($query) or die("error adding column processes_id into glpi_plugin_processmaker_cases" . $DB->error());
      } else {
         $flds = $DB->list_fields('glpi_plugin_processmaker_cases');
         if (strcasecmp( $flds['processes_id']['Type'], 'varchar(32)' ) == 0) {
            // required because autoload doesn't work for unactive plugin'
            include_once(GLPI_ROOT."/plugins/processmaker/inc/process.class.php");
            include_once(GLPI_ROOT."/plugins/processmaker/inc/case.class.php");
            $proc = new PluginProcessmakerProcess;
            $case = new PluginProcessmakerCase;
            foreach ($DB->request("SELECT * FROM glpi_plugin_processmaker_cases WHERE LENGTH( processes_id ) = 32") as $row) {
               $proc->getFromGUID( $row['processes_id'] );
               $case->update(array( 'id' => $row['id'], 'processes_id' => $proc->getID() ) );
            }
            $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	               CHANGE COLUMN `processes_id` `processes_id` INT(11) NULL DEFAULT NULL AFTER `case_status`;
                  ";
            $DB->query($query) or die("error converting column processes_id into INT(11) in glpi_plugin_processmaker_cases" . $DB->error());
         }
      }


      if (!arFieldExists('glpi_plugin_processmaker_users', 'password')) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_users`
	            ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
               ADD COLUMN `password` VARCHAR(32) NULL DEFAULT NULL AFTER `pm_users_id`,
               ADD PRIMARY KEY (`id`);
               ";
         $DB->query($query) or die("error adding column 'password' to glpi_plugin_processmaker_users" . $DB->error());

         // also need to change text of tasks for tasks linked to cases
         $query = "UPDATE glpi_tickettasks SET content=REPLACE(content,'##_PluginProcessmakerCases\$processmakercases','##_PluginProcessmakerCase\$processmakercases')
               WHERE glpi_tickettasks.id IN (SELECT items_id FROM glpi_plugin_processmaker_tasks WHERE itemtype='TicketTask') AND content LIKE '%_PluginProcessmakerCases\$processmakercases%'";
         $DB->query($query) or die("error updating TicketTask" . $DB->error());
      }

      if (arFieldExists('glpi_plugin_processmaker_users', 'glpi_users_id')) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_users`
	               ALTER `glpi_users_id` DROP DEFAULT,
                  DROP PRIMARY KEY,
	               DROP COLUMN `id`,
	               DROP INDEX `glpi_users_id`;
                                 ";
         $DB->query($query) or die("error droping 'defaults' from 'glpi_users_id' to glpi_plugin_processmaker_users" . $DB->error());

         $query = "ALTER TABLE `glpi_plugin_processmaker_users`
	               CHANGE COLUMN `glpi_users_id` `id` INT(11) NOT NULL AUTO_INCREMENT FIRST,
                  ADD PRIMARY KEY (`id`);
               ";
         $DB->query($query) or die("error renaming 'glpi_users_id' into 'id' to glpi_plugin_processmaker_users" . $DB->error());
      }


      if (arFieldExists( 'glpi_plugin_processmaker_processes', 'is_helpdeskvisible')) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
                    CHANGE COLUMN `is_helpdeskvisible` `is_helpdeskvisible_notusedanymore` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Not used any more since version 2.2' AFTER `name`;";
         $DB->query($query);
      }

      if (!arFieldExists( 'glpi_plugin_processmaker_processes', 'itilcategories_id')) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	                ADD COLUMN `itilcategories_id` INT(11) NOT NULL DEFAULT '0',
	                ADD COLUMN `type` INT(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets';";

         $DB->query($query) or die("error adding columns 'itilcategories_id' and 'type' to glpi_plugin_processmaker_processes" . $DB->error());
      }

      if (!arFieldExists( 'glpi_plugin_processmaker_processes', 'project_type')) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	                ADD COLUMN `project_type` VARCHAR(50) NOT NULL DEFAULT 'classic';";

         $DB->query($query) or die("error adding columns 'project_type' to glpi_plugin_processmaker_processes" . $DB->error());
      }

      if (!arFieldExists('glpi_plugin_processmaker_taskcategories', 'is_active'))  {
         $query = "ALTER TABLE `glpi_plugin_processmaker_taskcategories`
	               ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT '1' AFTER `start`;" ;
         $DB->query($query) or die("error adding field is_active to glpi_plugin_processmaker_taskcategories table" . $DB->error());
      }


      if (arFieldExists('glpi_plugin_processmaker_crontaskactions', 'postdatas'))  {
         $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
               CHANGE COLUMN `postdatas` `postdata` MEDIUMTEXT NULL DEFAULT NULL AFTER `toclaim`;";
         $DB->query($query) or die("error changing 'postdatas' from glpi_plugin_processmaker_crontaskactions table" . $DB->error());
      }

      if (!arFieldExists('glpi_plugin_processmaker_crontaskactions', 'logs_out'))  {
         $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
	            ADD COLUMN `logs_out` MEDIUMTEXT NULL AFTER `postdata`;";
         $DB->query($query) or die("error adding 'logs_out' field into glpi_plugin_processmaker_crontaskactions table" . $DB->error());
      }

      if (!arFieldExists("glpi_plugin_processmaker_crontaskactions", "is_targettoclaim")) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_crontaskactions`
	         CHANGE COLUMN `toclaim` `is_targettoclaim` TINYINT(1) NOT NULL DEFAULT '0' AFTER `users_id`;";
         $DB->query($query) or die("error renaming toclaim in glpi_plugin_processmaker_crontaskactions" . $DB->error());
      }


      //if (!arFieldExists("glpi_plugin_processmaker_caselinks", "plugin_processmaker_taskcategories_id_source")) {
      //   $query = "ALTER TABLE `glpi_plugin_processmaker_caselinks`
      //             ADD COLUMN `plugin_processmaker_taskcategories_id_source` INT(11) NULL DEFAULT NULL AFTER `sourcetask_guid`,
      //             ADD COLUMN `plugin_processmaker_taskcategories_id_target` INT(11) NULL DEFAULT NULL AFTER `targettask_guid`,
      //             ADD COLUMN `plugin_processmaker_processes_id` INT(11) NULL DEFAULT NULL AFTER `targetprocess_guid`;";
      //   $DB->query($query) or die("error adding col plugin_processmaker_taskcategories_id_source to glpi_plugin_processmaker_caselinks" . $DB->error());

      //   $query = "UPDATE glpi_plugin_processmaker_caselinks AS pm_cl
      //             LEFT JOIN glpi_plugin_processmaker_taskcategories AS pm_tcsource ON pm_tcsource.pm_task_guid=pm_cl.sourcetask_guid
      //             LEFT JOIN glpi_plugin_processmaker_taskcategories AS pm_tctarget ON pm_tctarget.pm_task_guid=pm_cl.targettask_guid
      //             LEFT JOIN glpi_plugin_processmaker_processes AS pm_pr ON pm_pr.process_guid=pm_cl.targetprocess_guid
      //             SET pm_cl.plugin_processmaker_taskcategories_id_source = pm_tcsource.id,
      //                 pm_cl.plugin_processmaker_taskcategories_id_target = pm_tctarget.id,
      //                 pm_cl.plugin_processmaker_processes_id = pm_pr.id;";
      //   $DB->query($query) or die("error migrating data into col plugin_processmaker_taskcategories_id_source in glpi_plugin_processmaker_caselinks" . $DB->error());

      //   $query = "ALTER TABLE `glpi_plugin_processmaker_caselinks`
      //             DROP COLUMN `sourcetask_guid`,
      //             DROP COLUMN `targettask_guid`,
      //             DROP COLUMN `targetprocess_guid`;";
      //   $DB->query($query) or die("error dropping col plugin_processmaker_taskcategories_id_source from glpi_plugin_processmaker_caselinks" . $DB->error());
      //}

      if (!arFieldExists("glpi_plugin_processmaker_caselinks", "is_targettoclaim")) {
         $query = "ALTER TABLE `glpi_plugin_processmaker_caselinks`
	            CHANGE COLUMN `targettoclaim` `is_targettoclaim` TINYINT(1) NOT NULL DEFAULT '0' AFTER `sourcecondition`;" ;
         $DB->query($query) or die("error renaming targettoclaim in glpi_plugin_processmaker_caselinks" . $DB->error());
      }

   //if( !arTableExists('glpi_plugin_processmaker_selfservicedrafts')){
   //   $query = "CREATE TABLE `glpi_plugin_processmaker_selfservicedrafts` (
   //                 `id` INT(11) NOT NULL AUTO_INCREMENT,
   //                 `users_id` INT(11) NOT NULL,
   //                 `plugin_processmaker_processes_id` INT(11) NOT NULL,
   //                 `url` TEXT NOT NULL,
   //                 PRIMARY KEY (`id`),
   //                 INDEX `users_id` (`users_id`)
   //              )
   //              COLLATE='utf8_general_ci'
   //              ENGINE=InnoDB
   //              ;" ;
   //   $DB->query($query) or die("error creating glpi_plugin_processmaker_selfservicedrafts" . $DB->error());
   //}

   return '3.2.8';
}