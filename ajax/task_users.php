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

$commoninputs = "<input type='hidden' name='items_id' value='".$_REQUEST['items_id']."'>".
                "<input type='hidden' name='itemtype' value='".$_REQUEST['itemtype']."'>".
                "<input type='hidden' name='cases_id' value='".$_REQUEST['cases_id']."'>".
                "<input type='hidden' name='delIndex' value='".$_REQUEST['delIndex']."'>".
                "<input type='hidden' name='users_id' value='".$_REQUEST['users_id']."'>".
                "<input type='hidden' name='taskGuid' value='".$_REQUEST['taskGuid']."'>".
                "<input type='hidden' name='delThread' value='".$_REQUEST['delThread']."'>".
                "<input type='hidden' name='tasktype' value='".$_REQUEST['tasktype']."'>".
                "<input type='hidden' name='tasks_id' value='".$_REQUEST['tasks_id']."'>";

$PM_SOAP = new PluginProcessmakerProcessmaker; // not used in this context, just here to define the type of $PM_SOAP
$PM_DB = new PluginProcessmakerDB;
$rand = rand();

echo "<form style='margin-bottom: 0px' name='processmaker_form_task$rand-".$_REQUEST['delIndex']."' id='processmaker_form_task$rand-".$_REQUEST['delIndex']."' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
echo __('Re-assign task to', 'processmaker')."&nbsp;";
echo "<input type='hidden' name='action' value='reassign_reminder'>";
echo "<input type='hidden' name='comment' value=''>";
echo $commoninputs;

$can_unclaim = false; // by default
$query = "SELECT TAS_GROUP_VARIABLE FROM TASK WHERE TAS_UID='".$_REQUEST['taskGuid']."' AND TAS_ASSIGN_TYPE='SELF_SERVICE';";
$res = $PM_DB->query($query);
if ($PM_DB->numrows($res) > 0) {
   $can_unclaim = true;
}

PluginProcessmakerUser::dropdown( ['name'   => 'users_id_recipient',
                                   'value'  => $_REQUEST['users_id'],
                                   'used' => $_REQUEST['used'],
                                   'entity' => 0, //$item->fields["entities_id"], // not used, as any user can be assigned to any tasks
                                   'entity_sons' => false, // not used, as any user can be assigned to any tasks
                                   'right'  => 'all',
                                   'rand'  => $rand,
                                   'width' => '',
                                   'specific_tags' => ['taskGuid' => $_REQUEST['taskGuid']]
                                  ]);

echo "&nbsp;&nbsp;";
echo "<input type='submit' name='reassign$rand' value='".__('Re-assign', 'processmaker')."' class='submit'>";
echo "<input type='submit' name='reassign' value='".__('Re-assign', 'processmaker')."' class='submit' style='display:none'>";

echo HTML::scriptBlock("
      $(function () {
         // Dialog helpers
         // Create the dialog with \"Re-assign\" button
         function showCommentDlg(title, content, alttext) {

            var dlgContents = {
               title: title,
               modal: true,
               width: 'auto',
               height: 'auto',
               resizable: false,
               close: function (event, ui) {
                  $(this).dialog('destroy').remove();
               },
               buttons: [{
                  text: alttext,
                  id: 'submit$rand',
                  disabled: 'disabled',
                  click: function() {
                     $('#processmaker_form_task$rand-".$_REQUEST['delIndex']." input[name=comment]').val($('#comment$rand').val());
                     //$('#processmaker_form_task$rand-".$_REQUEST['delIndex']."').submit();
                     $('input[name=reassign').click();
                     $('#submit$rand').button('disable');
                  }
               }],
               show: true,
               hide: true
            }
            $('<div id=reassign$rand></div>').appendTo($('#processmaker_form_task$rand-".$_REQUEST['delIndex']."'));
            var locDlg = $('#reassign$rand').html(content + '<p><textarea id=comment$rand rows=6 cols=60></textarea></p><font color=red>Input at least 10 words in English to justify.</font>').dialog(dlgContents);
            $('#comment$rand').focus();
            $('#comment$rand').on('keydown keyup', function(e) {
               if ($('#comment$rand').val().split(/\W+/).length > 10) {
                  $('#submit$rand').button('enable');
               } else {
                  $('#submit$rand').button('disable');
               }
            });

            return locDlg;
         };

         $('input[name=reassign$rand]').click(function (e) {
            e.preventDefault();
            if ($('input[name=users_id]').val() == $('input[name=users_id_recipient]').val()) {
               // task is already assigned to this user
               alert('".__('Task is already assigned to this user or group!', 'processmaker')."', '".__('Re-assign task', 'processmaker')."');
            } else if ($('input[name=users_id_recipient]').val() == 0) {
               // un-claim               
               if (".($can_unclaim ? 1 : 0)." && $('input[name=users_id]').val() != 0) {
                  showCommentDlg('".__('Un-claim task', 'processmaker')."',
                                 '".__('Please input reason to un-claim<br/>(task will be re-assigned to former group):', 'processmaker')."',
                                 '".__('Un-claim', 'processmaker')."');
               } else {
                  // task can't be unclaim because it isn't SELF_SERVICE
                  alert('".__('Can\\\'t un-assign Task!', 'processmaker')."', '".__('Un-claim task', 'processmaker')."');
               }
            } else {
               showCommentDlg('".__('Re-assign task', 'processmaker')."',
                              '".__('Please input reason to re-assign:', 'processmaker')."',
                              '".__('Re-assign', 'processmaker')."');
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
