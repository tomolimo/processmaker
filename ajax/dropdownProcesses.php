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
// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// Purpose of file: to return list of processes which can be started by end-user
// ----------------------------------------------------------------------

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "dropdownProcesses.php")) {
   include ("../../../inc/includes.php");
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not access directly to this file");
}


Session::checkLoginUser();

if (isset($_REQUEST["entity_restrict"])
    && !is_array($_REQUEST["entity_restrict"])
    && (substr($_REQUEST["entity_restrict"], 0, 1) === '[')
    && (substr($_REQUEST["entity_restrict"], -1) === ']')) {
    $_REQUEST["entity_restrict"] = json_decode($_REQUEST["entity_restrict"]);
    $_REQUEST["entity_restrict"] = $_REQUEST["entity_restrict"][0];
}

// Security
if (!($item = getItemForItemtype($_REQUEST['itemtype']))) {
   exit();
}

$one_item = -1;
if (isset($_POST['_one_id'])) {
   $one_item = $_POST['_one_id'];
}
// Count real items returned
$count = 0;

if (!isset($_REQUEST['emptylabel']) || ($_REQUEST['emptylabel'] == '')) {
   $_REQUEST['emptylabel'] = Dropdown::EMPTY_VALUE;
}

$search="";
if (!empty($_REQUEST['searchText'])) {
   $search = ['LIKE', Search::makeTextSearchValue($_REQUEST['searchText'])];
}

$processes = [];

// Empty search text : display first
if (empty($_REQUEST['searchText'])) {
   if ($_REQUEST['display_emptychoice']) {
      if (($one_item < 0) || ($one_item  == 0)) {
         array_push($processes, ['id'   => 0,
                                  'text' => $_REQUEST['emptylabel']]);
      }
   }
}

$processall = (isset($_REQUEST['specific_tags']['process_restrict']) && !$_REQUEST['specific_tags']['process_restrict']);
$count_cases_per_item = isset($_REQUEST['specific_tags']['count_cases_per_item']) ? $_REQUEST['specific_tags']['count_cases_per_item'] : [];

$result = PluginProcessmakerProcess::getSqlSearchResult(false, $search);

//if ($DB->numrows($result)) {
//   while ($data = $DB->fetch_array($result)) {
//if ($result->numrows()) {
   foreach ($result as $data) {
      $process_entities = PluginProcessmakerProcess::getEntitiesForProfileByProcess($data["id"], $_SESSION['glpiactiveprofile']['id'], true);
      $can_add = $data['max_cases_per_item'] == 0 || !isset($count_cases_per_item[$data["id"]]) || $count_cases_per_item[$data["id"]] < $data['max_cases_per_item'];
      if ($processall
          || ($data['maintenance'] != 1
              && in_array( $_REQUEST["entity_restrict"], $process_entities)
              && $can_add) ) {

         array_push( $processes, ['id'   => $data["id"],
                                  'text' => $data["name"]
                                 ]);
         $count++;
      }
   }
//}

$ret['results'] = $processes;
$ret['count']   = $count;
echo json_encode($ret);
