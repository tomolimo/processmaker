<?php
class PluginProcessmakerMenu extends CommonGLPI {
   static $rightname = 'plugin_processmaker_config';

   static function getMenuName() {
      return 'ProcessMaker';
   }

   static function getMenuContent() {

      if (!Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE])) {
         return;
      }

      $front_page = "/plugins/processmaker/front";
      $menu = [];
      $menu['title'] = self::getMenuName();
      $menu['page']  = "$front_page/process.php";
      $menu['links']['search'] = PluginProcessmakerProcess::getSearchURL(false);
      if (Session::haveRightsOr("config", [READ, UPDATE])) {
         $menu['links']['config'] = PluginProcessmakerConfig::getFormURL(false);
      }

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
