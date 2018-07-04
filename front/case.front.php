<?php
//include_once '../../../inc/includes.php';

//// check if it is from PM pages
//if (isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] )) {
//   // then get item id from DB
//   $myCase = new PluginProcessmakerCase;
//   if ($myCase->getFromGUID($_REQUEST['APP_UID'])) {
//      //$PM_SOAP = new PluginProcessmakerProcessmaker();
//      //$PM_SOAP->login( );

//      if (isset( $_REQUEST['form'] )) {
//         $PM_SOAP->derivateCase($myCase, $_REQUEST); //, $_SERVER['HTTP_COOKIE'] ) ;
//      }
//   }

//} else 
//   if (isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID'])) {
//   // Claim task management
//   // here we are in a Claim request
//   $myCase = new PluginProcessmakerCase;
//   if ($myCase->getFromGUID( $_REQUEST['form']['APP_UID'] )) {
//      //$PM_SOAP = new PluginProcessmakerProcessmaker();
//      //$PM_SOAP->login( );

//      $pmClaimCase = $PM_SOAP->claimCase($myCase->fields['case_guid'], $_REQUEST['DEL_INDEX'] );

//      // now manage tasks associated with item
//      $PM_SOAP->claimTask($myCase->getID(), $_REQUEST['DEL_INDEX']);
//   }
//}

//// now redirect to item form page
//$config = $PM_SOAP->config; // PluginProcessmakerConfig::getInstance();
//echo "<html><body><script>";
//if (isset($config->fields['domain']) && $config->fields['domain'] != '') {
//   echo "document.domain='{$config->fields['domain']}';";
//}
//echo "</script><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>";