<?php

include_once ("../../../inc/includes.php");

Session::checkCentralAccess();

$profile = new Profile();
$right   = new PluginProcessmakerProcess_Profile();
$process = new PluginProcessmakerProcess();

if (isset($_POST["add"])) {

   $right->check(-1, UPDATE, $_POST);
   if ($right->add($_POST)) {
      //Event::log($_POST["processes_id"], "PluginProcessMakerProcess", 4, "setup",
      //            $_SESSION["glpiname"]." ".$LANG['log'][61]);
   }
   Html::back();

} else if (isset($_POST["delete"])) {

   if (isset($_POST["item"]) && count($_POST["item"])) {
      foreach ($_POST["item"] as $key => $val) {
         if ($val == 1) {
            if ($right->can($key, UPDATE)) {
               $right->delete(array('id' => $key));
            }
         }
      }
      //if (isset($_POST["processes_id"])) {
      //        Event::log($_POST["processes_id"], "users", 4, "setup",
      //                   $_SESSION["glpiname"]." ".$LANG['log'][62]);
      //    }
   }
    Html::back();

}

Html::displayErrorAndDie("lost");
