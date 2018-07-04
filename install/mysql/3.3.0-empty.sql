/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping structure for table glpi.glpi_plugin_processmaker_caselinkactions
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_caselinkactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_caselinks_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caselinks_id_name` (`plugin_processmaker_caselinks_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_caselinks
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_caselinks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `externalapplication` text,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `is_externaldata` (`is_externaldata`),
  KEY `is_self` (`is_self`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_cases
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_cases` (
	`id` INT(11) NOT NULL,
	`itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket',
	`items_id` INT(11) NOT NULL,
	`entities_id` INT(11) NOT NULL DEFAULT '0',
	`name` MEDIUMTEXT NOT NULL DEFAULT '',
   `case_guid` VARCHAR(32) NOT NULL,
	`case_status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
	`plugin_processmaker_processes_id` INT(11) NULL DEFAULT NULL,
   `plugin_processmaker_cases_id` INT(11) NULL DEFAULT NULL,
	INDEX `items` (`itemtype`, `items_id`),
	INDEX `case_status` (`case_status`),
	PRIMARY KEY (`id`),
	UNIQUE INDEX `case_guid` (`case_guid`),
	INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`),
	INDEX `plugin_processmaker_cases_id` (`plugin_processmaker_cases_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_configs
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT 'ProcessMaker',
  `pm_server_URL` varchar(250) NOT NULL DEFAULT 'http://localhost/',
  `pm_workspace` varchar(50) NOT NULL DEFAULT 'workflow',
  `pm_admin_user` varchar(255) DEFAULT NULL,
  `pm_admin_passwd` varchar(255) DEFAULT NULL,
  `pm_theme` varchar(50) NOT NULL DEFAULT 'glpi_classic',
  `date_mod` timestamp NULL DEFAULT NULL,
  `taskcategories_id` int(11) DEFAULT NULL,
  `users_id` int(11) DEFAULT NULL,
  `pm_group_guid` varchar(32) DEFAULT NULL,
  `comment` text,
  `pm_dbserver_name` varchar(255) DEFAULT 'localhost',
  `pm_dbname` varchar(50) DEFAULT 'wf_workflow',
  `pm_dbserver_user` varchar(255) DEFAULT NULL,
  `pm_dbserver_passwd` varchar(255) DEFAULT NULL,
  `domain` varchar(50) DEFAULT '',
  `maintenance` tinyint(1) NOT NULL DEFAULT '0',
  `db_version` varchar(10) NOT NULL DEFAULT '3.3.0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_crontaskactions
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_crontaskactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_caselinks_id` int(11) DEFAULT NULL,
  `plugin_processmaker_cases_id` int(11) DEFAULT '0',
  `users_id` int(11) NOT NULL DEFAULT '0',
  `is_targettoclaim` tinyint(1) NOT NULL DEFAULT '0',
  `postdata` mediumtext,
  `logs_out` mediumtext,
  `state` int(11) NOT NULL,
  `date_mod` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_processes
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_processes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_guid` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `hide_case_num_title` tinyint(1) NOT NULL DEFAULT '0',
  `insert_task_comment` tinyint(1) NOT NULL DEFAULT '0',
  `comment` text,
  `taskcategories_id` int(11) DEFAULT NULL,
  `itilcategories_id` int(11) NOT NULL DEFAULT '0',
  `type` int(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets',
  `date_mod` timestamp NULL DEFAULT NULL,
  `project_type` varchar(50) NOT NULL DEFAULT 'classic',
  PRIMARY KEY (`id`),
  UNIQUE KEY `process_guid` (`process_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_processes_profiles
CREATE TABLE `glpi_plugin_processmaker_processes_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_processmaker_processes_id` int(11) NOT NULL,
  `profiles_id` int(11) NOT NULL,
  `entities_id` int(11) NOT NULL,
  `is_recursive` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `plugin_processmaker_processes_id_profiles_id_entities_id` (`plugin_processmaker_processes_id`, `profiles_id`, `entities_id`),
  KEY `entities_id` (`entities_id`),
  KEY `profiles_id` (`profiles_id`),
  KEY `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`),
  KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;


-- Dumping structure for table glpi.glpi_plugin_processmaker_taskcategories
CREATE TABLE `glpi_plugin_processmaker_taskcategories` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`plugin_processmaker_processes_id` INT(11) NOT NULL,
	`pm_task_guid` VARCHAR(32) NOT NULL,
	`taskcategories_id` INT(11) NOT NULL,
	`is_start` TINYINT(1) NOT NULL DEFAULT '0',
	`is_active` TINYINT(1) NOT NULL DEFAULT '1',
	`is_subprocess` TINYINT(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	UNIQUE INDEX `pm_task_guid` (`pm_task_guid`),
	UNIQUE INDEX `items` (`taskcategories_id`),
	INDEX `plugin_processmaker_processes_id` (`plugin_processmaker_processes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_tasks
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `items_id` int(11) NOT NULL,
  `itemtype` varchar(32) NOT NULL,
  `plugin_processmaker_cases_id` int(11) NOT NULL,
  `plugin_processmaker_taskcategories_id` int(11) NOT NULL,
  `del_index` int(11) NOT NULL,
  `del_thread` INT(11) NOT NULL,
  `del_thread_status` varchar(32) NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks` (`plugin_processmaker_cases_id`,`del_index`),
  UNIQUE KEY `items` (`itemtype`,`items_id`),
  KEY `del_thread_status` (`del_thread_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Dumping structure for table glpi.glpi_plugin_processmaker_users
CREATE TABLE IF NOT EXISTS `glpi_plugin_processmaker_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pm_users_id` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pm_users_id` (`pm_users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
