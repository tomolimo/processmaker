<?php

include_once ("../../../inc/includes.php");

Html::header($LANG['processmaker']['title'][1], $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "caselinks");

if (Session::haveRightsOr("plugin_processmaker_config", [READ, UPDATE])) {

   Search::show('PluginProcessmakerCaselink');

} else {
   Html::displayRightError();
}
Html::footer();

