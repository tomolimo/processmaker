<?php

function update_3_4_10_to_4_0_0() {
   global $DB;

   // needs to change password encryption
   $pmglpikey = new PluginProcessmakerGlpikey;

   if ($pmglpikey->migratePasswords()) {
      return '4.0.0';
   } 
   
   return false;
}