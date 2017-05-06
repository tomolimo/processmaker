<?php

include_once ("../../../inc/includes.php");

Html::header($LANG['processmaker']['title'][1], $_SERVER['PHP_SELF'], "plugins", "processmaker");

if (Session::haveRight("plugin_processmaker_config", READ) || Session::haveRight("config", UPDATE)) {
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

