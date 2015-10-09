<?php
/**
 */

class PluginProcessmakerConfig extends CommonDBTM {

    static private $_instance = NULL;

    function canCreate() {
        return Session::haveRight('config', 'w');
    }

    function canView() {
        return Session::haveRight('config', 'r');
    }

    static function getTypeName() {
        global $LANG;

        return $LANG['common'][12];
    }

    function getName($with_comment=0) {
        global $LANG;

        return $LANG['processmaker']['title'][1];
    }

    /**
     * Singleton for the unique config record
     */
    static function getInstance() {

        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    //static function install(Migration $mig) {
    //    global $DB, $LANG;

    //    $table = 'glpi_plugin_behaviors_configs';
    //    if (!TableExists($table)) { //not installed

    //        $query = "CREATE TABLE `$table` (
    //                 `id` int(11) NOT NULL,
    //                 `use_requester_item_group` tinyint(1) NOT NULL default '0',
    //                 `use_requester_user_group` tinyint(1) NOT NULL default '0',
    //                 `is_ticketsolutiontype_mandatory` tinyint(1) NOT NULL default '0',
    //                 `is_ticketrealtime_mandatory` tinyint(1) NOT NULL default '0',
    //                 `is_requester_mandatory` tinyint(1) NOT NULL default '0',
    //                 `is_ticketdate_locked` tinyint(1) NOT NULL default '0',
    //                 `use_assign_user_group` tinyint(1) NOT NULL default '0',
    //                 `tickets_id_format` VARCHAR(15) NULL,
    //                 `remove_from_ocs` tinyint(1) NOT NULL default '0',
    //                 `add_notif` tinyint(1) NOT NULL default '0',
    //                 `use_lock` tinyint(1) NOT NULL default '0',
    //                 `date_mod` datetime default NULL,
    //                 `comment` text,
    //                 PRIMARY KEY  (`id`)
    //               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    //        $DB->query($query) or die($LANG['update'][90] . "&nbsp;:<br>" . $DB->error());

    //        $query = "INSERT INTO `$table` (id, date_mod) VALUES (1, NOW())";
    //        $DB->query($query) or die($LANG['update'][90] . "&nbsp;:<br>" . $DB->error());

    //    } else {
    //        // Upgrade

    //        $mig->addField($table, 'tickets_id_format',        'string');
    //        $mig->addField($table, 'remove_from_ocs',          'bool');
    //        $mig->addField($table, 'is_requester_mandatory',   'bool');

    //        // version 0.78.0 - feature #2801 Forbid change of ticket's creation date
    //        $mig->addField($table, 'is_ticketdate_locked',     'bool');

    //        // Version 0.80.0 - set_use_date_on_state now handle in GLPI
    //        $mig->dropField($table, 'set_use_date_on_state');

    //        // Version 0.80.4 - feature #3171 additional notifications
    //        $mig->addField($table, 'add_notif',                'bool');

    //        // Version 0.83.0 - groups now have is_requester and is_assign attribute
    //        $mig->dropField($table, 'sql_user_group_filter');
    //        $mig->dropField($table, 'sql_tech_group_filter');

    //        // Version 0.83.1 - prevent update on ticket updated by another user
    //        $mig->addField($table, 'use_lock',                 'bool');

    //    }

    //    return true;
    //}

    //static function uninstall() {
    //    global $DB;

    //    if (TableExists('glpi_plugin_behaviors_configs')) { //not installed

    //        $query = "DROP TABLE `glpi_plugin_behaviors_configs`";
    //        $DB->query($query) or die($DB->error());
    //    }
    //    return true;
    //}

    static function showConfigForm($item) {
        global $LANG, $DB;

       
        $ui_theme = array(
          'classic' => 'classic',
          'neoclassic' => 'neoclassic',
          'uxmodern' => 'uxmodern' ,
          'uxs' => 'uxs'
        );        
        
        $config = self::getInstance();

        $config->showFormHeader();

        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['name']."&nbsp;:</td><td>";
        echo $config->fields['name'];
        echo "</td><td colspan='2' class='center'>".$LANG['processmaker']['config']['comments']."&nbsp;:";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['URL']."&nbsp;:</td><td>";
        echo "<input type='text' name='pm_server_URL' value='".$config->fields['pm_server_URL']."'>" ;
        echo "</td><td rowspan='5' colspan='2' class='center'>";
        echo "<textarea cols='60' rows='8' name='comment' >".$config->fields['comment']."</textarea>";
        echo "<br>".$LANG['common'][26]."&nbsp;: ";
        echo Html::convDateTime($config->fields["date_mod"]);
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['workspace']."&nbsp;:</td><td>";
        echo "<input type='text' name='pm_workspace' value='".$config->fields['pm_workspace']."'>" ;
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['theme']."&nbsp;:</td><td>";
        Dropdown::showFromArray('pm_theme', $ui_theme,
                        array('value' => $config->fields['pm_theme']));
        echo "</td></tr>";
        
        $taskCatogrie = new TaskCategory;
        $taskCatogrie->getFromDB( $config->fields['taskcategories_id'] ) ;
        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['main_task_category']."&nbsp;:</td><td>";
        echo "<a href='".Toolbox::getItemTypeFormURL( 'TaskCategory' )."?id=". $config->fields['taskcategories_id']."'>".str_replace(" ", "&nbsp;", $taskCatogrie->fields['name']);
        if ($_SESSION["glpiis_ids_visible"]) {
            echo " (".$config->fields['taskcategories_id'].")";
        }
        echo "</a>" ;
        echo "</td></tr>\n";

        $taskUser = new User;
        $taskUser->getFromDB( $config->fields['users_id'] ) ;
        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['taskwriter']."&nbsp;:</td><td>";
        echo "<a href='".Toolbox::getItemTypeFormURL( 'User' )."?id=". $config->fields['users_id']."'>".str_replace(" ", "&nbsp;", $taskUser->getName());
        if ($_SESSION["glpiis_ids_visible"]) {
            echo " (".$config->fields['users_id'].")";
        }
        echo "</a>" ;
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".$LANG['processmaker']['config']['pm_group_name']."&nbsp;:</td><td>";        
        $query = "SELECT * FROM wf_".$config->fields['pm_workspace'].".content WHERE CON_CATEGORY='GRP_TITLE' and CON_ID='".$config->fields['pm_group_guid']."' ;" ; 
        echo "<table>";
        foreach( $DB->request( $query ) as $row ) {
            echo "<tr><td>".$row['CON_LANG']."</td><td>".$row['CON_VALUE']."</td>";
        }
        echo "</table>" ;
        echo "</td></tr>\n";

        
        $config->showFormButtons(array('candel'=>false));

        return false;
    }


    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
        global $LANG;

        if ($item->getType()=='Config') {
            return $LANG['processmaker']['title'][1];
        }
        return '';
    }


    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

        if ($item->getType()=='Config') {
            self::showConfigForm($item);
        }
        return true;
    }

}
