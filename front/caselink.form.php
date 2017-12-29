<?php

include_once ("../../../inc/includes.php");

Plugin::load('processmaker', true);

if (!isset($_REQUEST["id"])) {
   $_REQUEST["id"] = "";
}

$PluginCaselink = new PluginProcessmakerCaselink();

if (isset($_REQUEST["update"])) {
   $PluginCaselink->check($_REQUEST['id'], UPDATE);
   $PluginCaselink->update($_REQUEST);
   Html::back();
} elseif (isset($_REQUEST['add'])) {
   $PluginCaselink->check($_REQUEST['id'], UPDATE);
   $PluginCaselink->add($_REQUEST);
   Html::back();
} elseif (isset($_REQUEST['purge'])) {
   $PluginCaselink->check($_REQUEST['id'], PURGE);
   $PluginCaselink->delete($_REQUEST, true);
   $PluginCaselink->redirectToList();
} else {

   Html::header($LANG['processmaker']['title'][1], $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "caselinks");

   $PluginCaselink->display($_REQUEST);

   Html::footer();
}
