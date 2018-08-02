<?php

include_once ("../../../inc/includes.php");

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
   $PluginProcess->refreshTasks($_REQUEST);
   Html::back();

} else {

   Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "processes");

   $PluginProcess->display($_REQUEST);

   Html::footer();
}
