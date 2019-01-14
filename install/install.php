<?php

function processmaker_install(){
   global $DB;

   // installation from scratch
   include_once(GLPI_ROOT."/plugins/processmaker/setup.php");
   $info = plugin_version_processmaker();
   switch($info['version']){
      case '3.3.0' :
         $version = '3.3.0';
         break;
      case '3.3.1' :
      case '3.3.2' :
      case '3.3.3' :
      case '3.3.4' :
      case '3.3.5' :
      case '3.3.6' :
      case '3.3.7' :
         $version = '3.3.1';
         break;
      case '3.3.8' :
      default :
         $version = '3.3.8';
         break;
   }
   $DB->runFile(GLPI_ROOT . "/plugins/processmaker/install/mysql/$version-empty.sql");

   // add configuration singleton
   $query = "INSERT INTO `glpi_plugin_processmaker_configs` (`id`) VALUES (1);";
   $DB->query( $query ) or die("error creating default record in glpi_plugin_processmaker_configs" . $DB->error());
}
