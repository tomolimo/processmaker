<?php
/**
 */

if (strpos($_SERVER['PHP_SELF'], "dropdownTicketCategories.php")) {
   include ("../../../inc/includes.php");
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
} else if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

$opt['entity'] = $_POST["entity_restrict"] ?? -1;

if (isset($_POST["condition"])) {
   $opt['condition'] = $_POST["condition"];
}

$currentcateg = new ITILCategory();
$currentcateg->getFromDB($_POST['value']);

if ($_POST["type"]) {
   switch ($_POST['type']) {
      case Ticket::INCIDENT_TYPE :
         $opt['condition']['is_incident'] = '1';
         if ($currentcateg->getField('is_incident') == 1) {
            $opt['value'] = $_POST['value'];
         }
         break;

      case Ticket::DEMAND_TYPE:
         $opt['condition']['is_request'] = '1';
         if ($currentcateg->getField('is_request') == 1) {
            $opt['value'] = $_POST['value'];
         }
         break;
   }
}

ITILCategory::dropdown($opt);
