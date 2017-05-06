<?php


if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class PluginProcessmakerProfile extends CommonDBTM {

    /**
     * Summary of getAllRights
     * @return array[]
     */
   static function getAllRights() {
      global $LANG;

      $rights = array(
                  array('itemtype' => 'PluginProcessmakerConfig',
                           'label'    =>  $LANG['processmaker']['profile']['process_config'],
                           'field'    => 'plugin_processmaker_config',
                   'rights' => array(READ    => __('Read'), UPDATE  => __('Update'))),
                   array('itemtype' => 'PluginProcessmakerConfig',
                           'label'    =>  $LANG['processmaker']['profile']['case_delete'],
                           'field'    => 'plugin_processmaker_deletecase',
                   'rights' => array(DELETE  => __('Delete')))
                   );

      return $rights;
   }


    /**
     * Summary of showForm
     * @param mixed $ID
     * @param mixed $openform
     * @param mixed $closeform
     * @return bool
     */
   function showForm($ID=0, $openform=TRUE, $closeform=TRUE) {
      global $LANG;

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
      $prof->displayRightsChoiceMatrix($rights, array('canedit'       => $canedit,
                                                 'default_class' => 'tab_bg_2',
                                                 'title'         => $LANG['processmaker']['title'][1]));

      if ($canedit && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $ID));
         echo Html::submit(_sx('button', 'Save'),
                          array('name' => 'update'));
         echo "</div>\n";
      }

      Html::closeForm();

   }

    /**
     * Summary of createAdminAccess
     * @param mixed $ID
     */
   static function createAdminAccess($ID) {

      self::addDefaultProfileInfos($ID, array('plugin_processmaker_config' => READ + UPDATE, 'plugin_processmaker_deletecase' => DELETE), true);

   }

    /**
     * Summary of getTabNameForItem
     * @param CommonGLPI $item
     * @param mixed      $withtemplate
     * @return string|string[]
     */
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      if ($item->getType()=='Profile') {
         return $LANG['processmaker']['title'][1];
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
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI;

      if ($item->getType()=='Profile') {
         $ID = $item->getID();
         $prof = new self();
         self::addDefaultProfileInfos($ID,
                                 array('plugin_processmaker_config'       => 0,
                                       'plugin_processmaker_deletecase'   => 0
                                       ));

             $prof->showForm($ID);
      }
      return true;
   }

    /**
     * @param $profile
     **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
      global $DB;

      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'") && $drop_existing) {
            $profileRight->deleteByCriteria(array('profiles_id' => $profiles_id, 'name' => $right));
         }
         if (!countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'")) {
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
