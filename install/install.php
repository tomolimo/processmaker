<?php

function processmaker_install() {
   global $DB;

   // installation from scratch
   $DB->runFile(GLPI_ROOT . "/plugins/processmaker/install/mysql/processmaker-empty.sql");

   // add configuration singleton
   $query = "INSERT INTO `glpi_plugin_processmaker_configs` (`id`) VALUES (1);";
   $DB->query( $query ) or die("error creating default record in glpi_plugin_processmaker_configs" . $DB->error());
}
