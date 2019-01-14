<?php
/*

 */

// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// Purpose of file:
// ----------------------------------------------------------------------

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "dropdownUsers.php")) {
    include ("../../../inc/includes.php");
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
    die("Can not access directly to this file");
}


Session::checkLoginUser();

$PM_DB = new PluginProcessmakerDB;
$dbu = new DbUtils;

if (!isset($_REQUEST['right'])) {
    $_REQUEST['right'] = "all";
}

// Default view : Nobody
if (!isset($_REQUEST['all'])) {
    $_REQUEST['all'] = 0;
}

$used = [];

if (isset($_REQUEST['used'])) {
   $used = $_REQUEST['used'];
}

if (!isset($_REQUEST['value'])) {
   $_REQUEST['value'] = 0;
}

$one_item = -1;
if (isset($_REQUEST['_one_id'])) {
   $one_item = $_REQUEST['_one_id'];
}

if (!isset($_REQUEST['page'])) {
   $_REQUEST['page']       = 1;
   $_REQUEST['page_limit'] = $CFG_GLPI['dropdown_max'];
}

if ($one_item < 0) {
   $start  = ($_REQUEST['page']-1)*$_REQUEST['page_limit'];
   $LIMIT = "LIMIT $start,".$_REQUEST['page_limit'];
   $result = PluginProcessmakerUser::getSqlSearchResult( $_REQUEST['specific_tags']['taskGuid'], false, $_REQUEST['right'], $_REQUEST["entity_restrict"],
                                   $_REQUEST['value'], $used, $_REQUEST['searchText'], $LIMIT);
} else {
   $query = "SELECT DISTINCT `glpi_users`.*
             FROM `glpi_users`
             WHERE `glpi_users`.`id` = '$one_item';";
   $result = $DB->query($query);
}
$users = [];

// Count real items returned
$count = 0;
if ($DB->numrows($result)) {
   while ($data = $DB->fetch_assoc($result)) {
      $users[$data["id"]] = $dbu->formatUserName($data["id"], $data["name"], $data["realname"],
                                           $data["firstname"]);
      $logins[$data["id"]] = $data["name"];
   }
}

if (!function_exists('dpuser_cmp')) {
   function dpuser_cmp($a, $b) {
      return strcasecmp($a, $b);
   }
}

$datas = [];

// Display first if empty search
if ($_REQUEST['page'] == 1 && empty($_REQUEST['searchText'])) {
   if (($one_item < 0) || ($one_item == 0)) {
      if ($_REQUEST['all'] == 0) {
         array_push($datas, ['id'   => 0,
                                  'text' => Dropdown::EMPTY_VALUE]);
      } else if ($_REQUEST['all'] == 1) {
         array_push($datas, ['id'   => 0,
                                  'text' => __('All')]);
      }
   }
}

if (count($users)) {
   foreach ($users as $ID => $output) {
      $title = sprintf('%1$s - %2$s', $output, $logins[$ID]);

      array_push($datas, ['id'    => $ID,
                               'text'  => $output,
                               'title' => $title]);
      $count++;
   }
}


if (($one_item >= 0)
    && isset($datas[0])) {
   echo json_encode($datas[0]);
} else {
   $ret['results'] = $datas;
   $ret['count']   = $count;
   echo json_encode($ret);
}

