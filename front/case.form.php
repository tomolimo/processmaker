<?php

include_once ("../../../inc/includes.php");


$locCase = new PluginProcessmakerCase();

function glpi_processmaker_case_reload_page() {
   global $PM_SOAP;
   // now redirect to item form page
   $config = $PM_SOAP->config;
   echo "<html><body><script>";
   if (isset($config->fields['domain']) && $config->fields['domain'] != '') {
      echo "document.domain='{$config->fields['domain']}';";
   }
   echo "</script><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>";
}


// check if it is from PM pages
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'route' && isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] )) {
   // then get item id from DB
   if ($locCase->getFromGUID($_REQUEST['APP_UID'])) {

      if (isset( $_REQUEST['form'] )) {
         $PM_SOAP->derivateCase($locCase, $_REQUEST);
      }
   }
   glpi_processmaker_case_reload_page();

} else
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete') {
   // delete case from case table, this will also delete the tasks
   if ($locCase->getFromDB($_POST['cases_id']) && $locCase->deleteCase()) {
      Session::addMessageAfterRedirect(__('Case has been deleted!', 'processmaker'), true, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Unable to delete case!', 'processmaker'), true, ERROR);
   }
   // will redirect to item or to list if no item
   $locCase->redirectToList();

} else
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'cancel') {
   // cancel case from PM
   $locCase = new PluginProcessmakerCase;
   $locCase->getFromDB($_POST['cases_id']);
   $resultPM = $PM_SOAP->cancelCase($locCase->fields['case_guid']);
   if ($resultPM->status_code === 0) {
      if ($locCase->cancelCase()) {
         Session::addMessageAfterRedirect(__('Case has been cancelled!', 'processmaker'), true, INFO);
      } else {
         Session::addMessageAfterRedirect(__('Unable to cancel case!', 'processmaker'), true, ERROR);
      }
   } else {
      Session::addMessageAfterRedirect(__('Unable to cancel case!', 'processmaker'), true, ERROR);
   }
   Html::back();
} else
if (isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID'])) {
   // Claim task management
   // here we are in a Claim request
   $myCase = new PluginProcessmakerCase;
   if ($myCase->getFromGUID( $_REQUEST['form']['APP_UID'] )) {

      $pmClaimCase = $PM_SOAP->claimCase($myCase->fields['case_guid'], $_REQUEST['DEL_INDEX'] );

      // now manage tasks associated with item
      $PM_SOAP->claimTask($myCase->getID(), $_REQUEST['DEL_INDEX']);
   }
   glpi_processmaker_case_reload_page();

} else
if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {

   if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
      Html::helpHeader(__('Process cases', 'processmaker'), '', $_SESSION["glpiname"]);
   } else {
      Html::header(__('Process cases', 'processmaker'), $_SERVER['PHP_SELF'], "helpdesk", "PluginProcessmakerCase", "cases");
   }

   if (!$PM_SOAP->config->fields['maintenance']) {
      if ($locCase->getFromDB($_REQUEST['id'])) {
         $locCase->display($_REQUEST);
      }
   } else {
      PluginProcessmakerProcessmaker::showUnderMaintenance();
   }

   Html::footer();
}



