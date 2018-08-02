<?php

/**
 * casehistory short summary.
 *
 * casehistory description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCasehistory extends CommonDBTM {
   static function displayTabContentForItem(CommonGLPI $case, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI, $PM_SOAP;

      $rand = rand();

      $caseHistoryURL = $PM_SOAP->serverURL
         ."/cases/ajaxListener?action=caseHistory&rand=$rand";

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>";

      $iframe = "<iframe
                  id='caseiframe-caseHistory'
                  style='border: none;'
                  width='100%'
                  src='$caseHistoryURL'
                  onload=\"onOtherFrameLoad( 'caseHistory', 'caseiframe-caseHistory', 'body', 0 );\">
                </iframe>";

      $PM_SOAP->initCaseAndShowTab(['APP_UID' => $case->fields['case_guid'], 'DEL_INDEX' => 1], $iframe, $rand) ;

   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0){
      return __('History', 'processmaker');
   }
}