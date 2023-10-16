<?php

include_once ("../../../inc/includes.php");

Session::checkCentralAccess();

$right = new PluginProcessmakerProcess_Profile();

if (isset($_POST["add"])) {

   $right->check(-1, UPDATE, $_POST);
   $right->add($_POST);
   Html::back();
}

Html::displayErrorAndDie("lost");
