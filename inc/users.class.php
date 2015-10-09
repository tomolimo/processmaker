<?php

/**
 * user short summary.
 *
 * user description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerUsers extends CommonDBTM {

    
    ///**
    // * Execute the query to select box with all PM users where select key = name
    // *
    // * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
    // *
    // * @param $taskId id of the PM task
    // * @param $count true if execute an count(*),
    // * @param $value default value
    // * @param $used Already used items ID: not to display in dropdown
    // * @param $search pattern
    // *
    // * @return mysql result set.
    // **/
    //static function getSqlSearchResult ($taskId, $count=true, $value=0,
    //                                  $used=array(), $search='') {
    //    global $DB, $CFG_GLPI;

    //    $where = " TAS_UID = '$taskId' " ;

    //    $where .= " AND `glpi_users`.`is_deleted` = '0'
    //              AND `glpi_users`.`is_active` = '1' ";

    //    if ((is_numeric($value) && $value)
    //        || count($used)) {

    //        $where .= " AND `glpi_users`.`id` NOT IN (";
    //        if (is_numeric($value)) {
    //            $first = false;
    //            $where .= $value;
    //        } else {
    //            $first = true;
    //        }
    //        foreach ($used as $val) {
    //            if ($first) {
    //                $first = false;
    //            } else {
    //                $where .= ",";
    //            }
    //            $where .= $val;
    //        }
    //        $where .= ")";
    //    }

    //    if ($count) {
    //        $query = "SELECT COUNT(DISTINCT glpi_users.id ) AS cpt ";
    //    } else {
    //        $query = "SELECT DISTINCT glpi_users.* ";
    //    }
        
    //    $query .= "from wf_workflow.task_user
    //                join wf_workflow.group_user on wf_workflow.group_user.GRP_UID=wf_workflow.task_user.USR_UID and wf_workflow.task_user.TU_RELATION = 2 and wf_workflow.task_user.TU_TYPE=1
    //                join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.pm_users_id=wf_workflow.group_user.USR_UID
    //                                    join glpi_users on glpi_users.id=glpi_plugin_processmaker_users.glpi_users_id " ;

    //    $query .= " LEFT JOIN `glpi_useremails`
    //                 ON (`glpi_users`.`id` = `glpi_useremails`.`users_id`)";

    //    if ($count) {
    //        $query .= " WHERE $where ";
    //    } else {
    //        if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
    //            $where .= " AND (`glpi_users`.`name` ".Search::makeTextSearch($search)."
    //                         OR `glpi_users`.`realname` ".Search::makeTextSearch($search)."
    //                         OR `glpi_users`.`firstname` ".Search::makeTextSearch($search)."
    //                         OR `glpi_users`.`phone` ".Search::makeTextSearch($search)."
    //                         OR `glpi_useremails`.`email` ".Search::makeTextSearch($search)."
    //                         OR CONCAT(`glpi_users`.`realname`,' ',`glpi_users`.`firstname`) ".
    //                                       Search::makeTextSearch($search).")";
    //        }
    //        $query .= " WHERE $where ";

    //        if ($_SESSION["glpinames_format"] == FIRSTNAME_BEFORE) {
    //            $query.=" ORDER BY `glpi_users`.`firstname`,
    //                           `glpi_users`.`realname`,
    //                           `glpi_users`.`name` ";
    //        } else {
    //            $query.=" ORDER BY `glpi_users`.`realname`,
    //                           `glpi_users`.`firstname`,
    //                           `glpi_users`.`name` ";
    //        }

    //        if ($search != $CFG_GLPI["ajax_wildcard"]) {
    //            $query .= " LIMIT 0,".$CFG_GLPI["dropdown_max"];
    //        }
    //    }

    //    return $DB->query($query);
    //}
    
    
    
    /**
     * Execute the query to select box with all glpi users where select key = name
     *
     * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
     *
     * @param $count true if execute an count(*),
     * @param $right limit user who have specific right
     * @param $entity_restrict Restrict to a defined entity
     * @param $value default value
     * @param $used Already used items ID: not to display in dropdown
     * @param $search pattern
     *
     * @return mysql result set.
     **/
    static function getSqlSearchResult ($taskId, $count=true, $right="all", $entity_restrict=-1, $value=0,
                                        $used=array(), $search='') {
        global $DB, $CFG_GLPI;

        // No entity define : use active ones
        if ($entity_restrict < 0) {
            $entity_restrict = $_SESSION["glpiactiveentities"];
        }

        $joinprofile = false;
        switch ($right) {
            case "interface" :
                $where = " `glpi_profiles`.`interface` = 'central' ";
                $joinprofile = true;
                $where .= getEntitiesRestrictRequest("AND","glpi_profiles_users",'',$entity_restrict,1);
                break;

            case "id" :
                $where = " `glpi_users`.`id` = '".Session::getLoginUserID()."' ";
                break;

            case "delegate" :
                $groups = self::getDelegateGroupsForUser($entity_restrict);
                $users  = array();
                if (count($groups)) {
                    $query = "SELECT `glpi_users`.`id`
                         FROM `glpi_groups_users`
                         LEFT JOIN `glpi_users`
                              ON (`glpi_users`.`id` = `glpi_groups_users`.`users_id`)
                         WHERE `glpi_groups_users`.`groups_id` IN ('".implode("','",$groups)."')
                               AND `glpi_groups_users`.`users_id` <> '".Session::getLoginUserID()."'";
                    $result = $DB->query($query);

                    if ($DB->numrows($result)) {
                        while ($data=$DB->fetch_array($result)) {
                            $users[$data["id"]] = $data["id"];
                        }
                    }
                }
                // Add me to users list for central
                if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
                    $users[Session::getLoginUserID()] = Session::getLoginUserID();
                }

                if (count($users)) {
                    $where = " `glpi_users`.`id` IN ('".implode("','",$users)."')";
                } else {
                    $where = '0';
                }
                break;

            case "all" :
                $where = " `glpi_users`.`id` > '1' ".
                         getEntitiesRestrictRequest("AND","glpi_profiles_users",'',$entity_restrict,1);
                break;

            default :
                $joinprofile = true;
                // Check read or active for rights
                $where = " (`glpi_profiles`.`".$right."` IN ('1', 'r', 'w') ".
                            getEntitiesRestrictRequest("AND", "glpi_profiles_users", '',
                                                       $entity_restrict, 1)." ";

                if (!in_array($right,Profile::$helpdesk_rights)) {
                    $where .= " AND `glpi_profiles`.`interface` = 'central' ";
                }
                $where .= ')';
        }

        $where .= " AND TAS_UID = '$taskId' " ;
        
        $where .= " AND `glpi_users`.`is_deleted` = '0'
                  AND `glpi_users`.`is_active` = '1' ";

        if ((is_numeric($value) && $value)
            || count($used)) {

            $where .= " AND `glpi_users`.`id` NOT IN (";
            if (is_numeric($value)) {
                $first = false;
                $where .= $value;
            } else {
                $first = true;
            }
            foreach ($used as $val) {
                if ($first) {
                    $first = false;
                } else {
                    $where .= ",";
                }
                $where .= $val;
            }
            $where .= ")";
        }

        if ($count) {
            $query = "SELECT COUNT(DISTINCT glpi_users.id ) AS cpt ";
        } else {
            $query = "SELECT DISTINCT glpi_users.* ";
        }
        
        $query .= "from wf_workflow.task_user
                    join wf_workflow.group_user on wf_workflow.group_user.GRP_UID=wf_workflow.task_user.USR_UID and wf_workflow.task_user.TU_RELATION = 2 and wf_workflow.task_user.TU_TYPE=1
                    join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.pm_users_id=wf_workflow.group_user.USR_UID
                                        join glpi_users on glpi_users.id=glpi_plugin_processmaker_users.glpi_users_id " ;
        
        $query .= " LEFT JOIN `glpi_useremails`
                     ON (`glpi_users`.`id` = `glpi_useremails`.`users_id`)";
        $query .= " LEFT JOIN `glpi_profiles_users`
                     ON (`glpi_users`.`id` = `glpi_profiles_users`.`users_id`)";

        if ($joinprofile) {
            $query .= " LEFT JOIN `glpi_profiles`
                        ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`) ";
        }

        if ($count) {
            $query .= " WHERE $where ";
        } else {
            if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
                $where .= " AND (`glpi_users`.`name` ".Search::makeTextSearch($search)."
                             OR `glpi_users`.`realname` ".Search::makeTextSearch($search)."
                             OR `glpi_users`.`firstname` ".Search::makeTextSearch($search)."
                             OR `glpi_users`.`phone` ".Search::makeTextSearch($search)."
                             OR `glpi_useremails`.`email` ".Search::makeTextSearch($search)."
                             OR CONCAT(`glpi_users`.`realname`,' ',`glpi_users`.`firstname`) ".
                                           Search::makeTextSearch($search).")";
            }
            $query .= " WHERE $where ";

            if ($_SESSION["glpinames_format"] == FIRSTNAME_BEFORE) {
                $query.=" ORDER BY `glpi_users`.`firstname`,
                               `glpi_users`.`realname`,
                               `glpi_users`.`name` ";
            } else {
                $query.=" ORDER BY `glpi_users`.`realname`,
                               `glpi_users`.`firstname`,
                               `glpi_users`.`name` ";
            }

            if ($search != $CFG_GLPI["ajax_wildcard"]) {
                $query .= " LIMIT 0,".$CFG_GLPI["dropdown_max"];
            }
        }

        return $DB->query($query);
    }

    
    /**
     * Make a select box with all glpi users where select key = name
     *
     * Parameters which could be used in options array :
     *    - name : string / name of the select (default is users_id)
     *    - right : string / limit user who have specific right :
     *        id -> only current user (default case);
     *        interface -> central ;
     *        all -> all users ;
     *        specific right like show_all_ticket, create_ticket....
     *    - comments : boolean / is the comments displayed near the dropdown (default true)
     *    - entity : integer or array / restrict to a defined entity or array of entities
     *                   (default -1 : no restriction)
     *    - entity_sons : boolean / if entity restrict specified auto select its sons
     *                   only available if entity is a single value not an array(default false)
     *    - all : Nobody or All display for none selected
     *          all=0 (default) -> Nobody
     *          all=1 -> All
     *          all=-1-> nothing
     *    - rand : integer / already computed rand value
     *    - toupdate : array / Update a specific item on select change on dropdown
     *                   (need value_fieldname, to_update, url (see Ajax::updateItemOnSelectEvent for informations)
     *                   and may have moreparams)
     *    - used : array / Already used items ID: not to display in dropdown (default empty)
     *    - on_change : string / value to transmit to "onChange"
     *
     * @param $options possible options
     *
     * @return nothing (print out an HTML select box)
     **/
    static function dropdown($options=array()) {
        global $DB, $CFG_GLPI, $LANG;

        // Default values
        $p['name']           = 'users_id';
        $p['value']          = '';
        $p['right']          = 'id';
        $p['all']            = 0;
        $p['on_change']      = '';
        $p['comments']       = 1;
        $p['entity']         = -1;
        $p['entity_sons']    = false;
        $p['used']           = array();
        $p['ldap_import']    = false;
        $p['toupdate']       = '';
        $p['rand']           = mt_rand();
        $p['pmTaskId']       = 0 ;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        if (!($p['entity']<0) && $p['entity_sons']) {
            if (is_array($p['entity'])) {
                echo "entity_sons options is not available with array of entity";
            } else {
                $p['entity'] = getSonsOf('glpi_entities',$p['entity']);
            }
        }

        // Make a select box with all glpi users
        $use_ajax = false;

        if ($CFG_GLPI["use_ajax"]) {
            $res = self::getSqlSearchResult ($p['pmTaskId'], true, $p['right'], $p['entity'], $p['value'], $p['used']);
            //$res = self::getSqlSearchResult ($taskId, true, $p['value'], $p['used']);
            $nb = ($res ? $DB->result($res,0,"cpt") : 0);
            if ($nb > $CFG_GLPI["ajax_limit_count"]) {
                $use_ajax = true;
            }
        }
        $user = getUserName($p['value'],2);

        $default_display  = "<select id='dropdown_".$p['name'].$p['rand']."' name='".$p['name']."'>";
        $default_display .= "<option value='".$p['value']."'>";
        $default_display .= Toolbox::substr($user["name"], 0, $_SESSION["glpidropdown_chars_limit"]);
        $default_display .= "</option></select>";

        $view_users = (Session::haveRight("user", "r"));

        $params = array('searchText'       => '__VALUE__',
                        'value'            => $p['value'],
                        'myname'           => $p['name'],
                        'all'              => $p['all'],
                        'right'            => $p['right'],
                        'comment'          => $p['comments'],
                        'rand'             => $p['rand'],
                        'on_change'        => $p['on_change'],
                        'entity_restrict'  => $p['entity'],
                        'used'             => $p['used'],
                        'update_item'      => $p['toupdate'],
                        'pmTaskId'         => $p['pmTaskId']);
        if ($view_users) {
            $params['update_link'] = $view_users;
        }

        $default = "";
        if (!empty($p['value']) && $p['value']>0) {
            $default = $default_display;

        } else {
            $default = "<select name='".$p['name']."' id='dropdown_".$p['name'].$p['rand']."'>";
            if ($p['all']) {
                $default.= "<option value='0'>[ ".$LANG['common'][66]." ]</option></select>";
            } else {
                $default.= "<option value='0'>".Dropdown::EMPTY_VALUE."</option></select>\n";
            }
        }

        Ajax::dropdown($use_ajax, "/plugins/processmaker/ajax/dropdownUsers.php", $params, $default, $p['rand']);

        // Display comment
        if ($p['comments']) {
            if (!$view_users) {
                $user["link"] = '';
            } else if (empty($user["link"])) {
                $user["link"] = $CFG_GLPI['root_doc']."/front/user.php";
            }
            Html::showToolTip($user["comment"],
                              array('contentid' => "comment_".$p['name'].$p['rand'],
                                    'link'      => $user["link"],
                                    'linkid'    => "comment_link_".$p["name"].$p['rand']));
        }

        if (Session::haveRight('import_externalauth_users','w')
            && $p['ldap_import']
            && EntityData::isEntityDirectoryConfigured($_SESSION['glpiactive_entity'])) {

            echo "<img alt='' title=\"".$LANG['ldap'][35]."\" src='".$CFG_GLPI["root_doc"].
            "/pics/add_dropdown.png' style='cursor:pointer; margin-left:2px;'
                onClick=\"var w = window.open('".$CFG_GLPI['root_doc'].
            "/front/popup.php?popup=add_ldapuser&amp;rand=".$p['rand']."&amp;entity=".
            $_SESSION['glpiactive_entity']."' ,'glpipopup', 'height=400, ".
            "width=1000, top=100, left=100, scrollbars=yes' );w.focus();\">";
        }
        return $p['rand'];
    }
    
    
}
