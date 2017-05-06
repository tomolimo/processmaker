<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 *  This class manages the mail settings
 */
class ProcessmakerConfig extends CommonDBTM {

    var $table = 'glpi_plugins_processmaker_config';


   static function getTypeName($nb=0) {
      global $LANG;

      return 'Process Maker Plugin Configuration';
   }


   function defineTabs($options=array()) {

      $ong = array();
      $this->addStandardTab(__CLASS__, $ong, $options);

      return $ong;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG, $CFG_GLPI;

      switch ($item->getType()) {
         case __CLASS__ :
            $tabs[1] = $LANG['common'][12];
             return $tabs;
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI;

      if ($item->getType() == __CLASS__) {
         switch ($tabnum) {
            case 1 :
               $item->showFormMailServerConfig();
                break;
         }
      }
      return true;
   }


    /**
     * Print the mailing config form
     *
     * @param $ID integer ID of the item
     * @param $options array
     *     - target filename : where to go when done.
     *     - tabs integer : ID of the tab to display
     *
     * @return Nothing (display)
     *
     **/
   function showForm($ID, $options=array()) {
      global $LANG, $CFG_GLPI;

      if (!Session::haveRight("config", UPDATE)) {
         return false;
      }
      if (!$CFG_GLPI['use_mailing']) {
         $options['colspan'] = 1;
      }

      $this->getFromDB($ID);
      $this->showTabs($options);
      $this->addDivForTabs();
      return true;
   }


   static function canCreate() {
      return Session::haveRight('config', UPDATE);
   }


   static function canView() {
      return Session::haveRight('config', READ);
   }



}


