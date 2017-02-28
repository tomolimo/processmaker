<?php
//define('DO_NOT_CHECK_HTTP_REFERER', 1);
include_once '../../../inc/includes.php';
//include_once '../inc/processmaker.class.php' ;
//include_once '../inc/case.class.php' ;

// check if it is from PM pages
if( isset( $_REQUEST['UID'] ) && isset( $_REQUEST['APP_UID'] ) && isset( $_REQUEST['__DynaformName__'] ) ) {
    // then get item id from DB
    $myCase = new PluginProcessmakerCase ;
    if( $myCase->getFromDB( $_REQUEST['APP_UID'] ) ) {
        $myProcessMaker = new PluginProcessmakerProcessmaker() ;
        $myProcessMaker->login( ) ;

        if( isset( $_REQUEST['form'] ) ) {

         $myProcessMaker->derivateCase( $myCase, $_REQUEST); //, $_SERVER['HTTP_COOKIE'] ) ;

        }
    }
}
// Claim task management
elseif( isset( $_REQUEST['form'] ) && isset( $_REQUEST['form']['BTN_CATCH'] ) && isset( $_REQUEST['form']['APP_UID']) ){
    // here we are in a Claim request
    $myCase = new PluginProcessmakerCase ;
    if( $myCase->getFromDB( $_REQUEST['form']['APP_UID'] ) ) {
        $myProcessMaker = new PluginProcessmakerProcessmaker() ;
        $myProcessMaker->login( ) ;

        $pmClaimCase = $myProcessMaker->claimCase( $myCase->getID(), $_REQUEST['DEL_INDEX'] ) ;

        // now manage tasks associated with item
        $myProcessMaker->claimTask(  $myCase->getID(), $_REQUEST['DEL_INDEX'] ) ;
    }

}

// now redirect to item form page
//Html::redirect( Toolbox::getItemTypeFormURL($myCase->getField('itemtype')));
echo "<html><body><script></script><input id='GLPI_FORCE_RELOAD' type='hidden' value='GLPI_FORCE_RELOAD'/></body></html>" ;


