<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */
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
    * @param array           $tags contains taskGuid and grpGuid (task UID and Group UID)
    * @param boolean         $count true if execute an count(*),
    * @param string|string[] $right limit user who have specific right
    * @param integer         $entity_restrict Restrict to a defined entity
    * @param integer         $value default value
    * @param integer[]       $used array: Already used items ID: not to display in dropdown
    * @param string          $search pattern
    * @param integer         $start            start LIMIT value (default 0)
    * @param integer         $limit            limit LIMIT value (default -1 no limit)
    * @param boolean         $inactive_deleted true to retreive also inactive or deleted users
    * @return DBmysqlIterator
   **/
   static function getSqlSearchResult (array $tags, $count = true, $right = "all", $entity_restrict = -1, $value = 0,
                                       array $used = [], $search = '',$start = 0, $limit = -1,
                                       $inactive_deleted = 0) {
      global $DB, $PM_DB, $CFG_GLPI;

      // first need to get all users from $taskId

      // TU_TYPE in (1, 2) means 1 is normal user, 2 is for adhoc users
      $tu_type = [];
      $tu_type[] = Session::haveRight('plugin_processmaker_case', ADHOC_REASSIGN) ? 2 : -1; // by default get (or not if no rights) the ad-hoc users
      if ($tags['grpGuid'] == 0) {
         $tu_type[] = 1; // if no group for selfservice value based assignment then get also the users
      }
      $res1 = new QuerySubQuery([
                        'SELECT'       => 'GROUP_USER.USR_UID AS pm_user_id',
                        'FROM'         => 'TASK_USER',
                        'INNER JOIN'   => [
                           'GROUP_USER' => [
                              'FKEY' => [
                                 'GROUP_USER' => 'GRP_UID',
                                 'TASK_USER' => 'USR_UID',
                                 ['AND' => [
                                    'TASK_USER.TU_RELATION' => 2,
                                    'TASK_USER.TU_TYPE' => $tu_type
                                    ]
                                 ]
                              ]
                           ]
                        ],
                        'WHERE' => [
                              'TAS_UID'               => $tags['taskGuid'],
                        ]
         ]);
      $res2 = new QuerySubQuery([
                        'SELECT' => 'TASK_USER.USR_UID AS pm_user_id',
                        'FROM'   => 'TASK_USER',
                        'WHERE'  => [
                           'AND' => [
                              'TAS_UID'               => $tags['taskGuid'],
                              'TASK_USER.TU_RELATION' => 1,
                              'TASK_USER.TU_TYPE' => $tu_type
                           ]
                        ]
         ]);

      $subqueries = [$res1, $res2];

      if ($tags['grpGuid'] != 0) {
         // then add the user for the selfservice value based assignement
         $res3 = new QuerySubQuery([
                       'SELECT' => 'GROUP_USER.USR_UID AS pm_user_id',
                       'FROM'   => 'GROUP_USER',
                       'WHERE'  =>  ['GROUP_USER.GRP_UID' => $tags['grpGuid']]

            ]);

         $subqueries[] = $res3;
      }

      $union = new QueryUnion($subqueries);

      $res = $PM_DB->request([
                        'FROM' => $union
                     ]);
      $pmUsers = [ ];
      foreach ($res as $pmUser) {
         $pmUsers[ ] = $pmUser[ 'pm_user_id' ];
      }

      $joinprofile = false;

      switch ($right) {
         case "id" :
            $used[] = Session::getLoginUserID();
            $query['WHERE']['AND']['glpi_users.id'] = Session::getLoginUserID();
             break;

         case "all" :
            $query['WHERE']['AND']['glpi_users.id'] = ['>', 0];
             break;
      }

      if (count($pmUsers) == 0) { // to prevent add of empty array in where clause
         $pmUsers = 0;
      }
      $query['WHERE']['AND']['glpi_plugin_processmaker_users.pm_users_id'] = $pmUsers;
      $query['WHERE']['AND']['glpi_users.is_deleted'] = 0;
      $query['WHERE']['AND']['glpi_users.is_active']  = 1;

      if ((is_numeric($value) && $value)
          || count($used)) {

         if (is_numeric($value)) {
            $used[] = $value;
         }
      }

      if ($count) {
         $query['FIELDS'] = 'glpi_users.id';
         $query['COUNT'] = 'cpt';
         $query['DISTINCT'] = true;

      } else {
         $query['FIELDS']   = ['glpi_users.id', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name', 'glpi_useremails.email'];
         $query['DISTINCT'] = true;

      }

      $query['FROM'] = 'glpi_plugin_processmaker_users';
      $query['INNER JOIN'] = [
                                 'glpi_users' => [
                                 'FKEY' => [
                                    'glpi_users' => 'id',
                                    'glpi_plugin_processmaker_users' => 'id'
                                    ]
                                 ]
                              ];

      $query['LEFT JOIN'] = [
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

      if ($joinprofile) {
         $query['LEFT JOIN'] = [
                                'glpi_profiles' => [
                                    'FKEY' => [
                                       'glpi_profiles'      => 'id',
                                       'glpi_profiles_user' => 'profiles_id'
                                    ]
                                 ]
                              ];
      }

      if ($count && count($used)) {
         $query['WHERE']['AND']['NOT']['glpi_users.id'] = $used;
      } else {
         if (strlen($search)>0 && $search!=$CFG_GLPI["ajax_wildcard"]) {
            $txt_search = Search::makeTextSearchValue($search);

            $firstname_field = $DB->quoteName(User::getTableField('firstname'));
            $realname_field = $DB->quoteName(User::getTableField('realname'));
            $fields = $_SESSION["glpinames_format"] == User::FIRSTNAME_BEFORE
               ? [$firstname_field, $realname_field]
               : [$realname_field, $firstname_field];
            $concat = new \QueryExpression(
               'CONCAT(' . implode(',' . $DB->quoteValue(' ') . ',', $fields) . ')'
               . ' LIKE ' . $DB->quoteValue($txt_search)
            );
            $query['WHERE']['AND'] = [
                                          'glpi_users.name' => ['LIKE', $txt_search],
                                          'OR' => [
                                             'glpi_users.realname'   => ['LIKE', $txt_search],
                                             'glpi_users.firstname'  => ['LIKE', $txt_search],
                                             'glpi_users.phone'      => ['LIKE', $txt_search],
                                             'glpi_useremails.email' => ['LIKE', $txt_search],
                                             $concat
                                          ]
                                       ];
         }
         if (count($used)) {
             $query['WHERE']['AND']['NOT']['glpi_users.id'] = $used;
         }

         if ($_SESSION["glpinames_format"] == User::FIRSTNAME_BEFORE) {
            $query['ORDER'] = ['glpi_users.firstname', 'glpi_users.realname', 'glpi_users.name'];
         } else {
            $query['ORDER'] = ['glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name'];
         }

         if ($search != $CFG_GLPI["ajax_wildcard"]) {
            $query['LIMIT'] = $limit;
            $query['START'] = $start;
         }
      }

      return $DB->request($query);
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

      $options['url'] = Plugin::getWebDir('processmaker') .'/ajax/dropdownUsers.php';
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
      if (is_numeric($glpiUserId)) {
         $obj = new self;
         if ($obj->getFromDB($glpiUserId)) {
            return $obj->fields['pm_users_id'];
         }
      }
      return false;
   }


   /**
    * Summary of getGlpiIdFromAny
    * Returns the GLPI id of the user or false if not found
    * Accept either PM GUID, GLPI logon, or GLPI ID
    * @param  $any
    * @return mixed GLPI ID of the user or false if not found
    */
   public static function getGlpiIdFromAny($any) {
      $ret = self::getGLPIUserId($any);
      if ($ret) {
         return $ret;
      }
      $ret = self::getPMUserId($any);
      if ($ret) {
         return $any;
      }
      $usr = new User;
      if ($usr->getFromDBbyName($any)) {
         return $usr->getId();
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
