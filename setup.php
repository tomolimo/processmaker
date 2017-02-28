<?php


// Init the hooks of the plugins -Needed
function plugin_init_processmaker() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['processmaker'] = true;

    $plugin = new Plugin();
    if ($plugin->isInstalled('processmaker')
        && $plugin->isActivated('processmaker')
        && Session::getLoginUserID() ) {


        Plugin::registerClass('PluginProcessmakerProcessmaker');//, array(
        //                                                 'notificationtemplates_types' => true,
        //                                                 'addtabon'                    => array('Ticket')));

        Plugin::registerClass('PluginProcessmakerCase', array(
                                                      'notificationtemplates_types' => true,
                                                      'addtabon'                    => array('Ticket')));
        Plugin::registerClass('PluginProcessmakerTaskCategory');

        if (Session::haveRight('config', UPDATE)) {
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

        //$PLUGIN_HOOKS['canedit']['processmaker']
        //= array('TicketTask' => array('PluginProcessmakerProcessmaker',
        //                          'canedit_item_processmakertickettask'));


        $PLUGIN_HOOKS['pre_show_item']['processmaker']
           = array('PluginProcessmakerProcessmaker', 'pre_show_item_processmakerticket');

        $PLUGIN_HOOKS['pre_show_tab']['processmaker']
           = array('PluginProcessmakerProcessmaker', 'pre_show_tab_processmaker');
        $PLUGIN_HOOKS['post_show_tab']['processmaker']
           = array('PluginProcessmakerProcessmaker', 'post_show_tab_processmaker');

        //$PLUGIN_HOOKS['post_show_item']['processmaker']
        //   = array('Ticket' => array('PluginProcessmakerProcessmaker',
        //                             'post_show_item_processmakerticket'));

        // Display a menu entry ?
        if (Session::haveRight('config', READ) ) {
            $PLUGIN_HOOKS['menu_toadd']['processmaker'] = array('tools' => 'PluginProcessmakerProcess');
        }

        Plugin::registerClass('PluginProcessmakerProcess', array( 'massiveaction_nodelete_types' => true) ) ;

        $PLUGIN_HOOKS['pre_item_add']['processmaker'] = array(
              'Ticket' => array('PluginProcessmakerProcessmaker', 'plugin_pre_item_add_processmaker')

          );

        $PLUGIN_HOOKS['pre_item_update']['processmaker'] = array(
              'Ticket' => 'plugin_pre_item_update_processmaker'
          );
        //        , 'TicketFollowup' => 'plugin_pre_item_update_processmaker_followup'

        $PLUGIN_HOOKS['item_update']['processmaker'] = array(
     	'TicketSatisfaction' => 'plugin_item_update_processmaker_satisfaction',
      'TicketTask' => 'plugin_item_update_processmaker_tasks'
          );

        $PLUGIN_HOOKS['item_add']['processmaker'] = array(
                 'Ticket' => array('PluginProcessmakerProcessmaker', 'plugin_item_add_processmaker')
             );

        $PLUGIN_HOOKS['item_get_datas']['processmaker'] = array(
                 'NotificationTargetTicket' => array('PluginProcessmakerProcessmaker', 'plugin_item_get_datas_processmaker')
             );

        $PLUGIN_HOOKS['item_get_pdfdatas']['processmaker'] = array(
          'PluginPdfTicketTask' => array('PluginProcessmakerProcessmaker', 'plugin_item_get_pdfdatas_processmaker')
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
      $CFG_GLPI['planning_types'][] = 'PluginProcessmakerTask';
      $PLUGIN_HOOKS['post_init']['processmaker'] = 'plugin_processmaker_post_init';
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_processmaker(){
   global $LANG;

   return array ('name'           => 'Process Maker',
                'version'        => '3.1.0',
                'author'         => 'Olivier Moron',
                'homepage'       => '',
                'minGlpiVersion' => '9.1');
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_processmaker_check_prerequisites(){
   if (version_compare(GLPI_VERSION,'9.1','lt') || version_compare(GLPI_VERSION,'9.2','ge')) {
      echo "This plugin requires GLPI 9.1 or higher";
      return false;
   }
   //$plug = new Plugin ;
   //if (!$plug->isActivated('mhooks') || version_compare( $plug->fields['version'], '1.2.0', '<')) {
   //    echo "'mhooks 1.2.0' plugin is needed to run 'processmaker' plugin, please add it to your GLPI plugin configuration.";
   //    return false;
   //}
   return true;
}

function plugin_processmaker_check_config($verbose = false){
   // $plug = new Plugin ;
   // if ($plug->isActivated('mhooks') && version_compare( $plug->fields['version'], '1.2.0', '>=')) {
   //     return true;
   // }

   // if ($verbose) {
   //     echo "'mhooks 1.2.0' plugin is needed to run 'processmaker' plugin, please add it to your GLPI plugin configuration.";
   // }

   //return false;
   return true;
}


function plugin_processmaker_haveRight($module,$right) {

    return Session::haveRight("plugin_processmaker_".$module, $right) ;
}

?>