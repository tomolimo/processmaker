<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2022 by Raynet SAS a company of A.Raymond Network.

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
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class PluginProcessmakerProfile extends CommonDBTM {

    /**
     * Summary of getAllRights
     * @return array[]
     */
   static function getAllRights() {
      $rights = [
                  ['itemtype' => 'PluginProcessmakerConfig',
                        'label'    =>  __('Process configuration', 'processmaker'),
                        'field'    => 'plugin_processmaker_config',
                        'rights' => [READ    => __('Read'), UPDATE  => __('Update')]],
                   ['itemtype' => 'PluginProcessmakerConfig',
                        'label'    =>  __('Cases', 'processmaker'),
                        'field'    => 'plugin_processmaker_case',
                        'rights' => [READ => __('Read'), CANCEL => __('Cancel', 'processmaker'), DELETE  => __('Delete'), ADHOC_REASSIGN => __('Ad Hoc user re-assign', 'processmaker')]]
                   ];

      return $rights;
   }


    /**
     * Summary of showForm
     * @param mixed $ID
     * @param mixed $openform
     * @param mixed $closeform
     * @return bool
     */
   function showForm($ID = 0, $openform = true, $closeform = true) {

      if (!Session::haveRight("profile", READ)) {
         return false;
      }

      $canedit = Session::haveRight("profile", UPDATE);
      $prof = new Profile();
      if ($ID) {
         $prof->getFromDB($ID);
      }
      echo "<form action='".$prof->getFormURL()."' method='post'>";
      $rights = $this->getAllRights();
      $prof->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                 'default_class' => 'tab_bg_2',
                                                 'title'         => __('ProcessMaker', 'processmaker')]);

      if ($canedit && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::submit(_sx('button', 'Save'),
                          ['name' => 'update']);
         echo "</div>\n";
      }

      Html::closeForm();

      return true;
   }

    /**
     * Summary of createAdminAccess
     * @param mixed $ID
     */
   static function createAdminAccess($ID) {
      self::addDefaultProfileInfos($ID, ['plugin_processmaker_config' => READ + UPDATE, 'plugin_processmaker_case' => READ + DELETE + CANCEL + ADHOC_REASSIGN], true);
   }

    /**
     * Summary of getTabNameForItem
     * @param CommonGLPI $item
     * @param mixed      $withtemplate
     * @return string|string[]
     */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType()=='Profile') {
         return __('ProcessMaker', 'processmaker');
      }
      return '';
   }

    /**
     * Summary of displayTabContentForItem
     * @param CommonGLPI $item
     * @param mixed      $tabnum
     * @param mixed      $withtemplate
     * @return bool
     */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      if ($item->getType()=='Profile') {
         $ID = $item->getID();
         $prof = new self();
         self::addDefaultProfileInfos($ID,
                                 ['plugin_processmaker_config' => 0,
                                       'plugin_processmaker_case'   => 0
                                       ]);

             $prof->showForm($ID);
      }
      return true;
   }

    /**
     * @param $profile
     **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
      global $DB;
      $dbu = new DbUtils;
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights', ['AND' => ['profiles_id' => $profiles_id, 'name' => $right]]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights', ['AND' => ['profiles_id' => $profiles_id, 'name' => $right]])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }
}
