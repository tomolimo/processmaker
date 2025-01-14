<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */

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

            $ID = 0;
            if (isset($_REQUEST['id'])) {
               $ID = $_REQUEST['id'];
            }
            if (isset($datas['id'])) {
                $ID = $datas['id'];
            }
            if ($ID && $asyncdata->getFromDB($ID) && $asyncdata->fields['state'] == PluginProcessmakerCrontaskaction::WAITING_DATA) {
               $initialdatas = json_decode($asyncdata->fields['formdata'], true);
               if (isset($datas['form'])) {
                   $datas = $datas['form'];
               }
               $initialdatas['form'] = array_merge( $initialdatas['form'], $datas );
               $formdata = json_encode($initialdatas, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
               $asyncdata->update( [ 'id' => $ID, 'state' => PluginProcessmakerCrontaskaction::DATA_READY, 'formdata' => $formdata ] );
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
