<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

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
//// ----------------------------------------------------------------------
//// Original Author of file: Olivier Moron
//// Purpose of file: to return list of processes which can be started by end-user
//// ----------------------------------------------------------------------

//// Direct access to file
//if (strpos($_SERVER['PHP_SELF'], "dropdownTaskcategories.php")) {
//   include ("../../../inc/includes.php");
//   header("Content-Type: text/html; charset=UTF-8");
//   Html::header_nocache();
//}

//if (!defined('GLPI_ROOT')) {
//   die("Can not access directly to this file");
//}


//Session::checkLoginUser();


//// Security
//if (!($item = getItemForItemtype($_REQUEST['itemtype']))) {
//   exit();
//}

//$one_item = -1;
//if (isset($_POST['_one_id'])) {
//   $one_item = $_POST['_one_id'];
//}
//// Count real items returned
//$count = 0;

//if (!isset($_REQUEST['emptylabel']) || ($_REQUEST['emptylabel'] == '')) {
//   $_REQUEST['emptylabel'] = Dropdown::EMPTY_VALUE;
//}

//$search="";
//if (!empty($_REQUEST['searchText'])) {
//   $search = Search::makeTextSearch($_REQUEST['searchText']);
//}

//$taskcategories = array();

//// Empty search text : display first
//if (empty($_REQUEST['searchText'])) {
//   if ($_REQUEST['display_emptychoice']) {
//      if (($one_item < 0) || ($one_item  == 0)) {
//         array_push($taskcategories, array('id'   => 0,
//                                  'text' => $_REQUEST['emptylabel']));
//      }
//   }
//}

//$result = PluginProcessmakerTaskCategory::getSqlSearchResult(false, $search);

//if ($DB->numrows($result)) {
//   while ($data=$DB->fetch_array($result)) {
//         array_push( $taskcategories, array( 'id' => $data["id"],
//                                         'text' => $data["name"] ));
//         $count++;
//   }
//}

//$ret['results'] = $taskcategories;
//$ret['count']   = $count;
//echo json_encode($ret);
