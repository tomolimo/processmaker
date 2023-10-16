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
/**
 * PluginProcessmakerCasemap short summary.
 *
 * casemap description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasemap extends CommonDBTM {


   /**
    * Summary of displayTabContentForItem
    * @param CommonGLPI $case 
    * @param mixed $tabnum 
    * @param mixed $withtemplate 
    */
   static function displayTabContentForItem(CommonGLPI $case, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $PM_SOAP;

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);
      $project_type = $proj->fields['project_type'];

      $rand = rand();
      $iframeId = "caseiframe-caseMap-{$rand}";

      $glpi_data = urlencode(json_encode([
          'glpi_url'          => $CFG_GLPI['url_base'],
          'glpi_tabtype'      => 'map',
          'glpi_tabpanelname' => 'caseMap',
          'glpi_iframeid'     => $iframeId,
          'glpi_elttagname'   => 'body', //'p-center-layout'
          'glpi_isbpmn'       => $project_type == 'bpmn' ? true : false,
          'glpi_sid'          => $PM_SOAP->getPMSessionID(),
          'glpi_app_uid'      => $case->fields['case_guid'],
          'glpi_pro_uid'      => $proj->fields['process_guid'],

          ]));

      $url = "/designer?prj_uid=".$proj->fields['process_guid']."&prj_readonly=true&app_uid=".$case->fields['case_guid']; // BPMN default value (v3)
      if ($project_type != 'bpmn') {
          // classic project type (v2)
          $url = "/cases/ajaxListener?action=processMap";
      }

      $url = $PM_SOAP->serverURL
          .$url
          ."&sid=" . $PM_SOAP->getPMSessionID()
          ."&glpi_data=$glpi_data";

     echo "<iframe id='$iframeId' name='$iframeId' style='border:none;' class='tab_bg_2' width='100%' src='$url'></iframe>";

   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0) {
      return __('Map', 'processmaker');
   }

}
