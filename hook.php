<?php

include_once 'inc/processmaker.class.php';

function plugin_processmaker_MassiveActions($type) {
   global $LANG;

   switch ($type) {
      case 'PluginProcessmakerProcess' :
         if (plugin_processmaker_haveRight('config', UPDATE )) {
            return array('plugin_processmaker_taskrefresh' => $LANG['processmaker']['config']['refreshtasklist']);
         }
          break;
      case 'PluginProcessmakerProcess_Profile' :
         if (plugin_processmaker_haveRight('config', UPDATE )) {
            return array('purge' => $LANG['processmaker']['process']['profile']);
         }
         break;
   }
   return array();
}


function plugin_processmaker_MassiveActionsDisplay($options) {
   global $LANG;

   switch ($options['itemtype']) {
      case 'PluginProcessmakerProcess' :
         switch ($options['action']) {
            // No case for add_document : use GLPI core one
            case "plugin_processmaker_taskrefresh" :
               echo "<input type='submit' name='massiveaction' class='submit' ".
                     "value='".$LANG['buttons'][2]."'>";
               break;

         }
         break;

   }
   return "";
}


function plugin_processmaker_MassiveActionsProcess($data) {

   switch ($data['action']) {

      case "plugin_processmaker_taskrefresh" :
         if ($data['itemtype'] == 'PluginProcessmakerProcess') {
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $process = new PluginProcessmakerProcess;
                  $process->refreshTasks( array( 'id' => $key ) );

               }
            }
         }
          break;
      case 'plugin_processmaker_process_profile_delete' :
         if ($data['itemtype'] == 'PluginProcessmakerProcess_Profile') {
            foreach ($data["item"] as $key => $val) {
               if ($val == 1) {
                  $process_profile = new PluginProcessmakerProcess_Profile;
                  $process_profile->delete( array( 'id' => $key ), true );

               }
            }
         }
          break;

   }
}

/**
 * Summary of plugin_processmaker_install
 *      Creates tables and initializes tasks, "GLPI Requesters" group
 *      and so on
 * @return true or die!
 */
function plugin_processmaker_install() {
   global $DB;

   if (TableExists("glpi_plugin_processmaker_config")) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_config`
	                ADD COLUMN `date_mod` DATETIME NULL DEFAULT NULL AFTER `pm_theme`,
	                ADD COLUMN `comment` TEXT NULL AFTER `date_mod`;
                  RENAME TABLE `glpi_plugin_processmaker_config` TO `glpi_plugin_processmaker_configs`;";
        $DB->query($query) or die("error creating glpi_plugin_processmaker_configs" . $DB->error());
   } else if (!TableExists("glpi_plugin_processmaker_configs")) {
      $query = "  CREATE TABLE `glpi_plugin_processmaker_configs` (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `name` VARCHAR(50) NOT NULL DEFAULT 'ProcessMaker',
                  `pm_server_URL` VARCHAR(250) NOT NULL DEFAULT 'http://localhost/',
                  `pm_workspace` VARCHAR(50) NOT NULL DEFAULT 'workflow',
                  `pm_admin_user` VARCHAR(255) NULL DEFAULT NULL,
                  `pm_admin_passwd` VARCHAR(255) NULL DEFAULT NULL,
                  `pm_theme` VARCHAR(50) NOT NULL DEFAULT 'glpi_classic',
                  `date_mod` DATETIME NULL DEFAULT NULL,
                  `taskcategories_id` INT(11) NULL ,
                  `users_id` INT(11) NULL DEFAULT NULL,
                  `pm_group_guid` VARCHAR(32) NULL DEFAULT NULL,
                  `comment` TEXT NULL,
                  `pm_dbserver_name` VARCHAR(255) NULL DEFAULT 'localhost',
                  `pm_dbserver_user` VARCHAR(255) NULL DEFAULT NULL,
                  `pm_dbserver_passwd` VARCHAR(255) NULL DEFAULT NULL,
                  `domain` VARCHAR(50) NULL DEFAULT '',
                  `maintenance` TINYINT(1) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`)
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;
			";

      $DB->query($query) or die("error creating glpi_plugin_processmaker_configs" . $DB->error());

      //add a default configuration
      $query = "
         INSERT INTO `glpi_plugin_processmaker_configs` (`id`) VALUES (1);
      ";
      $DB->query( $query ) or die("error creating default record in glpi_plugin_processmaker_configs" . $DB->error());
   }

   if (!FieldExists("glpi_plugin_processmaker_configs", "pm_dbserver_name" )) {
        $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
                     ADD COLUMN `pm_dbserver_name` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_group_guid`,
                     ADD COLUMN `pm_dbserver_user` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_dbserver_name`,
                     ADD COLUMN `pm_dbserver_passwd` VARCHAR(255) NULL DEFAULT NULL AFTER `pm_dbserver_user`;";
         $DB->query($query) or die("error adding fields pm_dbserver_name, pm_dbserver_user, pm_dbserver_passwd to glpi_plugin_processmaker_configs" . $DB->error());
   }

   if (!FieldExists("glpi_plugin_processmaker_configs", "maintenance" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
               	ADD COLUMN `maintenance` TINYINT(1) NOT NULL DEFAULT '0' AFTER `pm_dbserver_passwd`;
               ;";
      $DB->query($query) or die("error adding field maintenance to glpi_plugin_processmaker_configs" . $DB->error());
   }

   if (!FieldExists("glpi_plugin_processmaker_configs", "domain" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
	               ADD COLUMN `domain` VARCHAR(50) NULL DEFAULT '' AFTER `pm_dbserver_passwd`;
               ";
      $DB->query($query) or die("error adding field domain to glpi_plugin_processmaker_configs" . $DB->error());
   }

   if (!FieldExists("glpi_plugin_processmaker_configs", "maintenance" )) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_configs`
               	ADD COLUMN `maintenance` TINYINT(1) NOT NULL DEFAULT '0' AFTER `domain`;
               ;";
      $DB->query($query) or die("error adding fields maintenance to glpi_plugin_processmaker_configs" . $DB->error());
   }

   if (TableExists("glpi_plugin_processmaker_profiles")) {
      $query = "DROP TABLE `glpi_plugin_processmaker_profiles` ;";
      $DB->query($query) or die("error dropping glpi_plugin_processmaker_profiles" . $DB->error());
   }

   if (!TableExists("glpi_plugin_processmaker_cases")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_cases` (
	                        `id` VARCHAR(32) NOT NULL,
	                        `items_id` INT(11) NOT NULL,
	                        `itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket',
	                        `case_num` INT(11) NOT NULL,
	                        `case_status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
                           `processes_id` INT(11) NULL DEFAULT NULL,
	                        UNIQUE INDEX `items` (`itemtype`, `items_id`),
	                        INDEX `case_status` (`case_status`)
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;
			";

      $DB->query($query) or die("error creating glpi_plugin_processmaker_cases" . $DB->error());
   }

   if (!FieldExists("glpi_plugin_processmaker_cases", "processes_id")) {
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
            $proc->getFromDBbyExternalID( $row['processes_id'] );
            $case->update(array( 'id' => $row['id'], 'processes_id' => $proc->getID() ) );
         }
         $query = "ALTER TABLE `glpi_plugin_processmaker_cases`
	               CHANGE COLUMN `processes_id` `processes_id` INT(11) NULL DEFAULT NULL AFTER `case_status`;
                  ";
         $DB->query($query) or die("error converting column processes_id into INT(11) in glpi_plugin_processmaker_cases" . $DB->error());
      }
   }

   if (!TableExists("glpi_plugin_processmaker_tasks")) {
        $query = "CREATE TABLE `glpi_plugin_processmaker_tasks` (
	                        `id` INT(11) NOT NULL AUTO_INCREMENT,
	                        `items_id` INT(11) NOT NULL,
	                        `itemtype` VARCHAR(32) NOT NULL,
	                        `case_id` VARCHAR(32) NOT NULL,
	                        `del_index` INT(11) NOT NULL,
	                        PRIMARY KEY (`id`),
	                        UNIQUE INDEX `case_id` (`case_id`, `del_index`),
	                        UNIQUE INDEX `items` (`itemtype`, `items_id`)
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;
			";

        $DB->query($query) or die("error creating glpi_plugin_processmaker_tasks" . $DB->error());
   }

   if (!TableExists("glpi_plugin_processmaker_users")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_users` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `pm_users_id` VARCHAR(32) NOT NULL ,
	               `password` VARCHAR(32) NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
            	UNIQUE INDEX `pm_users_id` (`pm_users_id`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB;
			";

      $DB->query($query) or die("error creating glpi_plugin_processmaker_users" . $DB->error());
   }

   if (!FieldExists('glpi_plugin_processmaker_users', 'password')) {
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

   if (FieldExists('glpi_plugin_processmaker_users', 'glpi_users_id')) {
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

   if (!TableExists("glpi_plugin_processmaker_processes")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_processes` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
            `process_guid` VARCHAR(32) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT '0',
            `hide_case_num_title` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_task_comment` TINYINT(1) NOT NULL DEFAULT '0',
            `comment` TEXT NULL,
            `taskcategories_id` INT(11) NULL ,
            `itilcategories_id` INT(11) NOT NULL DEFAULT '0',
            `type` INT(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets',
            `date_mod` DATETIME NULL DEFAULT NULL,
            `project_type` VARCHAR(50) NOT NULL DEFAULT 'classic',
            PRIMARY KEY (`id`),
            UNIQUE INDEX `process_guid` (`process_guid`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB;
			";

      $DB->query($query) or die("error creating glpi_plugin_processmaker_processes" . $DB->error());
   }

   if (FieldExists( 'glpi_plugin_processmaker_processes', 'is_helpdeskvisible')) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
                    CHANGE COLUMN `is_helpdeskvisible` `is_helpdeskvisible_notusedanymore` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Not used any more since version 2.2' AFTER `name`;";
      $DB->query($query);
   }

   if (!FieldExists( 'glpi_plugin_processmaker_processes', 'itilcategories_id')) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	                ADD COLUMN `itilcategories_id` INT(11) NOT NULL DEFAULT '0',
	                ADD COLUMN `type` INT(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets';";

      $DB->query($query) or die("error adding columns 'itilcategories_id' and 'type' to glpi_plugin_processmaker_processes" . $DB->error());
   }

   if (!FieldExists( 'glpi_plugin_processmaker_processes', 'project_type')) {
      $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	                ADD COLUMN `project_type` VARCHAR(50) NOT NULL DEFAULT 'classic';";

      $DB->query($query) or die("error adding columns 'project_type' to glpi_plugin_processmaker_processes" . $DB->error());
   }

   if (!TableExists("glpi_plugin_processmaker_processes_profiles")) { // since version 2.2
      $query = "CREATE TABLE `glpi_plugin_processmaker_processes_profiles` (
	               `id` INT(11) NOT NULL AUTO_INCREMENT,
	               `processes_id` INT(11) NOT NULL DEFAULT '0',
	               `profiles_id` INT(11) NOT NULL DEFAULT '0',
	               `entities_id` INT(11) NOT NULL DEFAULT '0',
	               `is_recursive` TINYINT(1) NOT NULL DEFAULT '1',

	               PRIMARY KEY (`id`),
	               INDEX `entities_id` (`entities_id`),
	               INDEX `profiles_id` (`profiles_id`),
	               INDEX `processes_id` (`processes_id`),
	               INDEX `is_recursive` (`is_recursive`)
               )
         COLLATE='utf8_unicode_ci'
         ENGINE=InnoDB;";
      $DB->query($query) or die("error creating glpi_plugin_processmaker_processes_profiles" . $DB->error());

   }

   if (!TableExists("glpi_plugin_processmaker_taskcategories")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_taskcategories` (
	               `id` INT(11) NOT NULL AUTO_INCREMENT,
	               `processes_id` INT(11) NOT NULL,
	               `pm_task_guid` VARCHAR(32) NOT NULL,
	               `taskcategories_id` INT(11) NOT NULL,
	               `start` BIT(1) NOT NULL DEFAULT b'0',
	               PRIMARY KEY (`id`),
	               UNIQUE INDEX `pm_task_guid` (`pm_task_guid`),
	               UNIQUE INDEX `items` (`taskcategories_id`),
	               INDEX `processes_id` (`processes_id`)
               )
               COLLATE='utf8_general_ci'
               ENGINE=InnoDB
               ;
		";

      $DB->query($query) or die("error creating glpi_plugin_processmaker_taskcategories" . $DB->error());

   }

   if (!TableExists("glpi_plugin_processmaker_crontaskactions")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_crontaskactions` (
                     `id`        INT(11) NOT NULL AUTO_INCREMENT,
                     `plugin_processmaker_caselinks_id` INT(11) NULL DEFAULT NULL,
                     `itemtype`  VARCHAR(100) NOT NULL,
                     `items_id`  INT(11) NOT NULL DEFAULT '0',
                     `users_id`  INT(11) NOT NULL DEFAULT '0',
	                  `toclaim`   TINYINT(1) NOT NULL DEFAULT '0',
                     `postdatas` TEXT NULL DEFAULT NULL,
                     `state`     INT(11) NOT NULL ,
	                  `date_mod`  DATETIME NULL DEFAULT NULL,
                     PRIMARY KEY (`id`)
                  )
                  COLLATE='utf8_general_ci'
                  ENGINE=InnoDB;";
      $DB->query($query) or die("error creating glpi_plugin_processmaker_crontaskactions" . $DB->error());
   }

   if (!TableExists("glpi_plugin_processmaker_caselinks")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_caselinks` (
	                  `id` INT(11) NOT NULL AUTO_INCREMENT,
	                  `name` VARCHAR(255) NOT NULL,
	                  `is_active` TINYINT(1) NOT NULL DEFAULT '0',
	                  `is_externaldata` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0:insert data from case,1:wait for external application to set datas',
	                  `is_self` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0:use linked tickets, 1:use self',
	                  `sourcetask_guid` VARCHAR(32) NULL DEFAULT NULL,
	                  `targettask_guid` VARCHAR(32) NULL DEFAULT NULL,
	                  `targetprocess_guid` VARCHAR(32) NULL DEFAULT NULL,
	                  `targetdynaform_guid` VARCHAR(32) NULL DEFAULT NULL,
	                  `sourcecondition` TEXT NULL,
	                  `targettoclaim` TINYINT(1) NOT NULL DEFAULT '0',
                  	`externalapplication` TEXT NULL,
	                  `date_mod` DATETIME NULL DEFAULT NULL,
	                  PRIMARY KEY (`id`),
	                  INDEX `is_active` (`is_active`),
	                  INDEX `is_externaldata` (`is_externaldata`),
	                  INDEX `is_self` (`is_self`)
                  )
                  COLLATE='utf8_general_ci'
                  ENGINE=InnoDB;";
      $DB->query($query) or die("error creating glpi_plugin_processmaker_caselinks" . $DB->error());
   }

   if (!TableExists("glpi_plugin_processmaker_caselinkactions")) {
      $query = "CREATE TABLE `glpi_plugin_processmaker_caselinkactions` (
	                  `id` INT(11) NOT NULL AUTO_INCREMENT,
	                  `plugin_processmaker_caselinks_id` INT(11) NULL DEFAULT NULL,
	                  `name` VARCHAR(255) NOT NULL,
	                  `value` TEXT NULL,
	                  PRIMARY KEY (`id`),
	                  UNIQUE INDEX `caselinks_id_name` (`plugin_processmaker_caselinks_id`, `name`)
                  )
                  COLLATE='utf8_general_ci'
                  ENGINE=InnoDB
                  ;";
      $DB->query($query) or die("error creating glpi_plugin_processmaker_caselinkactions" . $DB->error());
   }

    //if( !TableExists('glpi_plugin_processmaker_selfservicedrafts')){
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

    // To be called for each task managed by the plugin
    // task in class
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmusers', DAY_TIMESTAMP, array( 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL));
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmorphancases', DAY_TIMESTAMP, array('param' => 10, 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL));
   CronTask::Register('PluginProcessmakerProcessmaker', 'pmtaskactions', MINUTE_TIMESTAMP, array('state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL));

   // required because autoload doesn't work for unactive plugin'
   include_once(GLPI_ROOT."/plugins/processmaker/inc/profile.class.php");
   PluginProcessmakerProfile::createAdminAccess($_SESSION['glpiactiveprofile']['id']);

   return true;
}

function plugin_processmaker_uninstall() {
   global $DB;

   CronTask::Unregister('PluginProcessmakerProcessmaker');

   return true;
}


function plugin_processmaker_getAddSearchOptions($itemtype) {
    global $LANG;

    $sopt = array();
   if ($itemtype == 'Ticket') {
      $sopt[10001]['table']     = 'glpi_plugin_processmaker_cases';
      $sopt[10001]['field']     = 'case_status';
      //$sopt[1001]['linkfield'] = 'id';
      $sopt[10001]['massiveaction'] = false;
      $sopt[10001]['name']      = $LANG['processmaker']['search']['case'].' - '.$LANG['processmaker']['search']['status'];
      $sopt[10001]['datatype']       = 'text';
      $sopt[10001]['forcegroupby'] = true;
      //$sopt[10001]['searchtype'] = 'equals';

      //$sopt[1001]['itemlink_type'] = 'PluginProcessmakerTicketcase';

      //$sopt[1001]['table']          = 'glpi_plugin_processmaker_ticketcase';
      //$sopt[1001]['field']          = 'case_status';
      //$sopt[1001]['massiveaction']  = false;
      //$sopt[1001]['name']           = 'Case - Status';
      //$sopt[1001]['forcegroupby']   = true;
      //$sopt[1001]['datatype']       = 'itemlink';
      // $sopt[1001]['itemlink_type']  = 'PluginProcessmakerProcessmaker';
      //$sopt[1001]['joinparams']     = array('beforejoin'
      //                                       => array('table'      => 'glpi_plugin_processmaker_ticketcase',
      //                                                'linkfield' => 'ticket_id'));

      //$sopt[1001]['joinparams']['jointype'] = "itemtype_id";
      //$sopt[1001]['pfields_type']  = ;
   }
    return $sopt;
}

function plugin_processmaker_addLeftJoin($type,$ref_table,$new_table,$linkfield,&$already_link_tables) {

   switch ($type) {

      case 'Ticket':
         switch ($new_table) {

            case "glpi_plugin_processmaker_cases" :
               $out= " LEFT JOIN `glpi_plugin_processmaker_cases`
                        ON (`$ref_table`.`id` = `glpi_plugin_processmaker_cases`.`items_id` AND `glpi_plugin_processmaker_cases`.`itemtype` like 'Ticket') ";
               return $out;

         }

      return "";

   }

    return "";
}

/**
 * Summary of plugin_pre_item_update_processmaker
 * @param CommonITILObject $parm is an object
 * @return void
 */
function plugin_pre_item_update_processmaker(CommonITILObject $parm) {
    global $DB;

   if (isset($_SESSION['glpiname']) && $parm->getType() == 'Ticket') {
      $locCase = new PluginProcessmakerCase;

      $itemId = $parm->getID();
      $itemType = $parm->getType();

      if ($locCase->getCaseFromItemTypeAndItemId( $itemType, $itemId )) {
         $locPM = new PluginProcessmakerProcessmaker;
         $locPM->login();

         // case is existing for this item
         // beware to empty injection when not modified!!!
         $locVar = array( );
         foreach ($parm->input as $key => $val) {
            switch ($key) {
               case 'global_validation' :
                  $locVar[ 'GLPI_TICKET_GLOBAL_VALIDATION' ] = $val;
                   break;
               case 'itilcategories_id' :
                  $locVar[ 'GLPI_ITEM_ITIL_CATEGORY_ID' ] = $val;
                  break;
               case 'due_date' :
                  $locVar[ 'GLPI_TICKET_DUE_DATE' ] = $val;
                   break;
               case 'urgency' :
                  $locVar[ 'GLPI_TICKET_URGENCY' ] = $val;
                   break;
               case 'impact' :
                  $locVar[ 'GLPI_ITEM_IMPACT' ] = $val;
                 break;
               case 'priority' :
                  $locVar[ 'GLPI_ITEM_PRIORITY' ] = $val;
                 break;
            }
         }

         $pmResponse = $locPM->sendVariables( $locCase->getID(), $locVar);
      }
   }

}

/**
 * Summary of plugin_item_update_processmaker_satisfaction
 * inject satisfaction survey into case
 * @param mixed $parm is the object
 */
function plugin_item_update_processmaker_satisfaction($parm) {
   global $DB, $GLOBALS;

   $locCase = new PluginProcessmakerCase;
   if ($locCase->getCaseFromItemTypeAndItemId( 'Ticket', $parm->fields['tickets_id'] )) {
      // case is existing for this item

      $locPM = new PluginProcessmakerProcessmaker;
      $locPM->login();

      $pmResponse = $locPM->sendVariables( $locCase->getID(), array(
                                                                     'GLPI_SATISFACTION_QUALITY' => $parm->fields['satisfaction']
                                                                     ));
   }
}

/**
 * Summary of plugin_pre_item_purge_processmaker
 * @param mixed $parm is the object
 */
function plugin_pre_item_purge_processmaker ( $parm ) {

   if ($parm->getType() == 'Ticket_User' && is_array( $parm->fields ) && isset( $parm->fields['type'] )  && $parm->fields['type'] == 2) {
      $itemId = $parm->fields['tickets_id'];
      $itemType = 'Ticket';
      $technicians = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, 2 ); // 2 for technicians

      if (PluginProcessmakerCase::getCaseIdFromItemTypeAndItemId( $itemType, $itemId ) && count($technicians) == 1) {
         $parm->input = null; // to cancel deletion of the last tech in the ticket
      }
   }
}

/**
 * Summary of plugin_item_purge_processmaker
 * @param mixed $parm is the object
 */
function plugin_item_purge_processmaker($parm) {
    global $DB;

   if ($parm->getType() == 'Ticket_User' && is_array( $parm->fields ) && isset( $parm->fields['type'] )  && $parm->fields['type'] == 2) {

      // We just deleted a tech from this ticket then we must if needed "de-assign" the tasks assigned to this tech
      // and re-assign them to the first tech in the list !!!!

      $locCase = new PluginProcessmakerCase;

      $itemId = $parm->fields['tickets_id'];
      $itemType = 'Ticket';

      if ($locCase->getCaseFromItemTypeAndItemId( $itemType, $itemId )) {
         // case is existing for this item
         $technicians = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, 2 ); // 2 for technicians
         $locPM = new PluginProcessmakerProcessmaker;
         $locPM->login();
         $locVars = array( 'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $technicians[0]['glpi_id'],
                             'GLPI_TICKET_TECHNICIAN_PM_ID' => $technicians[0]['pm_id'] );

         // and we must find all tasks assigned to this former user and re-assigned them to new user (if any :))!
         $caseInfo = $locPM->getCaseInfo( $locCase->getID() );
         if ($caseInfo !== false) {
            $locPM->sendVariables( $locCase->getID( ), $locVars );
            // need to get info on the thread of the GLPI current user
            // we must retreive currentGLPI user from this array
            $GLPICurrentPMUserId = PluginProcessmakerUser::getPMUserId( $parm->fields['users_id'] );
            if (isset( $caseInfo->currentUsers ) && is_array( $caseInfo->currentUsers )) {
               foreach ($caseInfo->currentUsers as $caseUser) {
                  if ($caseUser->userId == $GLPICurrentPMUserId && in_array( $caseUser->delThreadStatus, array('DRAFT', 'OPEN', 'PAUSE' ) )) {
                     $pmResponse = $locPM->reassignCase( $locCase->getID(), $caseUser->delIndex, $GLPICurrentPMUserId, $technicians[0]['pm_id'] );
                     // now should managed GLPI Tasks previously assigned to the $GLPICurrentPMUserId
                     if ($pmResponse->status_code == 0) {
                        // ATTENTION: should be aware of: ticket tech == task tech
                        // In this particular flow due to 'Change Management'

                        // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks
                        // we need the delindex of the current glpi task, and the delindex of the new one
                        // search for new delindex
                        $newCaseInfo = $locPM->getCaseInfo( $locCase->getID() );
                        $newDelIndex = 0;
                        foreach ($newCaseInfo->currentUsers as $newCaseUser) {
                           if ($newCaseUser->taskId == $caseUser->taskId && $newCaseUser->delThread == $caseUser->delThread) {
                              $newDelIndex = $newCaseUser->delIndex;
                              break;
                           }
                        }
                        $locPM->reassignTask( $locCase->getID(), $caseUser->delIndex, $newDelIndex, $technicians[0]['glpi_id'] );
                     }
                  }
               }
            }
         }

      }
   }
}

function plugin_processmaker_post_init() {
   global $PM_DB;
   $PM_DB = new PluginProcessmakerDB;
}


function plugin_processmaker_giveItem($itemtype,$ID,$data,$num) {

   return;
}


/**
   * Summary of plugin_item_add_update_processmaker_tasks
   * @param mixed $parm
   */
function plugin_item_update_processmaker_tasks($parm) {
   global $DB, $CFG_GLPI;

   // we need to test if a specific case is completed, and if so
   // we should complete the linked cases (via linked tickets)
   $pmTaskCat = new PluginProcessmakerTaskCategory;
   if ($pmTaskCat->getFromDBbyCategory( $parm->fields['taskcategories_id'] )
            && in_array( 'state', $parm->updates )
            && $parm->input['state'] == 2) {  // the task has just been set to DONE state

      $itemtype = str_replace( 'Task', '', $parm->getType() );

      foreach ($DB->request( 'glpi_plugin_processmaker_caselinks', "is_active = 1 AND sourcetask_guid='".$pmTaskCat->fields['pm_task_guid']."'") as $targetTask) {

         $srcCaseId = PluginProcessmakerProcessmaker::getCaseIdFromItem( $itemtype, $parm->fields['tickets_id']);

         // Must check the condition
         $casevariables = array();

         $matches = array();
         if (preg_match_all( "/@@(\w+)/u", $targetTask['sourcecondition'], $matches  )) {
            $casevariables = $matches[1];
         }

         $targetTask['targetactions'] = array(); // empty array by default
         foreach ($DB->request( 'glpi_plugin_processmaker_caselinkactions', 'plugin_processmaker_caselinks_id = '.$targetTask['id']) as $actionvalue) {
            $targetTask['targetactions'][$actionvalue['name']] = $actionvalue['value'];
            if (preg_match_all( "/@@(\w+)/u", $actionvalue['value'], $matches  )) {
               $casevariables = array_merge( $casevariables, $matches[1] );
            }
         }
         $externalapplication = false; // by default
         if ($targetTask['is_externaldata'] && isset($targetTask['externalapplication'])) {
            // must read some values
            $externalapplication = json_decode( $targetTask['externalapplication'], true );
            // must be of the form
            // {"method":"POST","url":"http://arsupd201.ar.ray.group:8000/search_by_userid/","params":{"user":"@@USER_ID","system":"GPP","list":"@@ROLE_LIST"}}
            // Where method is the POST, GET, ... method
            // url is the URL to be called
            // params is a list of parameters to get from running case
            foreach ($externalapplication['params'] as $paramname => $variable) {
               if (preg_match_all( "/@@(\w+)/u", $variable, $matches  )) {
                  $casevariables = array_merge( $casevariables, $matches[1] );
               }
            }
         }

         // ask for those case variables
         $myProcessMaker = new PluginProcessmakerProcessmaker();
         $myProcessMaker->login( );
         // now tries to get the variables to check condition
         $infoForTasks = $myProcessMaker->getVariables( $srcCaseId, $casevariables );
         foreach ($infoForTasks as $casevar => $varval) {
            $infoForTasks[ "@@$casevar" ] = "'$varval'";
            unset( $infoForTasks[ $casevar ] );
         }
         $targetTask['sourcecondition'] = str_replace( array_keys($infoForTasks), $infoForTasks, $targetTask['sourcecondition'] );

         if (eval( "return ".$targetTask['sourcecondition'].";" )) {
            // look at each linked ticket if a case is attached and then if a task like $val is TO_DO
            // then will try to routeCase for each tasks in $val

            $postdatas = array();
            foreach ($targetTask['targetactions'] as $action => $actionvalue) {
               $postdatas['form'][$action] = eval( "return ".str_replace( array_keys($infoForTasks), $infoForTasks, $actionvalue)." ;" );
            }
            $postdatas['UID']                        = $targetTask['targetdynaform_guid'];
            $postdatas['__DynaformName__']           = $targetTask['targetprocess_guid']."_".$targetTask['targetdynaform_guid'];
            $postdatas['__notValidateThisFields__']  = '[]';
            $postdatas['DynaformRequiredFields']     = '[]';
            $postdatas['form']['btnGLPISendRequest'] = 'submit';

            $externalapplicationparams = array();
            if ($externalapplication) {
               // must call curl
               foreach ($externalapplication['params'] as $paramname => $variable) {
                  $externalapplicationparams[$paramname] = eval( "return ".str_replace( array_keys($infoForTasks), $infoForTasks, $variable)." ;" );
               }
               $externalapplicationparams['callback']="http://".$_SERVER['SERVER_NAME' ].$CFG_GLPI['root_doc']."/plugins/processmaker/ajax/asynchronousdatas.php";
               $ch = curl_init();
               $externalapplication['url'] = eval( "return '".str_replace( array_keys($infoForTasks), $infoForTasks, $externalapplication['url'])."' ;" ); // '???
               curl_setopt($ch, CURLOPT_URL, $externalapplication['url'] );
            }

            if ($targetTask['is_self']) {
               // MUST BE done on a add task hook, and not on an update task hook

               //$query = "SELECT glpi_plugin_processmaker_cases.id, MAX(glpi_plugin_processmaker_tasks.del_index) AS del_index FROM glpi_tickettasks
               //            JOIN glpi_plugin_processmaker_taskcategories ON glpi_plugin_processmaker_taskcategories.taskcategories_id=glpi_tickettasks.taskcategories_id
               //            JOIN glpi_plugin_processmaker_cases ON glpi_plugin_processmaker_cases.processes_id=glpi_plugin_processmaker_taskcategories.processes_id
               //            RIGHT JOIN glpi_plugin_processmaker_tasks ON glpi_plugin_processmaker_tasks.items_id=glpi_tickettasks.id AND glpi_plugin_processmaker_tasks.case_id=glpi_plugin_processmaker_cases.id
               //            WHERE glpi_plugin_processmaker_taskcategories.pm_task_guid = '".$targetTask['targettask_guid']."' AND glpi_tickettasks.state = 1 AND glpi_tickettasks.tickets_id=".$parm->fields['tickets_id'] ;

               //$res = $DB->query($query) ;
               //if( $res && $DB->numrows($res) > 0 && $case=$DB->fetch_assoc($res) && isset($case['id']) && isset($case['del_index']) ) {
               //foreach( $DB->request($query) as $case ) {
               $taskCase = $myProcessMaker->taskCase( $srcCaseId );
               foreach ($taskCase as $task) {
                  // search for target task guid
                  if ($task->guid == $targetTask['targettask_guid']) {
                     break;
                  }
               }

               $postdatas['APP_UID']                    = $srcCaseId;
               $postdatas['DEL_INDEX']                  = $task->delegate;

               //need to get the 'ProcessMaker' user
               $pmconfig = PluginProcessmakerConfig::getInstance();

               $cronaction = new PluginProcessmakerCrontaskaction;
               $cronaction->add( array( 'plugin_processmaker_caselinks_id' => $targetTask['id'],
                                          'itemtype'  => $itemtype,
                                          'items_id'  => $parm->fields['tickets_id'],
                                          'users_id'  => $pmconfig->fields['users_id'],
                                          'toclaim'   => $targetTask['targettoclaim'],
                                          'state'     => ($targetTask['is_externaldata'] ? PluginProcessmakerCrontaskaction::WAITING_DATAS : PluginProcessmakerCrontaskaction::DATAS_READY),
                                          'postdatas' => json_encode( $postdatas, JSON_HEX_APOS | JSON_HEX_QUOT)
                                          ),
                                 null,
                                 false);

               if ($externalapplication) {
                  // must call external application in order to get the needed data asynchroneously
                  // must be of the form
                  // {"url":"http://arsupd201.ar.ray.group:8000/search_by_userid/","params":{"user":"@@USER_ID","system":"GPP","list":"@@ROLE_LIST"}}
                  // url is the URL to be called

                  $externalapplicationparams['id'] = $cronaction->getID();

                  $externalapplicationparams = json_encode( $externalapplicationparams, JSON_HEX_APOS | JSON_HEX_QUOT);

                  curl_setopt($ch, CURLOPT_POST, 1);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $externalapplicationparams);
                  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($externalapplicationparams)));
                  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );

                  // curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1 ) ;
                  // curl_setopt($ch, CURLOPT_PROXY, "localhost:8888");

                  $response = curl_exec ($ch);

                  //Toolbox::logDebug( $response ) ;

                  curl_close ($ch);
               }
               //               }
            } else {
               foreach (Ticket_Ticket::getLinkedTicketsTo( $parm->fields['tickets_id'] ) as $tlink) {
                  if ($tlink['link'] == Ticket_Ticket::LINK_TO) {
                     $query = "SELECT glpi_plugin_processmaker_cases.id, MAX(glpi_plugin_processmaker_tasks.del_index) AS del_index FROM glpi_tickettasks
                           JOIN glpi_plugin_processmaker_taskcategories ON glpi_plugin_processmaker_taskcategories.taskcategories_id=glpi_tickettasks.taskcategories_id
                           JOIN glpi_plugin_processmaker_cases ON glpi_plugin_processmaker_cases.processes_id=glpi_plugin_processmaker_taskcategories.processes_id
                           RIGHT JOIN glpi_plugin_processmaker_tasks ON glpi_plugin_processmaker_tasks.items_id=glpi_tickettasks.id AND glpi_plugin_processmaker_tasks.case_id=glpi_plugin_processmaker_cases.id
                           WHERE glpi_plugin_processmaker_taskcategories.pm_task_guid = '".$targetTask['targettask_guid']."' AND glpi_tickettasks.state = 1 AND glpi_tickettasks.tickets_id=".$tlink['tickets_id'];
                     foreach ($DB->request($query) as $case) {
                        // must be only one row

                        $postdatas['APP_UID']                    = $case['id'];
                        $postdatas['DEL_INDEX']                  = $case['del_index'];

                        $cronaction = new PluginProcessmakerCrontaskaction;
                        $cronaction->add( array( 'plugin_processmaker_caselinks_id' => $targetTask['id'],
                                                   'itemtype'  => $itemtype,
                                                   'items_id'  => $parm->fields['tickets_id'],
                                                   'users_id'  => Session::getLoginUserID(),
                                                   'toclaim'   => $targetTask['targettoclaim'],
                                                   'state'     => ($targetTask['is_externaldata'] ? PluginProcessmakerCrontaskaction::WAITING_DATAS : PluginProcessmakerCrontaskaction::DATAS_READY),
                                                   'postdatas' => json_encode( $postdatas, JSON_HEX_APOS | JSON_HEX_QUOT)
                                                   ),
                                          null,
                                          false);
                     }
                  }
               }
            }
         }

      }
   }
}
