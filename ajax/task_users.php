<?php

// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// Purpose of file:
// ----------------------------------------------------------------------

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "task_users.php")) {
   include ("../../../inc/includes.php");
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not access directly to this file");
}

Session::checkLoginUser();

$PM_SOAP = new PluginProcessmakerProcessmaker; // not used in this context, just here to define the type of $PM_SOAP
$PM_DB = new PluginProcessmakerDB;
$rand = rand();

echo "<form style='margin-bottom: 0px' name='processmaker_form_task$rand-".$_REQUEST['delIndex']."' id='processmaker_form_task$rand-".$_REQUEST['delIndex']."' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
echo __('Re-assign task to', 'processmaker')."&nbsp;";
echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
echo "<input type='hidden' name='items_id' value='".$_REQUEST['items_id']."'>";
echo "<input type='hidden' name='itemtype' value='".$_REQUEST['itemtype']."'>";
echo "<input type='hidden' name='cases_id' value='".$_REQUEST['cases_id']."'>";
echo "<input type='hidden' name='delIndex' value='".$_REQUEST['delIndex']."'>";
echo "<input type='hidden' name='users_id' value='".$_REQUEST['users_id']."'>";
echo "<input type='hidden' name='taskGuid' value='".$_REQUEST['taskGuid']."'>";
echo "<input type='hidden' name='delThread' value='".$_REQUEST['delThread']."'>";

PluginProcessmakerUser::dropdown( ['name'   => 'users_id_recipient',
                                          'value'  => $_REQUEST['users_id'],
                                          'used' => [$_REQUEST['users_id']],
                                          'entity' => 0, //$item->fields["entities_id"], // not used, as any user can be assigned to any tasks
                                          'entity_sons' => false, // not used, as any user can be assigned to any tasks
                                          'right'  => 'all',
                                          'rand'  => $rand,
                                          'width' => '',
                                          'specific_tags' => ['taskGuid' => $_REQUEST['taskGuid']]]);
echo "&nbsp;&nbsp;";
echo "<input type='submit' name='reassign' value='".__('Re-assign', 'processmaker')."' class='submit'>";
Html::closeForm(true);

