<?php
/*
 -------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */
define('PROCESSMAKER_VERSION', '5.0.2');

// Minimal GLPI version, inclusive
define('PLUGIN_PROCESSMAKER_MIN_GLPI', '10.0');
// Maximum GLPI version, exclusive
define('PLUGIN_PROCESSMAKER_MAX_GLPI', '10.1');

// Minimal PM version, inclusive
define('PLUGIN_PROCESSMAKER_MIN_PM', '3.3.0-community-RE-2.0');
// Maximum PM version, inclusive
define('PLUGIN_PROCESSMAKER_MAX_PM', '3.3.0-community-RE-2.99');

define('PLUGIN_PROCESSMAKER_ROOT', Plugin::getPhpDir('processmaker'));


// used for case cancellation
define("CANCEL", 256);
// used for ad-hoc user re-assign
define("ADHOC_REASSIGN", 512);

// Init the hooks of the plugins -Needed
function plugin_init_processmaker() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['processmaker'] = true;

   $plugin = new Plugin();
   if ($plugin->isActivated('processmaker')) {

      $objects = ['Ticket', 'Change', 'Problem'];

      Plugin::registerClass('PluginProcessmakerProcessmaker');

      Plugin::registerClass('PluginProcessmakerCase', ['addtabon' => $objects, 'notificationtemplates_types' => true]);

      Plugin::registerClass('PluginProcessmakerTask', ['notificationtemplates_types' => true]);

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

      $PLUGIN_HOOKS['pre_show_tab']['processmaker']
         = ['PluginProcessmakerProcessmaker', 'pre_show_tab_processmaker'];

      $PLUGIN_HOOKS['show_in_timeline']['processmaker']
         = ['PluginProcessmakerProcessmaker', 'show_in_timeline_processmaker'];
      //$PLUGIN_HOOKS['post_show_tab']['processmaker']
      //   = ['PluginProcessmakerProcessmaker', 'post_show_tab_processmaker'];

      // Display a menu entry ?
      if (Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
         // tools
         $PLUGIN_HOOKS['menu_toadd']['processmaker']['tools'] = 'PluginProcessmakerMenu';
      }

      if (Session::haveRightsOr('plugin_processmaker_case', [READ, UPDATE])) {
         // helpdesk
         $PLUGIN_HOOKS['menu_toadd']['processmaker']['helpdesk'] = 'PluginProcessmakerCase';
      }

      Plugin::registerClass('PluginProcessmakerProcess'); // , [ 'massiveaction_nodelete_types' => true] );

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

      // Javascript
      $plugin = new Plugin();
      if ($plugin->isActivated('processmaker')
           && Session::getLoginUserID() ) {

         $url      = explode("/", $_SERVER['PHP_SELF']);
         $pageName = explode("?", array_pop($url));
         switch ($pageName[0]) {
            case "tracking.injector.php":
            case "helpdesk.public.php":
               $PLUGIN_HOOKS['add_javascript']['processmaker'] = ["js/helpdesk.public.js.php"];
               break;
            case "planning.php":
               $PLUGIN_HOOKS['add_javascript']['processmaker'] = ["js/planning.js"];
               break;
            case "central.php":
               $PLUGIN_HOOKS['add_javascript']['processmaker'] = ["js/central.js"];
               break;
         }
         $PLUGIN_HOOKS['add_javascript']['processmaker'][] = "js/processmaker_icon.js";

         //$PLUGIN_HOOKS['add_javascript']['processmaker'][] = "js/jsloader.js";
         $PLUGIN_HOOKS['add_javascript']['processmaker'][] = "js/cases.js";

         // css
         $PLUGIN_HOOKS['add_css']['processmaker'] = 'css/task.css';
      }

      $PLUGIN_HOOKS['use_massive_action']['processmaker'] = 1;

      $CFG_GLPI['planning_types'][] = 'PluginProcessmakerTask';
      $PLUGIN_HOOKS['post_init']['processmaker'] = 'plugin_processmaker_post_init';

      // in order to set rights when in helpdesk interface
      // otherwise post-only users can't see cases and then can't act on a case task.
      $PLUGIN_HOOKS['change_profile']['processmaker'] = 'plugin_processmaker_change_profile';

      // in order to manage the password encryption which has been in glpi_configs table since 4.4.0
      $PLUGIN_HOOKS['secured_configs']['processmaker'] = [
          'pm_admin_passwd',
          'pm_dbserver_passwd',
          ];

      // in order to push some info to javascript
      $PLUGIN_HOOKS['redefine_menus']['processmaker'] = 'plugin_processmaker_redefine_menus';

   }

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
            'min' => PLUGIN_PROCESSMAKER_MIN_GLPI,
            'max' => PLUGIN_PROCESSMAKER_MAX_GLPI
         ],
      ]
   ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_processmaker_check_prerequisites() {
   if (version_compare(GLPI_VERSION, PLUGIN_PROCESSMAKER_MIN_GLPI, 'lt') || version_compare(GLPI_VERSION, PLUGIN_PROCESSMAKER_MAX_GLPI, 'ge')) {
      echo "This plugin requires GLPI >= ". PLUGIN_PROCESSMAKER_MIN_GLPI ." and < " . PLUGIN_PROCESSMAKER_MAX_GLPI;
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

