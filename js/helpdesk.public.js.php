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

$config = $PM_SOAP->config; //PluginProcessmakerConfig::getInstance();
if (!$config['maintenance']) {
   echo "$(function () {
         // look if name='helpdeskform' is present. If yes replace the form.location
         var ahrefTI = '".Plugin::getWebDir('processmaker')."/front/tracking.injector.php';
         var formLink = $(\"#itil-form\")[0];
         if (formLink != undefined) {
            formLink.action = ahrefTI;
         }
      });
   "; // end of echo
}
