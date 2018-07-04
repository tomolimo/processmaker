<?php

/**
 * PluginProcessmakerCasemap short summary.
 *
 * casemap description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasemap extends CommonDBTM {

   static function displayTabContentForItem(CommonGLPI $case, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI, $PM_SOAP;

      $config = $PM_SOAP->config;
      $rand = rand();

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);
      $project_type = $proj->fields['project_type'];

      $caseMapUrl = $PM_SOAP->serverURL.(
         $project_type=='bpmn' ?
            "/designer?sid=".$PM_SOAP->getPMSessionID()."&prj_uid=".$proj->fields['process_guid']."&prj_readonly=true&app_uid=".$case->fields['case_guid']
            :
            "/cases/ajaxListener?sid=".$PM_SOAP->getPMSessionID()."&action=processMap" //&GLPI_PRO_UID={$proj->fields['process_guid']}"
         )."&glpi_domain={$config->fields['domain']}&rand=$rand&GLPI_APP_UID={$case->fields['case_guid']}&GLPI_PRO_UID={$proj->fields['process_guid']}";

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

      echo "<iframe id='caseiframe-caseMap' style='border: none;' width='100%' src='$caseMapUrl'
            onload=\"onOtherFrameLoad( 'caseMap', 'caseiframe-caseMap', 'body', ".($project_type=='bpmn' ? "true" : "false" )." );\"></iframe>";
   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      global $LANG;
      return $LANG['processmaker']['item']['case']['viewcasemap'];
   }

}