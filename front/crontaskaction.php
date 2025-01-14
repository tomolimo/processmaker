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
include_once ("../../../inc/includes.php");

Html::header(__('ProcessMaker', 'processmaker'), $_SERVER['PHP_SELF'], "tools", "PluginProcessmakerMenu", "crontaskaction");

if (Session::haveRightsOr("plugin_processmaker_config", [READ, UPDATE])) {

   Search::show('PluginProcessmakerCrontaskaction');

} else {
   Html::displayRightError();
}
Html::footer();

