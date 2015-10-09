<?php


// Init the hooks of the plugins -Needed
function plugin_init_processmaker() {

    global $PLUGIN_HOOKS;

   Plugin::registerClass('PluginProcessmakerProcessmaker');//, array(
   //                                                 'notificationtemplates_types' => true,
   //                                                 'addtabon'                    => array('Ticket')));

   Plugin::registerClass('PluginProcessmakerCases', array(
                                                 'notificationtemplates_types' => true,
                                                 'addtabon'                    => array('Ticket')));
   Plugin::registerClass('PluginProcessmakerTaskCategory');
   
   if (Session::haveRight('config','w')) {   
    Plugin::registerClass('PluginProcessmakerConfig', array('addtabon' => 'Config'));
    $PLUGIN_HOOKS['config_page']['processmaker'] = 'front/config.form.php';
   }
   
   Plugin::registerClass('PluginProcessmakerProfile',
                      array('addtabon' => 'Profile'));
   $PLUGIN_HOOKS['change_profile']['processmaker']   = array('PluginProcessmakerProfile','select');

   Plugin::registerClass('PluginProcessmakerProcess_Profile');

   
   $PLUGIN_HOOKS['csrf_compliant']['processmaker'] = true;
    
   // tabs management
   //$PLUGIN_HOOKS['headings']['processmaker']        = 'plugin_get_headings_processmaker';
   //$PLUGIN_HOOKS['headings_action']['processmaker'] = 'plugin_headings_actions_processmaker';

   $PLUGIN_HOOKS['canedit']['processmaker'] 
   = array('TicketTask' => array('PluginProcessmakerProcessmaker', 
                             'canedit_item_processmakertickettask'));

   
   $PLUGIN_HOOKS['pre_show_item']['processmaker'] 
   = array('Ticket' => array('PluginProcessmakerProcessmaker', 
                             'pre_show_item_processmakerticket'));
   
   $PLUGIN_HOOKS['post_show_item']['processmaker'] 
      = array('Ticket' => array('PluginProcessmakerProcessmaker', 
                                'post_show_item_processmakerticket'));

  // $PLUGIN_HOOKS["helpdesk_menu_entry"]['processmaker'] = '/front/processmaker.helpdesk.form.php';

   // Display a menu entry ?
   if (plugin_processmaker_haveRight("process_config","r")) {
       $PLUGIN_HOOKS['menu_entry']['processmaker']      = 'front/process.php';
       $PLUGIN_HOOKS['submenu_entry']['processmaker']['search'] = 'front/process.php';
   }
   
   Plugin::registerClass('PluginProcessmakerProcess', array( 'massiveaction_nodelete_types' => true) ) ;
   //$CFG_GLPI["massiveaction_nodelete_types"][] = 'PluginProcessmakerProcess' ;

   // Config page
   //if (Session::haveRight('config','w')) {
   //    $PLUGIN_HOOKS['config_page']['processmaker'] = 'front/config.form.php';
   //}
    /*,
    'TicketFollowup' => array('PluginProcessmakerProcessmaker', 'plugin_pre_item_add_processmaker_followup') */
  
    $PLUGIN_HOOKS['pre_item_add']['processmaker'] = array(
      	'Ticket' => array('PluginProcessmakerProcessmaker', 'plugin_pre_item_add_processmaker')
        
      );
 
    $PLUGIN_HOOKS['pre_item_update']['processmaker'] = array(
      	'Ticket' => 'plugin_pre_item_update_processmaker' 
      );
    //        , 'TicketFollowup' => 'plugin_pre_item_update_processmaker_followup'
    
    $PLUGIN_HOOKS['item_update']['processmaker'] = array(
      	'TicketSatisfaction' => 'plugin_item_update_processmaker_satisfaction' 
      );

    $PLUGIN_HOOKS['item_add']['processmaker'] = array(
             'Ticket' => array('PluginProcessmakerProcessmaker', 'plugin_item_add_processmaker')
         );
    
    $PLUGIN_HOOKS['item_get_datas']['processmaker'] = array(
             'NotificationTargetTicket' => array('PluginProcessmakerProcessmaker', 'plugin_item_get_datas_processmaker')
         );
         


$PLUGIN_HOOKS['pre_item_purge']['processmaker'] = array(
             'Ticket_User' => 'plugin_pre_item_purge_processmaker'
             ) ;
   $PLUGIN_HOOKS['item_purge']['processmaker'] = array(
             'Ticket_User' => 'plugin_item_purge_processmaker'
             ) ;

   $url      = explode("/", $_SERVER['PHP_SELF']);
   $pageName = explode("?", array_pop($url));
   switch($pageName[0]) {
       case "tracking.injector.php":
       case "helpdesk.public.php":
           //$plug = new Plugin;
           //if( !$plug->isActivated('rayusermanagementticket') )
                $PLUGIN_HOOKS['add_javascript']['processmaker'] = "js/helpdesk.public.js.php";
           break;
       
   }

   $PLUGIN_HOOKS['use_massive_action']['processmaker'] = 1;

   //$PLUGIN_HOOKS['planning_populate']['processmaker'] = "plugin_planning_populate_processmaker"; // used for task descriptions
   
}

// Get the name and the version of the plugin - Needed
function plugin_version_processmaker(){
   global $LANG;

   return array ('name'           => 'Process Maker',
                'version'        => '2.4.1',
                'author'         => 'Olivier Moron',
                'homepage'       => '',
                'minGlpiVersion' => '0.83.8');
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_processmaker_check_prerequisites(){
   if (version_compare(GLPI_VERSION,'0.83.8','lt') || version_compare(GLPI_VERSION,'0.84','ge')) {
      echo "This plugin requires GLPI 0.83.8 or higher";
      return false;
   } 
   $plug = new Plugin ;
   if (!$plug->isActivated('mhooks') || version_compare( $plug->fields['version'], '1.1.0', '<')) { 
       echo "'mhooks 1.1.0' plugin is needed to run 'processmaker' plugin, please add it to your GLPI plugin configuration.";
       return false;
   } 
   return true;
}

function plugin_processmaker_check_config($verbose = false){
    $plug = new Plugin ;
    if ($plug->isActivated('mhooks') && version_compare( $plug->fields['version'], '1.1.0', '>=')) { 
        return true;
    } 

    if ($verbose) {
        echo "'mhooks 1.1.0' plugin is needed to run 'processmaker' plugin, please add it to your GLPI plugin configuration.";
    }

   return false;
}


function plugin_processmaker_haveRight($module,$right) {
    $matches=array(""  => array("", "r", "w"), // should never happend
                   "r" => array("r", "w"),
                   "w" => array("w"),
                   "1" => array("1"),
                   "0" => array("0", "1")); // should never happend;

    if (isset($_SESSION["glpi_plugin_processmaker_profile"][$module])
          && in_array($_SESSION["glpi_plugin_processmaker_profile"][$module], $matches[$right])) {
        return true;
    } else {
        return false;
    }
}

?>