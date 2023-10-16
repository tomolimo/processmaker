<?php
/**
 */

include ( "../../../inc/includes.php");

Session::setActiveTab('Config', 'PluginProcessmakerConfig$1');
Html::redirect($CFG_GLPI["root_doc"]."/front/config.form.php");
