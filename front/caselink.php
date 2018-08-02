<?php

include_once ("../../../inc/includes.php");

Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "caselinks");

if (Session::haveRightsOr("plugin_processmaker_config", [READ, UPDATE])) {

   Search::show('PluginProcessmakerCaselink');

} else {
   Html::displayRightError();
}
Html::footer();

