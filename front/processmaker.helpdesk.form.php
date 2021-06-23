<?php
include_once ("../../../inc/includes.php");


/**
 * Summary of processMakerShowProcessList
 * @param mixed $ID
 * @param mixed $from_helpdesk
 * @return boolean
 */
function processMakerShowProcessList ($ID, $from_helpdesk) {
   global $DB, $CFG_GLPI, $_SESSION;

   if (!Session::haveRight("ticket", CREATE)) {
      return false;
   }

   $rand = rand();
   echo "<form name=   'processmaker_form$rand' id='processmaker_form$rand' method='post' action='".Toolbox::getItemTypeFormURL("PluginProcessmakerProcessmaker")."'>";
   echo "<div class='center'><table class='tab_cadre_fixehov'>";
   echo "<tr><th colspan='2'>".__('Process - Case', 'processmaker')."</th></tr>";

   echo "<tr class='tab_bg_2'><td class='right'  colspan='1'>";
   echo __('Select the process you want to add', 'processmaker');
   echo "<input type='hidden' name='action' value='newcase'>";
   echo "<input type='hidden' name='id' value='-1'>";
   echo "<input type='hidden' name='itemtype' value='Ticket'>";
   echo "<input type='hidden' name='itilcategories_id' value='".$_REQUEST['itilcategories_id']."'>";
   echo "<input type='hidden' name='type' value='".$_REQUEST['type']."'>";
   PluginProcessmakerProcess::dropdown( [ 'value' => 0, 'entity' => $_SESSION['glpiactive_entity'], 'name' => 'plugin_processmaker_processes_id' ]);
   echo "</td><td class='center'>";
   echo "<input type='submit' name='additem' value='Start' class='submit'>";
   echo "</td></tr>";

   echo "</table>";
   Html::closeForm();

   return true;
}


/**
 * Summary of processMakerShowCase
 * @param mixed $users_id
 * @param mixed $from_helpdesk
 */
function processMakerShowCase($users_id, $from_helpdesk) {
   global $CFG_GLPI, $PM_SOAP;

   $caseInfo = $PM_SOAP->getCaseInfo( $_REQUEST['case_guid'] );
   if ($caseInfo->status_code == 0) {
      // case is created
      // Must show it...

      $rand = rand();

      $PM_SOAP->echoDomain();
      echo "<script type='text/javascript' src='".$CFG_GLPI["root_doc"]."/plugins/processmaker/js/cases.helpdesk.js?rand=$rand'></script>";

      $tkt = new Ticket;

      // as showFormHelpdesk uses $_REQUEST, we must set it
      $_REQUEST = $_REQUEST;

      //// must be using bare text
      //$save_rich_text = $CFG_GLPI["use_rich_text"];
      //$CFG_GLPI["use_rich_text"] = false;

      // to get the HTML code for the helpdesk form
      $saved_ob_level = ob_get_level();
      ob_start();

      $tkt->showFormHelpdesk($users_id);

      $buffer = ob_get_clean();

      //$CFG_GLPI["use_rich_text"] = $save_rich_text;

      // 9.1 only: hack to fix an issue with the initEditorSystem which calls scriptStart without calling scriptEnd
      if (ob_get_level() > $saved_ob_level) {
         $buffer = ob_get_clean().$buffer;
      }

      //echo $buffer;
      //return;

      // to change this HTML code
      $dom = new DOMDocument();

      // will convert '&' to '&amp;', '<' to '&lt;' and '>' to '&gt;'
      $buffer = htmlspecialchars($buffer, ENT_NOQUOTES);
      // will restore '&lt;' to '<' and '&gt;' to '>'
      // so that only the already escaped entites will get the double encoding
      // will also change </b> end of bold into a local identifier
      $endOfBold = 'end_of_bold'.rand();
      $endOfSpan = 'end_of_span'.rand();
      $buffer = str_replace(['&lt;', '&gt;', '</b>', '</span>'], ['<', '>', $endOfBold, $endOfSpan], $buffer);

      // will convert any UTF-8 char that can't be expressed in ASCII into an HTML entity
      $buffer = mb_convert_encoding($buffer, 'HTML-ENTITIES');

      $dom->loadHTML($buffer, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
      $xpath = new DOMXPath($dom);

      // hide some fields
      $list = [ 'name', 'type', 'locations_id', 'itilcategories_id', 'items_id', 'add' ];
      $xpath_str = '//*[@name="'.implode( '"]/ancestor::tr[1] | //*[@name="', $list ).'"]/ancestor::tr[1]';
      $res = $xpath->query($xpath_str);
      foreach ($res as $elt) {
         $elt->setAttribute( 'style', 'display:none;');
      }

      // add an input for processguid in the form
      $res = $xpath->query('//form[@name="helpdeskform"]');
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_process_guid');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', $caseInfo->processId);

      // add an input for processid in the form
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_processes_id');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', $_REQUEST['processes_id']);

      // special case for content textarea which is in the same tr than the file upload
      $res = $xpath->query('//*[@name="content"]/ancestor::div[1] | //*[@name="content"]/ancestor::tr[1]/td[1]');
      foreach ($res as $elt) {
         $elt->setAttribute( 'style', 'display:none;');
      }

      $res = $xpath->query('//*[@name="content"]/ancestor::td[1]');
      foreach ($res as $elt) {
         // there should be only one td
         $elt->setAttribute( 'colspan', '2');
      }

      $res = $xpath->query('//*[@name="content"]/ancestor::tr[1]');
      //$res = $xpath->query('//*[@name="add"]/ancestor::tr[@class="tab_bg_1"]/preceding-sibling::tr[1]');
      $table = $xpath->query('//*[@name="add"]/ancestor::table[1]');

      $tr = $table->item(0)->insertBefore(new DOMElement('tr'), $res->item(0));
      //$tr = $table->item(0)->appendChild(new DOMElement('tr'));

      $td = $tr->appendChild(new DOMElement('td'));
      $td->setAttribute('colspan', '2');

      $iframe = $td->appendChild(new DOMElement('iframe'));

      $pmCaseUser = $caseInfo->currentUsers[0]; // by default
      $paramsURL = "DEL_INDEX={$pmCaseUser->delIndex}&action={$caseInfo->caseStatus}";

      $iframe->setAttribute('id', 'caseiframe' );
      $iframe->setAttribute('onload', "onLoadFrame( event, '{$caseInfo->caseId}', {$pmCaseUser->delIndex}, {$caseInfo->caseNumber}, '{$caseInfo->processName}') ;" );
      $iframe->setAttribute('width', '100%' );
      $iframe->setAttribute('style', 'border:none;' );
      $iframe->setAttribute('src', "{$PM_SOAP->serverURL}/cases/cases_Open?sid={$PM_SOAP->getPMSessionID()}&APP_UID={$caseInfo->caseId}&{$paramsURL}&rand=$rand&glpi_domain={$PM_SOAP->config->fields['domain']}" );

      // set the width and the title of the first table th
      $th = $xpath->query('//*[@name="add"]/ancestor::table[1]/*/th[1]');
      $th->item(0)->setAttribute('width', '30%');
      $th->item(0)->nodeValue = $caseInfo->processName;

      $buffer = $dom->saveHTML();

      // revert back </b> and </span>
      $buffer = str_replace([$endOfSpan, $endOfBold], ['</span>', '</b>'], $buffer);

      // will revert back any char converted above
      $buffer = mb_convert_encoding($buffer, 'UTF-8', 'HTML-ENTITIES');
      echo $buffer;
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


// Change profile system
if (isset($_REQUEST['newprofile'])) {
   if (isset($_SESSION["glpiprofiles"][$_REQUEST['newprofile']])) {
      Session::changeProfile($_REQUEST['newprofile']);

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
if (isset($_REQUEST["active_entity"])) {
   if (!isset($_REQUEST["is_recursive"])) {
      $_REQUEST["is_recursive"] = 0;
   }
   if (Session::changeActiveEntities($_REQUEST["active_entity"], $_REQUEST["is_recursive"])) {
      if ($_REQUEST["active_entity"] == $_SESSION["glpiactive_entity"]) {
         Html::redirect(preg_replace("/entities_id.*/", "", $_SERVER['HTTP_REFERER']));
      }
   }
}

// Redirect management
if (isset($_REQUEST["redirect"])) {
   Toolbox::manageRedirect($_REQUEST["redirect"]);
}

// redirect if no create ticket right
if (!Session::haveRight('ticket', CREATE)
    && !Session::haveRight('reminder_public', READ)
    && !Session::haveRight("rssfeed_public", READ)) {

   if (Session::haveRight('followup', ITILFollowup::SEEPUBLIC) //TicketFollowup::SEEPUBLIC
        || Session::haveRight('task', TicketTask::SEEPUBLIC)
    || Session::haveRightsOr('ticketvalidation', [TicketValidation::VALIDATEREQUEST,
                                                       TicketValidation::VALIDATEINCIDENT])) {
      Html::redirect($CFG_GLPI['root_doc']."/front/ticket.php");

   } else if (Session::haveRight('reservation', ReservationItem::RESERVEANITEM)) {
      Html::redirect($CFG_GLPI['root_doc']."/front/reservationitem.php");

   } else if (Session::haveRight('knowbase', KnowbaseItem::READFAQ)) {
      Html::redirect($CFG_GLPI['root_doc']."/front/helpdesk.faq.php");
   }
}

Session::checkHelpdeskAccess();

Html::helpHeader(__('New ticket'), $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);


if (isset($_REQUEST['case_guid'])) {
   $res = $DB->request('glpi_plugin_processmaker_cases', ['case_guid' => $_REQUEST['case_guid']]);
   //$query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE case_guid='".$_REQUEST['case_guid']."'";
   //$res = $DB->query( $query );
   //if ($DB->numrows( $res )) { // a ticket already exists for this case, then show new cases
   if ($res->numrows()) { // a ticket already exists for this case, then show new cases
      processMakerShowProcessList(Session::getLoginUserID(), 1);
   } else {
      // before showing the case, we must check the rights for this user to view it, if entity has been changed in the meanwhile
      // and must check if entity of the ticket is in the tree of authorized entities for current profile
      $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_REQUEST["itilcategories_id"], $_REQUEST["type"], $_SESSION['glpiactiveprofile']['id'], $_REQUEST['entities_id'] );
      if (in_array( $_REQUEST['entities_id'], $_SESSION['glpiactiveentities']) && in_array_recursive( $_REQUEST['processes_id'], $processList )) {
         processMakerShowCase(Session::getLoginUserID(), 1);
      } else {
         Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php?create_ticket=1");
      }

   }
} else {
   processMakerShowProcessList(Session::getLoginUserID(), 1);
}

Html::helpFooter();

