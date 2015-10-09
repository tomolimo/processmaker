<?php
/*
 *
 *  */

// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// Purpose of file: script to be used to purge logos from DB
// ----------------------------------------------------------------------

// Ensure current directory as run command prompt
chdir(dirname($_SERVER["SCRIPT_FILENAME"]));

define('DO_NOT_CHECK_HTTP_REFERER', 1);
define('GLPI_ROOT', '../..');
include (GLPI_ROOT . "/inc/includes.php");
include_once 'inc/processmaker.class.php' ;

$myCronTask = new CronTask;

if( $myCronTask->getFromDBbyName( "PluginProcessmakerProcessmaker", "pmusers" ) ) {
    $myCronTask->start();
    
    $ret = PluginProcessmakerProcessmaker::cronPMUsers( $myCronTask ) ;
    
    $myCronTask->end( $ret ) ;
} else
    echo "Cron not found!\n" ;



?>