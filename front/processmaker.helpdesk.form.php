<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2022 by Raynet SAS a company of A.Raymond Network.

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
   PluginProcessmakerProcess::dropdown([
       'value'         => 0, 
       'entity'        => $_SESSION['glpiactive_entity'], 
       'name'          => 'plugin_processmaker_processes_id', 
       'specific_tags' => [
            'process_restrict' => 1
            ]
        ]);
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

      //$rand = rand();

      //$PM_SOAP->echoDomain();
      //echo "<script type='text/javascript' src='" .
      //   Plugin::getWebDir('processmaker') .
      //   "/js/cases.helpdesk.js?rand=$rand'></script>";
      //echo Html::script(Plugin::getWebDir('processmaker') . "/js/cases.helpdesk.js", ['version' => PROCESSMAKER_VERSION]);
      //$PM_SOAP->loadJS("/js/cases.js");


      $tkt = new Ticket;

      // as showFormHelpdesk uses $_POST, we must set it
      $_POST = $_REQUEST;

      // to get the HTML code for the helpdesk form
      //$saved_ob_level = ob_get_level();
      ob_start();

      $tkt->showFormHelpdesk($users_id);

      $buffer = ob_get_clean();

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

      // let insert in form the guid of the case
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_caseguid');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', $caseInfo->caseId);

      // let insert in form the number of the case
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_casenum');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', $caseInfo->caseNumber);

      // let insert in form the delindex of the case
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_delindex');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', $caseInfo->currentUsers[0]->delIndex);

      // let insert in form the action
      $input = $res->item(0)->appendChild(new DOMElement('input'));
      $input->setAttribute('name', 'processmaker_action');
      $input->setAttribute('type', 'hidden');
      $input->setAttribute('value', 'routecase');


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
      $table = $xpath->query('//*[@name="add"]/ancestor::table[1]');

      $tr = $table->item(0)->insertBefore(new DOMElement('tr'), $res->item(0));

      $td = $tr->appendChild(new DOMElement('td'));
      $td->setAttribute('colspan', '2');

      $iframe = $td->appendChild(new DOMElement('iframe'));

      $pmCaseUser = $caseInfo->currentUsers[0]; // by default
      $paramsURL = "DEL_INDEX={$pmCaseUser->delIndex}&action={$caseInfo->caseStatus}";

      $iframeId = 'caseiframe';

      $iframe->setAttribute('id', $iframeId);
      $iframe->setAttribute('width', '100%' );
      $iframe->setAttribute('style', 'border:none;' );

      $glpi_data = urlencode(json_encode([
         'glpi_url'               => $CFG_GLPI['url_base'],
         'glpi_tabtype'           => 'task',
         'glpi_iframeid'          => $iframeId,
         'glpi_sid'               => $PM_SOAP->getPMSessionID(),
         'glpi_app_uid'           => $caseInfo->caseId,
         'glpi_pro_uid'           => $caseInfo->processId,
         'glpi_del_index'         => $pmCaseUser->delIndex,
         'glpi_hide_claim_button' => false,
         'glpi_task_guid'         => $pmCaseUser->taskId
         ]));
      $iframe->setAttribute(
          'src',
          "{$PM_SOAP->serverURL}/cases/cases_Open?sid={$PM_SOAP->getPMSessionID()}&APP_UID={$caseInfo->caseId}&{$paramsURL}&glpi_data={$glpi_data}"
          );

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

