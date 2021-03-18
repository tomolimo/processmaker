<?php

/**
 * PluginProcessmakerGlpikey short summary.
 *
 * PluginProcessmakerGlpikey description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerGlpikey extends GLPIKey {

   protected $fields = [
      'glpi_plugin_processmaker_configs.pm_admin_passwd',
      'glpi_plugin_processmaker_configs.pm_dbserver_passwd',
   ];

   /**
    * Get fields
    *
    * @return array
    */
   public function getFields() :array {
      return $this->fields;
   }


   /**
    * Generate GLPI security key used for decryptable passwords
    * and update values in DB if necessary.
    * @return boolean
    */
   public function migratePasswords() {
      global $DB;

      // Fetch old key and migrate 
      $sodium_key = null;
      $old_key = $this->getLegacyKey();

      if ($DB instanceof DBmysql) {
         return $this->migrateFieldsInDb($sodium_key, $old_key);
      }

      return false;
   }


}