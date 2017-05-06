<?php
// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "processmaker/js/helpdesk.public.js.php")) {
   $AJAX_INCLUDE = 1;
   define('GLPI_ROOT', '../../..');
   include (GLPI_ROOT."/inc/includes.php");
   header("Content-type: application/javascript");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not access directly to this file");
}

echo "$(function () {      
      // look if name='helpdeskform' is present. If yes replace the form.location
      var ahrefTI = '".$CFG_GLPI["root_doc"]."/plugins/processmaker/front/tracking.injector.php';
      var formLink = $(\"form[name='helpdeskform']\")[0];
      if (formLink != undefined) {
         formLink.action = ahrefTI;
      }
   });
"; // end of echo

