<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

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
echo "<input type='hidden' name='action' value='reassign_reminder'>";
echo "<input type='hidden' name='comment' id='comment$rand' value=''>";
echo $commoninputs;

echo '<table class="tab_cadre_fixe">';
echo '<tr>';
echo '<td class="tab_bg_1" nowrap>';
echo "<b>" . __('Re-assign task to', 'processmaker') . "</b>";
echo '</td>';

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
$task_table = getTableForItemType($_REQUEST['tasktype']);

$res = $DB->request([
            'SELECT'    => [
            'pm.is_reassignreason_mandatory AS pm_is_reassignreason_mandatory',
            'gppp.is_reassignreason_mandatory AS gppp_is_reassignreason_mandatory',
            'pm.before_time AS pm_before_time',
            'pm.after_time AS pm_after_time',
            'pm.users_id AS pm_users_id',
            'gpptr.before_time AS tr_before_time',
            'gpptr.after_time AS tr_after_time',
            'gpptr.users_id AS tr_users_id',
            'gpptr.id AS plugin_processmaker_taskrecalls_id',
            'gtt.begin AS task_begin',
            'gtt.end AS task_end',
            'gpptr.when AS tr_when',
            'gppt.id AS pm_tasks_id'
            ],
            'FROM'      => 'glpi_plugin_processmaker_taskcategories AS pm',
            'LEFT JOIN' => [
                'glpi_plugin_processmaker_processes AS gppp' => [
                    'ON' => [
                        'gppp' => 'id',
                        'pm'   => 'plugin_processmaker_processes_id'
                    ]
                ],
                'glpi_plugin_processmaker_tasks as gppt' => [
                    'ON' => [
                        'pm' => 'id',
                        'gppt' => 'plugin_processmaker_taskcategories_id',
                        ['AND' => ['gppt.items_id' => $_REQUEST['tasks_id'],
                                   'gppt.itemtype'=> $_REQUEST['tasktype']]
                        ]
                    ]
                ],
                'glpi_plugin_processmaker_taskrecalls as gpptr' => [
                    'ON' => [
                        'gpptr' => 'plugin_processmaker_tasks_id',
                        'gppt' => 'id'
                    ]
                ],
                $task_table . ' as gtt' => [
                    'ON' => [
                        'gtt' => 'id',
                        'gppt' => 'items_id'
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

echo '<td class="tab_bg_1" colspan=2 nowrap>';
PluginProcessmakerUser::dropdown( ['name'   => 'users_id_recipient',
                                   'value'  => $_REQUEST['users_id'],
                                   'used' => $_REQUEST['used'] ?? [],
                                   'entity' => 0, //$item->fields["entities_id"], // not used, as any user can be assigned to any tasks
                                   'entity_sons' => false, // not used, as any user can be assigned to any tasks
                                   'right'  => 'all',
                                   'all'    => ($can_unclaim ? 0 : -1),
                                   'rand'  => $rand,
                                   'width' => '',
                                   'specific_tags' => ['taskGuid' => $_REQUEST['taskGuid'], 'grpGuid' => ($grp !== false ? $grp['uid'] : 0)]
                                  ]);
echo '</td>';
echo '<td class="tab_bg_1" nowrap>';

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

echo '</td>';

echo '<td class="tab_bg_1" colspan="2">';
echo '</td>';


echo '<td class="tab_bg_1" colspan=2>';
if (!isset($PM_SOAP->config)) {
    $PM_SOAP->config = Config::getConfigurationValues('plugin:processmaker');
}
if (Session::getLoginUserID() != $_REQUEST['users_id']
    && $PM_SOAP->config['users_id'] != $_REQUEST['users_id']) {
   echo "<input type='submit' name='reminder' value='".__('Send immediate reminder', 'processmaker')."' class='submit'>";
}
echo '</td>';

// check rights
$caneditreminders = Session::getCurrentInterface() == 'central' && $PM_SOAP->config['users_id'] != $_REQUEST['users_id'];

if ($caneditreminders) {
    echo '<td class="tab_bg_1" colspan=2>';
    echo '<div class="pointer" onclick="showHideReminderSettings();">Automatic reminder settings&nbsp;<i id="reminder_arrow" class="fas fa-caret-down pointer" style="font-size: larger;" title="Show/Hide"></i></div>';
    echo Html::scriptBlock("
        function showHideReminderSettings() {
            if ($('.reminder_settings').css('display') == 'none') {
                $('.reminder_settings').css('display', '');
            } else {
                $('.reminder_settings').css('display', 'none');
            }
            $('#reminder_arrow').toggleClass('fa-caret-up fa-caret-down');
        }
    ");
    echo '</td>';
}

echo '</tr>';
echo '</table>';


if ($caneditreminders) {
    // add info on reminder
    // show before time
    // show after time
    // show next date
    // show user
    // add submit button
    echo Html::scriptBlock("
        function formatedTimestamp (d, hm_only) {
            const date = d.toISOString().split('T')[0];
            const time = d.toTimeString().split(' ')[0];
            if (typeof hm_only === 'undefined' && hm_only === true) {
                return date + ' ' + time;
            }
            const hm = time.split(':')
            return date + ' ' + hm[0] + ':' + hm[1];
        }

        function computeNextDate (elt_name, new_date_name, ref_name, ope) {
            var delta = $('[name=' + elt_name + ']').val() * 1000; // in milliseconds
            var ref = $('[name=' + ref_name + ']').val(); // date_begin or date_end
            var new_date = __('None', 'processmaker'); // by default
            if (delta >= 0) {
                if (ope === 'sub') {
                    delta = -delta;
                }
                new_date = formatedTimestamp(new Date(Date.parse(ref) + delta));
                var current_date = formatedTimestamp(new Date());
                if (new_date < current_date) {
                    new_date = formatedTimestamp(new Date(Date.parse(current_date) + delta));
                }
            }
            $('[name=' + new_date_name + ']').val(new_date);
            $('#' + new_date_name).text(new_date);
        }

        function selectUser (userid, username) {
            // Set the actual_users_id value, creating a new option if necessary
            if ($('[name=actual_users_id]').find('option[value=' + userid + ']').length) {
                $('[name=actual_users_id]').val(userid).trigger('change');
            } else { 
                // Create a DOM Option and pre-select by default
                var newOption = new Option(decodeURIComponent(username.replace(/\+/g, ' ')), userid, true, true);
                // Append it to the select
                $('[name=actual_users_id]').append(newOption).trigger('change');
            }
        }

        function reset2Defaults () {
            if ($('[name=actual_before_time]').val() != " . PluginProcessmakerTaskCategory::REMINDER_STOP . "
                && $('[name=task_begin]').val() > '" . $_SESSION["glpi_currenttime"] . "') {
                $('[name=actual_before_time]').val($('[name=default_before_time]').val()).trigger('change');
            }

            $('[name=actual_after_time]').val($('[name=default_after_time]').val()).trigger('change');

            // Set the actual_users_id value, creating a new option if necessary
            selectUser($('[name=default_users_id]').val(), $('[name=default_user_name]').val());
        }
    ");
    echo "<input type='hidden' name='plugin_processmaker_taskrecalls_id' value='".$taskCat['plugin_processmaker_taskrecalls_id']."'>";
    echo "<input type='hidden' name='task_begin' value='".$taskCat['task_begin']."'>";
    echo "<input type='hidden' name='task_end' value='".$taskCat['task_end']."'>";
    echo "<input type='hidden' name='plugin_processmaker_tasks_id' value='".$taskCat['pm_tasks_id']."'>";

    echo '<hr class="reminder_settings" style="display:none">';
    echo '<table class="tab_cadre_fixe reminder_settings" style="display:none">';

    echo "<input type='hidden' name='default_before_time' value='{$taskCat['pm_before_time']}'>";
    echo "<input type='hidden' name='default_after_time' value='{$taskCat['pm_after_time']}'>";

    $taskCat['pm_users_id'] = $taskCat['pm_users_id'] ?? 0;
    echo "<input type='hidden' name='default_users_id' value='{$taskCat['pm_users_id']}'>";

    $user_info = urlencode($taskCat['pm_users_id'] > 0 ? getUserName($taskCat['pm_users_id']) : Dropdown::EMPTY_VALUE);
    echo "<input type='hidden' name='default_user_name' value='{$user_info}'>";

    echo '<tr>';

    echo '<td class="tab_bg_1 pm_reminder" nowrap>';
    echo "<i>" . __('Before start (once)', 'processmaker') . "</i>&nbsp;";
    echo '</td>';
    echo '<td class="tab_bg_1" nowrap>';
    if ($taskCat['tr_before_time'] === null) {
        $taskCat['tr_before_time'] = PluginProcessmakerTaskCategory::REMINDER_NONE;
    }
    if ($taskCat['tr_before_time'] == PluginProcessmakerTaskCategory::REMINDER_STOP) {
        echo __('Done', 'processmaker');
        echo "<input type='hidden' name='actual_before_time' value='". PluginProcessmakerTaskCategory::REMINDER_STOP ."'>";
    } else if ($_SESSION["glpi_currenttime"] > $taskCat['task_begin']) {
        echo __('None');
        echo "<input type='hidden' name='actual_before_time' value='". PluginProcessmakerTaskCategory::REMINDER_NONE ."'>";
    } else {
        Dropdown::showFromArray('actual_before_time', PluginProcessmakerTaskCategory::getAllBeforeReminders(), [
            'value'     => $taskCat['tr_before_time'],
            'on_change' => 'computeNextDate("actual_before_time", "next_reminder_before", "task_begin", "sub");'
        ]);
    }
    echo '</td>';

    echo '<td class="tab_bg_1 pm_reminder" nowrap>';
    echo "<i>" . __('After end (every)', 'processmaker') . "</i>";
    echo '</td>';
    echo '<td class="tab_bg_1" nowrap>';
    if ($taskCat['tr_after_time'] === null) {
        $taskCat['tr_after_time'] = PluginProcessmakerTaskCategory::REMINDER_NONE;
    }
    Dropdown::showFromArray('actual_after_time', PluginProcessmakerTaskCategory::getAllAfterReminders(), [
        'value' => $taskCat['tr_after_time'],
        'on_change' => 'computeNextDate("actual_after_time", "next_reminder_after", "task_end", "add");'
    ]);
    echo '</td>';

    echo '<td class="tab_bg_1" nowrap>';
    echo "<i>" . __('Sender', 'processmaker') . "</i>";
    echo "&nbsp;&nbsp;&nbsp;";
    $user_info = urlencode(getUserName(Session::getLoginUserID()));
    echo "<i class='fas fa-male pointer' title='" . __('Set me as sender', 'processmaker') . "' onclick=\"selectUser(" . Session::getLoginUserID() . ", '{$user_info}');\"></i>";
    echo '</td>';
    echo '<td class="tab_bg_1" nowrap>';
    User::dropdown(['name'                 => 'actual_users_id',
                    'display_emptychoice'  => true,
                    'right'                => 'all',
                    'width'                => '',
                    'value'                => $taskCat['tr_users_id']]);

    echo '</td>';
    echo '</tr>';

    echo '<tr>';

    echo '<td class="tab_bg_1 pm_reminder" nowrap>';
    echo "<i>" . __('When', 'processmaker') . "</i>&nbsp;";
    echo '</td>';
    echo '<td id="next_reminder_before" class="tab_bg_1" nowrap>';
    if ($taskCat['tr_before_time'] == PluginProcessmakerTaskCategory::REMINDER_NONE) {
        $next_reminder_before = __('None', 'processmaker');
    } elseif ($taskCat['tr_before_time'] == PluginProcessmakerTaskCategory::REMINDER_STOP) {
        $next_reminder_before = __('Done', 'processmaker');
    } else {
        $next_reminder_before = $taskCat['tr_when'];
    }
    echo strtotime($next_reminder_before) ? date("Y-m-d H:i", strtotime($next_reminder_before)) : $next_reminder_before;
    echo '</td>';
    echo "<input type='hidden' name='next_reminder_before' value='$next_reminder_before' readonly>";

    echo '<td class="tab_bg_1 pm_reminder" nowrap>';
    echo "<i>" . __('Next occurrence', 'processmaker') . "</i>&nbsp;";
    echo '</td>';
    echo '<td id="next_reminder_after" class="tab_bg_1" nowrap>';
    if ($taskCat['tr_after_time'] == PluginProcessmakerTaskCategory::REMINDER_NONE) {
        $next_reminder_after = __('None', 'processmaker');
    } else {
        $next_reminder_after = ($taskCat['tr_before_time'] >= 0 ? date("Y-m-d H:i:s", strtotime($taskCat['task_end']) + $taskCat['tr_after_time']) : $taskCat['tr_when']);
    }
    echo strtotime($next_reminder_after) ? date("Y-m-d H:i", strtotime($next_reminder_after)) : $next_reminder_after;
    echo '</td>';
    echo "<input type='hidden' name='next_reminder_after' value='$next_reminder_after' readonly>";

    echo '<td>';
    echo "<i class='fas fa-sync pointer' style='font-size: larger;' title='" . __('Reset to defaults', 'processmaker') . "' onclick='reset2Defaults();'></i>";
    echo '</td>';

    echo '<td class="tab_bg_1">';
    echo "<input type='submit' name='reminder_settings' value='".__('Save', 'processmaker')."' class='submit'>";
    echo '</td>';

    echo '</tr>';

    echo '</table>';
}

echo '<hr style="border: 1px solid lightgrey;">';

Html::closeForm(true);
