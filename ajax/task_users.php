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
   die("Can not acces directly to this file");
}

Session::checkLoginUser();

$PM_DB = new PluginProcessmakerDB;
$rand = rand();

echo "<form style='margin-bottom: 0px' name='processmaker_form_task$rand-".$_REQUEST['delIndex']."' id='processmaker_form_task$rand-".$_REQUEST['delIndex']."' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
echo $LANG['processmaker']['item']['reassigncase']."&nbsp;";
echo "<input type='hidden' name='action' value='unpausecase_or_reassign_or_delete'>";
echo "<input type='hidden' name='id' value='".$_REQUEST['itemId']."'>";
echo "<input type='hidden' name='itemtype' value='".$_REQUEST['itemType']."'>";
echo "<input type='hidden' name='plugin_processmaker_caseId' value='".$_REQUEST['caseId']."'>";
echo "<input type='hidden' name='plugin_processmaker_delIndex' value='".$_REQUEST['delIndex']."'>";
echo "<input type='hidden' name='plugin_processmaker_userId' value='".$_REQUEST['userId']."'>";
echo "<input type='hidden' name='plugin_processmaker_taskId' value='".$_REQUEST['taskId']."'>";
echo "<input type='hidden' name='plugin_processmaker_delThread' value='".$_REQUEST['delThread']."'>";

PluginProcessmakerUser::dropdown( array('name'   => 'users_id_recipient',
                                          'value'  => PluginProcessmakerUser::getGLPIUserId( $_REQUEST['userId'] ),
                                          'entity' => 0, //$item->fields["entities_id"], // not used, as any user can be assigned to any tasks
                                          'entity_sons' => false, // not used, as any user can be assigned to any tasks
                                          'right'  => 'all',
                                          'rand'  => $rand,
                                          'width' => '',
                                          'specific_tags' => array('pmTaskId' => $_REQUEST['taskId'])));
echo "&nbsp;&nbsp;";
echo "<input type='submit' name='reassign' value='".$LANG['processmaker']['item']['buttonreassigncase']."' class='submit'>";
Html::closeForm(true);

