<?php
define('GLPI_ROOT', '../../..');
include (GLPI_ROOT."/inc/includes.php");


Html::header($LANG['processmaker']['title'][1], $_SERVER['PHP_SELF'], "plugins", "processmaker");

if (plugin_processmaker_haveRight("process_config","r") || Session::haveRight("config","w")) { 
    $process=new PluginProcessmakerProcess();

    if( isset( $_REQUEST['refresh'] ) && plugin_processmaker_haveRight("process_config","w") ) {
        $process->refresh();
        Html::back();
    }
        
    $process->title();

    Search::show('PluginProcessmakerProcess');

} else {
    Html::displayRightError();
}
Html::footer();

?>