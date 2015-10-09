<?php

define('GLPI_ROOT', '../../..');

include (GLPI_ROOT."/inc/includes.php");
Session::checkRight("profile", "r");

$prof = new PluginProcessmakerProfile();

//Save profile
if (isset ($_POST['update_user_profile'])) {
    $prof->update($_POST);
    Html::back();
}
?>