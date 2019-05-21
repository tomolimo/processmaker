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

   static function displayTabContentForItem(CommonGLPI $case, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI, $PM_SOAP;

      $rand = rand();

      $caseHistoryURL = $PM_SOAP->serverURL."/cases/ajaxListener?action=changeLogHistory&rand=$rand";

      $PM_SOAP->echoDomain();
      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.js'></script>"; //?rand=$rand'

      $iframe = "<iframe
                  id='caseiframe-caseChangeLogHistory'
                  style='border: none;'
                  width='100%'
                  src='$caseHistoryURL'
                  onload=\"onOtherFrameLoad( 'caseChangeLogHistory', 'caseiframe-caseChangeLogHistory', 'body', 0 );\">
                 </iframe>";

      $PM_SOAP->initCaseAndShowTab(['APP_UID' => $case->fields['case_guid'], 'DEL_INDEX' => 1], $iframe, $rand);

   }

   function getTabNameForItem(CommonGLPI $case, $withtemplate = 0) {
      return __('Change log', 'processmaker');
   }
}
