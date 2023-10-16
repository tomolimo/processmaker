<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */
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

$rand = rand();

$commoninputs = "<input type='hidden' name='items_id' value='".$_REQUEST['items_id']."'>".
                "<input type='hidden' name='itemtype' value='".$_REQUEST['itemtype']."'>".
                "<input type='hidden' name='cases_id' value='".$_REQUEST['cases_id']."'>".
                "<input type='hidden' name='delIndex' value='".$_REQUEST['delIndex']."'>".
                "<input type='hidden' name='users_id' id='users_id$rand' value='".$_REQUEST['users_id']."'>".
                "<input type='hidden' name='taskGuid' value='".$_REQUEST['taskGuid']."'>".
                "<input type='hidden' name='delThread' value='".$_REQUEST['delThread']."'>".
                "<input type='hidden' name='tasktype' value='".$_REQUEST['tasktype']."'>".
                "<input type='hidden' name='tasks_id' value='".$_REQUEST['tasks_id']."'>";

$PM_SOAP = new PluginProcessmakerProcessmaker;
$PM_DB = new PluginProcessmakerDB;

echo "<form style='margin-bottom: 0px' name='processmaker_form_task$rand-".$_REQUEST['delIndex']."' id='processmaker_form_task$rand-".$_REQUEST['delIndex']."' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
echo __('Re-assign task to', 'processmaker')."&nbsp;";
echo "<input type='hidden' name='action' value='reassign_reminder'>";
echo "<input type='hidden' name='comment' id='comment$rand' value=''>";
echo $commoninputs;

$can_unclaim = false; // by default
$grp = false;
$query = "SELECT TAS_GROUP_VARIABLE FROM TASK WHERE TAS_UID='".$_REQUEST['taskGuid']."' AND TAS_ASSIGN_TYPE='SELF_SERVICE';";
$res = $PM_DB->query($query);
if ($PM_DB->numrows($res) > 0 && $row = $PM_DB->fetchAssoc($res)) {
   $can_unclaim = true;
   if ($row['TAS_GROUP_VARIABLE'] != '') {
      //self-service value based assignment
      $PM_SOAP->login(true); // needs to be logged in to be able to call SOAP
      $grp = $PM_SOAP->getGLPIGroupIdForSelfServiceTask($_REQUEST['caseGuid'], $_REQUEST['taskGuid']);
   }
}

$res = $DB->request([
            'SELECT'    => [
            'pm.is_reassignreason_mandatory AS pm_is_reassignreason_mandatory',
            'gppp.is_reassignreason_mandatory AS gppp_is_reassignreason_mandatory'
            ],
            'FROM'      => 'glpi_plugin_processmaker_taskcategories AS pm',
            'LEFT JOIN' => [
            'glpi_plugin_processmaker_processes AS gppp' => [
                'FKEY' => [
                    'gppp' => 'id',
                    'pm'   => 'plugin_processmaker_processes_id'
                ]
            ]
            ],
            'WHERE'     => [
            'pm.pm_task_guid' => $_REQUEST['taskGuid']
            ]
]);

// there is only one row
$taskCat = $res->current();
$ask_for_reason = PluginProcessmakerTaskCategory::inheritedReAssignReason($taskCat['pm_is_reassignreason_mandatory'], $taskCat['gppp_is_reassignreason_mandatory']);

PluginProcessmakerUser::dropdown( ['name'   => 'users_id_recipient',
                                   'value'  => $_REQUEST['users_id'],
                                   //'used' => $_REQUEST['used'], // not set to be able to alert when trying to re-assigned to the same user
                                   'entity' => 0, //$item->fields["entities_id"], // not used, as any user can be assigned to any tasks
                                   'entity_sons' => false, // not used, as any user can be assigned to any tasks
                                   'right'  => 'all',
                                   'all'    => ($can_unclaim ? 0 : -1),
                                   'rand'  => $rand,
                                   'width' => '',
                                   'specific_tags' => ['taskGuid' => $_REQUEST['taskGuid'], 'grpGuid' => ($grp !== false ? $grp['uid'] : 0)]
                                  ]);

echo "&nbsp;&nbsp;";
echo "<input type='submit' name='reassign$rand' value='".__('Re-assign', 'processmaker')."' class='submit'>";
echo "<input type='submit' name='reassign' id='reassign$rand' value='".__('Re-assign', 'processmaker')."' class='submit' style='display:none'>";

echo HTML::scriptBlock("
      $(function () {
         // Dialog helpers
         // Create the dialog with \"Re-assign\" button
         function showCommentDlg(title, content, alttext) {
            modalId = title.replaceAll(' ', '_')
            var modal = $('#'+title);
            if (modal && modal.length == 0) {
               var modal = '<div class=\"modal fade testmodalprocess\" id=\"'+ modalId +'\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"exampleModalCenterTitle\" aria-hidden=\"true\">'
                     + '<div class=\"modal-dialog modal-dialog-centered\"role=\"document\">'
                     + '<div class=\"modal-content\">'
                     + '<div class=\"modal-header\">'
                     + '<h5 class=\"modal-modalId\" id=\"changeChoiceTitle\">'+ title +'</h5>'
                     + '<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>'
                     + '</div >'
                     + '<div class=\"modal-body\">'
                     + '</div>'
                     + '<div class=\"modal-footer\">'
                     + '<button type=\"button\" class=\"btn btn-primary\" id=\"submit$rand\" disabled>'+alttext+'</button>'
                     + '</div>'
                     + '</div>'
                     + '</div>';
               $('body').append(modal);
            }
            $('#'+modalId).modal('show');
            $('#'+modalId+' .modal-body').append('<label for=\"message-text\" class=\"col-form-label\">'+content+'</label><textarea class=\"form-control\" id=\"commenttxtarea$rand\" style=\"height: 100px\"></textarea></p><font color=red>".addslashes(__('Input at least 10 words to justify.','processmaker'))."</font>');
            $('#commenttxtarea$rand').focus();
            $('#commenttxtarea$rand').on('keydown keyup', function(e) {
               if ($('#commenttxtarea$rand').val().split(/\W+/).length > 10) {
                  $('#submit$rand').prop('disabled', false);
               } else {
                  $('#submit$rand').prop('disabled', true);
               }
            });
            $('#'+modalId).on('hide.bs.modal', function () {
               $('#'+modalId).remove();
            })
            $('#submit$rand').click(() => {
               $('#comment$rand').val($('#commenttxtarea$rand').val());
               $('#reassign$rand').click();
               $('#submit$rand').button('disable');
               $('#'+modalId).modal('hide').remove();
         });
         };


         $('input[name=reassign$rand]').click(function (e) {
//debugger;
            let post = true;
            e.preventDefault();
            let users_id_val = $('#users_id$rand').val();
            let users_id_recipient_val = $('#dropdown_users_id_recipient$rand').val();
            if (users_id_val == users_id_recipient_val) {
               // task is already assigned to this user
               if (users_id_val == 0) {
                  alert('".addslashes(__('Task is already un-assigned!', 'processmaker'))."', '".addslashes(__('Re-assign task', 'processmaker'))."');
               } else {
                  alert('".addslashes(__('Task is already assigned to this user!', 'processmaker'))."', '".addslashes(__('Re-assign task', 'processmaker'))."');
               }
               post = false;
            } else if (users_id_recipient_val == 0) {
               // un-claim
               if (".($can_unclaim ? 1 : 0)." && users_id_val != 0) {
                  if (" . ($ask_for_reason ? 1 : 0) . ") {
                      showCommentDlg('".addslashes(__('Un-claim task', 'processmaker'))."',
                                     '".addslashes(__('Please input reason to un-claim<br/>(task will be re-assigned to former group):', 'processmaker'))."',
                                     '".addslashes(__('Un-claim', 'processmaker'))."');
                      post = false;
                  }
               } else {
                  // task can't be unclaimed because it isn't SELF_SERVICE
                  alert('".addslashes(__("Can't un-assign task!", 'processmaker'))."', '".addslashes(__('Un-claim task', 'processmaker'))."');
                  post = false;
               }
            } else if (" . ($ask_for_reason ? 1 : 0) . ") {
                       showCommentDlg('".addslashes(__('Re-assign task', 'processmaker'))."',
                                      '".addslashes(__('Please input reason to re-assign:', 'processmaker'))."',
                                      '".addslashes(__('Re-assign', 'processmaker'))."');
                       post = false;
            }
            if (post) {
                // here we must click on the reassign button to force POST
                $('#reassign$rand').click();
            }
            return false;
         });
      })
   ");


if (Session::getLoginUserID() != $_REQUEST['users_id']) {
   echo "&nbsp;&nbsp;";
   echo "<input type='submit' name='reminder' value='".__('Send reminder', 'processmaker')."' class='submit'>";
}

Html::closeForm(true);
