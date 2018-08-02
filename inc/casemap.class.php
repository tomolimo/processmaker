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

      $rand = rand();

      $proj = new PluginProcessmakerProcess;
      $proj->getFromDB($case->fields['plugin_processmaker_processes_id']);
      $project_type = $proj->fields['project_type'];

      $caseMapUrl = $PM_SOAP->serverURL.(
         $project_type=='bpmn' ?
            "/designer?prj_uid=".$proj->fields['process_guid']."&prj_readonly=true&app_uid=".$case->fields['case_guid']
            :
            "/cases/ajaxListener?action=processMap"
         )."&rand=$rand";

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

      $iframe = "<iframe
                  id='caseiframe-caseMap'
                  style='border: none;' width='100%'
                  src='$caseMapUrl'
                  onload=\"onOtherFrameLoad( 'caseMap', 'caseiframe-caseMap', 'body', ".($project_type=='bpmn' ? "true" : "false" )." );\">
                 </iframe>";

      $PM_SOAP->initCaseAndShowTab(['APP_UID' => $case->fields['case_guid'], 'DEL_INDEX' => 1], $iframe, $rand) ;

   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      return __('Map', 'processmaker');
   }

}