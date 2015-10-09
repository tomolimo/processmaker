<?php

include_once 'inc/processmaker.class.php' ;


//function plugin_planning_populate_processmaker($param) {
//    if ( !isset($param['begin']) || !isset($param['who']) ) {
//        return $param;
//    }
    
//    if( isset($param['items'] ) ) {
//        foreach( $param['items'] as $key => &$val) { 
//            $item=false;
//            if( isset( $val['tickettasks_id'] ) )
//                $item = new TicketTask ;
//            if( $item ) {
//                $item->getFromDB( $val['id'] ) ;
//                PluginProcessmakerProcessmaker::canedit_item_processmakertickettask( $item ) ;            
//                $val['content']=$item->fields['content'];
//            }
//        }
//    }


//    return $param;
//}

function plugin_processmaker_MassiveActions($type) {
    global $LANG;

    switch ($type) {
        case 'PluginProcessmakerProcess' :
            if( plugin_processmaker_haveRight('process_config', 'w' ) )
                return array('plugin_processmaker_taskrefresh' => 'Synchronize Task List');
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
    global $LANG,$DB;

    switch ($data['action']) {

        case "plugin_processmaker_taskrefresh" :
            if ($data['itemtype'] == 'PluginProcessmakerProcess') {
                foreach ($data["item"] as $key => $val) {
                    if ($val == 1) {
                        $process = new PluginProcessmakerProcess;
                        //$process->getFromDB($key);
                        $process->refreshTasks( array( 'id' => $key ) ) ;
                        
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
	global $DB ;

    if (TableExists("glpi_plugin_processmaker_config")) {
        $query = "ALTER TABLE `glpi_plugin_processmaker_config`
	                ADD COLUMN `date_mod` DATETIME NULL DEFAULT NULL AFTER `pm_theme`,
	                ADD COLUMN `comment` TEXT NULL AFTER `date_mod`;
                  RENAME TABLE `glpi_plugin_processmaker_config` TO `glpi_plugin_processmaker_configs`;" ;
		$DB->query($query) or die("error creating glpi_plugin_processmaker_configs" . $DB->error());                                       
    }
	else    
	if (!TableExists("glpi_plugin_processmaker_configs")) {
		$query = "  CREATE TABLE `glpi_plugin_processmaker_configs` (
	                `id` INT(11) NOT NULL AUTO_INCREMENT,
	                `name` VARCHAR(50) NOT NULL,
	                `pm_server_URL` VARCHAR(250) NOT NULL DEFAULT 'http://localhost/',
	                `pm_workspace` VARCHAR(50) NOT NULL DEFAULT 'workflow',
	                `pm_theme` VARCHAR(50) NOT NULL DEFAULT 'classic',
	                `date_mod` DATETIME NULL DEFAULT NULL,
                    `taskcategories_id` INT(11) NULL ,
	                `users_id` INT(11) NULL DEFAULT NULL,
                    `pm_group_guid` VARCHAR(32) NULL DEFAULT NULL,
                    `comment` TEXT NULL,
	                PRIMARY KEY (`id`)
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;
			";
        
		$DB->query($query) or die("error creating glpi_plugin_processmaker_configs" . $DB->error());
        
        // ProcessMaker user creation
        $user = new User;
        $user->add( array( 'name' => 'ProcessMaker', 'realname' => 'Process', 'firstname' => 'Maker') ) ;
        
        // ProcessMaker plugin configuration
        $DB->query("INSERT INTO glpi_plugin_processmaker_configs ( id, name, users_id) VALUES ( 1, 'Process Maker 1', ".$user->getID()." );" ) or die("error when inserting default config" . $DB->error());        
	}
    
    if (!TableExists("glpi_plugin_processmaker_profiles")) {
		$query = "CREATE TABLE `glpi_plugin_processmaker_profiles` (
	                    `id` INT(11) NOT NULL AUTO_INCREMENT,
	                    `profiles_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_profiles (id)',
	                    `process_config` CHAR(1) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	                    PRIMARY KEY (`id`),
	                    INDEX `profiles_id` (`profiles_id`)
                    )
                    COLLATE='utf8_unicode_ci'
                    ENGINE=InnoDB;" ;
		$DB->query($query) or die("error creating glpi_plugin_processmaker_profiles" . $DB->error());
	}
            
	if (!TableExists("glpi_plugin_processmaker_cases")) {
		$query = "CREATE TABLE `glpi_plugin_processmaker_cases` (
	                        `id` VARCHAR(32) NOT NULL,
	                        `items_id` INT(11) NOT NULL,
	                        `itemtype` VARCHAR(10) NOT NULL DEFAULT 'Ticket',
	                        `case_num` INT(11) NOT NULL,
	                        `case_status` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
	                        UNIQUE INDEX `items` (`itemtype`, `items_id`),
	                        INDEX `case_status` (`case_status`)
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;
			";
	
		$DB->query($query) or die("error creating glpi_plugin_processmaker_cases" . $DB->error());
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
				`glpi_users_id` INT(11) NOT NULL ,
                `pm_users_id` VARCHAR(32) NOT NULL ,                
	            UNIQUE INDEX `glpi_users_id` (`glpi_users_id`),
            	UNIQUE INDEX `pm_users_id` (`pm_users_id`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB;
			";
        
		$DB->query($query) or die("error creating glpi_plugin_processmaker_users" . $DB->error());
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
                `task_category_id` INT(11) NULL ,
                `itilcategories_id` INT(11) NOT NULL DEFAULT '0',
	            `type` INT(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets',
                `date_mod` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `process_guid` (`process_guid`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB;
			";
        
		$DB->query($query) or die("error creating glpi_plugin_processmaker_processes" . $DB->error());
    }

    if( FieldExists( 'glpi_plugin_processmaker_processes', 'is_helpdeskvisible') ) {            
        $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
                    CHANGE COLUMN `is_helpdeskvisible` `is_helpdeskvisible_notusedanymore` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'Not used any more since version 2.2' AFTER `name`;" ;
        $DB->query($query) ;            
    }
    
    if( !FieldExists( 'glpi_plugin_processmaker_processes', 'itilcategories_id') ) {            
        $query = "ALTER TABLE `glpi_plugin_processmaker_processes`
	                ADD COLUMN `itilcategories_id` INT(11) NOT NULL DEFAULT '0',
	                ADD COLUMN `type` INT(11) NOT NULL DEFAULT '1' COMMENT 'Only used for Tickets';" ;
        
        $DB->query($query) or die("error adding columns 'itilcategories_id' and 'type' to glpi_plugin_processmaker_processes" . $DB->error());            
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
            ENGINE=InnoDB;" ;
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
                AUTO_INCREMENT=57
                ;
			";
        
		$DB->query($query) or die("error creating glpi_plugin_processmaker_taskcategories" . $DB->error());
        
    }
    
    // create default TaskCategory if needed
    // verify if taskcategory_id is set in config
    // required cause autoload don't work for unactive plugin'
    include_once(GLPI_ROOT."/plugins/processmaker/inc/config.class.php");
    $config = new PluginProcessmakerConfig ;
    $config->getFromDB( 1 ) ;
    $pmCategory = $config->fields['taskcategories_id'] ;
    if( !$pmCategory ) {
        // add main category into config and glpi_taskcategories
        $taskCat = new TaskCategory;
        $pmCategory = $taskCat->add( array( 'is_recursive' => 1, 'name' => 'Process Maker', 'comment' => 'Is top category for Process Maker tasks. Name can be changed if desired.'  ) ) ;  
        if( $pmCategory ) 
            $config->update( array( 'id' => $config->getID(), 'taskcategories_id' => $pmCategory ) ) ;
    }        
    
    $myProcessMaker = new PluginProcessmakerProcessmaker() ; 
    $myProcessMaker->login(true) ; // to force admin login
    
    // verify if group 'GLPI Requesters' exists in config, if not will create it in PM and add GUID in config
    $pmGroup = $config->fields['pm_group_guid'] ;
    if( !$pmGroup ) {
        $pmres = $myProcessMaker->createGroup( "GLPI Requesters" ) ;
        if( $pmres->status_code == 0 ) 
            $config->update( array( 'id' => $config->getID(), 'pm_group_guid' => $pmres->groupUID ) ) ;
    }        

        
    // To be called for each task managed by the plugin
    // task in class
    CronTask::Register('PluginProcessmakerProcessmaker', 'pmusers', DAY_TIMESTAMP, array( 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL));
    //CronTask::Register('PluginProcessmakerProcessmaker', 'pmnotifications', DAY_TIMESTAMP, array( 'state' => CronTask::STATE_DISABLE, 'mode' => CronTask::MODE_EXTERNAL));
    
    
    // required cause autoload doesn't work for unactive plugin'
    include_once(GLPI_ROOT."/plugins/processmaker/inc/profile.class.php");
    PluginProcessmakerProfile::createAdminAccess($_SESSION['glpiactiveprofile']['id']);
    
	return true;
}

function plugin_processmaker_uninstall() {
	global $DB;
	
	// Current version tables
	//if (TableExists("glpi_plugin_processmaker_ticketcase")) {
	//	$query = "DROP TABLE `glpi_plugin_processmaker_ticketcase`";
	//	$DB->query($query) or die("error deleting glpi_plugin_processmaker_ticketcase");
	//}
    
    CronTask::Unregister('PluginProcessmakerProcessmaker');
    
    //if (TableExists("glpi_plugin_processmaker_processes")) {
    //    $query = "DROP TABLE `glpi_plugin_processmaker_processes`";
    //    $DB->query($query) or die("error deleting glpi_plugin_processmaker_processes");
    //}
    
    // now uninstall triggers from PM tables
    //$DB->close();
    //$DB->dbdefault='wf_workflow';
    //$DB->connect( ) ;
    //$DB->runFile(GLPI_ROOT.'/plugins/processmaker/config/pm_db_uninstall.sql') or die("error creating triggers on wf_workflow database!" . $DB->error());
    //$DB->close();
    //$DB->dbdefault='glpi';
    //$DB->connect( ) ;
    //

    
	return true;
}

//// Define headings added by the plugin
//function plugin_get_headings_processmaker($item, $withtemplate) {
//    global $LANG ;
    
//    switch (get_class($item)) {
//        case 'Ticket' :
//            return array(1 => $LANG['processmaker']['ticket']['tab']);
            
//        //case 'Profile' :
//        //    $prof = new Profile();
//        //    if ($item->fields['interface'] == 'central') {
//        //        return array(1 => "Test PLugin");
//        //    }
//        //    return array();

//        //case 'Computer' :
//        //    // new object / template case
//        //    if ($withtemplate) {
//        //        return array();
//        //        // Non template case / editing an existing object
//        //    }
//        //    return array(1 => "Test PLugin");

//        //case 'ComputerDisk' :
//        //case 'Supplier' :
//        //    if ($item->getField('id')) { // Not in create mode
//        //        return array(1 => "Test PLugin",
//        //                     2 => "Test PLugin 2");
//        //    }
//        //    break;

//        //case 'Central' :
//        //case 'Preference':
//        //case 'Notification':
//        //    return array(1 => "Test PLugin");
//    }
//    return false;
//}

//// Define headings actions added by the plugin
//function plugin_headings_actions_processmaker($item) {

//    switch (get_class($item)) {
//        //case 'Profile' :
//        //case 'Computer' :
//        //    return array(1 => "plugin_headings_processmaker");

//        //case 'ComputerDisk' :
//        //case 'Supplier' :
//        //    return array(1 => "plugin_headings_processmaker",
//        //                 2 => "plugin_headings_processmaker");

//        //case 'Central' :
//        //case 'Preference' :
//        //case 'Notification' :
//        case 'Ticket' :
//            return array(1 => "plugin_headings_processmaker");
//    }
//    return false;
//}

//// Example of an action heading
//function plugin_headings_processmaker($item, $withtemplate=0) {
//    global $LANG, $DB, $GLOBALS ;

//    if (!$withtemplate) {
//        echo "<div class='center'>";
//        switch (get_class($item)) {
//            //case 'Central' :
//            //    echo "Plugin central action ".$LANG['plugin_processmaker']["test"];
//            //    break;

//            //case 'Preference' :
//            //    // Complete form display
//            //    $data = plugin_version_processmaker();

//            //    echo "<form action='Where to post form'>";
//            //    echo "<table class='tab_cadre_fixe'>";
//            //    echo "<tr><th colspan='3'>".$data['name']." - ".$data['version'];
//            //    echo "</th></tr>";

//            //    echo "<tr class='tab_bg_1'><td>Name of the pref</td>";
//            //    echo "<td>Input to set the pref</td>";

//            //    echo "<td><input class='submit' type='submit' name='submit' value='submit'></td>";
//            //    echo "</tr>";

//            //    echo "</table>";
//            //    echo "</form>";
//            //    break;

//            //case 'Notification' :
//            //    echo "Plugin mailing action ".$LANG['plugin_processmaker']["test"];
//            //    break;

//            case 'Ticket' :
//                //echo "Show the iframe";                
//                $rand = rand();
//                echo "<form name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
//                echo "<div class='center'><table class='tab_cadre_fixehov'>";
//                echo "<tr><th colspan='2'>".$LANG['processmaker']['ticket']['tab']."</th></tr>";
                
//                //$userGLPI = new User();
//                //$userGLPI->getFromDB( Session::getLoginUserID() ) ;
//                //if( $userGLPI->fields['language'] != null )
//                //    $lang =  substr( $userGLPI->fields['language'], 0, 2)  ;
//                //else
//                //    $lang = "en" ;
//                $myProcessMaker = new PluginProcessmakerProcessmaker( ) ;
//                $myProcessMaker->login(); //openSession( $userGLPI->fields['name'], "md5:37d442efb43ebb80ec6f9649b375ab72", $lang ) ; 
                
//                // first search for the case
//                $ticket_id = $item->getField('id') ;               
//                $caseInfo = $myProcessMaker->getCaseFromItem( "Ticket", $ticket_id ) ;
//                if( $caseInfo !== false ){  
//                    // need to get info on the thread of the GLPI current user
//                    // we must retreive currentGLPI user from this array
//                    $GLPICurrentPMUserId = PluginProcessmakerProcessmaker::getPMUserId( Session::getLoginUserID() ) ; //$userGLPI->fields['id'] ) ;
//                    $pmCaseUser = $caseInfo->currentUsers[0] ; // by default
//                    foreach( $caseInfo->currentUsers as $caseUser) {
//                        if( $caseUser->userId == $GLPICurrentPMUserId ){
//                            $pmCaseUser = $caseUser ;
//                            break ;
//                        }
//                    }
//                    if( $pmCaseUser->delThreadStatus == 'PAUSE' ) {
//                        // means the case itself may not be PAUSED, but task assigned to current GLPI user is paused...
//                        // then forced PAUSE view for this thread
//                        // and give possibility to unpause it
//                        $caseInfo->caseStatus = 'PAUSED' ;
//                    }
                        
//                    switch ( $caseInfo->caseStatus ) {
//                        case "CANCELLED"  :
//                            echo "<tr><th colspan='2'>".$LANG['processmaker']['ticket']['cancelledcase']."</th></tr>";
//                            $paramsURL = "DEL_INDEX=1" ;
//                            break;
//                        case 'PAUSED' :
//                            // we need to add a button to unpause the case
//                            echo "<input type='hidden' name='action' value='unpausecase'>";
//                            echo "<input type='hidden' name='id' value='".$ticket_id."'>";
//                            echo "<input type='hidden' name='itemtype' value='".$item->gettype()."'>";
//                            echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$caseInfo->caseId."'>";
//                            echo "<input type='hidden' name='plugin_processmaker_delIndex' value='".$pmCaseUser->delIndex."'>";
//                            echo "<input type='hidden' name='plugin_processmaker_userId' value='".$pmCaseUser->userId."'>";
//                            echo "<tr><th>".$LANG['processmaker']['ticket']['pausedtask']."</th><th>";
//                            echo "<input type='submit' name='unpausecase' value='".$LANG['processmaker']['ticket']['unpause']."' class='submit'>";
//                            echo "</th></tr>";
                            
//                        case "DRAFT" :
//                        case "TO_DO" : 
//                            $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus ;                            
//                            break ;
//                        case "COMPLETED" :
//                            echo "<tr><th colspan='2'>".$LANG['processmaker']['ticket']['completedcase']."</th></tr>";
//                            $paramsURL = "DEL_INDEX=1" ;
//                            break ;
//                    }
//                    echo "<tr class='tab_bg_2' ><td colspan=2>" ; 
//                    echo "<script> 
//                        //var bLoads = 0 ;
                        
//                        function reloadParent( locParent ) {
//                            if( locParent.location == locParent.parent.location )
//                                locParent.location.reload( ) ;
//                            else
//                                reloadParent( locParent.parent ) ;
//                        }

//                        var oldHandler ;
//                        var oldParent ;
//                        var submitButton  ;
//                        function onClickContinue( obj ) {
//                            // call old handler
//                            if( obj != undefined ) 
//                                oldHandler(obj.target); 
//                            reloadParent( oldParent ) ;  
//                        }

                        
//                        function onLoadFrame() {
//                            //debug();
//                            var caseTimerCounter = 0 ;
//                            var caseTimer = window.setInterval(function() {
//                                //debug() ;

//                                var contentDocument = document.getElementById('caseiframe').contentDocument.getElementById('openCaseFrame').contentDocument ;
                                
//                                var cancelButton = contentDocument.getElementById('form[BTN_CANCEL]') ; 
//                                var formDerivation = contentDocument.getElementById('frmDerivation') ;
//                                var buttonContinue = contentDocument.getElementById('btnContinue') ;
                                
//                                if(cancelButton != undefined) {
//                                    window.clearInterval(caseTimer) ;
//                                    cancelButton.style.visibility='hidden';
//                                    // TODO manage 'Claim' button
//                                } elseif( formDerivation != undefined && buttonContinue != undefined ) {
//                                    window.clearInterval(caseTimer) ;
//                                    buttonContinue.form.action = '' ;
//                                    oldHandler = buttonContinue.onclick ;
//                                    buttonContinue.onclick = onClickContinue ;
//                                    oldParent = document ;                        
//                                } else 
//                                    caseTimerCounter = caseTimerCounter + 1 ;
                                    
//                                if( caseTimerCounter > 3000 )  {
//                                    window.clearInterval(caseTimer) ;
//                                    }
//                            }, 10) ;
//                            //alert( bLoads ) ;
//                            //if( bLoads >= 1 ) 
//                            // means something has been done in the iFrame of ProcessMaker, then we may reload the complete page in order to 
//                            // 1) prevent view of the casesListExtJs
//                            // 2) refresh the view of the case with the open form
//                            //    reloadParent( parent ) ;
//                            //else bLoads = bLoads + 1 ;
//                        }
                                        
//                        </script>";
                    
//                    echo "<iframe onload='onLoadFrame();' id='caseiframe' height=1080 width=100% src='".$myProcessMaker->serverURL."/cases/open?sid=".$_SESSION["pluginprocessmaker"]["Session"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."' >" ; 
//                    echo "</iframe>";                   
//                    echo "</td></tr>";
//                } else {
                    
//                    // no running case for this ticket
//                    // propose to start one
//                    echo "<tr><th colspan='2'>".$LANG['processmaker']['ticket']['nocase'] ;
                    
//                    // check if ticket is not solved nor closed
//                    if( $item->fields['status'] != 'solved' && $item->fields['status'] != 'closed' ) {                    
//                        // propose case start
//                        echo "&nbsp;-&nbsp;".$LANG['processmaker']['ticket']['startone'];
//                        echo "</th></tr>";
                    
//                        echo "<tr class='tab_bg_2'><td class='right'  colspan='1'>";
//                        echo $LANG['processmaker']['ticket']['selectprocess']."&nbsp;";
//                        echo "<input type='hidden' name='action' value='newcase'>";
//                        echo "<input type='hidden' name='id' value='".$item->getField('id')."'>";
//                        echo "<input type='hidden' name='itemtype' value='".$item->gettype()."'>";
//                        Dropdown::show('PluginProcessmakerProcessmaker', array( 'name' => 'plugin_processmaker_process_id', 'condition' => "trigger_guid != ''")); // condition is used to prevent start of cases without trigger
//                        echo "</td><td class='center'>";
//                        echo "<input type='submit' name='additem' value='".$LANG['processmaker']['ticket']['start']."' class='submit'>";
//                        echo "</td></tr>";
//                    }
//                    else echo "</th></tr>";
//                }

//                echo "</table>";
//                echo "</form>";
//                break;
                
//            default :
//                echo "Plugin function with headings CLASS=".get_class($item)." id=".$item->getField('id');
//                break;
//        }
//        echo "</div>";
//    }
//}

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
        $sopt[10001]['forcegroupby'] = true ;
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

    switch ($type){
        
        case 'Ticket':
            switch ($new_table){

                case "glpi_plugin_processmaker_cases" : 
                    $out= " LEFT JOIN `glpi_plugin_processmaker_cases` 
                        ON (`$ref_table`.`id` = `glpi_plugin_processmaker_cases`.`items_id` AND `glpi_plugin_processmaker_cases`.`itemtype` like 'Ticket') ";
                    return $out;
                    break;
            }
            
            return "";
            break;
	}
	
	return "";
}

/**
 * Summary of plugin_pre_item_update_processmaker
 * @param CommonITILObject $parm 
 */
function plugin_pre_item_update_processmaker(CommonITILObject $parm) {
    global $DB ;

    if( isset($_SESSION['glpiname']) && $parm->getType() == 'Ticket' ) {
        $locCase = new PluginProcessmakerCases ;

        $itemId = $parm->getID() ;
        $itemType = $parm->getType() ;
        
  		if( $locCase->getCaseFromItemTypeAndItemId( $itemType, $itemId ) ) {
            $locPM = new PluginProcessmakerProcessmaker ;
            $locPM->login() ;

            // case is existing for this item            
			$technicians = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, 2 ) ; // 2 for technicians
            
            // beware to empty injection when not modified!!!
            $locVar = array( ) ;
            foreach( $parm->input as $key => $val ) {
                switch( $key ) {
                    case 'global_validation' : 
                        $locVar[ 'GLPI_TICKET_GLOBAL_VALIDATION' ] = $val ; 
                        break;
                    case 'due_date' : 
                        $locVar[ 'GLPI_TICKET_DUE_DATE' ] = $val ; 
                        break;
                    case 'urgency' : 
                        $locVar[ 'GLPI_TICKET_URGENCY' ] = $val ; 
                        break;
                    case 'impact' : 
                        $locVar[ 'GLPI_ITEM_IMPACT' ] = $val ; 
                        break;
                    case 'priority' : 
                        $locVar[ 'GLPI_ITEM_PRIORITY' ] = $val ; 
                        break;
                }                                                                               
            }
//            $locVar['GLPI_TICKET_TECHNICIAN_GLPI_ID']=$technicians[0]['glpi_id'];
//            $locVar['GLPI_TICKET_TECHNICIAN_PM_ID']=$technicians[0]['pm_id'];
            
            $pmResponse = $locPM->sendVariables( $locCase->getID(), $locVar) ;
            //$locCase->sendVariables( $locVar ) ;
        }
    }
        
}


function plugin_item_update_processmaker_satisfaction($parm) {
    global $DB, $GLOBALS ;  
    
    $locCase = new PluginProcessmakerCases ;
    if( $locCase->getCaseFromItemTypeAndItemId( 'Ticket', $parm->fields['tickets_id'] ) ) {
        // case is existing for this item            
        //$query = "select * from wf_workflow.application where APP_UID='".$locCase->getID()."';" ;
        
        //$res = $DB->query($query) ;
        //$app_data = array() ;
        //if( $DB->numrows($res) > 0) {
        //    $row = $DB->fetch_assoc($res);
        //    $app_data = unserialize($row['APP_DATA'] ) ;
            
        //}
          
        
        //$locVars = array( 'GLPI_SATISFACTION_QUALITY' => $parm->fields['satisfaction'], 
        //                  'GLPI_SATISFACTION_RESPONSETIME' => $parm->fields['responsetime'], 
        //                  'GLPI_SATISFACTION_FRIENDLINESS' => $parm->fields['friendliness'] ) ;
        
        $locPM = new PluginProcessmakerProcessmaker ;
        $locPM->login() ;
        
        $pmResponse = $locPM->sendVariables( $locCase->getID(), array(
                                                                        'GLPI_SATISFACTION_QUALITY' => $parm->fields['satisfaction'],
                                                                        'GLPI_SATISFACTION_RESPONSETIME' => $parm->fields['responsetime'],
                                                                        'GLPI_SATISFACTION_FRIENDLINESS' => $parm->fields['friendliness']
                                                                        )) ;
        //$locCase->sendVariables( $locVars ) ;
    }   
}
    
function plugin_pre_item_update_processmaker_followup($parm) {
}


function plugin_pre_item_purge_processmaker ( $parm ) {

        if( $parm->getType() == 'Ticket_User' && is_array( $parm->fields ) && isset( $parm->fields['type'] )  && $parm->fields['type'] == 2 ) {
            $itemId = $parm->fields['tickets_id'] ;
            $itemType = 'Ticket' ; 
            $technicians = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, 2 ) ; // 2 for technicians
            //$locCase = new PluginProcessmakerCases ;
                        
            if( PluginProcessmakerCases::getCaseIdFromItemTypeAndItemId( $itemType, $itemId ) && count($technicians) == 1 ) {
                $parm->input = null ; // to cancel deletion of the last tech in the ticket
            }            
        }
}   

function plugin_item_purge_processmaker($parm) {
    global $DB ;
        
    if( $parm->getType() == 'Ticket_User' && is_array( $parm->fields ) && isset( $parm->fields['type'] )  && $parm->fields['type'] == 2 ) {
            
        // We just deleted a tech from this ticket then we must if needed "de-assign" the tasks assigned to this tech
        // and re-assign them to the first tech in the list !!!!
        
        $locCase = new PluginProcessmakerCases ;
            
        $itemId = $parm->fields['tickets_id'] ;
        $itemType = 'Ticket' ; 
            
        if( $locCase->getCaseFromItemTypeAndItemId( $itemType, $itemId )  ) {
            // case is existing for this item            
            $technicians = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, 2 ) ; // 2 for technicians
            $locPM = new PluginProcessmakerProcessmaker ;
            $locPM->login() ;
            //$pmResponse = $locPM->sendVariables( $locCase->getID(), array( 
            //                                                'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $technicians[0]['glpi_id'], 
            //                                                'GLPI_TICKET_TECHNICIAN_PM_ID' => $technicians[0]['pm_id'] 
            //                                                ) ) ;
            $locVars = array( 'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $technicians[0]['glpi_id'], 
                                'GLPI_TICKET_TECHNICIAN_PM_ID' => $technicians[0]['pm_id'] )  ;
            //$locCase->sendVariables( $locVars ) ;
            
            // and we must find all tasks assigned to this former user and re-assigned them to new user (if any :))!
            $caseInfo = $locPM->getCaseInfo( $locCase->getID() ) ;                
            if( $caseInfo !== false ){  
                $locPM->sendVariables( $locCase->getID( ), $locVars ) ;
                // need to get info on the thread of the GLPI current user
                // we must retreive currentGLPI user from this array
                $GLPICurrentPMUserId = PluginProcessmakerProcessmaker::getPMUserId( $parm->fields['users_id'] ) ; 
                foreach( $caseInfo->currentUsers as $caseUser) {
                    if( $caseUser->userId == $GLPICurrentPMUserId && in_array( $caseUser->delThreadStatus, array('DRAFT', 'OPEN', 'PAUSE' ) ) ){
                        $pmResponse = $locPM->reassignCase( $locCase->getID(), $caseUser->delIndex, $GLPICurrentPMUserId, $technicians[0]['pm_id'] )  ;
                        // now should managed GLPI Tasks previously assigned to the $GLPICurrentPMUserId
                        if( $pmResponse->status_code == 0 ) {
                            // ATTENTION: should be aware of: ticket tech == task tech
                            // In this particular flow due to 'Change Management'
                                
                            // we need to change the delindex of the glpi task and the assigned tech to prevent creation of new tasks 
                            // we need the delindex of the current glpi task, and the delindex of the new one
                            // search for new delindex
                            $newCaseInfo = $locPM->getCaseInfo( $locCase->getID() ) ;
                            $newDelIndex = 0 ;
                            foreach( $newCaseInfo->currentUsers as $newCaseUser ){
                                if( $newCaseUser->taskId == $caseUser->taskId && $newCaseUser->delThread == $caseUser->delThread ) {
                                    $newDelIndex = $newCaseUser->delIndex ;
                                    break ;
                                }
                            }
                            $locPM->reassignTask( $locCase->getID(), $caseUser->delIndex, $newDelIndex, $technicians[0]['glpi_id'] ) ;
                        }
                    }
                }
            }    
            
        }
    }
}

?>