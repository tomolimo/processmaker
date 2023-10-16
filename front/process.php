<?php

include_once ("../../../inc/includes.php");

Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "processes");

if (Session::haveRightsOr("plugin_processmaker_config", [READ, UPDATE])) {
   $process=new PluginProcessmakerProcess();

   if (isset( $_REQUEST['refresh'] ) && Session::haveRight("plugin_processmaker_config", UPDATE)) {
      $process->refresh();
      Html::back();
   }

   $process->title();

   Search::show('PluginProcessmakerProcess');

} else {
   Html::displayRightError();
}
Html::footer();

