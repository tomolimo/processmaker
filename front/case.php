<?php

include_once ("../../../inc/includes.php");

Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "helpdesk", "PluginProcessmakerCase", "cases");

if (!$PM_SOAP->config->fields['maintenance']) {
   if (Session::haveRightsOr("plugin_processmaker_case", [READ, UPDATE])) {

      Search::show('PluginProcessmakerCase');
   } else {
      Html::displayRightError();
   }
} else {
   PluginProcessmakerProcessmaker::showUnderMaintenance();
}

Html::footer();

