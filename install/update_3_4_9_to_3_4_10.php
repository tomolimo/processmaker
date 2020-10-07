<?php

function update_3_4_9_to_3_4_10() {
   global $DB;

   // needs to change _update_ into _reassign_ in the events field of the glpi_notifications table

   $query = "UPDATE `glpi_notifications` SET `event` = REPLACE( `event`, '_update_', '_reassign_') WHERE `event` LIKE '%_update_%' AND `itemtype` = 'PluginProcessmakerTask';";
   $DB->query($query) or die("error when updating event field in glpi_notifications" . $DB->error());

   return '3.4.10';
}