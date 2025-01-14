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
/**
 * casechangelog short summary.
 *
 * casechangelog description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasechangelog extends CommonDBTM {

   static function displayTabContentForItem(CommonGLPI $case, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $PM_SOAP;

      $rand = rand();
      $iframeId = "caseiframe-caseChangeLogHistory-{$rand}";

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);

      $glpi_data = urlencode(json_encode([
          'glpi_url'          => $CFG_GLPI['url_base'],
          'glpi_tabtype'      => 'changeloghistory',
          'glpi_tabpanelname' => 'caseChangeLogHistory',
          'glpi_iframeid'     => $iframeId,
          'glpi_elttagname'   => 'body',          
          'glpi_sid'          => $PM_SOAP->getPMSessionID(),
          'glpi_app_uid'      => $case->fields['case_guid'],
          'glpi_pro_uid'      => $proj->fields['process_guid'],
          ]));

      $url = $PM_SOAP->serverURL
          ."/cases/ajaxListener"
          ."?action=changeLogHistory"
          ."&sid=" . $PM_SOAP->getPMSessionID()
          ."&glpi_data=$glpi_data";

     echo "<iframe id='$iframeId' name='$iframeId' style='border:none;' class='tab_bg_2' width='100%' src='$url'></iframe>";

   }


   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0) {
      return __('Change log', 'processmaker');
   }
}
