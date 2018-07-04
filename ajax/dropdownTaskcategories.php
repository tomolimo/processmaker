<?php

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
