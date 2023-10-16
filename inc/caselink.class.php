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

   static function canUpdate() {
      return Session::haveRight('plugin_processmaker_config', UPDATE);
   }

   static function canDelete() {
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

   static function getTypeName($nb = 0) {
      if ($nb>1) {
         return __('Case-links', 'processmaker');
      }
      return __('Case-link', 'processmaker');
   }

   function showForm ($ID, $options = ['candel'=>false]) {
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
      echo "<td >".__('Synchronous', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_synchronous", $this->fields["is_synchronous"]);
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

      //echo "<tr class='tab_bg_1'>";
      //echo "<td >".__('Reassign target task', 'processmaker')."</td><td>";
      //Dropdown::showYesNo("is_targettoreassign", $this->fields["is_targettoreassign"]);
      //echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Impersonate target task user', 'processmaker')."</td><td>";
      Dropdown::showYesNo("is_targettoimpersonate", $this->fields["is_targettoimpersonate"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('External application JSON config', 'processmaker')."</td><td>";
      echo "<textarea cols='100' rows='6' name='externalapplication' >".$this->fields["externalapplication"]."</textarea>";
      echo "</td></tr>";

      $this->showFormButtons($options );

   }


   /**
     * Summary of rawSearchOptions
    * @return mixed
    */
   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
              'id'                 => 'common',
              'name'               => __('ProcessMaker', 'processmaker')
           ];

      $tab[] = [
          'id'                 => '1',
          'table'              => $this->getTable(),
          'field'              => 'name',
          'name'               => __('Name'),
          'datatype'           => 'itemlink',
          'itemlink_type'      => 'PluginProcessmakerCaselink',
          'massiveaction'      => false
       ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this->getTable(),
         'field'              => 'is_active',
         'name'               => __('Active'),
         'massiveaction'      => true,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '9',
         'table'              => $this->getTable(),
         'field'              => 'date_mod',
         'name'               => __('Last update'),
         'massiveaction'      => false,
         'datatype'           => 'datetime'
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'is_externaldata',
         'name'               => __('External data', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '11',
         'table'              => $this->getTable(),
         'field'              => 'is_self',
         'name'               => __('Self', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '12',
         'table'              => $this->getTable(),
         'field'              => 'is_targettoclaim',
         'name'               => __('Claim target task', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'bool'
      ];

      $tab[] = [
         'id'                 => '13',
         'table'              => $this->getTable(),
         'field'              => 'externalapplication',
         'name'               => __('External application JSON config', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '14',
         'table'              => $this->getTable(),
         'field'              => 'sourcetask_guid',
         'name'               => __('Source task GUID', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '15',
         'table'              => $this->getTable(),
         'field'              => 'targettask_guid',
         'name'               => __('Target task GUID', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'targetdynaform_guid',
         'name'               => __('Target dynaform GUID', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '17',
         'table'              => $this->getTable(),
         'field'              => 'targetprocess_guid',
         'name'               => __('Target process GUID', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '18',
         'table'              => $this->getTable(),
         'field'              => 'sourcecondition',
         'name'               => __('Source condition', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      return $tab;
   }


   function prepareInputForUpdate($input) {
      return Toolbox::unclean_cross_side_scripting_deep($input);
   }


   function prepareInputForAdd($input) {
      return Toolbox::unclean_cross_side_scripting_deep($input);
   }


}
