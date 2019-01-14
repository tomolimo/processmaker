<?php

include_once ("../../../inc/includes.php");

Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "helpdesk", "PluginProcessmakerCase", "cases");

if (!$PM_SOAP->config->fields['maintenance']) {
   if (Session::haveRightsOr("plugin_processmaker_config", [READ, UPDATE])) {

      // force default sort to column id / DESC
      if (empty($_SESSION['glpisearch']['PluginProcessmakerCase'])
         || isset($_GET["reset"])
         || !isset($_GET["sort"])
         ) {
         $_SESSION['glpisearch']['PluginProcessmakerCase']['order'] = 'DESC';
         $_SESSION['glpisearch']['PluginProcessmakerCase']['sort'] = '1';
         if (isset($_GET["reset"])) {
            unset($_GET['reset']);
         }
      }
      Search::show('PluginProcessmakerCase');
   } else {
      Html::displayRightError();
   }
} else {
   PluginProcessmakerProcessmaker::showUnderMaintenance();
}

Html::footer();

