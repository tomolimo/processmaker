<?php

class PluginProcessmakerDB extends DBmysql {

   var $dbhost;

   var $dbuser;

   var $dbpassword;

   var $dbdefault;

   function __construct() {
      $config = PluginProcessmakerConfig::getInstance();
      if ($config->fields['pm_dbserver_name'] != ''
         && $config->fields['pm_dbserver_user'] != ''
         && $config->fields['pm_workspace'] != '' ) {
         $this->dbhost = $config->fields['pm_dbserver_name'];
         $this->dbuser = $config->fields['pm_dbserver_user'];
         $this->dbpassword = Toolbox::decrypt($config->fields['pm_dbserver_passwd'], GLPIKEY);
         $this->dbdefault = "wf_".$config->fields['pm_workspace'];
         parent::__construct();
      }
   }

}
