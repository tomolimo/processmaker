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
include_once ("../../../inc/includes.php");

Session::checkLoginUser();

$locCase = new PluginProcessmakerCase();

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'route' && isset($_REQUEST['APP_UID']) && isset($_REQUEST['DEL_INDEX'])) {
   // then get item id from DB
   if ($locCase->getFromGUID($_REQUEST['APP_UID'])) {
      $PM_SOAP->derivateCase($locCase, $_REQUEST);
   }
   //glpi_processmaker_case_reload_page();
   Html::back();

} else if (isset($_REQUEST['purge'])) {
   // delete case from case table, this will also delete the tasks
   if ($locCase->getFromDB($_REQUEST['id']) && $locCase->deleteCase()) {
      Session::addMessageAfterRedirect(__('Case has been deleted!', 'processmaker'), true, INFO);
   } else {
      Session::addMessageAfterRedirect(__('Unable to delete case!', 'processmaker'), true, ERROR);
   }
   // will redirect to item or to list if no item
   $locCase->redirectToList();

} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'cancel') {
   // cancel case from PM
   $locCase = new PluginProcessmakerCase;
   $locCase->getFromDB($_POST['cases_id']);
   $resultPM = $PM_SOAP->cancelCase($locCase->fields['case_guid']);
   if ($resultPM->status_code === 0) {
      if ($locCase->cancelCase()) {
         Session::addMessageAfterRedirect(__('Case has been cancelled!', 'processmaker'), true, INFO);
      } else {
         Session::addMessageAfterRedirect(__('Unable to cancel case!', 'processmaker'), true, ERROR);
         Toolbox::logError(__('Unable to cancel tasks in case!', 'processmaker') . "\n" . print_r($resultPM, true));
      }
   } else {
      Session::addMessageAfterRedirect(__('Unable to cancel case!', 'processmaker'), true, ERROR);
      Toolbox::logError(__('Unable to cancel case!', 'processmaker') . "\n" . print_r($resultPM, true));
   }
   Html::back();

} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'claim' && isset($_REQUEST['APP_UID']) && isset($_REQUEST['DEL_INDEX'])) {
   // Claim task management
   // here we are in a Claim request
   $myCase = new PluginProcessmakerCase;
   $myCase->getFromGUID($_REQUEST['APP_UID']);

   $pmClaimCase = $PM_SOAP->claimCase($myCase->fields['case_guid'], $_REQUEST['DEL_INDEX'] );
   // now manage tasks associated with item
   $PM_SOAP->claimTask($myCase->getID(), $_REQUEST['DEL_INDEX']);

   Html::back();

} else if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {

   if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
      Html::helpHeader(__('Process cases', 'processmaker'), '', $_SESSION["glpiname"]);
   } else {
      Html::header(__('Process cases', 'processmaker'), $_SERVER['PHP_SELF'], "helpdesk", "PluginProcessmakerCase", "cases");
   }

   if (!$PM_SOAP->config['maintenance']) {
      if ($locCase->getFromDB($_REQUEST['id'])) {
         $locCase->display($_REQUEST);
      }
   } else {
      PluginProcessmakerProcessmaker::showUnderMaintenance();
   }

   Html::footer();
}



