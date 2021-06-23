<?php

// ----------------------------------------------------------------------
// Original Author of file: MoronO
// Purpose of file: mimic tracking.injector.php
// ----------------------------------------------------------------------
if (isset( $_REQUEST['_glpi_csrf_token'] )) {
   define('GLPI_KEEP_CSRF_TOKEN', true);
}
$PM_POST = $_POST;
$PM_REQUEST = $_REQUEST;
$PM_GET = $_GET;
include( "../../../inc/includes.php" );

if (empty($_REQUEST["_type"])
    || ($_REQUEST["_type"] != "Helpdesk")
    || !$CFG_GLPI["use_anonymous_helpdesk"]) {
   Session::checkRight("ticket", CREATE);
}

// Security check
if (empty($_REQUEST) || count($_REQUEST) == 0) {
   Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php");
}

// here we are going to test if we must start a process
if (isset($_REQUEST["_from_helpdesk"]) && $_REQUEST["_from_helpdesk"] == 1
    && isset($_REQUEST["type"]) //&& $_REQUEST["type"] == Ticket::DEMAND_TYPE
    && isset($_REQUEST["itilcategories_id"])
    && isset($_REQUEST["entities_id"])) {
   // here we have to check if there is an existing process in the entity and with the category
   // if yes we will start it
   // if not we will continue
   // special case if RUMT plugin is enabled and no process is available and category is 'User Management' then must start RUMT.

   $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_REQUEST["itilcategories_id"], $_REQUEST["type"], $_SESSION['glpiactiveprofile']['id'], $_REQUEST["entities_id"] );

   // currently only one process should be assigned to this itilcategory so this array should contain only one row
   $processQt = count( $processList );
   if ($processQt == 1) {
      $_REQUEST['action']='newcase';
      $_REQUEST['plugin_processmaker_processes_id'] = $processList[0]['id'];
      include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.form.php");
      die();
   } else if ($processQt > 1) {
      // in this case we should show the process dropdown selection
      include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.helpdesk.form.php");
      die();
   } else {
      // in this case should start RUMT
      // if and only if itilcategories_id matches one of the 'User Management' categories
      // could be done via ARBehviours or RUMT itself
      $userManagementCat = [ 100556, 100557, 100558 ];
      $plug = new Plugin;
      if ($processQt == 0 && in_array( $_REQUEST["itilcategories_id"], $userManagementCat) && $plug->isActivated('rayusermanagementticket' )) {
         Html::redirect($CFG_GLPI['root_doc']."/plugins/rayusermanagementticket/front/rayusermanagementticket.helpdesk.public.php");
      }
   }
}

// prepare environment for std tracking.injector.php
// switch to front dir
chdir(GLPI_ROOT."/front");
// revert back $_POST, $_GET and $_REQUEST
$_GET = $PM_GET;
$_POST = $PM_POST;
$_REQUEST = $PM_REQUEST;
include (GLPI_ROOT . "/front/tracking.injector.php");
