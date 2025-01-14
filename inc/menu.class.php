<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

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
class PluginProcessmakerMenu extends CommonGLPI {
   static $rightname = 'plugin_processmaker_config';

   static function getMenuName() {
      return 'ProcessMaker';
   }

   static function getMenuContent() {

      if (!Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
         return [];
      }

      //$pm_plugin_url = Plugin::getWebDir('processmaker');
      $menu = [];
      $menu['title'] = self::getMenuName();
      $menu['page']  = PluginProcessmakerProcess::getSearchURL(false);
      $menu['links']['search'] = PluginProcessmakerProcess::getSearchURL(false);
      if (Session::haveRightsOr("config", [READ, UPDATE])) {
         $menu['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
      }
      //$menu['icon'] = '{% verbatim %}"></i><img src="'.$pm_plugin_url.'/pics/processmaker-xxs.png" style="vertical-align: middle;"/><i class="{% endverbatim %}';

      //$menu['icon'] = "\"src=\"$pm_plugin_url/pics/processmaker-xxs.png\" style=\"vertical-align: middle;";
      $itemtypes = ['PluginProcessmakerProcess' => 'processes',
                    'PluginProcessmakerCaselink' => 'caselinks'
            ];

      foreach ($itemtypes as $itemtype => $option) {
         $menu['options'][$option]['title']           = $itemtype::getTypeName(Session::getPluralNumber());
         $menu['options'][$option]['page']            = $itemtype::getSearchURL(false);
         $menu['options'][$option]['links']['search'] = $itemtype::getSearchURL(false);
         if (Session::haveRightsOr("config", [READ, UPDATE])) {
            $menu['options'][$option]['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
         }
         switch ($itemtype) {
            case 'PluginProcessmakerProcess':

               //if ($itemtype::canCreate()) {
               //   $menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
               //}
               break;
            case 'PluginProcessmakerCaselink':
               if (Session::haveRight("plugin_processmaker_config", UPDATE)) {
                  $menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
               }
               break;

            default :
               $menu['options'][$option]['page']            = PluginProcessmakerProcess::getSearchURL(false);
               break;
         }

      }
      return $menu;
   }


}
