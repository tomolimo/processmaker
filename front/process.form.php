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
include_once ("../../../inc/includes.php");

Session::checkLoginUser();

Plugin::load('processmaker', true); // ???

if (!isset($_REQUEST["id"])) {
   $_REQUEST["id"] = "";
}

$PluginProcess = new PluginProcessmakerProcess();

if (isset($_REQUEST["update"])) {
   $PluginProcess->check($_REQUEST['id'], UPDATE);
   $PluginProcess->update($_REQUEST);
   Html::back();

} else if (isset($_REQUEST["refreshtask"])) {
   $PluginProcess->check($_REQUEST['id'], UPDATE);
   $PluginProcess->refreshTasks($_REQUEST['id']);
   Html::back();

} else {

   Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "processes");

   $PluginProcess->display($_REQUEST);

   Html::footer();
}
