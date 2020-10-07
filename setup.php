<?php

define('PROCESSMAKER_VERSION', '3.4.24');

// used for case cancellation
define("CANCEL", 256);
// used for ad-hoc user re-assign
define("ADHOC_REASSIGN", 512);

// Init the hooks of the plugins -Needed
function plugin_init_processmaker() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['processmaker'] = true;

   $objects = ['Ticket', 'Change', 'Problem'];
   //   $objects = ['Ticket'];

   Plugin::registerClass('PluginProcessmakerProcessmaker');

   Plugin::registerClass('PluginProcessmakerCase', ['addtabon' => $objects, 'notificationtemplates_types'  => true]);

   Plugin::registerClass('PluginProcessmakerTask', ['notificationtemplates_types'  => true]);

   Plugin::registerClass('PluginProcessmakerTaskCategory', ['addtabon' => 'TaskCategory']);

   if (Session::haveRightsOr("config", [READ, UPDATE])) {
      Plugin::registerClass('PluginProcessmakerConfig', ['addtabon' => 'Config']);
      $PLUGIN_HOOKS['config_page']['processmaker'] = 'front/config.form.php';
   }

   Plugin::registerClass('PluginProcessmakerProfile', ['addtabon' => 'Profile']);

   Plugin::registerClass('PluginProcessmakerProcess_Profile');

   $PLUGIN_HOOKS['csrf_compliant']['processmaker'] = true;

   $PLUGIN_HOOKS['pre_show_item']['processmaker']
      = ['PluginProcessmakerProcessmaker', 'pre_show_item_processmaker'];

   //$PLUGIN_HOOKS['pre_item_form']['processmaker']
   //   = array('PluginProcessmakerProcessmaker', 'pre_item_form_processmakerticket');
   //$PLUGIN_HOOKS['post_item_form']['processmaker']
   //   = array('PluginProcessmakerProcessmaker', 'post_item_form_processmakerticket');

   $PLUGIN_HOOKS['pre_show_tab']['processmaker']
      = ['PluginProcessmakerProcessmaker', 'pre_show_tab_processmaker'];
   $PLUGIN_HOOKS['post_show_tab']['processmaker']
      = ['PluginProcessmakerProcessmaker', 'post_show_tab_processmaker'];

   // Display a menu entry ?
   if (Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
      // tools
      $PLUGIN_HOOKS['menu_toadd']['processmaker']['tools'] = 'PluginProcessmakerMenu';
   }

   if (Session::haveRightsOr('plugin_processmaker_case', [READ, UPDATE])) {
      // helpdesk
      $PLUGIN_HOOKS['menu_toadd']['processmaker']['helpdesk'] = 'PluginProcessmakerCase';
   }

   Plugin::registerClass('PluginProcessmakerProcess', [ 'massiveaction_nodelete_types' => true] );

   $hooks = [];
   foreach ($objects as $obj) {
      $hooks[$obj] = ['PluginProcessmakerProcessmaker', 'plugin_pre_item_add_processmaker'];
   }
   $PLUGIN_HOOKS['pre_item_add']['processmaker'] = $hooks;

   $hooks = [];
   foreach ($objects as $obj) {
      $hooks[$obj] = 'plugin_pre_item_update_processmaker';
   }
   $PLUGIN_HOOKS['pre_item_update']['processmaker'] = $hooks;

   $hooks = ['TicketSatisfaction' => 'plugin_item_update_processmaker_satisfaction',
             'User'               => 'plugin_item_update_processmaker_user'];
   foreach ($objects as $obj) {
      $hooks[$obj.'Task'] = 'plugin_item_update_processmaker_tasks';
   }
   $PLUGIN_HOOKS['item_update']['processmaker'] = $hooks;

   $hooks = [];
   foreach ($objects as $obj) {
      $hooks[$obj] = ['PluginProcessmakerProcessmaker', 'plugin_item_add_processmaker'];
   }
   $PLUGIN_HOOKS['item_add']['processmaker'] = $hooks;

   $hooks = [];
   foreach ($objects as $obj) {
      $hooks['NotificationTarget'.$obj] = ['PluginProcessmakerProcessmaker', 'plugin_item_get_data_processmaker'];
   }
   $PLUGIN_HOOKS['item_get_datas']['processmaker'] = $hooks;

   $hooks = [];
   foreach ($objects as $obj) {
      $hooks["PluginPdf".$obj."Task"] = ['PluginProcessmakerProcessmaker', 'plugin_item_get_pdfdata_processmaker'];
   }
   $PLUGIN_HOOKS['item_get_pdfdatas']['processmaker'] = $hooks;

   //$hooks = [];
   //foreach($objects as $obj){
   //   $hooks[$obj.'_User'] = 'plugin_pre_item_purge_processmaker';
   //}
   //$PLUGIN_HOOKS['pre_item_purge']['processmaker'] = $hooks;

   //$hooks = [];
   //foreach($objects as $obj){
   //   $hooks[$obj.'_User'] = 'plugin_item_purge_processmaker';
   //}
   //$PLUGIN_HOOKS['item_purge']['processmaker'] = $hooks;


   // Javascript
   $plugin = new Plugin();
   if ($plugin->isActivated('processmaker')
        && Session::getLoginUserID() ) {

      $url      = explode("/", $_SERVER['PHP_SELF']);
      $pageName = explode("?", array_pop($url));
      switch ($pageName[0]) {
         case "tracking.injector.php":
         case "helpdesk.public.php":
            $PLUGIN_HOOKS['add_javascript']['processmaker'] = "js/helpdesk.public.js.php";
            break;
         case "planning.php":
            $PLUGIN_HOOKS['add_javascript']['processmaker'] = "js/planning.js";
            break;
         case "central.php":
             $PLUGIN_HOOKS['add_javascript']['processmaker'] = "js/central.js";
             break;
         case "case.form.php":
         case "processmaker.helpdesk.form.php" :
            //$PLUGIN_HOOKS['add_javascript']['processmaker'] = "js/domain.js.php";
            break;
      }
   }

   $PLUGIN_HOOKS['use_massive_action']['processmaker'] = 1;

   $CFG_GLPI['planning_types'][] = 'PluginProcessmakerTask';
   $PLUGIN_HOOKS['post_init']['processmaker'] = 'plugin_processmaker_post_init';

   // in order to set rights when in helpdesk interface
   // otherwise post-only users can't see cases and then can't act on a case task.
   $PLUGIN_HOOKS['change_profile']['processmaker'] = 'plugin_processmaker_change_profile';

}


// Get the name and the version of the plugin - Needed
function plugin_version_processmaker() {
   return  [
      'name'          => 'Process Maker',
      'version'        => PROCESSMAKER_VERSION,
      'author'         => 'Olivier Moron',
      'license'        => 'GPLv3+',
      'homepage'       => 'https://github.com/tomolimo/processmaker',
      'requirements'   => [
         'glpi'   => [
            'min' => '9.2',
            'max' => '9.2.99'
         ],
       ]
   ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_processmaker_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '9.2', 'lt') || version_compare(GLPI_VERSION, '9.3', 'ge')) {
      echo "This plugin requires GLPI >= 9.2 and < 9.3";
      return false;
   }

   return true;
}

function plugin_processmaker_check_config($verbose = false) {

   return true;
}


function plugin_processmaker_haveRight($module, $right) {

   return Session::haveRight("plugin_processmaker_".$module, $right);
}

