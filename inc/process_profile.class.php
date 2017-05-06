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
   function can($ID, $right, array &$input = NULL) {
      return Session::haveRight('plugin_processmaker_config', $right);
   }

   function getTabNameForItem( CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      return $LANG['processmaker']['title'][4];
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      global $DB,$CFG_GLPI, $LANG;

      $ID = $item->getField('id');

      $canshowentity = Session::haveRight("entity", READ);
      $canedit = Session::haveRight('plugin_processmaker_config', UPDATE);

      $rand=mt_rand();

      if ($canedit) {
         echo "<div class='firstbloc'>";
         echo "<form name='entityprocess_form$rand' id='entityprocess_form$rand' method='post' action='";
         echo Toolbox::getItemTypeFormURL(__CLASS__)."'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_1'><th colspan='6'>".$LANG['processmaker']['title'][4]."</tr>";

         echo "<tr class='tab_bg_2'><td class='center'>";
         echo "<input type='hidden' name='processes_id' value='$ID'>";
         Entity::Dropdown( array('entity' => $_SESSION['glpiactiveentities']));
         echo "</td><td class='center'>".Profile::getTypeName(1)."</td><td>";
         Profile::dropdownUnder(array('value' => Profile::getDefault()));
         echo "</td><td class='center'>".__('Recursive')."</td><td>";
         Dropdown::showYesNo("is_recursive", 0);
         echo "</td><td class='center'>";
         echo "<input type='submit' name='add' value=\""._sx('button', 'Add')."\" class='submit'>";
         echo "</td></tr>";

         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }

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
      $num = $DB->numrows($result);

      echo "<div class='spaced'>";
      Html::openMassiveActionsForm('mass'.__CLASS__.$rand);

      if ($canedit && $num) {
         $massiveactionparams = array('num_displayed' => $num,
                           'container'     => 'mass'.__CLASS__.$rand);
         Html::showMassiveActions($massiveactionparams);
      }

      if ($num > 0) {
         echo "<table class='tab_cadre_fixehov'>";
         $header_begin  = "<tr>";
         $header_top    = '';
         $header_bottom = '';
         $header_end    = '';
         if ($canedit) {
            $header_begin  .= "<th>";
            $header_top    .= Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_bottom .= Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
            $header_end    .= "</th>";
         }
         $header_end .= "<th>"._n('Entity', 'Entities', Session::getPluralNumber())."</th>";
         $header_end .= "<th>".sprintf(__('%1$s (%2$s)'), Profile::getTypeName(Session::getPluralNumber()),
                                       __('D=Dynamic, R=Recursive'));
         $header_end .= "</th></tr>";
         echo $header_begin.$header_top.$header_end;

         while ($data = $DB->fetch_assoc($result)) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
               echo "<td width='10'>";
               if (in_array($data["entities_id"], $_SESSION['glpiactiveentities'])) {
                  Html::showMassiveActionCheckBox(__CLASS__, $data["linkID"]);
               } else {
                  echo "&nbsp;";
               }
                echo "</td>";
            }
            echo "<td>";

            $link = $data["completename"];
            if ($_SESSION["glpiis_ids_visible"]) {
                $link = sprintf(__('%1$s (%2$s)'), $link, $data["entities_id"]);
            }

            if ($canshowentity) {
                echo "<a href='".Toolbox::getItemTypeFormURL('Entity')."?id=".
                $data["entities_id"]."'>";
            }
            echo $link.($canshowentity ? "</a>" : '');
            echo "</td>";

            if (Profile::canView()) {
                $entname = "<a href='".Toolbox::getItemTypeFormURL('Profile')."?id=".$data["id"]."'>".
                             $data["name"]."</a>";
            } else {
                $entname =  $data["name"];
            }

            //                if ($data["is_dynamic"] || $data["is_recursive"]) {
            if ($data["is_recursive"]) {
                $entname = sprintf(__('%1$s %2$s'), $entname, "<span class='b'>(");
                //if ($data["is_dynamic"]) {
                //    //TRANS: letter 'D' for Dynamic
                //    $entname = sprintf(__('%1$s%2$s'), $entname, __('D'));
                //}
                //if ($data["is_dynamic"] && $data["is_recursive"]) {
                //    $entname = sprintf(__('%1$s%2$s'), $entname, ", ");
                //}
               if ($data["is_recursive"]) {
                  //TRANS: letter 'R' for Recursive
                  $entname = sprintf(__('%1$s%2$s'), $entname, __('R'));
               }
                $entname = sprintf(__('%1$s%2$s'), $entname, ")</span>");
            }
            echo "<td>".$entname."</td>";
            echo "</tr>";
         }
         echo $header_begin.$header_bottom.$header_end;
         echo "</table>";
      } else {
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".__('No item found')."</th></tr>";
         echo "</table>\n";
      }

      if ($canedit && $num) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
      }
      Html::closeForm();
      echo "</div>";
   }

    //static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
    //    global $CFG_GLPI;

    //    $action = $ma->getAction();

    //    switch ($action) {
    //        case 'profile_delete' :
    //            foreach ($ids as $id) {
    //                if ($item->can($id, DELETE)) {
    //                    if ($item->delete(array("id" => $id))) {
    //                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
    //                    } else {
    //                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
    //                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
    //                    }
    //                } else {
    //                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
    //                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
    //                }
    //            }
    //            break ;
    //    }
    //}

}
