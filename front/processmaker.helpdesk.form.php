<?php
if( !defined( 'GLPI_ROOT' ) ) 
    define('GLPI_ROOT', '../../..');
include_once (GLPI_ROOT."/inc/includes.php");
include_once '../inc/processmaker.class.php' ;

function processMakerShowProcessList ($ID, $from_helpdesk) {
    global $DB, $CFG_GLPI, $LANG, $_SESSION ;

    if (!Session::haveRight("create_ticket","1")) {
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
//    Dropdown::show('PluginProcessmakerProcessmaker', array( 'name' => 'plugin_processmaker_process_id', 'condition' => "is_active=1 and is_helpdeskvisible=1")); // condition is used to prevent start of none-active and none-helpdesk-visible cases
    PluginProcessmakerProcess::dropdown( array( 'entity' => $_SESSION['glpiactive_entity'], 'name' => 'plugin_processmaker_process_id' )); 
    echo "</td><td class='center'>";
    echo "<input type='submit' name='additem' value='Start' class='submit'>";
    echo "</td></tr>";

    echo "</table>";
    Html::closeForm();
}


/**
* Print the helpdesk form
*
* @param $ID int : ID of the user who want to display the Helpdesk
* @param $ticket_template int : ID ticket template for preview : false if not used for preview
*
* @return nothing (print the helpdesk)
**/
function showFormHelpdesk($ID, $pmItem, $caseInfo, $ticket_template=false) {
    global $DB, $CFG_GLPI, $LANG;

    if (!Session::haveRight("create_ticket","1")) {
        return false;
    }

    $query = "SELECT `realname`, `firstname`, `name`
            FROM `glpi_users`
            WHERE `id` = '$ID'";
    $result = $DB->query($query);


    $email  = UserEmail::getDefaultForUser($ID);


    // Set default values...
    $default_values = array('_users_id_requester_notif' => array('use_notification'  => ($email==""?0:1),
                                                        'alternative_email' => ''),
                    'nodelegate'                => 1,
                    '_users_id_requester'       => 0,
                    'name'                      => '',
                    'content'                   => '',
                    'itilcategories_id'         => 0,
                    'urgency'                   => 3,
                    'itemtype'                  => '',
                    'entities_id'               => $_SESSION['glpiactive_entity'],
                    'items_id'                  => 0,
                    'plan'                      => array(),
                    'global_validation'         => 'none',
                    'due_date'                  => 'NULL',
                    'slas_id'                   => 0,
                    '_add_validation'           => 0,
                    'type'                      => EntityData::getUsedConfig('tickettype',
                                                                            $_SESSION['glpiactive_entity'],
                                                                            '', Ticket::INCIDENT_TYPE),
                    '_right'                    => "id");

    if (!$ticket_template) {
        $options = $_REQUEST;
    }

    // Restore saved value or override with page parameter
    foreach ($default_values as $name => $value) {
        if (!isset($options[$name])) {
        if (isset($_SESSION["helpdeskSaved"][$name])) {
            $options[$name] = $_SESSION["helpdeskSaved"][$name];
        } else {
            $options[$name] = $value;
        }
        }
    }
    // Clean text fields
    $options['name']    = stripslashes($options['name']);
    $options['content'] = Html::cleanPostForTextArea($options['content']);

    if (!$ticket_template) {
        echo "<form method='post' name='helpdeskform' action='".
            $CFG_GLPI["root_doc"]."/front/tracking.injector.php' enctype='multipart/form-data'>";
    }


    $delegating = User::getDelegateGroupsForUser($options['entities_id']);

    if (count($delegating)) {
        echo "<div class='center'><table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>".$LANG['job'][69]."&nbsp;:&nbsp;";

        $rand   = Dropdown::showYesNo("nodelegate", $options['nodelegate']);

        $params = array ('nodelegate' => '__VALUE__',
                        'rand'       => $rand,
                        'right'      => "delegate",
                        '_users_id_requester'
                                    => $options['_users_id_requester'],
                        '_users_id_requester_notif'
                                    => $options['_users_id_requester_notif'],
                        'use_notification'
                                    => $options['_users_id_requester_notif']['use_notification'],
                        'entity_restrict'
                                    => $_SESSION["glpiactive_entity"]);

        Ajax::UpdateItemOnSelectEvent("dropdown_nodelegate".$rand, "show_result".$rand,
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownDelegationUsers.php",
                                    $params);

        echo "</th></tr>";
        echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
        echo "<div id='show_result$rand'>";

        $self = new Ticket();
        if ($options["_users_id_requester"] == 0) {
        $options['_users_id_requester'] = Session::getLoginUserID();
        } else {
        $options['_right'] = "delegate";
        }
        $self->showActorAddFormOnCreate(Ticket::REQUESTER, $options);
        echo "</div>";
        echo "</td></tr>";

        echo "</table></div>";
        echo "<input type='hidden' name='_users_id_recipient' value='".Session::getLoginUserID()."'>";
    }

    echo "<input type='hidden' name='_from_helpdesk' value='1'>";
    echo "<input type='hidden' name='requesttypes_id' value='".RequestType::getDefault('helpdesk').
        "'>";


    // Load ticket template if available :
    $tt = new TicketTemplate();

    // First load default entity one
    if ($template_id = EntityData::getUsedConfig('tickettemplates_id', $_SESSION["glpiactive_entity"])) {
        // with type and categ
        $tt->getFromDBWithDatas($template_id, true);
    }

    $field = '';
    if ($options['type'] && $options['itilcategories_id']) {
        $categ = new ITILCategory();
        if ($categ->getFromDB($options['itilcategories_id'])) {
        switch ($options['type']) {
            case Ticket::INCIDENT_TYPE :
                $field = 'tickettemplates_id_incident';
                break;

            case Ticket::DEMAND_TYPE :
                $field = 'tickettemplates_id_demand';
                break;
        }

        if (!empty($field) && $categ->fields[$field]) {
            // without type and categ
            $tt->getFromDBWithDatas($categ->fields[$field], false);
        }
        }
    }
    if ($ticket_template) {
        // with type and categ
        $tt->getFromDBWithDatas($ticket_template, true);
    }

    // Predefined fields from template : reset them
    if (isset($options['_predefined_fields'])) {
        $options['_predefined_fields']
                    = unserialize(rawurldecode(stripslashes($options['_predefined_fields'])));
    } else {
        $options['_predefined_fields'] = array();
    }

    // Store predefined fields to be able not to take into account on change template
    $predefined_fields = array();

    if (isset($tt->predefined) && count($tt->predefined)) {
        foreach ($tt->predefined as $predeffield => $predefvalue) {
        if (isset($options[$predeffield]) && isset($default_values[$predeffield])) {
            // Is always default value : not set
            // Set if already predefined field
            // Set if ticket template change
            if ($options[$predeffield] == $default_values[$predeffield]
                || (isset($options['_predefined_fields'][$predeffield])
                    && $options[$predeffield] == $options['_predefined_fields'][$predeffield])
                || (isset($options['_tickettemplates_id'])
                    && $options['_tickettemplates_id'] != $tt->getID())) {
                $options[$predeffield]           = $predefvalue;
                $predefined_fields[$predeffield] = $predefvalue;
            }
        } else { // Not defined options set as hidden field
            echo "<input type='hidden' name='$predeffield' value='$predefvalue'>";
        }
        }

    } else { // No template load : reset predefined values
        if (count($options['_predefined_fields'])) {
        foreach ($options['_predefined_fields'] as $predeffield => $predefvalue) {
            if ($options[$predeffield] == $predefvalue) {
                $options[$predeffield] = $default_values[$predeffield];
            }
        }
        }
    }
    unset($_SESSION["helpdeskSaved"]);

    if ($CFG_GLPI['urgency_mask']==(1<<3) || $tt->isHiddenField('urgency')) {
        // Dont show dropdown if only 1 value enabled or field is hidden
        echo "<input type='hidden' name='urgency' value='".$options['urgency']."'>";
    }

    // Display predefined fields if hidden
    if ($tt->isHiddenField('itemtype')) {
        echo "<input type='hidden' name='itemtype' value='".$options['itemtype']."'>";
        echo "<input type='hidden' name='items_id' value='".$options['items_id']."'>";
    }

    echo "<input type='hidden' name='entities_id' value='".$_SESSION["glpiactive_entity"]."'>";
    echo "<div class='center'><table class='tab_cadre_fixe'>";

    echo "<tr><th colspan='2'>".$caseInfo->processName."&nbsp;:&nbsp;";
    if (Session::isMultiEntitiesMode()) {
        echo "&nbsp;(".Dropdown::getDropdownName("glpi_entities", $_SESSION["glpiactive_entity"]).")";
    }
    echo "</th></tr>";

    echo "<tr class='tab_bg_1' style='display:none;'>";
    echo "<td>".$LANG['common'][17]."&nbsp;:".$tt->getMandatoryMark('type')."</td>";
    echo "<td>";
    Ticket::dropdownType('type', array('value'     => $options['type'],
                                    'on_change' => 'submit()'));
    echo "</td></tr>";

    echo "<tr class='tab_bg_1' style='display:none;'>";
    echo "<td>".$LANG['common'][36]."&nbsp;:";
    echo $tt->getMandatoryMark('itilcategories_id');
    echo "</td><td>";

    $condition = "`is_helpdeskvisible`='1'";

    switch ($options['type']) {
        case Ticket::DEMAND_TYPE :
        $condition .= " AND `is_request`='1'";
        break;

        default: // Ticket::INCIDENT_TYPE :
        $condition .= " AND `is_incident`='1'";
    }

    $opt = array('value'     => $options['itilcategories_id'],
                                        'condition' => $condition,
                                        'on_change' => 'submit()');
    if ($options['itilcategories_id'] && $tt->isMandatoryField("itilcategories_id")) {
        $opt['display_emptychoice'] = false;
    }

    Dropdown::show('ITILCategory', $opt);
    echo "</td></tr>";


    if ($CFG_GLPI['urgency_mask']!=(1<<3)) {
        if (!$tt->isHiddenField('urgency')) {
        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['joblist'][29]."&nbsp;:".$tt->getMandatoryMark('urgency')."</td>";
        echo "<td>";
        Ticket::dropdownUrgency("urgency", $options['urgency']);
        echo "</td></tr>";
        }
    }

    if (empty($delegating) && NotificationTargetTicket::isAuthorMailingActivatedForHelpdesk()) {
        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['help'][8]."&nbsp;:&nbsp;</td>";
        echo "<td>";
        if ($options["_users_id_requester"] == 0) {
        $options['_users_id_requester'] = Session::getLoginUserID();
        }
        $_REQUEST['value']            = $options['_users_id_requester'];
        $_REQUEST['field']            = '_users_id_requester_notif';
        $_REQUEST['use_notification'] = $options['_users_id_requester_notif']['use_notification'];
        include (GLPI_ROOT."/ajax/uemailUpdate.php");

        echo "</td></tr>";
    }

    if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0) {
        if (!$tt->isHiddenField('itemtype')) {
            echo "<tr class='tab_bg_1' style='display:none;'>";
        echo "<td>".$LANG['help'][24]."&nbsp;: ".$tt->getMandatoryMark('itemtype')."</td>";
        echo "<td>";
        Ticket::dropdownMyDevices($options['_users_id_requester'], $_SESSION["glpiactive_entity"],
                                $options['itemtype'], $options['items_id']);
        Ticket::dropdownAllDevices("itemtype", $options['itemtype'], $options['items_id'], 0, $options['_users_id_requester'],
                                    $_SESSION["glpiactive_entity"]);
        echo "<span id='item_ticket_selection_information'></span>";

        echo "</td></tr>";
        }
    }

    if (!$tt->isHiddenField('name')
        || $tt->isPredefinedField('name')) {
        echo "<tr class='tab_bg_1' style='display:none;'>";
        echo "<td>".$LANG['common'][57]."&nbsp;:".
                    $tt->getMandatoryMark('name')."</td>";
        echo "<td><input type='text' maxlength='250' size='80' name='name'
                        value=\"".$options['name']."\"></td></tr>";
    }

    if (!$tt->isHiddenField('content')
        || $tt->isPredefinedField('content')) {
        echo "<tr class='tab_bg_1' style='display:none;'>";
        echo "<td>".$LANG['joblist'][6]."&nbsp;:".
                    $tt->getMandatoryMark('content')."</td>";
        echo "<td><textarea name='content' cols='80' rows='14'>".$options['content']."</textarea>";
        echo "</td></tr>";
    }
    echo "<tr class='tab_bg_1'>";
    echo "<th class='center' colspan=2>&nbsp;";
    echo "</th></tr>";
    
    
    echo "<tr class='tab_bg_1'>";
    echo "<td class='center' colspan=2>";
    $rand = rand();
    $pmCaseUser = $caseInfo->currentUsers[0] ; // by default
    $paramsURL = "DEL_INDEX=".$pmCaseUser->delIndex."&action=".$caseInfo->caseStatus ;                            
    
    echo "<iframe onload='onLoadFrame( event, \"".$caseInfo->caseId."\", ".$pmCaseUser->delIndex.", ".$caseInfo->caseNumber.", \"".$caseInfo->processName."\") ;' id='caseiframe' width=100% style='border:none;' src='".$pmItem->serverURL."/cases/cases_Open?sid=". $_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&rand=$rand' ></iframe>"; 
    echo "</td></tr>";

    echo "<tr><th colspan='2'>&nbsp;";
    echo "</th></tr>";
    
    echo "<tr class='tab_bg_1'>";
    echo "<td>".$LANG['document'][2]." (".Document::getMaxUploadSize().")&nbsp;:&nbsp;";
    echo "<img src='".$CFG_GLPI["root_doc"]."/pics/aide.png' class='pointer' alt='".
            $LANG['central'][7]."' onclick=\"window.open('".$CFG_GLPI["root_doc"].
            "/front/documenttype.list.php','Help','scrollbars=1,resizable=1,width=1000,height=800')\">";

    echo "&nbsp;";
    Ticket::showDocumentAddButton(60);

    echo "</td>";
    echo "<td><div id='uploadfiles'><input type='file' name='filename[]' value='' size='60'></div>";

    echo "</td></tr>";

    if (!$ticket_template) {
        echo "<tr class='tab_bg_1' style='display:none;'>";
        echo "<td colspan='2' class='center'>";

        if ($tt->isField('id') && $tt->fields['id'] > 0) {
        echo "<input type='hidden' name='_tickettemplates_id' value='".$tt->fields['id']."'>";
        echo "<input type='hidden' name='_predefined_fields'
                        value=\"".rawurlencode(serialize($predefined_fields))."\">";
        }

        echo "<input type='submit' name='add' value=\"".$LANG['help'][14]."\" class='submit'>";

        echo "</td></tr>";
    }

    echo "</table></div>";
    if (!$ticket_template) {
        Html::closeForm();
    }
}



function processMakerShowCase( $ID, $from_helpdesk ) {
    global $LANG ;
    
    Html::helpHeader($LANG['job'][13], $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);
            
    //$userGLPI = new User();
    //$userGLPI->getFromDB( $ID ) ;
    //if( $userGLPI->fields['language'] != null )
    //    $lang =  substr( $userGLPI->fields['language'], 0, 2)  ;
    //else
    //    $lang = "en" ;
    
    $pmItem = new PluginProcessmakerProcessmaker( ) ; 
    $pmItem->login( ) ;
    
    //if( isset( $_REQUEST['sid'] )) {
    //    $pmItem->useSession( $_REQUEST['sid'], $lang ) ; 
    //}
    //else {
    //    $pmItem->openSession( $userGLPI->fields['name'], "md5:37d442efb43ebb80ec6f9649b375ab72", $lang ) ; 
    //}
    
    $caseInfo = $pmItem->getCaseInfo( $_REQUEST['case_id'] ) ;
    if ($caseInfo->status_code == 0){
        // case is created 
        // Must show it...
        // we may input this case into a temporary case table with session id for key
        // we need some more info, del_index...
//        $pmCaseUser = $caseInfo->currentUsers[0] ; // by default
        
        $rand = rand();

//        echo "<div id='toHideDefaultTicketCreation' style='display: none;'>" ;
        //echo "<div id='toHideDefaultTicketCreation' >" ;
        echo "<script type='text/javascript' src='".GLPI_ROOT."/plugins/processmaker/js/cases.helpdesk.js'></script>" ; //?rand=$rand'
        
        showFormHelpdesk(Session::getLoginUserID(), $pmItem, $caseInfo);
        //echo "</div>";

        //echo "<form name='processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
//        echo "<div class='center'><table class='tab_cadre_fixehov'>";
//        echo "<tr><th >Input your request, and click on 'Next Step' (when needed add files above)</th></tr>";
        //echo "<tr class='tab_bg_2' ><td id='GLPI-PM-scriptCases' colspan=2>
        //        <script type='text/javascript' src='".GLPI_ROOT."/plugins/processmaker/js/cases.helpdesk.js'></script> 
        //    </td></tr>" ; //?rand=$rand'
        
//        echo "<tr><td class='center'>";
                
        
////        echo "<iframe onload='onLoadFrame( event, \"".$caseInfo->caseId."\", ".$pmCaseUser->delIndex.", ".$caseInfo->caseNumber.", \"".$caseInfo->processName."\") ;' id='caseiframe' width=100% style='border:none;' src='".$pmItem->serverURL."/cases/cases_Open?sid=". $_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$caseInfo->caseId."&".$paramsURL."&rand=$rand' ></iframe>"; 
                
//        echo "</td></tr>";
        
        //echo "<tr><td class='center'>";
        
        ////echo "<div id='toHideDefaultTicketCreation' >" ;
        
        ////echo "</div>";
        //echo "</td></tr>";
        
        echo "</table>";
 //       Html::closeForm();
    }
    
           
}

function in_array_recursive($needle, $haystack) { 

    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack)); 

    foreach($it AS $element) { 
        if($element == $needle) { 
            return true; 
        } 
    } 

    return false; 
} 


// redirect if no create ticket right
if (!Session::haveRight('create_ticket', 1)) {
    if (Session::haveRight('observe_ticket', 1) || Session::haveRight('validate_ticket', 1)) {
        Html::redirect($CFG_GLPI['root_doc']."/front/ticket.php");
    } else if (Session::haveRight('reservation_helpdesk', 1)) {
        Html::redirect($CFG_GLPI['root_doc']."/front/reservationitem.php");
    } else if (Session::haveRight('faq', 'r')) {
        Html::redirect($CFG_GLPI['root_doc']."/front/helpdesk.faq.php");
    }
}

Session::checkHelpdeskAccess();

Html::helpHeader($LANG['job'][13], $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);

if (isset($_REQUEST['case_id'])) {
    $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE id='".$_REQUEST['case_id']."'" ;
    $res = $DB->query( $query ) ;
    if( $DB->numrows( $res ) ) // a ticket already exists for this case, then show new cases
        processMakerShowProcessList(Session::getLoginUserID(), 1);
    else {
        // before showing the case, we must check the rights for this user to view it, if entity has been changed in the meanwhile        
        $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_REQUEST["itilcategories_id"], $_REQUEST["type"], $_SESSION['glpiactiveprofile']['id'], $_SESSION['glpiactive_entity'] ) ;
        if( in_array_recursive( $_REQUEST['process_id'], $processList ) ) {
            processMakerShowCase(Session::getLoginUserID(), 1); 
        } else {
            Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
        }
        
    }
} else 
    processMakerShowProcessList(Session::getLoginUserID(), 1);

Html::helpFooter();

?>