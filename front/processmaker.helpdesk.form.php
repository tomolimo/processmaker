<?php
include_once ("../../../inc/includes.php");

function processMakerShowProcessList ($ID, $from_helpdesk) {
   global $DB, $CFG_GLPI, $LANG, $_SESSION;

   if (!Session::haveRight("ticket", CREATE)) {
      return false;
   }

    $rand = rand();
    echo "<form name=   'processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
    echo "<div class='center'><table class='tab_cadre_fixehov'>";
    echo "<tr><th colspan='2'>".$LANG['processmaker']['item']['tab']."</th></tr>";

    echo "<tr class='tab_bg_2'><td class='right'  colspan='1'>";
    echo $LANG['processmaker']['item']['selectprocess']."&nbsp;";
    echo "<input type='hidden' name='action' value='newcase'>";
    echo "<input type='hidden' name='id' value='-1'>";
    echo "<input type='hidden' name='itemtype' value='Ticket'>";
    echo "<input type='hidden' name='itilcategories_id' value='".$_REQUEST['itilcategories_id']."'>";
    echo "<input type='hidden' name='type' value='".$_REQUEST['type']."'>";
    PluginProcessmakerProcess::dropdown( array( 'value' => 0, 'entity' => $_SESSION['glpiactive_entity'], 'name' => 'plugin_processmaker_process_id' ));
    echo "</td><td class='center'>";
    echo "<input type='submit' name='additem' value='Start' class='submit'>";
    echo "</td></tr>";

    echo "</table>";
    Html::closeForm();
}


function showFormHelpdesk($ID, $pmItem, $caseInfo, $ticket_template=false) {
   global $DB, $CFG_GLPI;

   if (!Ticket::canCreate()) {
      return false;
   }

   if (!$ticket_template
       && Session::haveRightsOr('ticketvalidation', TicketValidation::getValidateRights())) {

      $opt                  = array();
      $opt['reset']         = 'reset';
      $opt['criteria'][0]['field']      = 55; // validation status
      $opt['criteria'][0]['searchtype'] = 'equals';
      $opt['criteria'][0]['value']      = CommonITILValidation::WAITING;
      $opt['criteria'][0]['link']       = 'AND';

      $opt['criteria'][1]['field']      = 59; // validation aprobator
      $opt['criteria'][1]['searchtype'] = 'equals';
      $opt['criteria'][1]['value']      = Session::getLoginUserID();
      $opt['criteria'][1]['link']       = 'AND';

      $url_validate = $CFG_GLPI["root_doc"]."/front/ticket.php?".Toolbox::append_params($opt,
                                                                                        '&amp;');

      if (TicketValidation::getNumberToValidate(Session::getLoginUserID()) > 0) {
         echo "<a href='$url_validate' title=\"".__s('Ticket waiting for your approval')."\"
                   alt=\"".__s('Ticket waiting for your approval')."\">".
         __('Tickets awaiting approval')."</a><br><br>";
      }
   }

   $email  = UserEmail::getDefaultForUser($ID);
   $default_use_notif = Entity::getUsedConfig('is_notif_enable_default', $_REQUEST['entities_id'], '', 1);

   // Set default values...
   $default_values = array('_users_id_requester_notif'
                                                 => array('use_notification'
                                                           => (($email == "")?0:$default_use_notif)),
                           'nodelegate'          => 1,
                           '_users_id_requester' => 0,
                           '_users_id_observer'  => array(0),
                           '_users_id_observer_notif'
                                                 => array('use_notification' => $default_use_notif),
                           'name'                => '',
                           'content'             => '',
                           'itilcategories_id'   => 0,
                           'locations_id'        => 0,
                           'urgency'             => 3,

                           'items_id'            => 0,
                           'entities_id'         => $_REQUEST['entities_id'],
                           'plan'                => array(),
                           'global_validation'   => CommonITILValidation::NONE,
                           '_add_validation'     => 0,
                           'type'                => Entity::getUsedConfig('tickettype',
                                                                          $_REQUEST['entities_id'],
                                                                          '', Ticket::INCIDENT_TYPE),
                           '_right'              => "id",
                           '_filename'           => array(),
                           '_tag_filename'       => array());

   // Get default values from posted values on reload form
   if (!$ticket_template) {
      if (isset($_POST)) {
         $values = Html::cleanPostForTextArea($_POST);
      }
   }

   $ticket = new Ticket();
   // Restore saved value or override with page parameter
   if (!function_exists('restoreInput')) {
      function restoreInput(Array $default=array()) {

         if (isset($_SESSION['saveInput']['Ticket'])) {
            $saved = Html::cleanPostForTextArea($_SESSION['saveInput']['Ticket']);

            // clear saved data when restored (only need once)
            unset($_SESSION['saveInput']['Ticket']);

            return $saved;
         }

         return $default;
      }
   }
   $saved = restoreInput();
   foreach ($default_values as $name => $value) {
      if (!isset($values[$name])) {
         if (isset($saved[$name])) {
            $values[$name] = $saved[$name];
         } else {
            $values[$name] = $value;
         }
      }
   }

   // Check category / type validity
   if ($values['itilcategories_id']) {
      $cat = new ITILCategory();
      if ($cat->getFromDB($values['itilcategories_id'])) {
         switch ($values['type']) {
            case Ticket::INCIDENT_TYPE :
               if (!$cat->getField('is_incident')) {
                  $values['itilcategories_id'] = 0;
               }
               break;

            case Ticket::DEMAND_TYPE :
               if (!$cat->getField('is_request')) {
                  $values['itilcategories_id'] = 0;
               }
               break;

            default :
               break;
         }
      }
   }

   if (!$ticket_template) {
      echo "<form method='post' name='helpdeskform' action='".
      $CFG_GLPI["root_doc"]."/front/tracking.injector.php' enctype='multipart/form-data'>";
   }

   $delegating = User::getDelegateGroupsForUser($values['entities_id']);

   if (count($delegating)) {
      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>".__('This ticket concerns me')." ";

      $rand   = Dropdown::showYesNo("nodelegate", $values['nodelegate']);

      $params = array('nodelegate' => '__VALUE__',
                      'rand'       => $rand,
                      'right'      => "delegate",
                      '_users_id_requester'
                                   => $values['_users_id_requester'],
                      '_users_id_requester_notif'
                                   => $values['_users_id_requester_notif'],
                      'use_notification'
                                   => $values['_users_id_requester_notif']['use_notification'],
                      'entity_restrict'
                                   => $_REQUEST['entities_id']);

      Ajax::UpdateItemOnSelectEvent("dropdown_nodelegate".$rand, "show_result".$rand,
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownDelegationUsers.php",
                                    $params);

      $class = 'right';
      if ($CFG_GLPI['use_check_pref'] && $values['nodelegate']) {
         echo "</th><th>".__('Check your personnal information');
         $class = 'center';
      }

      echo "</th></tr>";
      echo "<tr class='tab_bg_1'><td colspan='2' class='".$class."'>";
      echo "<div id='show_result$rand'>";

      $self = $ticket; // new self();
      if ($values["_users_id_requester"] == 0) {
         $values['_users_id_requester'] = Session::getLoginUserID();
      } else {
         $values['_right'] = "delegate";
      }
      $self->showActorAddFormOnCreate(CommonITILActor::REQUESTER, $values);
      echo "</div>";
      if ($CFG_GLPI['use_check_pref'] && $values['nodelegate']) {
         echo "</td><td class='center'>";
         User::showPersonalInformation(Session::getLoginUserID());
      }
      echo "</td></tr>";

      echo "</table></div>";
      echo "<input type='hidden' name='_users_id_recipient' value='".Session::getLoginUserID()."'>";

   } else {
      // User as requester
      $values['_users_id_requester'] = Session::getLoginUserID();

      if ($CFG_GLPI['use_check_pref']) {
         echo "<div class='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th>".__('Check your personnal information')."</th></tr>";
         echo "<tr class='tab_bg_1'><td class='center'>";
         User::showPersonalInformation(Session::getLoginUserID());
         echo "</td></tr>";
         echo "</table></div>";
      }
   }

   echo "<input type='hidden' name='_from_helpdesk' value='1'>";
   echo "<input type='hidden' name='requesttypes_id' value='".RequestType::getDefault('helpdesk').
   "'>";

   // Load ticket template if available :
   $tt = $ticket->getTicketTemplateToUse($ticket_template, $values['type'],
                                       $values['itilcategories_id'],
                                       $_REQUEST['entities_id']);

   // Predefined fields from template : reset them
   if (isset($values['_predefined_fields'])) {
      $values['_predefined_fields']
                     = Toolbox::decodeArrayFromInput($values['_predefined_fields']);
   } else {
      $values['_predefined_fields'] = array();
   }

   // Store predefined fields to be able not to take into account on change template
   $predefined_fields = array();

   if (isset($tt->predefined) && count($tt->predefined)) {
      foreach ($tt->predefined as $predeffield => $predefvalue) {
         if (isset($values[$predeffield]) && isset($default_values[$predeffield])) {
            // Is always default value : not set
            // Set if already predefined field
            // Set if ticket template change
            if (((count($values['_predefined_fields']) == 0)
                 && ($values[$predeffield] == $default_values[$predeffield]))
                || (isset($values['_predefined_fields'][$predeffield])
                    && ($values[$predeffield] == $values['_predefined_fields'][$predeffield]))
                || (isset($values['_tickettemplates_id'])
                    && ($values['_tickettemplates_id'] != $tt->getID()))) {
               $values[$predeffield]            = $predefvalue;
               $predefined_fields[$predeffield] = $predefvalue;
            }
         } else { // Not defined options set as hidden field
            echo "<input type='hidden' name='$predeffield' value='$predefvalue'>";
         }
      }
      // All predefined override : add option to say predifined exists
      if (count($predefined_fields) == 0) {
         $predefined_fields['_all_predefined_override'] = 1;
      }
   } else { // No template load : reset predefined values
      if (count($values['_predefined_fields'])) {
         foreach ($values['_predefined_fields'] as $predeffield => $predefvalue) {
            if ($values[$predeffield] == $predefvalue) {
               $values[$predeffield] = $default_values[$predeffield];
            }
         }
      }
   }

   if (($CFG_GLPI['urgency_mask'] == (1<<3))
       || $tt->isHiddenField('urgency')) {
      // Dont show dropdown if only 1 value enabled or field is hidden
      echo "<input type='hidden' name='urgency' value='".$values['urgency']."'>";
   }

   // Display predefined fields if hidden
   if ($tt->isHiddenField('items_id')) {

      if (!empty($values['items_id'])) {
         foreach ($values['items_id'] as $itemtype => $items) {
            foreach ($items as $items_id) {
               echo "<input type='hidden' name='items_id[$itemtype][$items_id]' value='$items_id'>";
            }
         }
      }
   }
   if ($tt->isHiddenField('locations_id')) {
      echo "<input type='hidden' name='locations_id' value='".$values['locations_id']."'>";
   }
   echo "<input type='hidden' name='entities_id' value='".$_REQUEST['entities_id']."'>";
   echo "<input type='hidden' name='processId' value='".$caseInfo->processId."'>";
   echo "<div class='center'><table class='tab_cadre_fixe'>";

   echo "<tr><th width='30%'>".$caseInfo->processName."</th><th>";

   if (Session::isMultiEntitiesMode()) {
      echo "(".Dropdown::getDropdownName("glpi_entities", $_REQUEST['entities_id']).")";
   }
   echo "</th></tr>";

   echo "<tr class='tab_bg_1' style='display:none;'>";
   echo "<td>".sprintf(__('%1$s%2$s'), __('Type'), $tt->getMandatoryMark('type'))."</td>";
   echo "<td>";
   Ticket::dropdownType('type', array('value'     => $values['type'],
                                    'on_change' => 'this.form.submit()'));
   echo "</td></tr>";

   echo "<tr class='tab_bg_1' style='display:none;'>";
   echo "<td>".sprintf(__('%1$s%2$s'), __('Category'),
                       $tt->getMandatoryMark('itilcategories_id'))."</td>";
   echo "<td>";

   $condition = "`is_helpdeskvisible`='1'";
   switch ($values['type']) {
      case Ticket::DEMAND_TYPE :
         $condition .= " AND `is_request`='1'";
         break;

      default: // Ticket::INCIDENT_TYPE :
         $condition .= " AND `is_incident`='1'";
   }
   $opt = array('value'     => $values['itilcategories_id'],
                'condition' => $condition,
                'entity'    => $_REQUEST['entities_id'],
                'on_change' => 'this.form.submit()');

   if ($values['itilcategories_id'] && $tt->isMandatoryField("itilcategories_id")) {
      $opt['display_emptychoice'] = false;
   }

   ITILCategory::dropdown($opt);
   echo "</td></tr>";

   if ($CFG_GLPI['urgency_mask'] != (1<<3)) {
      if (!$tt->isHiddenField('urgency')) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".sprintf(__('%1$s%2$s'), __('Urgency'), $tt->getMandatoryMark('urgency')).
         "</td>";
         echo "<td>";
         Ticket::dropdownUrgency(array('value' => $values["urgency"]));
         echo "</td></tr>";
      }
   }

   if (empty($delegating)
       && NotificationTargetTicket::isAuthorMailingActivatedForHelpdesk()) {
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Inform me about the actions taken')."</td>";
      echo "<td>";
      if ($values["_users_id_requester"] == 0) {
         $values['_users_id_requester'] = Session::getLoginUserID();
      }
      $_POST['value']            = $values['_users_id_requester'];
      $_POST['field']            = '_users_id_requester_notif';
      $_POST['use_notification'] = $values['_users_id_requester_notif']['use_notification'];
      include (GLPI_ROOT."/ajax/uemailUpdate.php");

      echo "</td></tr>";
   }
   if (($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0)
       && (count($_SESSION["glpiactiveprofile"]["helpdesk_item_type"]))) {
      if (!$tt->isHiddenField('itemtype')) {
         echo "<tr class='tab_bg_1' style='display:none;'>";
         echo "<td>".sprintf(__('%1$s%2$s'), __('Hardware type'),
                             $tt->getMandatoryMark('items_id'))."</td>";
         echo "<td>";

         $values['_canupdate'] = Session::haveRight('ticket', CREATE);
         Item_Ticket::itemAddForm($ticket, $values);
         echo "</td></tr>";
      }
   }

   if (!$tt->isHiddenField('locations_id')) {
      echo "<tr class='tab_bg_1' style='display:none;'><td>";
      printf(__('%1$s%2$s'), __('Location'), $tt->getMandatoryMark('locations_id'));
      echo "</td><td>";
      Location::dropdown(array('value'  => $values["locations_id"]));
      echo "</td></tr>";
   }

   if (!$tt->isHiddenField('_users_id_observer')
       || $tt->isPredefinedField('_users_id_observer')) {
      echo "<tr class='tab_bg_1'>";
      echo "<td>".sprintf(__('%1$s%2$s'), _n('Watcher', 'Watchers', 2),
                          $tt->getMandatoryMark('_users_id_observer'))."</td>";
      echo "<td>";
      $values['_right'] = "groups";

      if (!$tt->isHiddenField('_users_id_observer')) {
         // Observer

         if ($tt->isPredefinedField('_users_id_observer')
            && !is_array($values['_users_id_observer'])) {

            //convert predefined value to array
            $values['_users_id_observer'] = array($values['_users_id_observer']);
            $values['_users_id_observer_notif']['use_notification'] =
               array($values['_users_id_observer_notif']['use_notification']);

            // add new line to permit adding more observers
            $values['_users_id_observer'][1] = 0;
            $values['_users_id_observer_notif']['use_notification'][1] = 1;
         }

         echo "<div class='actor_single first-actor'>";
         if (isset($values['_users_id_observer'])) {
            $observers = $values['_users_id_observer'];
            foreach ($observers as $index_observer => $observer) {
               $options = array_merge($values, array('_user_index' => $index_observer));
               Ticket::showFormHelpdeskObserver($options);
            }
         }
         echo "</div>";

      } else { // predefined value
         if (isset($values["_users_id_observer"]) && $values["_users_id_observer"]) {
            echo Ticket::getActorIcon('user', CommonITILActor::OBSERVER)."&nbsp;";
            echo Dropdown::getDropdownName("glpi_users", $values["_users_id_observer"]);
            echo "<input type='hidden' name='_users_id_observer' value=\"".
            $values["_users_id_observer"]."\">";

         }
      }
      echo "</td></tr>";
   }

   if (!$tt->isHiddenField('name')
       || $tt->isPredefinedField('name')) {
      echo "<tr class='tab_bg_1' style='display:none;'>";
      echo "<td>".sprintf(__('%1$s%2$s'), __('Title'), $tt->getMandatoryMark('name'))."<td>";
      if (!$tt->isHiddenField('name')) {
         echo "<input type='text' maxlength='250' size='80' name='name'
                       value=\"".$values['name']."\">";
      } else {
         echo $values['name'];
         echo "<input type='hidden' name='name' value=\"".$values['name']."\">";
      }
      echo "</td></tr>";
   }

   if (!$tt->isHiddenField('content')
       || $tt->isPredefinedField('content')) {
      echo "<tr class='tab_bg_1' style='display:none;'>";
      echo "<td>".sprintf(__('%1$s%2$s'), __('Description'), $tt->getMandatoryMark('content')).
      "</td><td>";
      $rand      = mt_rand();
      $rand_text = mt_rand();

      $cols       = 90;
      $rows       = 6;
      $content_id = "content$rand";

      $values["content"] = $ticket->setSimpleTextContent($values["content"]);

      echo "<div id='content$rand_text'>";
      echo "<textarea id='$content_id' name='content' cols='$cols' rows='$rows'>".
      $values['content']."</textarea></div>";
      echo "</td></tr>";
   }

   echo "<tr class='tab_bg_1'>";
   echo "<td class='center' colspan='2'>";
   $rand = rand();
   $pmCaseUser = $caseInfo->currentUsers[0]; // by default
   $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus;
   echo "<iframe onload='onLoadFrame( event, \"".$caseInfo->caseId."\", ".$pmCaseUser->delIndex.", ".$caseInfo->caseNumber.", \"".$caseInfo->processName."\") ;'  id='caseiframe' width=100% style='border:none;' src='".$pmItem->serverURL."/cases/cases_Open?sid=". $_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&rand=$rand' ></iframe>";
   echo "</td></tr>";

   // File upload system
   $width = '100%';
   if ($CFG_GLPI['use_rich_text']) {
      $width = '50%';
   }
   echo "<tr class='tab_bg_1'>";
   echo "<td class='top'>".sprintf(__('%1$s (%2$s)'), __('File'), Document::getMaxUploadSize());
   DocumentType::showAvailableTypesLink();
   echo "</td>";
   echo "<td class='top'>";
   echo "<div id='fileupload_info'></div>";
   echo "</td>";
   echo "</tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td colspan='2'>";
   echo "<table width='100%'><tr>";
   echo "<td width='$width '>";

   echo Html::file(array('multiple' => true,
                         'values' => array('filename' => $values['_filename'],
                                           'tag' => $values['_tag_filename'])
                  ));
   //       "<div id='uploadfiles'><input type='file' name='filename[]' value='' size='60'></div>";
   echo "</td>";
   if ($CFG_GLPI['use_rich_text']) {
      echo "<td width='$width '>";
      if (!isset($rand)) {
         $rand = mt_rand();
      }

      echo Html::initImagePasteSystem($content_id, $rand);
      echo "</td>";
   }
   echo "</tr></table>";

   echo "</td>";
   echo "</tr>";

   if (!$ticket_template) {
      echo "<tr class='tab_bg_1' style='display:none;'>";
      echo "<td colspan='2' class='center'>";

      if ($tt->isField('id') && ($tt->fields['id'] > 0)) {
         echo "<input type='hidden' name='_tickettemplates_id' value='".$tt->fields['id']."'>";
         echo "<input type='hidden' name='_predefined_fields'
                   value=\"".Toolbox::prepareArrayForInput($predefined_fields)."\">";
      }
      echo "<input type='submit' name='add' value=\"".__s('Submit message')."\" class='submit'>";
      echo "</td></tr>";
   }

   echo "</table></div>";
   if (!$ticket_template) {
      Html::closeForm();
   }
}




function processMakerShowCase( $ID, $from_helpdesk ) {
   global $CFG_GLPI;

   $pmItem = new PluginProcessmakerProcessmaker( );
   $pmItem->login( );

    $caseInfo = $pmItem->getCaseInfo( $_REQUEST['case_id'] );
   if ($caseInfo->status_code == 0) {
      // case is created
      // Must show it...

      $rand = rand();

      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.helpdesk.js?rand=$rand'></script>"; //?rand=$rand'

      showFormHelpdesk(Session::getLoginUserID(), $pmItem, $caseInfo);
   }

}

function in_array_recursive($needle, $haystack) {

   $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));

   foreach ($it AS $element) {
      if ($element == $needle) {
         return true;
      }
   }

   return false;
}


//// redirect if no create ticket right
//if (!Session::haveRight('ticket', CREATE )) {
//    if (Session::haveRight('observe_ticket', 1) || Session::haveRight('validate_ticket', 1)) {
//        Html::redirect($CFG_GLPI['root_doc']."/front/ticket.php");
//    } else if (Session::haveRight('reservation_helpdesk', 1)) {
//        Html::redirect($CFG_GLPI['root_doc']."/front/reservationitem.php");
//    } else if (Session::haveRight('faq', READ)) {
//        Html::redirect($CFG_GLPI['root_doc']."/front/helpdesk.faq.php");
//    }
//}

//Session::checkHelpdeskAccess();

//Html::helpHeader($LANG['job'][13], $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);


// Change profile system
if (isset($_POST['newprofile'])) {
   if (isset($_SESSION["glpiprofiles"][$_POST['newprofile']])) {
      Session::changeProfile($_POST['newprofile']);

      if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
         Html::redirect($CFG_GLPI['root_doc']."/front/central.php");
      } else {
         Html::redirect($_SERVER['PHP_SELF']);
      }

   } else {
      Html::redirect(preg_replace("/entities_id=.*/", "", $_SERVER['HTTP_REFERER']));
   }
}

// Manage entity change
if (isset($_GET["active_entity"])) {
   if (!isset($_GET["is_recursive"])) {
      $_GET["is_recursive"] = 0;
   }
   if (Session::changeActiveEntities($_GET["active_entity"], $_GET["is_recursive"])) {
      if ($_GET["active_entity"] == $_SESSION["glpiactive_entity"]) {
         Html::redirect(preg_replace("/entities_id.*/", "", $_SERVER['HTTP_REFERER']));
      }
   }
}

// Redirect management
if (isset($_GET["redirect"])) {
   Toolbox::manageRedirect($_GET["redirect"]);
}

// redirect if no create ticket right
if (!Session::haveRight('ticket', CREATE)
    && !Session::haveRight('reminder_public', READ)
    && !Session::haveRight("rssfeed_public", READ)) {

   if (Session::haveRight('followup', TicketFollowup::SEEPUBLIC)
        || Session::haveRight('task', TicketTask::SEEPUBLIC)
    || Session::haveRightsOr('ticketvalidation', array(TicketValidation::VALIDATEREQUEST,
                                                       TicketValidation::VALIDATEINCIDENT))) {
      Html::redirect($CFG_GLPI['root_doc']."/front/ticket.php");

   } else if (Session::haveRight('reservation', ReservationItem::RESERVEANITEM)) {
      Html::redirect($CFG_GLPI['root_doc']."/front/reservationitem.php");

   } else if (Session::haveRight('knowbase', KnowbaseItem::READFAQ)) {
      Html::redirect($CFG_GLPI['root_doc']."/front/helpdesk.faq.php");
   }
}

Session::checkHelpdeskAccess();

Html::helpHeader(__('New ticket'), $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);


if (isset($_REQUEST['case_id'])) {
   $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE id='".$_REQUEST['case_id']."'";
   $res = $DB->query( $query );
   if ($DB->numrows( $res )) { // a ticket already exists for this case, then show new cases
      processMakerShowProcessList(Session::getLoginUserID(), 1);
   } else {
      // before showing the case, we must check the rights for this user to view it, if entity has been changed in the meanwhile
      // and must check if entity of the ticket is in the tree of authorized entities for current profile
      $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_REQUEST["itilcategories_id"], $_REQUEST["type"], $_SESSION['glpiactiveprofile']['id'], $_REQUEST['entities_id'] );
      if (in_array( $_REQUEST['entities_id'], $_SESSION['glpiactiveentities']) && in_array_recursive( $_REQUEST['process_id'], $processList )) {
         processMakerShowCase(Session::getLoginUserID(), 1);
      } else {
         Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
      }

   }
} else {
   processMakerShowProcessList(Session::getLoginUserID(), 1);
}

Html::helpFooter();

