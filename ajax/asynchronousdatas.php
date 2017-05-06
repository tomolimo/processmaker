<?php
if (strpos($_SERVER['PHP_SELF'],"asynchronousdatas.php")) {
   $AJAX_INCLUDE = 1;
   define('GLPI_ROOT','../../..');
   include (GLPI_ROOT."/inc/includes.php");
   //header("Content-Type: text/html; charset=UTF-8");
   header("Content-Type: application/json; charset=UTF-8");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not access directly to this file");
}

include_once dirname(__FILE__)."/../inc/crontaskaction.class.php" ;

if( isset($_SERVER['REQUEST_METHOD']) ) {
   switch($_SERVER['REQUEST_METHOD']) {
      case 'POST' :
         $request_body = file_get_contents('php://input');
         $datas = json_decode($request_body, true);

         $asyncdata = new PluginProcessmakerCrontaskaction ;
         if( isset($datas['id']) && $asyncdata->getFromDB( $datas['id'] ) && $asyncdata->fields['state'] == PluginProcessmakerCrontaskaction::WAITING_DATAS ) {
            $initialdatas = json_decode($asyncdata->fields['postdatas'], true);
            $initialdatas['form'] = array_merge( $initialdatas['form'], $datas['form'] ) ;
            $asyncdata->update( array( 'id' => $datas['id'], 'state' => PluginProcessmakerCrontaskaction::DATAS_READY, 'postdatas' => json_encode($initialdatas, JSON_HEX_APOS | JSON_HEX_QUOT) ) ) ;
            $ret = array( 'code' => '0', 'message' => 'Done' );
         } else {
            $ret = array( 'code' => '2', 'message' => 'Case is not existing, or state is not WAITING_DATAS' );
         }

         break;
      default:
         $ret = array( 'code' => '1', 'message' => 'Method not supported' ) ;
   }

   echo json_encode( $ret, JSON_HEX_APOS | JSON_HEX_QUOT ) ;

}
