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
 * PluginProcessmakerCrontaskaction is used to manage actions between cases
 *
 * Allows actions: routing cases (called slaves) from another case (called master)
 *
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCrontaskaction extends CommonDBTM {

   // formdata are of the form:
   // {"form":{"RELEASE_DONE":"0","btnGLPISendRequest":"submit"},"UID":"28421020557bffc5b374850018853291","__DynaformName__":"51126098657bd96b286ded7016691792_28421020557bffc5b374850018853291","__notValidateThisFields__":"[]","DynaformRequiredFields":"[]","APP_UID":"6077575685836f7d89cabe6013770123","DEL_INDEX":"4"}

   const CURL_ERROR   = 0;
   const WAITING_DATA = 1;
   const DATA_READY   = 2;
   const DONE         = 3;
   const NOT_DONE     = 4;

   function getName($options = array()) {
      return __(sprintf('Cron task action ID #%d', $this->getID()), 'processmaker');
   }


   static function canCreate() {
      return false;
   }

   static function canView() {
      return Session::haveRightsOr('plugin_processmaker_config', [READ, UPDATE]);
   }

   static function canUpdate() {
      return false;
   }

   static function canDelete() {
      return false;
   }

   static function canPurge() {
      return false;
   }

   function canUpdateItem() {
      return false;
   }

   function canDeleteItem() {
      return false;
   }

   function canPurgeItem() {
      return false;
   }

   function maybeDeleted() {
      return false;
   }

   static function getTypeName($nb = 0) {
      if ($nb>1) {
         return __('Cron task actions', 'processmaker');
      }
      return __('Cron task actions', 'processmaker');
   }

   function showForm ($ID, $options = ['candel'=>false]) {
      global $DB, $CFG_GLPI;

      //$this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Case number', 'processmaker')."</td><td>";
      echo "<input readonly size='100' type='text' name='plugin_processmaker_cases_id' value='".$this->fields["plugin_processmaker_cases_id"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Posted data to API', 'processmaker')."</td><td>";
      echo "<textarea readonly cols='100' rows='6' name='postdata' >".$this->fields["postdata"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Answer from API', 'processmaker')."</td><td>";
      echo "<textarea readonly cols='100' rows='6' name='retcode' >".$this->fields["retcode"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Status', 'processmaker')."</td><td>";
      echo "<input readonly size='100' type='text' name='state' value='".$this->fields["state"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Asynchronous data from API', 'processmaker')."</td><td>";
      echo "<textarea readonly cols='100' rows='6' name='formdata' >".$this->fields["formdata"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Asynchronous data from API', 'processmaker')."</td><td>";
      echo "<textarea readonly cols='100' rows='6' name='formdata' >".$this->fields["formdata"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Last update on')."</td><td>";
      echo "<input readonly size='100' type='text' name='state' value='".$this->fields["date_mod"]."'>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".__('Resend data to API', 'processmaker')."</td><td>";
      echo "<form name='form' method='post' action='".$this->getFormURL()."' enctype=\"multipart/form-data\">";
      echo "<button type='submit' class='vsubmit' name='resend' value='1'>".__('Resend', 'processmaker')."</button>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('id', ['value' => $ID]);

      echo "</td></tr>";
      echo "</table></div>";


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
          'field'              => 'id',
          'name'               => __('ID'),
          'datatype'           => 'itemlink',
          'itemlink_type'      => $this->getType(),
          'massiveaction'      => false
       ];

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'plugin_processmaker_cases_id',
         'name'               => __('Case number', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => '3',
         'table'              => $this->getTable(),
         'field'              => 'date_mod',
         'name'               => __('Last update'),
         'massiveaction'      => false,
         'datatype'           => 'datetime'
      ];

      $tab[] = [
         'id'                 => '4',
         'table'              => $this->getTable(),
         'field'              => 'postdata',
         'name'               => __('Posted data to API', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '5',
         'table'              => $this->getTable(),
         'field'              => 'retcode',
         'name'               => __('Answer from API', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];

      $tab[] = [
         'id'                 => '6',
         'table'              => $this->getTable(),
         'field'              => 'state',
         'name'               => __('Status', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => '7',
         'table'              => $this->getTable(),
         'field'              => 'formdata',
         'name'               => __('Asynchronous data from API', 'processmaker'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];


      return $tab;
   }

}
