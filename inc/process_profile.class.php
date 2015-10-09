<?php

/**
 * process_profile short summary.
 *
 * process_profile description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerProcess_Profile extends CommonDBTM
{
    function can($ID, $right, &$input = NULL) {
        return plugin_processmaker_haveRight('process_config', $right)  ;
    }
    
    function getTabNameForItem( CommonGLPI $item, $withtemplate=0) {
        global $LANG;
        return $LANG['processmaker']['title'][4];
    }
    
    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

        global $DB,$CFG_GLPI, $LANG;

        $ID = $item->getField('id');

        $canshowentity = Session::haveRight("entity","r");
        $canedit = plugin_processmaker_haveRight('process_config', 'w') ;
        
        $rand=mt_rand();
        echo "<form name='entityprocess_form$rand' id='entityprocess_form$rand' method='post' action='";
        echo Toolbox::getItemTypeFormURL(__CLASS__)."'>";

        if ($canedit) {
            echo "<div class='firstbloc'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='4'>".$LANG['processmaker']['title'][4]."</tr>";

            echo "<tr class='tab_bg_2'><td class='center'>";
            echo "<input type='hidden' name='processes_id' value='$ID'>";
            Dropdown::show('Entity', array('entity' => $_SESSION['glpiactiveentities']));
            echo "</td><td class='center'>".$LANG['profiles'][22]."&nbsp;: ";
            Profile::dropdownUnder(array('value' => Profile::getDefault()));
            echo "</td><td class='center'>".$LANG['profiles'][28]."&nbsp;: ";
            Dropdown::showYesNo("is_recursive",0);
            echo "</td><td class='center'>";
            echo "<input type='submit' name='add' value=\"".$LANG['buttons'][8]."\" class='submit'>";
            echo "</td></tr>";

            echo "</table></div>";
        }

        echo "<div class='spaced'><table class='tab_cadre_fixehov'>";
        echo "<tr><th colspan='2'>".$LANG['Menu'][37]."</th>";
        echo "<th>".$LANG['profiles'][22]." (D=".$LANG['profiles'][29].", R=".$LANG['profiles'][28].")";
        echo "</th></tr>";

        $query = "SELECT DISTINCT `glpi_plugin_processmaker_processes_profiles`.`id` AS linkID,
                       `glpi_profiles`.`id`,
                       `glpi_profiles`.`name`,
                       `glpi_plugin_processmaker_processes_profiles`.`is_recursive`,
                       `glpi_entities`.`completename`,
                       `glpi_plugin_processmaker_processes_profiles`.`entities_id`
                FROM `glpi_plugin_processmaker_processes_profiles`
                LEFT JOIN `glpi_profiles`
                     ON (`glpi_plugin_processmaker_processes_profiles`.`profiles_id` = `glpi_profiles`.`id`)
                LEFT JOIN `glpi_entities`
                     ON (`glpi_plugin_processmaker_processes_profiles`.`entities_id` = `glpi_entities`.`id`)
                WHERE `glpi_plugin_processmaker_processes_profiles`.`processes_id` = '$ID'
                ORDER BY `glpi_profiles`.`name`, `glpi_entities`.`completename`";
        $result = $DB->query($query);

        if ($DB->numrows($result) >0) {
            while ($data = $DB->fetch_array($result)) {
                echo "<tr class='tab_bg_1'>";
                echo "<td width='10'>";

                if ($canedit && in_array($data["entities_id"], $_SESSION['glpiactiveentities'])) {
                    echo "<input type='checkbox' name='item[".$data["linkID"]."]' value='1'>";
                } else {
                    echo "&nbsp;";
                }
                echo "</td>";

                if ($data["entities_id"] == 0) {
                    $data["completename"] = $LANG['entity'][2];
                }
                echo "<td>";

                if ($canshowentity) {
                    echo "<a href='".Toolbox::getItemTypeFormURL('Entity')."?id=".$data["entities_id"]."'>";
                }
                echo $data["completename"].
                ($_SESSION["glpiis_ids_visible"]?" (".$data["entities_id"].")":"");

                if ($canshowentity) {
                    echo "</a>";
                }
                echo "</td>";
                echo "<td>".$data["name"];

                if ($data["is_recursive"]) {
                    echo "<span class='b'>&nbsp;(";                    
                    echo "R";
                    echo ")</span>";
                }
                echo "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";

        if ($canedit) {
            Html::openArrowMassives("entityprocess_form$rand",true);
            Html::closeArrowMassives(array('delete' => $LANG['buttons'][6]));
        }
        Html::closeForm();
        echo "</div>";
    }

    
    
}
