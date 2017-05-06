<?php

// ----------------------------------------------------------------------
// Original Author of file: MoronO
// Purpose of file: mimic tracking.injector.php
// ----------------------------------------------------------------------

include( "../../../inc/includes.php");

if (empty($_POST["_type"])
    || ($_POST["_type"] != "Helpdesk")
    || !$CFG_GLPI["use_anonymous_helpdesk"]) {
    Session::checkRight("ticket", CREATE);
}

// Security check
if (empty($_POST) || count($_POST) == 0) {
    Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php");
}

// here we are going to test if we must start a process
if( isset($_POST["_from_helpdesk"]) && $_POST["_from_helpdesk"] == 1
    && isset($_POST["type"]) //&& $_POST["type"] == Ticket::DEMAND_TYPE
    && isset($_POST["itilcategories_id"])
    && isset($_POST["entities_id"])) {
    // here we have to check if there is an existing process in the entity and with the category
    // if yes we will start it
    // if not we will continue
    // special case if RUMT plugin is enabled and no process is available and category is 'User Management' then must start RUMT.

   $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_POST["itilcategories_id"], $_POST["type"], $_SESSION['glpiactiveprofile']['id'], $_POST["entities_id"] ) ;

    // currently only one process should be assigned to this itilcategory so this array should contain only one row
    $processQt = count( $processList ) ;
    if( $processQt == 1 ) {
        $_POST['action']='newcase';
        $_POST['plugin_processmaker_process_id'] = $processList[0]['id'];
        include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.form.php");
        die() ;
    } elseif( $processQt > 1 ) {
        // in this case we should show the process dropdown selection
        include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.helpdesk.form.php");
        die() ;
    } else{
        // in this case should start RUMT
        // if and only if itilcategories_id matches one of the 'User Management' categories
        // could be done via ARBehviours or RUMT itself
        $userManagementCat = array( 100556, 100557, 100558 ) ;
        $plug = new Plugin ;
        if( $processQt == 0 && in_array( $_POST["itilcategories_id"], $userManagementCat) && $plug->isActivated('rayusermanagementticket' )) {
            Html::redirect($CFG_GLPI['root_doc']."/plugins/rayusermanagementticket/front/rayusermanagementticket.helpdesk.public.php");

        }
    }


}

chdir(GLPI_ROOT."/front");
include (GLPI_ROOT . "/front/tracking.injector.php");
