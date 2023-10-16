<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

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
function update_3_4_9_to_3_4_10() {
   global $DB;

   // needs to change _update_ into _reassign_ in the events field of the glpi_notifications table

   $query = "UPDATE `glpi_notifications` SET `event` = REPLACE( `event`, '_update_', '_reassign_') WHERE `event` LIKE '%_update_%' AND `itemtype` = 'PluginProcessmakerTask';";
   $DB->query($query) or die("error when updating event field in glpi_notifications" . $DB->error());

   return '3.4.10';
}