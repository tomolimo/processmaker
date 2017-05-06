<?php
include_once '../../../inc/includes.php';

// check if it is from PM pages
if (isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] )) {
   // then get item id from DB
   $myCase = new PluginProcessmakerCase;
   if ($myCase->getFromDB( $_REQUEST['APP_UID'] )) {
      $myProcessMaker = new PluginProcessmakerProcessmaker();
      $myProcessMaker->login( );

      if (isset( $_REQUEST['form'] )) {
         $myProcessMaker->derivateCase( $myCase, $_REQUEST); //, $_SERVER['HTTP_COOKIE'] ) ;
      }
   }

} else if (isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID'])) {
   // Claim task management
   // here we are in a Claim request
   $myCase = new PluginProcessmakerCase;
   if ($myCase->getFromDB( $_REQUEST['form']['APP_UID'] )) {
      $myProcessMaker = new PluginProcessmakerProcessmaker();
      $myProcessMaker->login( );

      $pmClaimCase = $myProcessMaker->claimCase( $myCase->getID(), $_REQUEST['DEL_INDEX'] );

      // now manage tasks associated with item
      $myProcessMaker->claimTask(  $myCase->getID(), $_REQUEST['DEL_INDEX'] );
   }
}

// now redirect to item form page
$config = PluginProcessmakerConfig::getInstance();
echo "<html><body><script>";
if (isset($config->fields['domain']) && $config->fields['domain'] != '') {
   echo "document.domain='{$config->fields['domain']}';";
}
echo "</script><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>";


