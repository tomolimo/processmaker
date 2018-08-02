<?php

/**
 * PluginProcessmakerCaselink short summary.
 *
 * PluginProcessmakerCaselink description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCaselink extends CommonDBTM {

   static function canCreate() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   static function canView() {
      return Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE]);
   }

   static function canUpdate( ) {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   static function canDelete( ) {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   static function canPurge() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   function canUpdateItem() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   function canDeleteItem() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   function canPurgeItem() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   function maybeDeleted() {
      return false;
   }

   static function getTypeName($nb=0) {
      if ($nb>1) {
         return __('Case-links', 'processmaker');
      }
      return __('Case-link', 'processmaker');
   }

   function showForm ($ID, $options=array('candel'=>false)) {
      global $DB, $CFG_GLPI;

      $options['candel'] = true;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Name')."</td><td>";
      echo "<input size='100' type='text' name='name' value='".Html::cleanInputText($this->fields["name"])."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Active')."</td><td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('External data', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_externaldata", $this->fields["is_externaldata"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Self', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_self", $this->fields["is_self"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Source task GUID', 'processmaker')."</td><td>";
      //PluginProcessmakerTaskCategory::dropdown(array('name'                => 'plugin_processmaker_taskcategories_id_source',
      //                                               'display_emptychoice' => false,
      //                                               'value'               => $this->fields['plugin_processmaker_taskcategories_id_source']));
      echo "<input size='100' type='text' name='sourcetask_guid' value='".$this->fields["sourcetask_guid"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Target task GUID', 'processmaker')."</td><td>";
      //PluginProcessmakerTaskCategory::dropdown(array('name'                => 'plugin_processmaker_taskcategories_id_target',
      //                                               'display_emptychoice' => false,
      //                                               'value'               => $this->fields['plugin_processmaker_taskcategories_id_target']));
      echo "<input size='100' type='text' name='targettask_guid' value='".$this->fields["targettask_guid"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Target process GUID', 'processmaker')."</td><td>";
      //Dropdown::show( 'PluginProcessmakerProcess', array('name'                => 'plugin_processmaker_processes_id',
      //                                          'display_emptychoice' => true,
      //                                          'value'               => $this->fields['plugin_processmaker_processes_id'],
      //                                          'condition' => 'is_active = 1'));
      echo "<input size='100' type='text' name='targetprocess_guid' value='".$this->fields["targetprocess_guid"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Target dynaform GUID', 'processmaker')."</td><td>";
      echo "<input size='100' type='text' name='targetdynaform_guid' value='".$this->fields["targetdynaform_guid"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Source condition', 'processmaker')."</td><td>";
      //echo "<input size='100' type='text' name='sourcecondition' value='".$this->fields["sourcecondition"]."'>";
      echo "<textarea cols='100' rows='3' name='sourcecondition' >".$this->fields["sourcecondition"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Claim target task', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_targettoclaim", $this->fields["is_targettoclaim"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('External application JSON config', 'processmaker')."</td><td>";
      echo "<textarea cols='100' rows='6' name='externalapplication' >".$this->fields["externalapplication"]."</textarea>";
      echo "</td></tr>";


      $this->showFormButtons($options );

   }


   /**
    * Summary of getSearchOptions
    * @return mixed
    */
   function getSearchOptions() {
      $tab = array();

      $tab['common'] = __('ProcessMaker', 'processmaker');

      $tab[1]['table']         = $this->getTable();
      $tab[1]['field']         = 'name';
      $tab[1]['name']          = __('Name');
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = $this->getType();

      $tab[8]['table']         = $this->getTable();
      $tab[8]['field']         = 'is_active';
      $tab[8]['name']          = __('Active');
      $tab[8]['massiveaction'] = true;
      $tab[8]['datatype']      = 'bool';

      $tab[9]['table']         = $this->getTable();
      $tab[9]['field']         = 'date_mod';
      $tab[9]['name']          = __('Last update');
      $tab[9]['massiveaction'] = false;
      $tab[9]['datatype']      = 'datetime';

      $tab[10]['table']         = $this->getTable();
      $tab[10]['field']         = 'is_externaldata';
      $tab[10]['name']          = __('External data', 'processmaker');
      $tab[10]['massiveaction'] = false;
      $tab[10]['datatype']      = 'bool';

      $tab[11]['table']         = $this->getTable();
      $tab[11]['field']         = 'is_self';
      $tab[11]['name']          = __('Self', 'processmaker');
      $tab[11]['massiveaction'] = false;
      $tab[11]['datatype']      = 'bool';

      $tab[12]['table']         = $this->getTable();
      $tab[12]['field']         = 'is_targettoclaim';
      $tab[12]['name']          = __('Claim target task', 'processmaker');
      $tab[12]['massiveaction'] = false;
      $tab[12]['datatype']      = 'bool';

      $tab[13]['table']         = $this->getTable();
      $tab[13]['field']         = 'externalapplication';
      $tab[13]['name']          = __('External application JSON config', 'processmaker');
      $tab[13]['massiveaction'] = false;
      $tab[13]['datatype']      = 'text';

      $tab[14]['table']         = $this->getTable();
      $tab[14]['field']         = 'sourcetask_guid';
      $tab[14]['name']          = __('Source task GUID', 'processmaker');
      $tab[14]['massiveaction'] = false;
      $tab[14]['datatype']      = 'text';

      $tab[15]['table']         = $this->getTable();
      $tab[15]['field']         = 'targettask_guid';
      $tab[15]['name']          = __('Target task GUID', 'processmaker');
      $tab[15]['massiveaction'] = false;
      $tab[15]['datatype']      = 'text';

      $tab[16]['table']         = $this->getTable();
      $tab[16]['field']         = 'targetdynaform_guid';
      $tab[16]['name']          = __('Target dynaform GUID', 'processmaker');
      $tab[16]['massiveaction'] = false;
      $tab[16]['datatype']      = 'text';

      $tab[17]['table']         = $this->getTable();
      $tab[17]['field']         = 'targetprocess_guid';
      $tab[17]['name']          = __('Target process GUID', 'processmaker');
      $tab[17]['massiveaction'] = false;
      $tab[17]['datatype']      = 'text';

      $tab[18]['table']         = $this->getTable();
      $tab[18]['field']         = 'sourcecondition';
      $tab[18]['name']          = __('Source condition', 'processmaker');
      $tab[18]['massiveaction'] = false;
      $tab[18]['datatype']      = 'text';

//$tab[14]['table']         = 'glpi_taskcategories';
      //$tab[14]['field']         = 'completename'; //'plugin_processmaker_taskcategories_id_source';
      //$tab[14]['name']          = __('Source task');
      //$tab[14]['massiveaction'] = false;
      //$tab[14]['datatype']      = 'dropdown';
      //$tab[14]['forcegroupby']  = true;
      //$tab[14]['joinparams']    =
      //   [
      //      'beforejoin' => [
      //         'table' => 'glpi_plugin_processmaker_taskcategories',
      //         'joinparams' => [
      //            'beforejoin' => [
      //               'table' => 'glpi_plugin_processmaker_caselinks'
      //            ]
      //         ]
      //      ]
      //   ];


      return $tab;
   }

   //static function getMenuContent() {

   //   if (!Session::haveRight('entity', READ)) {
   //      return;
   //   }

   //   $front_page = "/plugins/processmaker/front";
   //   $menu = array();
   //   //$menu['title'] = self::getMenuName();
   //   //$menu['page']  = "$front_page/caselink.php";

   //   $itemtypes = array('PluginProcessmakerCaselink' => 'processmakercaselinks');

   //   foreach ($itemtypes as $itemtype => $option) {
   //      $menu['options'][$option]['title']           = $itemtype::getTypeName(Session::getPluralNumber());
   //      switch( $itemtype ) {
   //         case 'PluginProcessmakerCaselink':
   //            $menu['options'][$option]['page']            = $itemtype::getSearchURL(false);
   //            $menu['options'][$option]['links']['search'] = $itemtype::getSearchURL(false);
   //            if ($itemtype::canCreate()) {
   //               $menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
   //            }
   //            break ;
   //         default :
   //            $menu['options'][$option]['page']            = PluginProcessmakerCaselink::getSearchURL(false);
   //            break ;
   //      }

   //   }
   //   return $menu;
   //}
}