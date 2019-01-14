<?php
if (strpos($_SERVER['PHP_SELF'], "asynchronousdatas.php")) {
   $AJAX_INCLUDE = 1;
   define('GLPI_ROOT', '../../..');
   include (GLPI_ROOT."/inc/includes.php");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not access directly to this file");
}

include_once dirname(__FILE__)."/../inc/crontaskaction.class.php";
if (isset( $_SERVER['REQUEST_METHOD'] )  && $_SERVER['REQUEST_METHOD']=='OPTIONS') {
   header("Access-Control-Allow-Origin: *");
   header("Access-Control-Allow-Methods: POST");
   header("Access-Control-Allow-Headers: Content-Type");
} else {
   header("Access-Control-Allow-Origin: *");
   header("Content-Type: application/json; charset=UTF-8");

   if (isset($_SERVER['REQUEST_METHOD'])) {
      switch ($_SERVER['REQUEST_METHOD']) {
         case 'POST' :
            $request_body = file_get_contents('php://input');
            $datas = json_decode($request_body, true);

            $asyncdata = new PluginProcessmakerCrontaskaction;
            if (isset($datas['id']) && $asyncdata->getFromDB( $datas['id'] ) && $asyncdata->fields['state'] == PluginProcessmakerCrontaskaction::WAITING_DATA) {
               $initialdatas = json_decode($asyncdata->fields['postdata'], true);
               $initialdatas['form'] = array_merge( $initialdatas['form'], $datas['form'] );
               $postdata = json_encode($initialdatas, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
               $asyncdata->update( [ 'id' => $datas['id'], 'state' => PluginProcessmakerCrontaskaction::DATA_READY, 'postdata' => $postdata ] );
               $ret = [ 'code' => '0', 'message' => 'Done' ];
            } else {
               $ret = [ 'code' => '2', 'message' => 'Case is not existing, or state is not WAITING_DATA' ];
            }

            break;
         default:
            $ret = [ 'code' => '1', 'message' => 'Method '.$_SERVER['REQUEST_METHOD'].' not supported' ];
      }

      echo json_encode( $ret, JSON_HEX_APOS | JSON_HEX_QUOT );

   }
}
