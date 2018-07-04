<?php

/**
 * casechangelog short summary.
 *
 * casechangelog description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasechangelog extends CommonDBTM {

   static function displayTabContentForItem(CommonGLPI $case, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI, $PM_SOAP;

      $config = $PM_SOAP->config;
      $rand = rand();

      ////$caseInfo = $case->getCaseInfo();

      //$proj = new PluginProcessmakerProcess;
      ////      $proj->getFromGUID( $caseInfo->processId );
      //$proj->getFromDB($case->fields['plugin_processmaker_processes_id']);
      //$project_type = $proj->fields['project_type'];

      $caseHistoryURL = $PM_SOAP->serverURL."/cases/ajaxListener?action=changeLogHistory&rand=$rand&glpi_domain={$config->fields['domain']}&GLPI_APP_UID={$case->fields['case_guid']}";

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

      echo "<iframe id='caseiframe-caseChangeLogHistory' style='border: none;' width='100%' src='$caseHistoryURL'
            onload=\"onOtherFrameLoad( 'caseChangeLogHistory', 'caseiframe-caseChangeLogHistory', 'body', 0 );\"></iframe>";
   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      global $LANG;
      return $LANG['processmaker']['item']['case']['changelog'];
   }
}