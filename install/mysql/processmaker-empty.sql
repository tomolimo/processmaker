-- Dumping structure for table glpi.glpi_plugin_processmaker_caselinkactions
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_caselinkactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_caselinks_id` INT UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caselinks_id_name` (`plugin_processmaker_caselinks_id`,`name`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_caselinks
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_caselinks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_externaldata` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:insert data from case,1:wait for external application to set datas',
  `is_self` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:use linked tickets, 1:use self',
  `sourcetask_guid` varchar(32) DEFAULT NULL,
  `targettask_guid` varchar(32) DEFAULT NULL,
  `targetprocess_guid` varchar(32) DEFAULT NULL,
  `targetdynaform_guid` varchar(32) DEFAULT NULL,
  `sourcecondition` text,
  `is_targettoclaim` tinyint(1) NOT NULL DEFAULT '0',
  `is_targettoreassign` TINYINT(1) NOT NULL DEFAULT '0',
  `is_targettoimpersonate` TINYINT(1) NOT NULL DEFAULT '0',
  `externalapplication` TEXT NULL,
  `is_synchronous` TINYINT(1) NOT NULL DEFAULT '0',
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `is_externaldata` (`is_externaldata`),
  KEY `is_self` (`is_self`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_cases
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_cases` (
	`id` INT UNSIGNED NOT NULL,
	`itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket',
	`items_id` INT UNSIGNED NOT NULL,
	`entities_id` INT UNSIGNED NOT NULL DEFAULT '0',
	`name` MEDIUMTEXT NOT NULL,
   `case_guid` VARCHAR(32) NOT NULL,
	`case_status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
	`plugin_processmaker_processes_id` INT UNSIGNED NULL DEFAULT NULL,
   `plugin_processmaker_cases_id` INT UNSIGNED NULL DEFAULT NULL,
	`date_creation` TIMESTAMP NULL DEFAULT NULL,
	`date_mod` TIMESTAMP NULL DEFAULT NULL,
	INDEX `items` (`itemtype`, `items_id`),
	INDEX `case_status` (`case_status`),
	PRIMARY KEY (`id`),
	UNIQUE INDEX `case_guid` (`case_guid`),
	INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`),
	INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_crontaskactions
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_crontaskactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_caselinks_id` INT UNSIGNED DEFAULT NULL,
  `plugin_processmaker_cases_id` INT UNSIGNED DEFAULT '0',
  `users_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `is_targettoclaim` tinyint(1) NOT NULL DEFAULT '0',
  `postdata` MEDIUMTEXT NULL DEFAULT NULL,
  `retcode` MEDIUMTEXT NULL DEFAULT NULL,
  `state` INT UNSIGNED NOT NULL,
  `formdata` MEDIUMTEXT NULL DEFAULT NULL,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ;


-- Dumping structure for table glpi.glpi_plugin_processmaker_processes
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_processes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_guid` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `hide_case_num_title` tinyint(1) NOT NULL DEFAULT '0',
  `insert_task_comment` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text,
  `taskcategories_id` INT UNSIGNED DEFAULT NULL,
  `itilcategories_id` INT UNSIGNED NOT NULL DEFAULT '0',
  `type` INT UNSIGNED NOT NULL DEFAULT '1' COMMENT 'Only used for self-service Tickets',
  `date_mod` timestamp NULL DEFAULT NULL,
  `project_type` varchar(50) NOT NULL DEFAULT 'classic',
	`is_change` tinyint(1) NOT NULL DEFAULT '0',
	`is_problem` tinyint(1) NOT NULL DEFAULT '0',
	`is_incident` tinyint(1) NOT NULL DEFAULT '0',
	`is_request` tinyint(1) NOT NULL DEFAULT '0',
	`maintenance` TINYINT(1) NOT NULL DEFAULT '0',
   `max_cases_per_item` INT UNSIGNED NOT NULL DEFAULT '0',
	`is_reassignreason_mandatory` TINYINT(1) NOT NULL DEFAULT '-2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `process_guid` (`process_guid`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_processes_profiles
CREATE TABLE `glpi_plugin_processmaker_processes_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_processes_id` INT UNSIGNED NOT NULL,
  `profiles_id` INT UNSIGNED NOT NULL,
  `entities_id` INT UNSIGNED NOT NULL,
  `is_recursive` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `plugin_processmaker_processes_id_profiles_id_entities_id` (`plugin_processmaker_processes_id`, `profiles_id`, `entities_id`),
  KEY `entities_id` (`entities_id`),
  KEY `profiles_id` (`profiles_id`),
  KEY `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`),
  KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB ;


-- Dumping structure for table glpi.glpi_plugin_processmaker_taskcategories
CREATE TABLE `glpi_plugin_processmaker_taskcategories` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`plugin_processmaker_processes_id` INT UNSIGNED NOT NULL,
	`pm_task_guid` VARCHAR(32) NOT NULL,
	`taskcategories_id` INT UNSIGNED NOT NULL,
	`is_start` TINYINT(1) NOT NULL DEFAULT '0',
	`is_active` TINYINT(1) NOT NULL DEFAULT '1',
	`is_subprocess` TINYINT(1) NOT NULL DEFAULT '0',
	`is_reassignreason_mandatory` TINYINT(1) NOT NULL DEFAULT '-2',
	PRIMARY KEY (`id`),
	UNIQUE INDEX `pm_task_guid` (`pm_task_guid`),
	UNIQUE INDEX `items` (`taskcategories_id`),
	INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_tasks
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `items_id` INT UNSIGNED NOT NULL,
  `itemtype` varchar(32) NOT NULL,
  `plugin_processmaker_cases_id` INT UNSIGNED NOT NULL,
  `plugin_processmaker_taskcategories_id` INT UNSIGNED NOT NULL,
  `del_index` INT UNSIGNED NOT NULL,
  `del_thread` INT UNSIGNED NOT NULL,
  `del_thread_status` varchar(32) NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks` (`plugin_processmaker_cases_id`,`del_index`),
  UNIQUE KEY `items` (`itemtype`,`items_id`),
  KEY `del_thread_status` (`del_thread_status`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_users
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pm_users_id` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pm_users_id` (`pm_users_id`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_documents
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_cases_id` INT UNSIGNED NOT NULL,
  `documents_id` INT UNSIGNED NULL,
  `guid` VARCHAR(32) NOT NULL COMMENT 'PM document GUID',
  `version` INT UNSIGNED NOT NULL COMMENT 'PM document version',
  `link` VARCHAR(512) NOT NULL COMMENT 'PM document link',
  `mime` VARCHAR(50) NOT NULL,
  `is_output` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `documents_id` (`documents_id`),
  UNIQUE INDEX `guid_version` (`guid`, `version`),
  INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`),
  INDEX `is_output` (`is_output`)
) ENGINE=InnoDB;


-- Dumping structure for table glpi.glpi_plugin_processmaker_reassignreasontranslations
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_reassignreasontranslations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`plugin_processmaker_taskcategories_id` INT UNSIGNED NOT NULL,
	`language` VARCHAR(10) NOT NULL,
	`label` TEXT NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `plugin_processmaker_taskcategories_id_language` (`plugin_processmaker_taskcategories_id`, `language`),
	INDEX `language` (`language`),
	INDEX `plugin_processmaker_taskcategories_id` (`plugin_processmaker_taskcategories_id`)
) ENGINE=InnoDB;


/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;