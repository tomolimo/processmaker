<?php
define('GLPI_ROOT','../../..');
include (GLPI_ROOT."/inc/includes.php");
header("Content-type: application/javascript");

$config = PluginProcessmakerConfig::getInstance() ;
if( isset($config->fields['domain']) && $config->fields['domain'] != '' ) {
   echo "
      //debugger;
      var d = document,
          g = d.createElement('script'), 
          s = d.getElementsByTagName('script')[0]; 
      g.type = 'text/javascript';
      g.text = 'document.domain = \'".$config->fields['domain']."\';'; 
      s.parentNode.insertBefore(g, s);
   " ;
}
