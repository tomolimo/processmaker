<?php

/**
 * user short summary.
 *
 * user description.
 *
 * @version 2.0
 * @author MoronO
 */
class PluginProcessmakerUser extends CommonDBTM {


    /**
     * Execute the query to select box with all glpi users where select key = name
     *
     * Internaly used by showGroup_Users, dropdownUsers and ajax/dropdownUsers.php
     *
     * @param $count true if execute an count(*),
     * @param $right limit user who have specific right
     * @param $entity_restrict Restrict to a defined entity
     * @param $value default value
     * @param $used array: Already used items ID: not to display in dropdown
     * @param $search pattern
     *
    * @return DBmysqlIterator
     **/
   static function getSqlSearchResult ($taskId, $count = true, $right = "all", $entity_restrict = -1, $value = 0,
                                        $used = [], $search = '', $limit = '') {
      global $DB, $PM_DB, $CFG_GLPI;
      $res1 = new QuerySubQuery([
                        'SELECT'       => ['GROUP_USER.USR_UID AS pm_user_id'],
                        'FROM'         => 'TASK_USER',
                        'INNER JOIN'   => [
                           'GROUP_USER' => [
                              'FKEY' => [
                                 'GROUP_USER' => 'GRP_UID',
                                 'TASK_USER' => 'USR_UID',
                                 ['AND' => [
                                    'TASk_USER.TU_RELATION' => 2,
                                    'TASk_USER.TU_TYPE' => 1
                                    ]
                                 ]
                              ]
                           ]
                        ]
         ]);
      $res2 = new QuerySubQuery([
                        'SELECT' => 'TASK_USER.USR_UID AS pm_user_id',
                        'FROM'   => 'TASK_USER',
                        'WHERE'  => [
                           'AND' => [
                              'TAS_UID'               => $taskId,
                              'TASK_USER.TU_RELATION' => 1,
                              'TASk_USER.TU_TYPE'     => 1
                           ]
                        ]
         ]);
      $union = new QueryUnion([$res1, $res2]);
      $res = $PM_DB->request([
                        'FROM' => $union
                     ]);
      // first need to get all users from $taskId
      //$db_pm = PluginProcessmakerConfig::getInstance()->getProcessMakerDB();
      //$pmQuery = "SELECT GROUP_USER.USR_UID AS pm_user_id FROM TASK_USER
      //              JOIN GROUP_USER ON GROUP_USER.GRP_UID=TASK_USER.USR_UID AND TASK_USER.TU_RELATION = 2 AND TASK_USER.TU_TYPE=1
      //              WHERE TAS_UID = '$taskId'
      //            UNION
      //            SELECT TASK_USER.USR_UID AS pm_user_id FROM TASK_USER
      //               WHERE TAS_UID = '$taskId' AND TASK_USER.TU_RELATION = 1 AND TASK_USER.TU_TYPE=1 ; ";
      $pmUsers = [ ];
      //foreach ($PM_DB->request( $pmQuery ) as $pmUser) {
      foreach ($res as $pmUser) {
         $pmUsers[ ] = $pmUser[ 'pm_user_id' ];
      }

      //$where = '';
      $joinprofile = false;

      switch ($right) {
         case "id" :
            $used[] = Session::getLoginUserID();
            //$where = " `glpi_users`.`id` = '".Session::getLoginUserID()."' ";
            $query2['WHERE']['AND']['glpi_users.id'] = Session::getLoginUserID();
             break;

         case "all" :
            //$where = " `glpi_users`.`id` > '1' ";
            $query2['WHERE']['AND']['glpi_users.id'] = ['>', 1];
             break;
      }

      //$where .= " AND glpi_plugin_processmaker_users.pm_users_id IN ('".join("', '", $pmUsers)."') ";

      //$where .= " AND `glpi_users`.`is_deleted` = '0'
      //            AND `glpi_users`.`is_active` = '1' ";

      $query2['WHERE']['AND']['glpi_plugin_processmaker_users.pm_users_id'] = $pmUsers;
      $query2['WHERE']['AND']['glpi_users.is_deleted'] = 0;
      $query2['WHERE']['AND']['glpi_users.is_active']  = 1;

      if ((is_numeric($value) && $value)
          || count($used)) {

         //$where .= " AND `glpi_users`.`id` NOT IN (";
         if (is_numeric($value)) {
            //$first = false;
            //$where .= $value;
            $used[] = $value;
            //$query2['WHERE']['AND']['NOT']['glpi_users.id'] = $value;
         } else {
            //$first = true;
         }
         //$query2['WHERE']['AND']['NOT']['glpi_users.id'] = $used;
         //foreach ($used as $val) {
         //   if ($first) {
         //      $first = false;
         //  } else {
         //      $where .= ",";
         //   }
         //   $where .= $val;
         //}
         //$where .= ")";
      }

      if ($count) {
         //$query = "SELECT COUNT(DISTINCT glpi_users.id ) AS cpt ";
         $query2['SELECT'] = ['COUNT DISTINCT' => 'glpi_users.id AS cpt'];

      } else {
         //$query = "SELECT DISTINCT glpi_users.id , `glpi_users`.`realname`, `glpi_users`.`firstname`, `glpi_users`.`name`, `glpi_useremails`.`email` ";
         $query2['SELECT DISTINCT'] = 'glpi_users.id';
         $query2['FIELDS']          = ['glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name', 'glpi_useremails.email'];
      }
      $query2['FROM'] = 'glpi_plugin_processmaker_users';
      //$query .= "FROM glpi_plugin_processmaker_users
      //              JOIN glpi_users ON glpi_users.id=glpi_plugin_processmaker_users.id ";
      $query2['FROM'] = 'glpi_plugin_processmaker_users';
      $query2['INNER JOIN'] = [
                                 'glpi_users' => [
                                 'FKEY' => [
                                    'glpi_users' => 'id',
                                    'glpi_plugin_processmaker_users' => 'id'
                                    ]
                                 ]
                              ];

      //$query .= " LEFT JOIN `glpi_useremails`
      //               ON (`glpi_users`.`id` = `glpi_useremails`.`users_id` AND `glpi_useremails`.is_default = 1)";
      $query2['LEFT JOIN'] = [
                              'glpi_useremails' => [
                                 'FKEY' => [
                                    'glpi_users'      => 'id',
                                    'glpi_useremails' => 'users_id', [
                                       'AND' => [
                                          'glpi_useremails.is_default' => 1
                                       ]
                                    ]
                                 ]
                              ],
                              'glpi_profiles_users' => [
                                 'FKEY' => [
                                    'glpi_users'          => 'id',
                                    'glpi_profiles_users' => 'users_id'
                                 ]
                              ]
                              ];
      //$query .= " LEFT JOIN `glpi_profiles_users`
      //               ON (`glpi_users`.`id` = `glpi_profiles_users`.`users_id`)";

      if ($joinprofile) {
         $query2['LEFT JOIN'] = [
                                'glpi_profiles' => [
                                    'FKEY' => [
                                       'glpi_profiles'      => 'id',
                                       'glpi_profiles_user' => 'profiles_id'
                                    ]
                                 ]
                              ];
         //$query .= " LEFT JOIN `glpi_profiles`
         //               ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`) ";
      }

      if ($count) {
         $query2['WHERE']['AND']['NOT']['glpi_users.id'] = $used;
         //$query .= " WHERE $where ";
      } else {
         if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
            $query2['WHERE']['AND'] = [
                                          'glpi_users.name' => ['LIKE',Search::makeTextSearchValue($search)],
                                          'OR' => [
                                             'glpi_users.realname'   => ['LIKE',Search::makeTextSearchValue($search)],
                                             'glpi_users.firstname'  => ['LIKE',Search::makeTextSearchValue($search)],
                                             'glpi_users.phone'      => ['LIKE',Search::makeTextSearchValue($search)],
                                             'glpi_useremails.email' => ['LIKE',Search::makeTextSearchValue($search)],
                                             'RAW' => [
                                                "CONCAT(`glpi_users`.`realname`,' ',`glpi_users`.`firstname`)".Search::makeTextSearch($search)
                                             ]
                                          ]
                                       ];
            //$where .= " AND (`glpi_users`.`name` ".Search::makeTextSearch($search)."
            //                 OR `glpi_users`.`realname` ".Search::makeTextSearch($search)."
            //                 OR `glpi_users`.`firstname` ".Search::makeTextSearch($search)."
            //                 OR `glpi_users`.`phone` ".Search::makeTextSearch($search)."
            //                 OR `glpi_useremails`.`email` ".Search::makeTextSearch($search)."
            //                 OR CONCAT(`glpi_users`.`realname`,' ',`glpi_users`.`firstname`) ".
            //                          Search::makeTextSearch($search).")";
         }
         $query2['WHERE']['AND']['NOT']['glpi_users.id'] = $used;
         //$query .= " WHERE $where ";

         if ($_SESSION["glpinames_format"] == User::FIRSTNAME_BEFORE) {
            //$query.=" ORDER BY `glpi_users`.`firstname`,
            //                   `glpi_users`.`realname`,
            //                   `glpi_users`.`name` ";
            $query2['ORDER'] = ['glpi_users.firstname', 'glpi_users.realname', 'glpi_users.name'];
         } else {
            //$query.=" ORDER BY `glpi_users`.`realname`,
            //                   `glpi_users`.`firstname`,
            //                   `glpi_users`.`name` ";
            $query2['ORDER'] = ['glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name'];
         }

         if ($search != $CFG_GLPI["ajax_wildcard"]) {
            //$query .= " $limit";
            $query2['LIMIT'] = 200;
         }
      }

      return $DB->request($query2);
      //return $DB->query($query);
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
     * @param $options array of possible options
     *
     * @return int (print out an HTML select box)
     **/
   static function dropdown($options = []) {
      global $CFG_GLPI;

      $options['url'] = $CFG_GLPI["root_doc"].'/plugins/processmaker/ajax/dropdownUsers.php';
      return User::dropdown( $options );
   }


    /**
     * Summary of getGLPIUserId
     *      returns GLPI user ID from a Processmaker user ID
     * @param string $pmUserId
    * @return int GLPI user id, or 0 if not found
     */
   public static function getGLPIUserId($pmUserId) {
      $obj = new self;
      if ($obj->getFromDBByRequest([
                      'WHERE'  => [
                      'pm_users_id'  => $pmUserId
                      ],
                  ])) {
         return $obj->fields['id'];
      }

      return 0;
   }

    /**
     * Summary of getPMUserId
     *      returns processmaker user id for given GLPI user id
     * @param int $glpi_userId id of user from GLPI database
     * @return string which is the uid of user in Processmaker database, or false if not found
     */
   public static function getPMUserId($glpiUserId) {
      $obj = new self;
      if ($obj->getFromDB( Toolbox::cleanInteger($glpiUserId) )) {
         return $obj->fields['pm_users_id'];
      }
      return false;
   }

    ///**
    // * Summary of getNewPassword
    // * @param mixed $username
    // * @return string a new password computed
    // *              from uppercasing first letter of $username
    // *              and encoding
    // *              and adding a ramdon number (4 digits)
    // *              and truncating it to a length of 20 chars
    // */
    //public static function getNewPassword( $username ) {
    //    $newPass = Toolbox::encrypt( ucfirst( stripslashes( $username ) ), GLPIKEY) ;
    //    return substr( rand(1000,9999).$newPass, 0, 19) ;
    //}

}
