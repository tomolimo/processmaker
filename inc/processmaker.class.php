<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

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
use Sabre\DAV\Xml\Property\Href;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Summary of variableStruct
 *      class used to define case variables passed at case start or injected during process at any time.
 */
class variableStruct {
   public $name;
   public $value;
}

/**
 * Summary of getVariableStruct
 *      class used to get case variables at  any time during process.
 */
class getVariableStruct {
   public $name;
}


//$pmHideSolution = false;


if (!function_exists('http_formdata_flat_hierarchy')) {
   /**
   * Summary of http_formdata_flat_hierarchy
   * @param mixed $data
   * @return array
   */
   function http_formdata_flat_hierarchy($data) {
      $vars=[];
      foreach ($data as $key=>$value) {
         if (is_array($value)) {
            $temp = [];
            foreach ($value as $k2 => $val2) {
               $temp[ $key.'['.$k2.']' ] = $val2;
            }
            $vars = array_merge($vars, http_formdata_flat_hierarchy($temp));
         } else {
            $vars[$key] = $value;
         }
      }
      return $vars;
   }

}

if (!function_exists('stripcslashes_deep')) {
   /**
   * Strip c slash  for variable & array
   *
   * @param $value     array or string: item to stripslashes (array or string)
   *
   * @return stripcslashes item
   **/
   function stripcslashes_deep($value) {
      if (isset($value)) {
         $value = is_array($value) ?
                   array_map('stripcslashes_deep', $value) :
                   stripcslashes($value);
      }
      return $value;
   }
}

/**
 * PluginProcessmakerProcessmaker short summary.
 *
 * PluginProcessmakerProcessmaker description.
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerProcessmaker extends CommonDBTM {

   var $serverURL;

   var $config;
   private $pmSoapClient = null;
   private $pmWorkspace = "";
   private $pmAdminSession = false;

   var $taskWriter = 0;
   private $pm_group_guid = ''; // guid for default user group in Process Maker is used for all GLPI user synchronization into ProcessMaker
   var $lasterror;
   var $lang;

   static $rightname = '';

   const ERROR_CREATING_CASE  = 11;
   const ERROR_NO_RIGHTS      = 14;
   const ERROR_CREATING_CASE2 = 100;


   ///**
   //* Return the table used to store this object
   //*
   //* @return string
   //**/
   static function getTable($classname = null) {

      return "glpi_plugin_processmaker_processes";
   }


   /**
    * Summary of getAllTypeArray
    * @return string[]
    */
   static function getAllPMErrorArray() {

      $tab = [self::ERROR_CREATING_CASE  => _x('errors', 'Error creating case!', 'processmaker'),
              self::ERROR_NO_RIGHTS      => _x('errors', 'Can\'t create case: no rights for it!', 'processmaker'),
              self::ERROR_CREATING_CASE2 => _x('errors', 'Error creating case!', 'processmaker')];

      return $tab;
   }


   /**
    * Summary of getProcessTypeName
    * @param mixed $value
    * @return mixed
    */
   static function getPMErrorMessage($value) {

      $tab  = static::getAllPMErrorArray();
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }


   /**
   * Summary of addItemFollowup
   * @param mixed   $itemId
   * @param mixed   $txtForFollowup
   * @param integer $users_id       optional, if null will uses logged-in user
   */
   public function addItemFollowup($itemtype, $itemId, $txtForFollowup, $cases_id, $tasks_id, $users_id = null) {
      global $DB;
      $fu = new ITILFollowup();
      $input = $fu->fields;

      $input['content'] = $DB->escape($txtForFollowup['GLPI_ITEM_FOLLOWUP_CONTENT'] . "<input name='caseid' type='hidden' value='$cases_id'><input name='taskid' type='hidden' value='$tasks_id'>");

      if (isset($txtForFollowup['GLPI_ITEM_FOLLOWUP_IS_PRIVATE']) && $txtForFollowup['GLPI_ITEM_FOLLOWUP_IS_PRIVATE'] != "") {
         $input['is_private'] = $txtForFollowup['GLPI_ITEM_FOLLOWUP_IS_PRIVATE'];
      }
      if (isset($txtForFollowup['GLPI_ITEM_FOLLOWUP_REQUESTTYPES_ID']) && $txtForFollowup['GLPI_ITEM_FOLLOWUP_REQUESTTYPES_ID'] != "") {
         $input['requesttypes_id'] = $txtForFollowup['GLPI_ITEM_FOLLOWUP_REQUESTTYPES_ID'];
      }
      $input['items_id'] = $itemId;
      $input['users_id'] = (isset($users_id) ? $users_id : Session::getLoginUserID(true));
      $input['itemtype'] = $itemtype;

      $fu->add($input);
   }


   /**
   * Summary of openSoap
   * @return true if open succeeded, and pmSoapClient is initialized
   *         false otherwise
   */
   private function openSoap() {

      try {
         if ($this->pmSoapClient == null) {
            $this->lang = substr($_SESSION["glpilanguage"], 0, 2);
            if (strlen($this->lang) <> 2) {
               $this->lang = "en"; // by default
            }
            $this->config = Config::getConfigurationValues('plugin:processmaker');
            $this->pmWorkspace   = $this->config['pm_workspace'];
            $this->serverURL     = trim($this->config['pm_server_URL'], '/').'/sys'.$this->config['pm_workspace'].'/'.$this->lang.'/'.$this->config['pm_theme'];
            $this->taskWriter    = $this->config['users_id'];
            $this->pm_group_guid = $this->config['pm_group_guid'];

            $wsdl = $this->serverURL."/services/wsdl2";
            $context['ssl'] = ['verify_peer_name'    => $this->config['ssl_verify'],    // Verification of peer name
                               'verify_peer'         => $this->config['ssl_verify'],    // Verification of SSL certificate used
                              ];

            $options = ['stream_context' => stream_context_create($context),
                        'soap_version'   => SOAP_1_2,
                        'compression'    => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                        'keep_alive'     => false,
                        //'trace'      => true,
                        //'exceptions' => false,
                        //'proxy_host' => 'localhost',
                        //'proxy_port' => 8889
                       ];

            $this->pmSoapClient = new SoapClient($wsdl, $options);

         }

         return true;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         $this->lasterror = $e;
         return false; //null ;
      }
   }


   /**
    * Summary of getPMSessionID
    * @param mixed $case_guid
    * @return mixed
    */
   function getPMSessionID() {
      return $_SESSION["pluginprocessmaker"]["session"]['id'];
   }

   /**
   * Summary of login
   * @param mixed $admin_or_user if true will be admin, otherwise is user name (or user id), or current user
   * @return true if login has been correctly done with current GLPI user, or if a PM session was already open
   *         false if an exception occured (like SOAP error or PM login error)
   */
   function login($admin_or_user = false, $case_guid = 'default') {
      global $DB, $PM_DB;
      try {
         $locSession = new stdClass; // by default empty object
         if ($this->openSoap()) {
            $cookie_lifetime = ini_get('session.cookie_lifetime');
            if ($cookie_lifetime == 0) {
               $cookie_lifetime = 15 * 60; //= 15 minutes
            }
            if ($admin_or_user === true) { // admin rights has been requested, then force new login
               $glpikey = new GLPIKey();
               $locSession = $this->pmSoapClient->login([ 'userid' => $this->config['pm_admin_user'], 'password' => $glpikey->decrypt($this->config['pm_admin_passwd'])]);
               if (is_object($locSession) && $locSession->status_code == 0) {
                  $_SESSION["pluginprocessmaker"]["session"]["admin"] = true;
                  $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message;
                  $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp;
                  $_SESSION["pluginprocessmaker"]["session"]["users_id"] = 'admin';
                  $this->pmAdminSession = true;
                  return true;
               }
            } else if (empty($_SESSION["pluginprocessmaker"]["session"]["date"])
                  || ($_SESSION["pluginprocessmaker"]["session"]["admin"] == true)
                  || $this->pmAdminSession == true
                  || date_add(date_create($_SESSION["pluginprocessmaker"]["session"]["date"]), new DateInterval("PT".$cookie_lifetime."S")) < date_create(date("Y-m-d H:i:s")
                  || $_SESSION["pluginprocessmaker"]["session"]["users_id"] != $admin_or_user)
               ) {
               // get user from glpi_users table
               $gusr = new User;
               $ret_user = false;
               if (is_numeric($admin_or_user)) {
                  $ret_user = $gusr->getFromDB($admin_or_user);
               } else if ($admin_or_user !== false) {
                  $ret_user = $gusr->getFromDBbyName($admin_or_user);
               } else {
                  $ret_user = $gusr->getFromDB(Session::getLoginUserID());
               }
               if ($ret_user) {
                  // get user from glpi_plugin_processmaker_users table
                  $pmusr = new PluginProcessmakerUser;
                  $pmusr->getFromDB($gusr->getID());
                  if (is_object($pmusr) && array_key_exists('pm_users_id', $pmusr->fields)) {
                     // and must be updated also in PM db
                     $glpikey = new GLPIKey();
                     $pass = md5($glpikey->encrypt($gusr->getID().$gusr->getName().time()));
                     $PM_DB->update('RBAC_USERS', ['USR_PASSWORD' => $pass], ['USR_UID' => $pmusr->fields['pm_users_id']]);
                     $PM_DB->update('USERS', ['USR_PASSWORD' => $pass], ['USR_UID' => $pmusr->fields['pm_users_id']]);
                     // and then login with this user/password
                     $locSession = $this->pmSoapClient->login(['userid' => $gusr->fields['name'], 'password' => 'md5:'.$pass]);
                     if (is_object($locSession) && $locSession->status_code == 0) {
                        $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message;
                        $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp;
                        $_SESSION["pluginprocessmaker"]["session"]["admin"] = false;
                        $_SESSION["pluginprocessmaker"]["session"]["users_id"] = $gusr->getID();
                        $this->pmAdminSession = false;
                        return true;
                     }
                  } else {
                     Toolbox::logDebug("Processmaker Plugin: $admin_or_user - User not existing in glpi_plugin_processmaker_users table.");
                     return false;
                  }
               } else {
                  Toolbox::logDebug("Processmaker Plugin: $admin_or_user - User not existing in glpi_users table.");
                  return false;
               }
            } else {
               return true; // means a session is already existing in $_SESSION["pluginprocessmaker"]["session"]
            }
         }

         $this->pmAdminSession = false;
         unset($_SESSION["pluginprocessmaker"]["session"]);
         Toolbox::logDebug("Processmaker Plugin: $admin_or_user - Soap problem: ". print_r($locSession, true));
         $this->lasterror = $locSession;
         return false;
      } catch (Exception $e) {
         $this->pmAdminSession = false;
         unset($_SESSION["pluginprocessmaker"]["session"]);
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of processList
   *      Returns list of processes
   *      Embedded processList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#processList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return an array of processListStruct objects
   */
   function processList() {
      try {
         $pmProcessList = $this->pmSoapClient->processList([ 'sessionId' => $this->getPMSessionID()]);
         if (isset($pmProcessList->processes)) {
            if (is_array($pmProcessList->processes)) {
               return $pmProcessList->processes;
            } else {
               return [ 0 => $pmProcessList->processes ];
            }
         }

         return false;

      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of getCaseInfo
   *      returns information about a given case (as long as the logged in user has privileges to access the case).
   *      Embedded getCaseInfo() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#getCaseInfo.28.29)
   *      A session must be open before with login()
   *      Normalizes output of currentUsers to an array, even when only one element is returned by PM,
   *      Note: currentUsers field doesn't exist when case is CANCELLED
   * @param $caseGuid: The case GUID, which can be obtained with the caseList() function
   * @param $delIndex: The delegation index, which is a positive integer to identify the current task of the case. If empty then use current delIndex.
   * @return a getCaseInfoResponse object, or false exception occured
   */
   function getCaseInfo($caseGuid, $delIndex = '') {
      try {
         $pmCaseInfo = $this->pmSoapClient->getCaseInfo([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $caseGuid, 'delIndex' => $delIndex]);
         if (property_exists($pmCaseInfo, 'currentUsers')) {
            switch ($pmCaseInfo->caseStatus) {
               case 'DRAFT' :
               case 'TO_DO':
                  //                case 'CANCELLED' :
                  if (is_object($pmCaseInfo->currentUsers)) {
                     $pmCaseInfo->currentUsers = [ 0 => $pmCaseInfo->currentUsers ];
                  }
                  if ($pmCaseInfo->currentUsers[0]->delThreadStatus == 'PAUSE') {
                     $pmCaseInfo->caseStatus = "PAUSED";
                  }
                  break;
            }
         }
         return $pmCaseInfo;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }

   }

   /**
   * Summary of routeCase
   *       routes a case (i.e., moves the case to the next task in the process according to its routing rules).
   *       Embedded routeCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#routeCase.28.29)
   *       A session must be open before with login()
   * @param $case_guid string The case GUID, which can be obtained with the caseList() function
   * @param $delIndex integer The delegation index, which is a positive integer to identify the current task of the case. If empty then use current delIndex.
   * @return stdClass|boolean routeCaseResponse object or false when exception occured. routing is normalized to be always an array of routeListStruct
   */
   function routeCase($case_guid, $delIndex) {
      try {
         $pmRouteCaseResponse = $this->pmSoapClient->routeCase([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $case_guid, 'delIndex' => $delIndex]);
         if ($pmRouteCaseResponse->status_code != 0) {
             Toolbox::logDebug('routeCase res:', $pmRouteCaseResponse);
         }

         if (property_exists($pmRouteCaseResponse, 'routing') && is_object($pmRouteCaseResponse->routing)) {
             $pmRouteCaseResponse->routing = [ 0 => $pmRouteCaseResponse->routing];
         }

         return $pmRouteCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of triggerList
   *      Returns list of triggers
   *      Embedded triggerList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#triggerList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return an array of triggerListStruct objects, or false when exception occured
   */
   function triggerList() {
      try {
         $pmTriggerList = $this->pmSoapClient->triggerList([ 'sessionId' => $this->getPMSessionID()]);
         if (is_array($pmTriggerList->triggers)) {
             return  $pmTriggerList->triggers;
         } else {
            return [ 0 => $pmTriggerList->triggers ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of taskList
   *      Returns list of tasks to which the logged-in user is assigned
   *      Embedded taskList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#taskList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return an array of taskListStruct objects, or false when exception occured
   */
   function taskList() {
      try {
         $pmTaskList = $this->pmSoapClient->taskList([ 'sessionId' => $this->getPMSessionID()]);

         if (is_array($pmTaskList->tasks)) {
             return  $pmTaskList->tasks;
         } else {
            return [ 0 => $pmTaskList->tasks ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of taskCase
   *      Returns list of tasks to which the logged-in user is assigned
   *      Embedded taskList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#taskList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return array of taskListStruct objects, or false when exception occured
   */
   function taskCase($case_guid) {
      try {
         $pmTaskCase = $this->pmSoapClient->taskCase([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $case_guid ]);

         if (is_array($pmTaskCase->taskCases)) {
             return  $pmTaskCase->taskCases;
         } else {
            return [ 0 => $pmTaskCase->taskCases ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of claimCase
   * @param mixed $case_guid
   * @param mixed $delIndex
   * @return mixed
   */
   function claimCase($case_guid, $delIndex) {
      try {
         $pmClaimCase = $this->pmSoapClient->claimCase([ 'sessionId' => $this->getPMSessionID(), 'guid' => $case_guid, 'delIndex' => $delIndex]);
         return $pmClaimCase;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of unpauseCase
   *      Unpauses a specified case.
   *      Embedded UnpauseCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#UnpauseCase.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @param $caseGuid The unique ID of the case.
   * @param $delIndex The delegation index of the current task in the case.
   * @param $userGuid The unique ID of the user who will unpause the case.
   * @return an array of UnpauseCaseStruct, or false when exception occured
   */
   function unpauseCase($caseGuid, $delIndex, $userGuid) {
      try {
         $pmUnpauseCase = $this->pmSoapClient->unpauseCase([ 'sessionId' => $this->getPMSessionID(), 'caseUid' => $caseGuid, 'delIndex' => $delIndex, 'userUid' => $userGuid]);

         if (is_array($pmUnpauseCase->processes)) {
             return  $pmUnpauseCase->processes;
         } else {
            return [ 0 => $pmUnpauseCase->processes ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of caseList
   *      returns a list of the cases for the logged-in user.
   *      Embedded caseList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#caseList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return an array of cases, or false when exception occured
   */
   function caseList() {
      try {
         $pmCaseList = $this->pmSoapClient->caseList([ 'sessionId' => $this->getPMSessionID()]);

         if (is_array($pmCaseList->cases)) {
             return  $pmCaseList->cases;
         } else {
            return [ 0 => $pmCaseList->cases ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
    * Summary of systemInformation
    *      returns information about the PM system
    *      Embedded systemInformation() PM web service call (definition: http://wiki.processmaker.com/index.php/ProcessMaker_WSDL_Web_Services#systemInformation.28.29)
    *      A session must be open before with login()
    * @return an object containing information, or false when exception occured
    */
   function systemInformation() {
      try {
         $pmSystemInfo = $this->pmSoapClient->systemInformation([ 'sessionId' => $this->getPMSessionID()]);
         return $pmSystemInfo;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of reassignCase
   *      reassigns a case to a different user. Note that the logged-in user needs to have the PM_REASSIGNCASE permission in his/her role in order to be able to reassign the case.
   *      Embedded caseList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#reassignCase.28.29)
   *      A session must be open before with login()
   * @param $caseGuid The case GUID, which can be obtained with the caseList() function.
   * @param $delIndex The current delegation index number of the case, which can be obtained with the caseList() function.
   * @param $userGuidSource The PM user GUID who is currently assigned the case, which can be obtained with the caseList() function.
   * @param $userGuidTarget The PM target user GUID who will be newly assigned to the case, which can be obtained with userList(). The case can only be reassigned to a user who is one of the assigned users or ad-hoc users to the current task in the case.
   * @return stdClass, a pmResponse object, or false when exception occured
   */
   function reassignCase($caseGuid, $delIndex, $userGuidSource, $userGuidTarget) {
      try {
         $pmResults = $this->pmSoapClient->reassignCase(['sessionId' => $this->getPMSessionID(),
                                                          'caseId' => $caseGuid,
                                                          'delIndex' => $delIndex,
                                                          'userIdSource' => $userGuidSource,
                                                          'userIdTarget'=> $userGuidTarget]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of soapDeleteCase
   *      Deletes a case
   *      Embedded deleteCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#deleteCase.28.29)
   *      A session must be open before with login()
   *      Beware that at any time you may delete a case!!!
   * @param $caseUid The case ID, which can be obtained with the caseList() function.
   * @return A deleteCaseResponse object, or false when exception occured
   */
   function soapDeleteCase($caseUid) {
      try {
         $deleteCaseResponse = $this->pmSoapClient->deleteCase([ 'sessionId' => $this->getPMSessionID(), 'caseUid' => $caseUid]);
         return $deleteCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of cancelTask
   *      Cancels a task
   *      Embedded cancelCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#cancelCase.28.29)
   *      A session must be open before with login()
   *      Beware that this will only cancel the task with delIndex
   *           in the case of // tasks you must call cancelTask for each running task.
   *           in the case of one and only one task running, then it will cancel the case
   * @param $caseUid The case ID, which can be obtained with the caseList() function.
   * @param $delIndex The delegation index of the current task in the case.
   * @param $userUid: The unique ID of the user who will unpause the case.
   * @return A cancelCaseResponse object, or false when exception occured
   */
   function cancelTask($caseUid, $delIndex, $userUid) {
      try {
         $cancelTaskResponse = $this->pmSoapClient->cancelCase([ 'sessionId' => $this->getPMSessionID(), 'caseUid' => $caseUid, 'delIndex' => $delIndex, 'userUid' => $userUid]);
         return $cancelTaskResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of cancelCase
   *      Cancels a case
   *      Embedded cancelCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#cancelCase.28.29)
   *      A session must be open before with login()
   *      Beware that this will cancel all running task
   *           in the case of // tasks you must call cancelCase for each running task.
   *           in the case of one and only one task is running, then it will cancel the case
   * @param $caseUid The case ID, which can be obtained with the caseList() function.
   * @param $delIndex The delegation index of the current task in the case.
   * @param $userUid: The unique ID of the user who will unpause the case.
   * @return A cancelCaseResponse object, or false when exception occured
   */
   function cancelCase($caseUid) {
      try {
          $pmCaseInfo = $this->getCaseInfo($caseUid);
          if ($pmCaseInfo->status_code == 0) {
              if (count($pmCaseInfo->currentUsers) > 1) {
                  $pmCancelTask = $this->cancelTask($caseUid, null, null);
              } else {
                  $pmCancelTask = $this->cancelTask($caseUid, $pmCaseInfo->currentUsers[0]->delIndex, PluginProcessmakerUser::getPMUserId($_SESSION['glpiID']));
                  if ($pmCancelTask->status_code != 0) {
                      return $pmCancelTask;
                  }
              }
          }
          return $pmCancelTask;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of newCaseImpersonate
   *      Starts a new case under the name of the logged-in user.
   *      The task that will be started is the default one (=must be unique in process definition)!
   *      logged-in user must be assigned to this task, otherwise use newCaseImpersonate() to start the case.
   *      New case is started with DRAFT status.
   *      Embedded newCaseImpersonate() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#newCaseImpersonate.28.29)
   *      A session must be open before with login(), in order to call this function.
   * @param $processes_id integer: The GLPI ID of the process that must be used to start a new case
   * @param $userId The ID of the user who initiates the case, which can be obtained with userList().
   * @param $vars an array of associative variables (name => value) that will be injected into the case as case variables
   * @return A newCaseResponse object, or false when exception occured
   */
   function newCaseImpersonate($processes_id, $userId, $vars = null) {
      try {
         $this->getFromDB($processes_id);

         if ($vars !== null) {
            $aVars = [];
            foreach ($vars as $key => $val) {
               $obj = new variableStruct();
               $obj->name = $key;
               $obj->value = $val;
               $aVars[] = $obj;
            }
         } else {
            $aVars = '';
         }

         $newCaseResponse = $this->pmSoapClient->newCaseImpersonate([ 'sessionId' => $this->getPMSessionID(), 'processId'=> $this->fields['process_guid'], 'userId' => $userId, 'taskId'=>'', 'variables'=> $aVars]);
         return $newCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of newCase
   *      Starts a new case under the name of the logged-in user.
   *      The task that will be started is the default one (=must be unique in process definition)!
   *      logged-in user must be assigned to this task, otherwise use newCaseImpersonate() to start the case.
   *      New case is started with DRAFT status.
   *      Embedded newCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#newCase.28.29)
   *      A session must be open before with login()
   * @param $processes_id integer: the GLPI ID of the process which will be instantied into a case
   * @param array  $vars      an array of associative variables (name => value) that will be injected into the case as case variables
   * @return boolean|newCaseResponse: false when exception occured
   */
   function newCase($processes_id, $vars = []) {
      try {
         $this->getFromDB($processes_id);

         $aVars = [];
         foreach ($vars as $key => $val) {
            $obj = new variableStruct();
            $obj->name = $key;
            $obj->value = $val;
            $aVars[] = $obj;
         }

         $newCaseResponse = $this->pmSoapClient->newCase([ 'sessionId' => $this->getPMSessionID(), 'processId'=> $this->fields['process_guid'], 'taskId'=>'', 'variables'=> $aVars]);

         return $newCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
    * Summary of outputDocumentList
    *      Embedded newCase() PM web service call (definition: https://wiki.processmaker.com/3.3/ProcessMaker_WSDL_Web_Services#outputDocumentList.28.29)
    * @param mixed $sessionId
    * @param mixed $caseId
    */
   function outputDocumentList($sessionId, $caseId) {
      try {
         $odlResponse = $this->pmSoapClient->outputDocumentList(['sessionId' => $sessionId, 'caseId'=> $caseId]);

         if (isset($odlResponse->documents) && is_array($odlResponse->documents)) {
            return $odlResponse->documents;
         } else {
            if (isset($odlResponse->documents)) {
               return [0 => $odlResponse->documents];
            } else {
               return [];
            }
         }
      }
      catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
    * Summary of inputDocumentList
    *      Embedded newCase() PM web service call (definition: https://wiki.processmaker.com/3.3/ProcessMaker_WSDL_Web_Services#inputDocumentList.28.29)
    * @param mixed $sessionId
    * @param mixed $caseId
    */
   function inputDocumentList($sessionId, $caseId) {
      try {
         $idlResponse = $this->pmSoapClient->inputDocumentList(['sessionId' => $sessionId, 'caseId'=> $caseId]);

         if (isset($idlResponse->documents) && is_array($idlResponse->documents)) {
            return $idlResponse->documents;
         } else {
            if (isset($idlResponse->documents)) {
               return [0 => $idlResponse->documents];
            } else {
               return [];
            }
         }
      }
      catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of sendVariables
   *      Sends variables to a case.
   *      Embedded sendVariables() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#sendVariables.28.29)
   *      A session must be open before with login()
   * @param string $caseGuid The GUID of the case
   * @param array  $vars   an array of associative variables (name => value) that will be injected into the case as case variables
   * @return A pmResponse object, or false when exception occured
   */
   function sendVariables($caseGuid, $vars = []) {
      if (count($vars) == 0) { // nothing to send
          return true;
      }
      try {
         $aVars = [];
         foreach ($vars as $key => $val) {
            $obj = new variableStruct();
            $obj->name = $key;
            if (is_array($val)) {
                $obj->value = join("|", $val);
            } else {
               $obj->value = $val;
            }
            $aVars[] = $obj;
         }

         $pmResponse = $this->pmSoapClient->sendVariables([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $caseGuid, 'variables'=> $aVars]);

         return $pmResponse;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }



   /**
   * Summary of getVariables
   *      Gets variables from a case.
   *      Embedded getVariables() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#getVariables.28.29)
   *      A session must be open before with login()
   * @param string $case_guid The uID of the case
   * @param array  $vars   an array of variable name that will be read from the case as case variables Normalizes output to an array, even when only one element is returned by PM Normalizes output to an array, even when only one element is returned by PM
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return array: an associative array (variable_name => value), or false when exception occured. The return array can be empty if requested variables are not found.
   */
   function getVariables($case_guid, $vars = []) {
      try {
         $aVars = [];
         foreach ($vars as $key => $name) {
            $obj = new getVariableStruct();
            $obj->name = $name;
            $aVars[] = $obj;
         }

         $pmvariableListResponse = $this->pmSoapClient->getVariables([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $case_guid, 'variables'=> $aVars]);

         $variablesArray = [];

         if ($pmvariableListResponse->status_code == 0 && isset($pmvariableListResponse->variables)) {
            if (is_array($pmvariableListResponse->variables)) {
               foreach ($pmvariableListResponse->variables as $variable) {
                   $variablesArray[$variable->name] = $variable->value;
               } } else {
               $variablesArray[$pmvariableListResponse->variables->name] = $pmvariableListResponse->variables->value;
               }
         }

         return $variablesArray;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of groupList
   *      returns a list of the groups. For privileges to see the list, the logged-in user must have the PM_USERS permission in his/her role.
   *      Embedded groupList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#groupList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return an array of groupListStruct, or false when exception occured
   */
   function groupList() {
      try {
         $pmGroupList = $this->pmSoapClient->groupList([ 'sessionId' => $this->getPMSessionID()]);

         if (is_array($pmGroupList->groups)) {
             return  $pmGroupList->groups;
         } else {
            return [ 0 => $pmGroupList->groups ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of assignUserToGroup
   *      assigns a user to a group. For privileges to assign a user, the logged-in user must have the PM_USERS permission in his/her role.
   *      Embedded assignUserToGroup() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#assignUserToGroup.28.29)
   *      A session must be open before with login()
   * @param $userId a Processmaker user id (see userList())
   * @param $groupId a Processmaker group id (see groupList())
   * @return A pmResponse object, or false when exception occured
   */
   function assignUserToGroup($userId, $groupId) {
      try {
         $pmResults = $this->pmSoapClient->assignUserToGroup([ 'sessionId' => $this->getPMSessionID(),
                                                             'userId' => $userId,
                                                             'groupId' => $groupId
                                                             ]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   *  Summary of createGroup
   *      creates a new group. For privileges to create a group, the logged-in user must have the PM_USERS permission in his/her role.
   *      group will be created as 'ACTIVE'
   *      Embedded createGroup() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#createGroup.28.29)
   *      A session must be open before with login()
   * @param $name: the name of the group to be created
   * @return A pmResponse object, or false when exception occured
   */
   function createGroup($name) {
      try {
         $pmResults = $this->pmSoapClient->createGroup([ 'sessionId' => $this->getPMSessionID(),
                                                             'name' => $name ]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of updateGroup
   *      updates group directly into Processmaker DB
   * @param $group_id: guid of the pm group
   * @param $groupStatus: new status to be set to $group_id, could be 'ACTIVE' or 'INACTIVE'
   * @return true if group status has been modified, false otherwise
   */
   function updateGroup($group_id, $groupStatus) {
      global $PM_DB;
      $PM_DB->update('GROUPWF', ['GRP_STATUS' => $groupStatus], ['GRP_UID' => $group_id]);
      //$query = "UPDATE GROUPWF SET GRP_STATUS='$groupStatus' WHERE GRP_UID='$group_id';";
      //$PM_DB->query($query);
      if ($PM_DB->affected_rows != 1) {
          return false;
      } else {
         return true;
      }
   }

   /**
   * Summary of userList
   *      returns a list of the Processmaker users. For privileges to see the list, the logged-in user must have the PM_USERS permission in his/her role.
   *      Embedded userList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#userList.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even if only one element is returned by PM
   * @return an array of userListStruct, or false when exception occured
   */
   function userList() {
      try {
         $pmUserList = $this->pmSoapClient->userList([ 'sessionId' => $this->getPMSessionID()]);

         if (is_array($pmUserList->users)) {
             return  $pmUserList->users;
         } else {
            return [ 0 => $pmUserList->users ];
         }
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of createUser
   *      creates a new user. For privileges to create a user, the logged-in user must have the PM_USERS permission in his/her role.
   *      Embedded createUser() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#createUser.28.29)
   *      A session must be open before with login()
   * @param $userId The username for the new user. The unique ID for the user will be automatically generated. Is the user Windows login!
   * @param $firstname The user's first name. If empty (== null or == "") will default to $userId.
   * @param $lastname The user's last name. If empty (== null or == "") will default to $userId.
   * @param $email The user's email address. If empty (== null or == "") will default to $userId@DoNotReply.com.
   * @param $role The user's role, such as 'PROCESSMAKER_ADMIN', 'PROCESSMAKER_MANAGER' or 'PROCESSMAKER_OPERATOR'. Possible values can be looked up with the roleList() function.
   * @param $password The user's password, such as 'Be@gle2'. (It will be automatically converted into an MD5 hash when inserted in the database.)
   * @param $status The user's status, such as "ACTIVE", "INACTIVE".
   * @return returns a createUserResponse object, or false if exception occurred
   */
   function createUser($userId, $firstname, $lastname, $email, $role, $password, $status) {
      try {
         if ($firstname == null || $firstname == "") {
            $firstname = $userId;
         }
         if ($lastname == null || $lastname == "") {
            $lastname = $userId;
         }
         if ($email == "") {
            $email = $userId."@DoNotReply.com";
         }

         $pmResults = $this->pmSoapClient->createUser([ 'sessionId' => $this->getPMSessionID(),
                                                             'userId' => $userId,
                                                             'firstname'=> $firstname,
                                                             'lastname' => $lastname,
                                                             'email' => $email,
                                                             'role' => $role,
                                                             'password' => $password,
                                                             'status' => $status ]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }


   /**
   * Summary of updateUser
   *      updates user information.
   *      Embedded updateUser() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#updateUser.28.29)
   *      A session must be open before with login()
   * @param $userUid the unique Id for the user (Processmaker user id)
   * @param $userName is the user logon. IT IS STRONGLY ADVISE NOT TO CHANGE THIS INFORMATION
   * @param $firstname The user's first name. If empty (== null or == "") will default to $userName.
   * @param $lastname The user's last name. If empty (== null or == "") will default to $userName.
   * @param $status The user's status, such as "ACTIVE", "INACTIVE".
   * @return returns a UpdateUserResponse  object, or false if exception occurred
   */
   function updateUser($userUid, $userName, $firstName, $lastName, $status) {
      try {
         if ($firstName == null || $firstName == "") {
            $firstName = $userName;
         }
         if ($lastName == null || $lastName == "") {
            $lastName = $userName;
         }

         $pmResults = $this->pmSoapClient->updateUser([ 'sessionId' => $this->getPMSessionID(),
                                                             'userUid' => $userUid,
                                                             'userName' => $userName,
                                                             'firstName'=> $firstName,
                                                             'lastName' => $lastName,
                                                             'status' => $status
                                                             ]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }

   /**
   * Summary of executeTrigger
   *      executes a ProcessMaker trigger.
   *      Note that triggers which are tied to case derivation will be executed automatically, so this function does not need to be called when deriving cases.
   *      Embedded executeTrigger() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#executeTrigger.28.29)
   *      A session must be open before with login()
   * @param $caseId The case ID, which is can be found with caseList().
   * @param $triggerIndex The ID of the trigger to execute, which can be found with triggerList().
   * @param $delIndex The delegation index number of the case, which can be found with caseList().
   * @return A pmResponse object. If successful, the message will contain "executed: <TRIGGER_CODE>". Otherwise false in case of SOAP error
   */
   function executeTrigger($caseId, $triggerIndex, $delIndex) {
      try {
         $pmResults = $this->pmSoapClient->executeTrigger([ 'sessionId' => $this->getPMSessionID(), 'caseId' => $caseId, 'triggerIndex'=> $triggerIndex, 'delIndex' => $delIndex ]);
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug($e);
         return false;
      }
   }



   /**
   * summary of cronInfo
   *      Gives localized information about 1 cron task
   * @param $name of the task
   * @return array of strings
   */
   static function cronInfo($name) {
      switch ($name) {
         case 'pmusers' :
             return ['description' => __('Syncs GLPI users and groups into ProcessMaker.', 'processmaker')];
         case 'pmorphancases' :
            return ['description' => __('Cleaning of orphan cases.', 'processmaker'), 'parameter' => __('Number of days to keep orphan cases', 'processmaker')];
         case 'pmtaskactions' :
            return ['description' => __('To apply task actions between cases.', 'processmaker')];
      }
      return [];
   }

   /**
   * summary of cronPMTaskActions
   *       Execute 1 task managed by the plugin
   * @param: $task CronTask class for log / stat
   * @return integer
   *    >0 : done
   *    <0 : to be run again (not finished)
   *     0 : nothing to do
   */
   static function cronPMTaskActions($crontask = null) {
      global $DB, $PM_DB, $PM_SOAP;
      $dbu = new DbUtils;

      // also create a GLPI session with the processmaker task writer
      $save_session    = $_SESSION;
      $save_session_id = session_id();

      // use of the PM plugin user
      if (!isset($PM_SOAP->config)) {
          $PM_SOAP->config = Config::getConfigurationValues('plugin:processmaker');
      }
      $usr = new User;
      $usr->getFromDB($PM_SOAP->config['users_id']);
      $auth = new Auth();
      $auth->user = $usr;
      $auth->auth_succeded = true;
      $auth->password_expired = false;
      Session::init($auth);

      $actionCode = 0; // by default
      $error = false;
      if ($crontask) {
         $crontask->setVolume(0); // start with zero
      }

      $existingpmsession = isset($_SESSION["pluginprocessmaker"]["session"]);
      $formerusers_id = 0;
      // get the list of taskactions to be done
      $locCase = new PluginProcessmakerCase;
      //foreach ($DB->request($dbu->getTableForItemType('PluginProcessmakerCrontaskaction'), ' `state` = '.PluginProcessmakerCrontaskaction::DATA_READY) as $taskaction) {
      foreach ($DB->request($dbu->getTableForItemType('PluginProcessmakerCrontaskaction'), ['state' => PluginProcessmakerCrontaskaction::DATA_READY]) as $taskaction) {
         if ($locCase->getFromDB($taskaction['plugin_processmaker_cases_id'])) {
            // there is an existing case for this crontaskaction.
            try {

               $users_id = $taskaction['users_id'];
               if ($formerusers_id != $users_id) {
                  unset($_SESSION["pluginprocessmaker"]["session"]); // to reset previous user login if any
               }

               $caselink = new PluginProcessmakerCaselink;
               $caselink->getFromDB($taskaction['plugin_processmaker_caselinks_id']);

               // get current task in current case
               $PM_SOAP->login(true);
               $caseinfo = $locCase->getCaseInfo();
               $currenttask = false;
               if (property_exists($caseinfo, 'currentUsers')) {
                  foreach ($caseinfo->currentUsers as $loctask) {
                     if ($loctask->taskId == $caselink->fields['targettask_guid']) {
                        $currenttask = $loctask;
                        break;
                     }
                  }
               }

               // if $currenttask is false then it means that the target task MUST not derived
               if ($currenttask) {
                  if ($caselink->fields['is_targettoimpersonate'] && !$taskaction['is_targettoclaim']) {
                     // 1) get the current assigned user for this task
                     // then must login with the user assigned to the task, and not with the user who did the current task
                     if ($currenttask->userId != '') {
                        // 3) change login: impersonate
                        $users_id = PluginProcessmakerUser::getGLPIUserId($loctask->userId);
                     }
                  }

                  $PM_SOAP->login($users_id);

                  $formdata = json_decode($taskaction['formdata'], true);

                  // must filter arrays as arrays are grids and index must start at 1 instead of 0 like in json
                  // TODO: to check if it would be possible to do this in a more generic way
                  foreach ($formdata['form'] as &$field) {
                     if (is_array($field)) {
                        if (count($field) > 0) {
                           // then must reindex the array starting to 1 instead of 0
                           array_unshift($field, '');
                           unset($field[0]);
                        }
                     }
                  }

                  if ($taskaction['is_targettoclaim'] && !$caselink->fields['is_targettoimpersonate']) {
                     // must do a claim before solving task
                     if (!$PM_SOAP->claimCase($formdata['APP_UID'], $formdata['DEL_INDEX'])) {
                        throw new Exception("Can't claim case");
                     }

                     // do not send notifications
                     $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false);

                     // now manage tasks associated with item
                     $PM_SOAP->claimTask($formdata['APP_UID'], $formdata['DEL_INDEX'], $users_id);

                     PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);

                  }

                  $tkaction = new PluginProcessmakerCrontaskaction;
                  $tkaction->update(['id' => $taskaction['id'], 'state' => PluginProcessmakerCrontaskaction::DONE]);

                  $PM_SOAP->derivateCase($locCase, $formdata, $users_id);

                  if ($crontask) {
                     $crontask->addVolume(1);
                  }
                  if ($crontask) {
                     $crontask->log("Applied task action id: '".$taskaction['id']."'");
                  }
               } else {
                  $tkaction = new PluginProcessmakerCrontaskaction;
                  $tkaction->update(['id' => $taskaction['id'], 'state' => PluginProcessmakerCrontaskaction::NOT_DONE]);
                  if ($crontask) {
                     $crontask->log("Task action id: '".$taskaction['id']."' case task not found or not open!");
                  }
               }

            } catch (Exception $e) {
               if ($crontask) {
                  $crontask->log("Can't apply task action id: '".$taskaction['id']."'");
               }
               $error = true;
            }

            $formerusers_id = $users_id;
         }
      }

      if ($existingpmsession) {
         unset($_SESSION["pluginprocessmaker"]["session"]); // reset the one created during the foreach
         if (!Session::isCron()) {
            $PM_SOAP->login(); // re-log default user
         }
      }

      // restore previous session
      session_destroy(); // do not use Session::destroy() which does not destroy current session
      session_id($save_session_id);
      Session::start();
      $_SESSION = $save_session;

      if ($error) {
         return -1;
      } else {
         return $actionCode;
      }

   }


   /**
   * summary of cronPMOrphanCases
   *       Execute 1 task managed by the plugin
   * @param: $task CronTask class for log / stat
   * @return integer
   *    >0 : done
   *    <0 : to be run again (not finished)
   *     0 : nothing to do
   */
   static function cronPMOrphanCases($task) {
      global $PM_DB, $DB, $PM_SOAP;

      //plugin_processmaker_post_init();

      // get list of case assigned to GLPI items
      $draftCases = [0];
      $query = "SELECT id FROM glpi_plugin_processmaker_cases WHERE case_status = 'DRAFT';";
      //foreach ($DB->request($query) as $row) {
      foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_plugin_processmaker_cases'], ['case_status' => 'DRAFT']) as $row) {
         $draftCases[] = $row['id'];
      }

      $actionCode = 0; // by default
      $error = false;
      $task->setVolume(0); // start with zero

      if (count($draftCases) > 0) {
         //$PM_SOAP = new self;
         $PM_SOAP->login(true);
         $query = "SELECT * FROM APPLICATION
                  WHERE APP_DATA LIKE '%s:24:\"GLPI_SELFSERVICE_CREATED\";s:1:\"1\"%'
                     AND APP_STATUS = 'DRAFT'
                     AND DATEDIFF(NOW(), APP_UPDATE_DATE) > ".$task->fields['param']."
                     AND APP_NUMBER NOT IN (".implode(',', $draftCases).");
                ";
         $res = $PM_DB->request('APPLICATION', ['AND' => [
                                             'APP_DATA' => ['LIKE', '%s:24:\"GLPI_SELFSERVICE_CREATED\";s:1:\"1\"%'],
                                             'APP_STATUS' => 'DRAFT',
                                             'RAW' => ['DATEDIFF(NOW(), APP_UPDATE_DATE)' => ['>', $task->fields['param']]],
                                             'NOT' => ['APP_NUMBER' => $draftCases]
                                             ]]);
         //foreach ($PM_DB->request($query) as $row) {
         foreach ($res as $row) {
            $ret = $PM_SOAP->soapDeleteCase($row['APP_UID']);
            $task->addVolume(1);
            if ($ret !== false) {
               $task->log("Deleted case num: '".$row['APP_NUMBER']."'");
               $actionCode = 1;
            } else {
               $task->log("Can't delete case num: '".$row['APP_NUMBER']."'");
               $error = true;
            }
         }
      }
      if ($error) {
         return -1;
      } else {
         return $actionCode;
      }

   }


   /**
   * summary of cronPMUsers
   *       Executes 1 task managed by the plugin
   * @param $task CronTask class for log / stat
   * @return integer
   *    >0 : done
   *    <0 : to be run again (not finished)
   *     0 : nothing to do
   */
   static function cronPMUsers($task) {
      global $DB, $PM_DB, $PM_SOAP;

      //plugin_processmaker_post_init();

      $actionCode = 0; // by default
      $error = false;
      $task->setVolume(0); // start with zero

      // start a processmaker session
      if (!isset($PM_SOAP)) {
         $PM_SOAP = new PluginProcessmakerProcessmaker();
      }
      if (!$PM_SOAP->login(true)) {
         $task->log("Error PM: '".print_r($PM_SOAP->lasterror, true)."'");
         return -1;
      }

      $pmGroupList = $PM_SOAP->groupList();
      $pmGroup = null;
      foreach ($pmGroupList as $pmGrp) {
         if ($pmGrp->guid == $PM_SOAP->pm_group_guid) {
            $pmGroup = $pmGrp;
            break; // to get the name :)
         }
      }

      $pmUserList = [];
      foreach ($PM_SOAP->userList() as $pmuser) {
         $pmUserList[ strtolower($pmuser->name)] = [ 'name' => $pmuser->name, 'guid' => $pmuser->guid,  'status' => $pmuser->status ];
      }

      // get the complete user list from GLPI DB
      $glpiUserList = [];
      $res = $DB->request(['SELECT'     => ['glpi_users.id', 'glpi_users.name', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_users.is_active', 'glpi_users.is_deleted', 'glpi_plugin_processmaker_users.pm_users_id as pmUserId'],
                    'FROM'       => 'glpi_users',
                    'LEFT JOIN'  => ['glpi_plugin_processmaker_users' => ['FKEY' => ['glpi_plugin_processmaker_users' => 'id', 'glpi_users' => 'id']]],
                    'WHERE'      => ['name' => ['NOT LIKE', '*%']]
                  ]);
      foreach ($res as $dbgroup) {
         $glpiUserList[ strtolower($dbgroup['name'])] = $dbgroup;
      }

      $glpikey = new GLPIKey();
      $arrayDiff = array_diff_key($glpiUserList, $pmUserList);
      foreach ($arrayDiff as $user) {
         if ($user['is_active'] != 0 && $user['is_deleted'] != 1) {
            $status = "ACTIVE";
            $task->addVolume(1);
            $pass = substr($glpikey->encrypt($user['id'].$user['name'].time()), 0, 20); // must keep only 20 chars as the web service is limited to 20 for the password
            $pmResult = $PM_SOAP->createUser($user['name'], $user['firstname'], $user['realname'], "", "PROCESSMAKER_OPERATOR", $pass, $status);
            if ($pmResult->status_code == 0) {
               $task->log("Added user: '".$user['name']."'");

               // then assign user to group
               if($pmGroup) {
                  $pmResult2 = $PM_SOAP->assignUserToGroup($pmResult->userUID, $pmGroup->guid);
                  if ($pmResult2->status_code == 0) {
                      $task->log("Added user: '".$user['name']."' to '".$pmGroup->name."' group");
                  } else {
                     $task->log("Error PM: '".$pmResult2->message."'");
                  }
               }
               // insert into DB the link between glpi users and pm user
               $pmuser = new PluginProcessmakerUser;
               if ($pmuser->getFromDB($user['id'])) {
                  $pmuser->update([ 'id' => $user['id'], 'pm_users_id' => $pmResult->userUID ]);
               } else {
                  $pmuser->add([ 'id' => $user['id'], 'pm_users_id' => $pmResult->userUID ]);
               }
               $actionCode = 1;

            } else {
               $task->log("Error adding user: '".$user['name']."'");
               $task->log("Error PM: '".$pmResult->message."'");
               $actionCode = -1;
               $error = true;
            }
         } else {
            unset($glpiUserList[$user['name']]);
         }
      }

      if (!$error) {

         // now should refresh the existing users
         $arrayIntersect = array_intersect_key($glpiUserList, $pmUserList);
         foreach ($arrayIntersect as $user) {
            $pmuserlistGuid = $pmUserList[strtolower($user['name'])]['guid'];
            if ($user['pmUserId'] == null || ($user['pmUserId'] != $pmuserlistGuid)) { //must be deleted, inserted or updated into DB
               $existingID = PluginProcessmakerUser::getGLPIUserId($pmuserlistGuid);
               $pmuser = new PluginProcessmakerUser;
               if ($existingID && $existingID != $user['id']) {
                  // there is already an existing record, must delete it before updating or adding
                  $ret = $pmuser->delete([ 'id' => $existingID, 'pm_users_id' => $pmuserlistGuid ]);
               }
               // insert into DB the link between glpi users and pm user
               if ($pmuser->getFromDB($user['id'])) {
                  $ret = $pmuser->update([ 'id' => $user['id'], 'pm_users_id' => $pmuserlistGuid ]);
               } else {
                  $ret = $pmuser->add([ 'id' => $user['id'], 'pm_users_id' => $pmuserlistGuid ]);
               }

               if (!$ret) {
                  $task->log("Cannot update user: '".$user['id']."' into glpi_plugin_processmaker_users!");
               }

            }
            if ($user['is_active'] == 0 || $user['is_deleted'] == 1) {
                $status = "INACTIVE";
            } else {
               $status = "ACTIVE";
            }
            if ($status != $pmUserList[strtolower($user['name'])]['status']) {
               $task->addVolume(1);
               $pmResult = $PM_SOAP->updateUser($pmUserList[strtolower($user['name'])]['guid'], $user['name'], $user['firstname'], $user['realname'], $status);
               if ($pmResult->status_code == 0) {
                  $task->log("Updated user: '".$user['name']."', status: '".$pmUserList[strtolower($user['name'])]['status']."' -> '".$status."'");
                  $actionCode = 1;
               } else {
                  $task->log("Error updating user: '".$user['name']."'");
                  $task->log("Error PM: '".$pmResult->message."'");
                  $actionCode = -1;
                  $error = true;
               }
            }

         }
      }

      // now we should desactivate PM users who are not in glpi user list
      //if(!$error) {
      //    $status = "INACTIVE" ;
      //    $arrayDiff = array_diff_key($pmUserList , $glpiUserList) ;
      //    foreach($arrayDiff as $user){
      //        $task->addVolume(1);
      //        if($user['status'] == 'ACTIVE' && $user['name'] != 'admin' && $user['name'] != 'glpi'){
      //            $pmResult = $myProcessMaker->updateUser($user['guid'], $user['name'], null, null, $status) ;
      //            if($pmResult->status_code == 0) {
      //                $task->log("Updated user: '".$user['name']."', status: '".$user['status']."' -> '".$status."'") ;
      //                $actionCode = 1 ;
      //            } else {
      //                $task->log("Error updating user: '".$user['name']."'") ;
      //                $task->log("Error PM: '".$pmResult->message."'") ;
      //                $actionCode = -1 ;
      //                $error = true ;
      //            }
      //        }
      //    }
      //}

      // so now treat GLPI groups
      $glpiGroupList = [];
      $res = $DB->request(['SELECT' => ['id', 'name', 'is_task', 'is_usergroup'],
                           'FROM'   => 'glpi_groups',
                           'WHERE'  => ['AND' => ['is_task' => 1, 'is_usergroup' => 1]]
                        ]);
      foreach ($res as $dbgroup) {
         $glpiGroupList[$dbgroup['name']] = $dbgroup;
      }

      $pmGroupList = self::getPMGroups(); // array();

      // here we can compare group lists like done for the users
      $arrayDiff = array_diff_key($glpiGroupList, $pmGroupList);

      // then for each group we must check if it exists, and if not create a real PM group
      foreach ($arrayDiff as $group) {
         // it is not existing in PM
         // then create
         $pmResult = $PM_SOAP->createGroup($group['name']);
         if ($pmResult->status_code == 0) {
            $task->addVolume(1);
            $task->log("Added group: '".$group['name']."'");
         }
      }

      // review and update all users in each group
      $pmGroupList = self::getPMGroups(); // array();

      // now should refresh the existing users into groups
      $arrayDiff = array_intersect_key($glpiGroupList, $pmGroupList);
      foreach ($arrayDiff as $group) {
         // for each group will delete users and re-create them
         // not really optimized, but this way we are sure that groups are synchronized
         // must be redesigned
         $PM_DB->delete('GROUP_USER', ['GROUP_USER.GRP_UID' => $pmGroupList[$group['name']]['CON_ID']]);
         // and insert all users from real GLPI group
         $res = $DB->request(['SELECT'      => ['glpi_groups_users.users_id', 'glpi_plugin_processmaker_users.pm_users_id'],
                              'FROM'        => 'glpi_groups',
                              'INNER JOIN'  => ['glpi_groups_users' => ['FKEY' => ['glpi_groups_users' => 'groups_id', 'glpi_groups' => 'id']],
                                                'glpi_plugin_processmaker_users' => ['FKEY' => ['glpi_plugin_processmaker_users' => 'id', 'glpi_groups_users' => 'users_id']]],
                              'WHERE'       => ['glpi_groups.name' => $group['name']]]);

         foreach ($res as $user) {
            $PM_DB->insert('GROUP_USER', ['GRP_UID' => $pmGroupList[$group['name']]['CON_ID'],
                                          'USR_UID' => $user['pm_users_id']
                                         ]
                           );
         }
         $task->addVolume(1);
         $task->log("Updated users into PM group: '".$group['name']."'");
      }

      // now should renew the duedate of the users
      $PM_DB->update('USERS',
                        ['USR_DUE_DATE' => '2035-12-31'],
                        ['USR_DUE_DATE' => ['!=', '2035-12-31']]
                     );
      $PM_DB->update('RBAC_USERS',
                        ['USR_DUE_DATE' => '2035-12-31'],
                        ['USR_DUE_DATE' => ['!=', '2035-12-31']]
                     );
      if ($error) {
          return -1;
      } else {
         return $actionCode;
      }
   }


   public static function plugin_pre_item_add_processmaker($parm) {
      global $PM_DB;

      if (isset($parm->input['processmaker_caseguid'])) {
         // a case is already started for this ticket, then change ticket title and ticket type and ITILCategory

         $myProcessMaker = new PluginProcessmakerProcessmaker();
         $myProcessMaker->login();
         $caseInfo = $myProcessMaker->getCaseInfo($parm->input['processmaker_caseguid'], $parm->input['processmaker_delindex']);
         $parm->input['name'] = $PM_DB->escape($caseInfo->caseName);

         $casegetvariables = ['GLPI_ITEM_TITLE', 'GLPI_ITEM_INITIAL_DUE_DATE', 'GLPI_ITEM_DUE_DATE'];
         $caseresetvariables = [];

         $caseDueDate = $myProcessMaker->getVariables($parm->input['processmaker_caseguid'], $casegetvariables);
         if (array_key_exists('GLPI_ITEM_INITIAL_DUE_DATE', $caseDueDate)) {
            $parm->input['time_to_resolve'] = $caseDueDate['GLPI_ITEM_INITIAL_DUE_DATE'];
            $caseresetvariables['GLPI_ITEM_INITIAL_DUE_DATE'] = '';
         }
         if (array_key_exists('GLPI_ITEM_DUE_DATE', $caseDueDate)) {
            $parm->input['time_to_resolve'] = $caseDueDate['GLPI_ITEM_DUE_DATE'];
            $caseresetvariables['GLPI_ITEM_DUE_DATE'] = '';
         }
         $re = '/^(?\'date\'[0-9]{4}-[0-1][0-9]-[0-3][0-9])( (?\'time\'[0-2][0-9]:[0-5][0-9]:[0-5][0-9]))*$/';
         if (isset($parm->input['time_to_resolve']) && preg_match($re, $parm->input['time_to_resolve'], $matches) && !array_key_exists('time', $matches)) {
            $parm->input['time_to_resolve'] .= " 23:59:59";
         }

         $txtItemTitle = $caseInfo->caseName;
         if (array_key_exists('GLPI_ITEM_TITLE', $caseDueDate)) {
            $txtItemTitle = $caseDueDate['GLPI_ITEM_TITLE'];
            // reset item title case variable
            $caseresetvariables['GLPI_ITEM_TITLE'] = '';
         }
         $parm->input['name'] = $PM_DB->escape($txtItemTitle);

         if (count($caseresetvariables)) {
            $resultSave = $myProcessMaker->sendVariables($parm->input['processmaker_caseguid'], $caseresetvariables);
         }

         $procDef = new PluginProcessmakerProcess;
         $procDef->getFromGUID($caseInfo->processId);
         if (isset($parm->input['type'])) {
            $parm->input['type'] = $procDef->fields['type'];
         }

         if (isset($parm->input['itilcategories_id']) && ($parm->input['itilcategories_id'] == '' || $parm->input['itilcategories_id'] == 0)) {
            $parm->input['itilcategories_id'] = $procDef->fields['itilcategories_id'];
         }

      }
   }


   /**
    * Summary of plugin_item_add_processmaker
    * @param mixed $parm
    * @return void
    */
   public static function plugin_item_add_processmaker($parm) {
      global $DB, $GLOBALS, $PM_SOAP;

      if (isset($parm->input['processmaker_caseguid'])) {
         // a case is already started for this ticket, then bind them together
         $itemtype = $parm->getType();
         $items_id = $parm->fields['id'];
         $case_guid = $parm->input['processmaker_caseguid'];

         $caseInfo = $PM_SOAP->getCaseInfo($case_guid);//$parm->input['processmaker_delindex']);

         $myCase = new PluginProcessmakerCase;
         $myCase->add(['id' => $parm->input['processmaker_casenum'],
                       'itemtype' => $itemtype,
                       'items_id' => $items_id,
                       'entities_id' => $parm->fields['entities_id'],
                       'name' => $DB->escape($caseInfo->caseName),
                       'case_guid' => $case_guid,
                       'case_status' => $caseInfo->caseStatus,
                       'plugin_processmaker_processes_id' => $parm->input['processmaker_processes_id']
                       ]);

         // here we create a fake task that will be used to store the creator of the case
         // this is due for traceability only
         $PM_SOAP->add1stTask($myCase->getID(), $myCase->fields['itemtype'], $myCase->fields['items_id'], $caseInfo, ['notif' => false, 'userId' => Session::getLoginUserID()]); // no notif

         // before routing, send items_id and itemtype
         // as this information was not available at case creation
         $myCase->sendVariables([ "GLPI_TICKET_ID" => $items_id,
                                   "GLPI_ITEM_ID"   => $items_id,
                                   "GLPI_ITEM_TYPE" => $itemtype,
                                 ]);

         $PM_SOAP->derivateCase($myCase, ['DEL_INDEX' => $parm->input['processmaker_delindex']]);

      } else {//if(array_key_exists('_head', $parm->input)) { // this ticket have been created via email receiver.
         $ptnProcessToStart = "/##TicketProcess:\s*#([0-9a-f]{32})\s*##/i";
         $str = $parm->input['content'];
         if (preg_match($ptnProcessToStart, $str, $matches) > 0) {
            // and it is requested to start a case of process
            $processGuid = $matches[1];
            $hasCase = self::hasCase('Ticket', $parm->fields['id']);
            if ($hasCase === false) {
               // check writer
               $writer = new User;
               $writer->getFromDB($parm->fields['users_id_recipient']);
               //$PM_SOAP = new PluginProcessmakerProcessmaker();
               $PM_SOAP->login($writer->fields['name']);
               $locProcess = new PluginProcessmakerProcess;
               if ($locProcess->getFromGUID($processGuid)) {
                  $found = false;
                  // check rights of writer to start this $processGuid on the Ticket entity
                  foreach (Profile_User::getUserProfiles($writer->getID()) as $profID) {
                     if (in_array($parm->fields['entities_id'], PluginProcessmakerProcess::getEntitiesForProfileByProcess($locProcess->getID(), $profID, true))) {
                        $found=true;
                        break;
                     }
                  }
                  if ($found) {
                     $PM_SOAP->startNewCase($locProcess->getID(), 'Ticket', $parm->fields['id'], $writer->getID());
                  }
               }
            }
         }
      }
      return;
   }


   public static function plugin_pre_item_add_processmaker_followup($parm) {
      //global $DB ;

   }


   /**
      * Summary of addWatcher
      * add $techId as watcher to $glpi_item when techId has no rights on it
      * @param string  $itemType is the type of the CommonITILObject
      * @param integer $itemId   is the id of the ITIL object
      * @param integer $techId   is the users_id to be added
      * @return boolean true if added,
      */
   public static function addWatcher($itemType, $itemId, $techId) {
      if ($techId && $itemType != '' && $itemId > 0) {
         $dbu = new DbUtils;
         $glpi_item = $dbu->getItemForItemtype($itemType);
         $glpi_item->getFromDB($itemId);

         // then we should check if this user has rights on the item, if not then we must add it to the watcher list!
         $glpi_item = $dbu->getItemForItemtype($itemType);
         $glpi_item->getFromDB($itemId);
         if (!$glpi_item->isUser(CommonITILActor::REQUESTER, $techId)
               && !$glpi_item->isUser(CommonITILActor::OBSERVER, $techId)
               && !$glpi_item->isUser(CommonITILActor::ASSIGN, $techId)) {

            // then we must add this tech user to watcher list
            $glpi_item_user = $dbu->getItemForItemtype($glpi_item->getType() . "_User");

            // do not send notifications
            $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false);
            $glpi_item_user->add([ $glpi_item::getForeignKeyField() => $glpi_item->getId(), 'users_id' => $techId, 'type' => CommonITILActor::OBSERVER, '_disablenotif' => true ]);
            PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);
            return true;
         }
      }
      return false;
   }

   public static function addGroupWatcher($itemType, $itemId, $groupId) {
      if($groupId && $itemType != '' && $itemId > 0) {
         $dbu = new DbUtils;
         $glpi_item = $dbu->getItemForItemtype($itemType);
         $glpi_item->getFromDB($itemId);

         // then we should check if this user has rights on the item, if not then we must add it to the watcher list!
         $glpi_item = $dbu->getItemForItemtype($itemType);
         $glpi_item->getFromDB($itemId);
         if (!$glpi_item->isGroup(CommonITILActor::REQUESTER, $groupId['id'])
               && !$glpi_item->isGroup(CommonITILActor::OBSERVER, $groupId['id'])
               && !$glpi_item->isGroup(CommonITILActor::ASSIGN, $groupId['id'])) {

            if($glpi_item->getType() === "Ticket" || $glpi_item->getType() === "Problem") {
               // then we must add this group to watcher list
               $glpi_item_group = $dbu->getItemForItemtype("Group_". $glpi_item->getType());
            } else {
               $glpi_item_group = $dbu->getItemForItemtype($glpi_item->getType() ."_Group");
            }

            // do not send notifications
            $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false);
            $glpi_item_group->add([ $glpi_item::getForeignKeyField() => $glpi_item->getId(), 'groups_id' => $groupId['id'], 'type' => CommonITILActor::OBSERVER, '_disablenotif' => true ]);
            PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);
            return true;
         }
      }
      return false;
   }


   /**
   * Summary of addTask
   *      adds a GLPI task to given item
   * @param $cases_id integer the GLPI id of the case
   * @param $itemtype string item type to which a task will be added
   * @param $items_id integer item id to which a task will be added
   * @param $caseInfo mixed getCaseInfoResponse object (see: getCaseInfo() function)
   * @param $delIndex integer index of the delegation
   * @param $techId integer GLPI user id to which a task must be assigned, if == 0 will use $pmTaskId
   * @param $pmTaskId string PM task guid, used when $techID == 0 to dynamically find the group (Self-Service)
   * @param $options array of options, default values are
   *            'txtTaskContent' => '',
   *            'start_date'     => '',
   *            'end_date'       => '',
   *            'reminder'       => '',
   *            'notif'          => true
   * @return
   */
   public function addTask($cases_id, $itemtype, $items_id, $caseInfo, $delIndex, $techId, $pmTaskId, $delThread, $options = []) {
      global $DB, $PM_DB, $_SESSION;

      $dbu = new DbUtils;

      $default_options = [
        'txtTaskContent' => '',
        'start_date'     => '',
        'end_date'       => '',
        'reminder'       => '',
        'notif'          => true
        ];

      foreach ($default_options as $key => $opt) {
         if (!isset($options[$key])) {
            $options[$key] = $opt;
         }
      }

      $glpi_task = $dbu->getItemForItemtype("{$itemtype}Task");
      $glpi_task->getEmpty();

      $input = []; // will contain all data for the Task

      $input[getForeignKeyFieldForItemType($itemtype)] = $items_id;
      // search for task category
      //
      $pmtaskcat = new PluginProcessmakerTaskCategory;
      $pmtaskcat->getFromGUID($pmTaskId);
      $input['taskcategories_id'] = $pmtaskcat->fields['taskcategories_id'];
      // load process information
      $pmProcess = new PluginProcessmakerProcess;
      $pmProcess->getFromDB($pmtaskcat->fields['plugin_processmaker_processes_id']);

      if ($options['start_date'] == '') {
         $options['start_date'] = new DateTime($_SESSION["glpi_currenttime"]);
      } else {
         $options['start_date'] = new DateTime($options['start_date']);
      }

      $input['begin'] = $options['start_date']->format("Y-m-d H:i:s");

      if ($options['end_date'] == '' || $options['end_date'] <= $input['begin']) {
         $options['end_date'] = clone $options['start_date'];
         $options['end_date']->add(new DateInterval('PT15M'));
      } else {
         $options['end_date'] = new DateTime($options['end_date']);
      }
      $input['end'] = $options['end_date']->format("Y-m-d H:i:s");
      $input['plan']['begin'] = $input['begin'];
      $temp = $options['start_date']->diff($options['end_date']);
      $input['plan']['_duration'] = $temp->days * DAY_TIMESTAMP + $temp->h * HOUR_TIMESTAMP + $temp->i * MINUTE_TIMESTAMP + $temp->s;
      if ($input['plan']['_duration'] == 0) {
         $input['plan']['_duration'] = 60; // at least
      }

      $input['users_id'] = $this->taskWriter;

      // manage groups
      if ($techId == 0) {
         // then we must look-up DB to get the group that will be assigned to the task
         $groups_id_tech = $this->getGLPIGroupIdForSelfServiceTask($caseInfo->caseId, $pmTaskId);
         self::addGroupWatcher($itemtype, $items_id, $groups_id_tech);
      } else {
         // adds the user tech to ticket watcher if neccessary
         self::addWatcher($itemtype, $items_id, $techId);
      }

      // manage task description
      $input['content'] = ""; // by default empty :)

      if ($pmProcess->fields["insert_task_comment"]) {
         $input['content'] .= "##processmaker.taskcomment##\n";
      }

      if ($options['txtTaskContent'] != '') {
         $input['content'] .= $options['txtTaskContent']."\n";
      } else if (!$pmProcess->fields["hide_case_num_title"]) {
         $input['content'] .= __('Case title: ', 'processmaker').$caseInfo->caseName."\n";
      }

      $input['content'] .= "##processmakercase.url##";

      $input['is_private'] = 0;
      //$input['actiontime'] = 0;
      $input['state'] = Planning::TODO; // == TO_DO
      $input['users_id_tech'] = 0; // by default as it can't be empty
      if ($techId) {
         $input['users_id_tech'] = $techId;
      } else if (is_array($groups_id_tech)) {
         $input['groups_id_tech'] = $groups_id_tech['id'];
      }

      //if ($options['reminder'] != '' && $techId) {
      //   $input['_planningrecall']   = ['before_time' => $options['reminder'],
      //                                  'itemtype'    => get_class($glpi_task),
      //                                  'items_id'    => '',
      //                                  'users_id'    => $techId,
      //                                  'field'       => 'begin'];
      //}

      $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false); // do not send notification yet as the PluginProcessmakerTask is not yet added to DB
      $glpi_task->add(Toolbox::addslashes_deep($input));
      PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);

      // to prevent error message for overlapping planning
      if (isset($_SESSION["MESSAGE_AFTER_REDIRECT"][WARNING])) {
         unset($_SESSION["MESSAGE_AFTER_REDIRECT"][WARNING]);
      }

      if ($glpi_task->getId() > 0) {
         // stores link to task in DB
         // can't use a GLPI framework object as PluginProcessmakerTask::getFromDB() has been overloaded
         $DB->insert('glpi_plugin_processmaker_tasks',
                     [
                     'items_id'                                => $glpi_task->getId(),
                     'itemtype'                                => $glpi_task->getType(),
                     'plugin_processmaker_cases_id'            => $cases_id,
                     'plugin_processmaker_taskcategories_id'   => $pmtaskcat->fields['id'],
                     'del_index'                               => $delIndex,
                     'del_thread'                              => $delThread
                     ]);
         $pmTaskId = $DB->insertId();

         // check if there is a recall reminder from the $pm_taskcat
         $before_time = $pmtaskcat->fields['before_time'];
         $after_time  = $pmtaskcat->fields['after_time'];
         if ($options['reminder'] != '') {
             $before_time = $options['reminder'];
         }
         if ($before_time != PluginProcessmakerTaskCategory::REMINDER_NONE || $after_time != PluginProcessmakerTaskCategory::REMINDER_NONE) {
             // then add a PluginProcessmakerTaskrecall object in DB
             $when = ($before_time > PluginProcessmakerTaskCategory::REMINDER_NONE ? date("Y-m-d H:i:s", strtotime($input['begin']) - $before_time) : date("Y-m-d H:i:s", strtotime($input['end']) + $after_time));
             $pr = new PluginProcessmakerTaskrecall();
             $pr->add([
                'plugin_processmaker_tasks_id' => $pmTaskId,
                'users_id'                     => $pmtaskcat->fields['users_id'],
                'before_time'                  => $before_time,
                'after_time'                   => $after_time,
                'when'                         => $when
             ]);
         }

         // send notification if needed for new task as now we have the PluginProcessmakerTask in the DB
         $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState($options['notif']);

         // send notification now!
         $pm_task = new PluginProcessmakerTask($glpi_task->getType());
         $pm_task->getFromDB($glpi_task->getId());
         $glpi_item = new $itemtype;
         $glpi_item->getFromDB($items_id);
         $pm_task->sendNotification('task_add', $glpi_task, $glpi_item);

         PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);
      }

   }


   /**
    * Summary of getGLPIGroupId
    * returns GLPI group id from pm group name
    * returns false when not found
    * @param  string $pmGroupName
    * @return bool|integer
    */
   static function getGLPIGroupId(string $pmGroupName) {
      global $DB;
      if ($pmGroupName != '') {
         $query = "SELECT id AS glpi_group_id FROM glpi_groups WHERE name LIKE '$pmGroupName';";
         $res = $DB->query($query);
         if ($DB->numrows($res) > 0) {
            $row = $DB->fetchArray($res);
            return $row['glpi_group_id'];
         }
      }
      return false;
   }

   /**
   * Summary of add1stTask
   *      adds a GLPI task to ticket
   * @param $cases_id integer the GLPI id of the case
   * @param $itemType string itemtype of object (Ticket, Problem, ....)
   * @param $itemId integer item id to which a task will be added
   * @param $caseInfo mixed getCaseInfoResponse object (see: getCaseInfo() function)
   * @param $options array of options, defaults are:
   *           'txtTaskContent' => '', is content of the task
   *           'userId'         => false, is user id to be assigned to task
   *           'notif'          => true, if true notifications will be sent
   * @return
   */
   public function add1stTask ($cases_id, $itemType, $itemId, $caseInfo, $options = []) {

      $default_options = [
        'txtTaskContent' => '',
        'userId'         => false,
        'notif'          => true
        ];
      foreach ($default_options as $key => $opt) {
         if (!isset($options[$key])) {
            $options[$key] = $opt;
         }
      }
      $start_date = new DateTime($_SESSION["glpi_currenttime"]);
      $official_date_time = $_SESSION["glpi_currenttime"];
      $_SESSION["glpi_currenttime"] = $start_date->sub(new DateInterval("PT1S"))->format("Y-m-d H:i:s");
      $userId = $options['userId'];
      unset($options['userId']); // unset it as it's not in the options of addTask

      $this->addTask($cases_id,
                      $itemType,
                      $itemId,
                      $caseInfo,
                      $caseInfo->currentUsers[0]->delIndex,
                      $userId,
                      $caseInfo->currentUsers[0]->taskId,
                      $caseInfo->currentUsers[0]->delThread,
                      $options
                   );
      $_SESSION["glpi_currenttime"] = $official_date_time;
   }


   /**
    * Summary of setItemStatus
    * @param mixed $itemtype
    * @param mixed $itemId
    * @param mixed $newstatus
   */
   public function setItemStatus($itemtype, $itemId, $newstatus) {
      $dbu = new DbUtils;
      $item = $dbu->getItemForItemtype($itemtype);
      if ($item->getFromDB($itemId)) { //&& $itemtype::isAllowedStatus($item->fields['status'], $newstatus)) {
          //$item->fields['status'] = $newstatus ;
         $item->update(['id' => $itemId, 'status' => $newstatus]);
      }
   }


   /**
    * Summary of setItemGlobalValidation
    * @param mixed $itemtype
    * @param mixed $itemId
    * @param mixed $newGlobalValidation
    */
   public function setItemGlobalValidation($itemtype, $itemId, $newGlobalValidation) {
      if (in_array($itemtype, ['Ticket', 'Change'])) {
         $dbu = new DbUtils;
         $item = $dbu->getItemForItemtype($itemtype);
         if ($item->getFromDB($itemId)) {
            $item->update(['id' => $itemId, 'global_validation' => $newGlobalValidation, '_rule_process' => true]);
         }
      }
   }



   /**
    * Summary of setTicketRequestType
    * @param mixed $itemtype
    * @param mixed $itemId
    * @param mixed $newrequestType
    */
   public function setTicketRequestType($itemtype, $itemId, $newrequestType) {
      if ($itemtype == 'Ticket') {
         $dbu = new DbUtils;
         $item = $dbu->getItemForItemtype($itemtype);
         if ($item->getFromDB($itemId)) {
            $item->update(['id' => $itemId, 'requesttypes_id' => $newrequestType]);
         }
      }
   }


   /**
    * Summary of setItemTitle
    * @param mixed $itemtype
    * @param mixed $itemId
    * @param mixed $newtitle
    */
   public function setItemTitle($itemtype, $itemId, $newtitle) {
      global $DB;

      $dbu = new DbUtils;
      $item = $dbu->getItemForItemtype($itemtype);
      if ($item->getFromDB($itemId)) {
         $item->update(['id' => $itemId, 'name' => $DB->escape($newtitle)]);
      }
   }


   /**
    * Summary of setItemDuedate
    * @param mixed $itemtype
    * @param mixed $itemId
    * @param mixed $duedate
    */
   public function setItemDuedate($itemtype, $itemId, $duedate) {
      $dbu = new DbUtils;
      $item = $dbu->getItemForItemtype($itemtype);
      if ($item->getFromDB($itemId)) {
         $item->update(['id' => $itemId, 'time_to_resolve' => $duedate]);
      }
   }


   /**
    * Summary of setItemSolution
    * @param mixed $itemType
    * @param mixed $itemId
    * @param mixed $casevariablevalues
    */
   public function setItemSolution($itemType, $itemId, $casevariablevalues) {
      global $DB;

      $dbu = new DbUtils;
      $item = $dbu->getItemForItemtype($itemType);
      if ($item->getFromDB($itemId)) {
         // default values
         $solutiontypes_id = 0;
         $solution_content = '';
         $to_add = false;

         // check solution template
         if (array_key_exists('GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID', $casevariablevalues)
            && $casevariablevalues['GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID'] != ''
            && $casevariablevalues['GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID'] != 0) {
            // get template
            $template = new SolutionTemplate;
            $template->getFromDB($casevariablevalues['GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID']);
            $entities = $template->isRecursive() ? $dbu->getSonsOf(Entity::getTable(), $template->getEntityID()) : [$template->getEntityID()];
            // and check entities
            if (in_array($item->getEntityID(), $entities)) {
               $solutiontypes_id = $template->fields['solutiontypes_id'];
               $solution_content = $template->fields['content'];
               $to_add = true;
            }
         }

         // check solution type
         if (array_key_exists('GLPI_ITEM_SET_SOLUTION_TYPE_ID', $casevariablevalues)
            && $casevariablevalues['GLPI_ITEM_SET_SOLUTION_TYPE_ID'] != ''
            && $casevariablevalues['GLPI_ITEM_SET_SOLUTION_TYPE_ID'] != 0) {
            // get solution type
            $type = new SolutionType;
            $type->getFromDB($casevariablevalues['GLPI_ITEM_SET_SOLUTION_TYPE_ID']);
            $entities = $type->isRecursive() ? $dbu->getSonsOf(Entity::getTable(), $type->getEntityID()) : [$type->getEntityID()];
            // and check entities
            if (in_array($item->getEntityID(), $entities)) {
               $solutiontypes_id = $type->getID();
               $to_add = true;
            }
         }

         // Check solution description
         if (array_key_exists('GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION', $casevariablevalues)
            && $casevariablevalues['GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION'] != '') {
            if ($solution_content != '') {
               $solution_content .= "\n";
            }
            $solution_content .= $DB->escape($casevariablevalues['GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION']);
            $to_add = true;
         }

         if ($to_add) {
            $solution = new ITILSolution();
            $solution->add([
               'itemtype'           => $itemType,
               'items_id'           => $itemId,
               'solutiontypes_id'   => $solutiontypes_id,
               'content'            => $solution_content
            ]);
         }
      }
   }


   /**
    * Summary of computeTaskDuration
    * @param mixed $task
    * @param mixed $entity
    * @return mixed
    */
   function computeTaskDuration($begin, $end, $entity) {

      $calendars_id = Entity::getUsedConfig('calendars_strategy', $entity, 'calendars_id', 0);
      $calendar     = new Calendar();

      // Using calendar
      if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
         return max(0, $calendar->getActiveTimeBetween($begin, $end));
      }
      // Not calendar defined
      return max(0, strtotime($end) - strtotime($begin));
   }



   /**
    * Summary of solveTask
    * @param string $cases_id GLPI case id
    * @param int    $delIndex
    * @param array  $options
    *                'txtToAppend'   => ''   : text to append to solved task
    *                'notif'         => true : if true will send notifications
    *                'users_id_tech' => is the users_id of the tech that solved the task
    *                'begin'         => is the new begin date of the task
    *                'end'           => is the new end date of the task
    *                'toInformation' => is the new status of the task (usually set to INFORMATION)
    * @return false|object the solved task, when found
   */
   public function solveTask($cases_id, $delIndex, $options = []) {
      global $DB;

      //$query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id=$cases_id and del_index=$delIndex; ";
      //$res = $DB->query($query);
      $res = $DB->request('glpi_plugin_processmaker_tasks', ['AND' => ['plugin_processmaker_cases_id' => $cases_id, 'del_index' => $delIndex]]);
      //if ($DB->numrows($res) > 0) {
      if ($row = $res->current()) {
         $dbu = new DbUtils;
         //$row = $DB->fetch_array($res);

         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB($row['items_id']);
         $hostItem = $dbu->getItemForItemtype($glpi_task->getItilObjectItemType());
         $itemFKField = getForeignKeyFieldForItemType($glpi_task->getItilObjectItemType());
         $hostItem->getFromDB($glpi_task->fields[ $itemFKField ]);

         // change current glpi_currenttime to be sure that date_mode for solved task will not be identical than date_mode of the newly started task
         $saved_date_time = $_SESSION["glpi_currenttime"];
         $_SESSION["glpi_currenttime"] = (new DateTime($saved_date_time))->sub(new DateInterval("PT1S"))->format("Y-m-d H:i:s");

         $default_options = [
           'txtToAppend'   => '',
           'notif'         => true,
           'users_id_tech' => null,
           'begin'         => $glpi_task->fields['begin'] > $_SESSION["glpi_currenttime"] ?
                              (new DateTime($_SESSION["glpi_currenttime"]))->sub(new DateInterval("PT1S"))->format("Y-m-d H:i:s") :
                              $glpi_task->fields['begin'],
           'end'           => $_SESSION["glpi_currenttime"],
           'toInformation' => false
           ];
         foreach ($default_options as $key => $opt) {
            if (!isset($options[$key]) || ($options[$key] === '')) {
               $options[$key] = $opt;
            }
         }

         $duration = $this->computeTaskDuration($options['begin'], $options['end'], $hostItem->fields['entities_id']);
         if ($options['txtToAppend'] != "") {
            $options['txtToAppend'] = "\n<hr>".$options['txtToAppend'];
         }
         $params = ['id'             => $row['items_id'],
                    'state'          => $options['toInformation'] ? Planning::INFO : Planning::DONE,
                    'begin'          => $options['begin'],
                    'end'            => $options['end'],
                    $itemFKField     => $hostItem->getID(),
                    'actiontime'     => $duration,
                    'users_id_tech'  => (isset($options['users_id_tech']) ? $options['users_id_tech'] : Session::getLoginUserID()),
                    //'groups_id_tech' => 0,
                    'content'        => $DB->escape($glpi_task->fields['content'].$options['txtToAppend'])
                   ];

         $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState(false); // do not send notification yet
         $glpi_task->update($params);
         PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);

         // Close the task
         //$DB->query("UPDATE glpi_plugin_processmaker_tasks SET del_thread_status = '".PluginProcessmakerTask::CLOSED."' WHERE id = {$row['id']}");
         $DB->update('glpi_plugin_processmaker_tasks', ['del_thread_status' => PluginProcessmakerTask::CLOSED], ['id' => $row['id']]);

         // send notification if needed for new task as now the PluginProcessmakerTask has been updated in the DB
         $donotif = PluginProcessmakerNotificationTargetProcessmaker::saveNotificationState($options['notif']);

         // send notification now!
         $pm_task = new PluginProcessmakerTask($glpi_task->getType());
         $pm_task->getFromDB($glpi_task->getId());
         $pm_task->sendNotification('task_done', $glpi_task, $hostItem);

         PluginProcessmakerNotificationTargetProcessmaker::restoreNotificationState($donotif);
         // restore current glpi time
         $_SESSION["glpi_currenttime"] = $saved_date_time;

         return $glpi_task;
      }

      return false;
   }

   /**
    * Summary of claimTask
    * will unassign group, and assign tech
    * @param mixed $cases_id GLPI case id
    * @param mixed $delIndex
    * @param mixed $users_id_tech optional is the id of the tech
    *                  who's claimed the task, default current logged-in user
   */
   public function claimTask($cases_id, $delIndex, $users_id_tech = null) {
      global $DB;
      $res = $DB->request('glpi_plugin_processmaker_tasks', ['AND' => ['plugin_processmaker_cases_id' => $cases_id, 'del_index' => $delIndex]]);
      //$query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE plugin_processmaker_cases_id='$cases_id' and del_index=$delIndex; ";
      //$res = $DB->query($query);
      //if ($DB->numrows($res) > 0) {
      if ($row = $res->current()) {
         $dbu = new DbUtils;
         //$row = $DB->fetch_array($res);
         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB($row['items_id']);

         $itemType = str_replace('Task', '', $row['itemtype']);
         $glpi_item = $dbu->getItemForItemtype($itemType);
         $glpi_item->getFromDB($glpi_task->fields[ getForeignKeyFieldForItemType($itemType) ]);

         $glpi_task->update([ 'id'                             => $row['items_id'],
                               $glpi_item->getForeignKeyField() => $glpi_item->getId(),
                               'users_id_tech'                  => (isset($users_id_tech)?$users_id_tech: Session::getLoginUserID()),
                               'groups_id_tech'                 => 0,
                               'update'                         => true]);
      }
   }


   // /**
   //  * Summary of getCaseIdFromItem
   //  *      get case id for an id item_id of type item_type (if a case if attached to it)
   //  * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
   //  * @param integer $item_id,   the id for the item
   //  * @return string the case guid, false if no case is attached to item, or if an error occurred
   //  */
   //public static function getCaseIdFromItem ($item_type, $item_id) {
   //   global $DB;
   //   $res = $DB->request('glpi_plugin_processmaker_cases', ['AND' => ['itemtype' => $item_type, 'items_id' => $item_id]]);
   //   //$query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE `itemtype` = '$item_type' AND `items_id` = $item_id ;";
   //   //  $res = $DB->query($query);
   //   //if ($DB->numrows($res) > 0) {
   //   //   // case is existing for this item
   //   //   // then get info from db
   //   //   $row = $DB->fetch_array($res);
   //   if ($row = $res->next()) {
   //      return $row['id'];
   //   }

   //   return false;
   //}

   /**
    * Summary of hasCase
    *      returns true if cases are attached to item, false otherwise
    * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
    * @param integer $item_id,   the id for the item
    * @return boolean true if at least one case is attached, otherwise false
    */
   public static function hasCase($item_type, $item_id) {
      global $DB;
      $res = $DB->request('glpi_plugin_processmaker_cases', ['AND' => ['itemtype' => $item_type, 'items_id' => $item_id]]);
      if ($res->numrows() > 0) {
         return true;
      }

      return false;
   }


   ///**
   // * Summary of getCaseGuidFromItem
   // *      get case id for an id item_id of type item_type (if a case if attached to it)
   // * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
   // * @param integer $item_id,   the id for the item
   // * @return string the case guid, false if no case is attached to item, or if an error occurred
   // */
   //public static function getCaseGuidFromItem ($item_type, $item_id) {
   //   global $DB;
   //   $res = $DB->request('glpi_plugin_processmaker_cases', ['AND' => ['itemtype' => $item_type, 'items_id' => $item_id]]);
   //   //$query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE `itemtype` = '$item_type' AND `items_id` = $item_id ;";
   //   //$res = $DB->query($query);
   //   //if ($DB->numrows($res) > 0) {
   //   //   // case is existing for this item
   //   //   // then get info from db
   //   //   $row = $DB->fetch_array($res);
   //   if ($row = $res->next()) {
   //      return $row['case_guid'];
   //   }

   //   return false;
   //}

   // /**
   //  * Summary of getCaseFromItem
   //  *      get case infos for an id item_id of type item_type (if a case if attached to it)
   //  * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
   //  * @param integer $item_id,   the id for the item
   //  * @return getCaseInfoResponse object, false if no case is attached to item, or if an error occurred
   //  */
   //public function getCaseFromItem($item_type, $item_id) {
   //   global $DB;

   //   $caseId = self::getCaseGuidFromItem($item_type, $item_id);
   //   if ($caseId !== false) {
   //      $caseInfo = $this->getCaseInfo($caseId);
   //      if ($caseInfo !== false && $caseInfo->status_code == 0) {
   //          return $caseInfo;
   //      } else {
   //         return false; // means any error
   //      }
   //   } else {
   //      return false; // means no case
   //   }
   //}

    /**
     * Summary of multiexplode
     * @param $delimiters
     * @param $string
     * @return
     */
   static function multiexplode($delimiters, $string) {

      $ready = str_replace($delimiters, $delimiters[0], $string);
      $launch = explode($delimiters[0], $ready);
      return  $launch;
   }

   /**
   * Summary of pre_show_item_processmaker
   * @param $params
   */
   public static function pre_show_item_processmaker($params) {

      $plugin = new Plugin();
      $itemtype = $params['item']->getType();
      if (in_array($itemtype, ['Ticket', 'Problem', 'Change'])
         && $params['options']['id']
         && !PluginProcessmakerCase::canSolve($params)
         && Session::getCurrentInterface() == "central") {
         // then we are in an ITIL Object
         // tabnum 1 : Processing Ticket
         // a case may be solved or not (ex: PIR tasks for Change Management)

         // don't display message if arbehaviours is install and activated
         $itemtype = strtolower($itemtype);
         if ((!$plugin->isInstalled('arbehaviours') || !$plugin->isActivated('arbehaviours'))
            && isset($_SESSION['glpiactiveprofile'][$itemtype.'_status'])) {

            echo Html::scriptBlock("
               //$('head').append('<style type=\"text/css\">li.solution {pointer-events:none; opacity:0.3;}</style>');
               //debugger;
               $('a.action-solution').css({pointerEvents: 'none', opacity: 0.3});
               glpi_toast_info('At least one \'Process case\' is running!<br> Adding a solution is currently disabled!', 'Solution disabled', {delay: Math.pow(2, 31) - 1});
            ");

            //self::displayMessage(__('At least one \'Process case\' is running!<br/>Adding a solution is currently disabled!', 'processmaker'), false, INFO);

            ////backup current $_SESSION['glpiactiveprofile'][$itemtype.'_status']
            //$_SESSION['glpiactiveprofile'][$itemtype.'_status_pm_save'] = $_SESSION['glpiactiveprofile'][$itemtype.'_status'];

            //// for all $params['options']['itemtype'].status, we must disable solved (life cycles)
            //foreach ($_SESSION['glpiactiveprofile'][$itemtype.'_status'] as $key => $value) {
            //   //$_SESSION['glpiactiveprofile'][$itemtype.'_status'][$key][CommonITILObject::SOLVED] = 0;
            //}
         }
      }

      if (!is_array($params['item']) && is_subclass_of($params['item'], 'CommonITILTask')) {
         // must check if Task is bound to a PM task
         $pmTask = new PluginProcessmakerTask($params['item']->getType());
         $is_pmtask = $pmTask->getFromDB($params['item']->getId());
         if (!$is_pmtask && $params['item']->fields['state'] == Planning::INFO) {
            // look if it is a meta task for this process
            // means a re-assign or an un-claim task info
            $pm_process = new PluginProcessmakerProcess;
            $restrict=[
                                  'WHERE'  => [
                                  'taskcategories_id'  => $params['item']->fields['taskcategories_id']
                                  ],
                        ];
            //if ($pm_process->getFromDBByQuery(" WHERE `taskcategories_id` = ".$params['item']->fields['taskcategories_id'])) {
            if ($pm_process->getFromDBByRequest($restrict)) {
               // then look into content to get case id
               $re = '/<input name=\'caseid\' type=\'hidden\' value=\'(?\'caseid\'\d+)\'><input name=\'taskid\' type=\'hidden\' value=\'(?\'taskid\'\d+)\'>/';
               if (preg_match($re, $params['item']->fields['content'], $matches)) {
                  // here we get the case id and the task id
                  $is_pmtask = $pmTask->getFromDB($matches['taskid']);
               }
            }
         }
         if ($is_pmtask) {
            $taskJSId = strtolower("viewitem".$params['item']->getType().$params['item']->getId()); //.$params['options']['rand']);
            $dataItemId = $params['item']->getId();
            $tmpCase = new PluginProcessmakerCase;
            $tmpCase->getFromDB($pmTask->fields['plugin_processmaker_cases_id']);
            $urlLink = $tmpCase->getLinkURL().'&forcetab=PluginProcessmakerTask$'.$pmTask->fields['items_id'];

            if ($pmTask->fields['del_thread_status'] != PluginProcessmakerTask::CLOSED) {
                if ($params['item']->fields['state'] != Planning::INFO) {
                    echo Html::scriptBlock("
                       function $taskJSId(ev) {
                             //debugger;
                             if ($(ev.target).parent('.read_more').length == 0) {
                                $('div[data-items-id=\"$dataItemId\"]').width('100%');
                                $('div[data-items-id=\"$dataItemId\"]').find('.edit_content').width('100%').css({ 'background-color': 'white', 'padding': '5px 0', 'text-align': 'center'})
                                $('div[data-items-id=\"$dataItemId\"]').find('.read-only-content').hide();
                                $('div[data-items-id=\"$dataItemId\"]').find('.edit_content').show().load('/plugins/processmaker/ajax/task.php', 
                                               {'cases_id': {$pmTask->fields['plugin_processmaker_cases_id']}, 
                                                'tabnum': {$pmTask->fields['items_id']}});
                                $('div[data-items-id=\"$dataItemId\"] .close-edit-content').show().toggleClass('d-none')
                                                                .click(function() {
                                                                    $(this).hide();
                                                                    $('div[data-items-id=\"$dataItemId\"] .edit_content').empty().hide();
                                                                    $('div[data-items-id=\"$dataItemId\"] .read-only-content').show();
                                                                    $('div[data-items-id=\"$dataItemId\"]').width('');
                                                                });
                             }
                          };
//debugger;
                       $('div[data-items-id=\"$dataItemId\"]').find('.read-only-content').parent().append('<div class=\"edit_content\"></div>');
                       $('div[data-items-id=\"$dataItemId\"]').find('.edit_content').hide();
                       //$('div[data-items-id=\"$dataItemId\"]').find('.timeline-header .timeline-item-buttons').append('<div class=\"close-edit-content\"></div>');
                       $('div[data-items-id=\"$dataItemId\"]').find('.timeline-header .close-edit-content').appendTo('div[data-items-id=\"$dataItemId\"] .timeline-item-buttons');
                       $('div[data-items-id=\"$dataItemId\"]').find('.close-edit-content').hide();
                       $('div[data-items-id=\"$dataItemId\"]').find('.text-content').on('click', $taskJSId).css('cursor', 'pointer');
                       $('div[data-items-id=\"$dataItemId\"]').find('.read_more').css('cursor', 'auto');
                       $('tr[id=\"$taskJSId\"]').children().on('click', $taskJSId).css('cursor', 'pointer');
                       $(function() {
                       $('div[data-items-id=\"$dataItemId\"]').find('.timeline-item-buttons').prepend(
                          '<a class=\"pm_task pm_task_badge\" href=\"{$urlLink}\">".str_replace("'", "\\'", $tmpCase->fields['name'])."' +
                          '<span class=\"pm_task pm_task_case\" case-id=\"{$tmpCase->fields['id']}\">{$tmpCase->fields['id']}</span>' +
                          '</a>'
                          );
                      });
                    ");
                } else {
                    echo Html::scriptBlock("
                       function $taskJSId(ev) {
                             //debugger;
                             if ($(ev.target).parent('.read_more').length == 0) {
                                document.location='$urlLink';
                             }
                          };
                       $('#$taskJSId').find('.item-content').on('click', $taskJSId).css('cursor', 'pointer');
                       $('#$taskJSId').find('.read_more').css('cursor', 'auto');
                       $('tr[id=\"$taskJSId\"]').children().on('click', $taskJSId).css('cursor', 'pointer');
                       $(function() {
                          $('#$taskJSId').find('.displayed_content span.state').parent().append(
                             '<a class=\"pm_task pm_task_badge\" href=\"$urlLink\">".str_replace("'", "\\'", $tmpCase->fields['name'])."' +
                             '<span class=\"pm_task pm_task_case\" case-id=\"{$tmpCase->fields['id']}\">{$tmpCase->fields['id']}</span>' +
                             '</a>'
                             );
                       });
                    ");
                }
            } else {
                echo Html::scriptBlock("
                   function $taskJSId(ev) {
                         if ($(ev.target).parent('.read_more').length == 0) {
                            document.location='$urlLink';
                         }
                      };
                   $('div[data-items-id=\"$dataItemId\"]').find('.text-content').on('click', $taskJSId).css('cursor', 'pointer');
                   $('div[data-items-id=\"$dataItemId\"]').find('.read_more').css('cursor', 'auto');
                   $('tr[id=\"$taskJSId\"]').children().on('click', $taskJSId).css('cursor', 'pointer');
                   $(function() {
                      $('div[data-items-id=\"$dataItemId\"]').find('.timeline-item-buttons').prepend(
                         '<a class=\"pm_task pm_task_badge\" href=\"{$urlLink}\">".str_replace("'", "\\'", $tmpCase->fields['name'])."' +
                         '<span class=\"pm_task pm_task_case\" case-id=\"{$tmpCase->fields['id']}\">{$tmpCase->fields['id']}</span>' +
                         '</a>'
                         );
                   });
                   //$('#$taskJSId').find('.displayed_content > div').last().html(
                   //   $('#$taskJSId').find('.displayed_content > div').last().html().replace(/(.*?)(<br>.*)/, '<span class=\"pm_task pm_task_category\">$1</span>$2')
                   //);
                ");
             }
            //will open all todo tasks when created within last 30 seconds
            if ((strtotime($pmTask->fields['date_creation']) > strtotime("-30 second") || strtotime($pmTask->fields['date_mod']) > strtotime("-30 second"))
                && (Session::getLoginUserID() === $pmTask->fields['users_id_tech'] || !empty(Group_User::getGroupUsers($pmTask->fields['groups_id_tech'], ['users_id' => Session::getLoginUserID()])))
                && $params['item']->fields['state'] == Planning::TODO) {
                echo Html::scriptBlock("
                    $('div[data-items-id=\"$dataItemId\"]').width('100%');
                    $('div[data-items-id=\"$dataItemId\"]').find('.edit_content').width('100%').css({ 'background-color': 'white', 'padding': '5px 0', 'text-align': 'center'})
                    $('div[data-items-id=\"$dataItemId\"]').find('.read-only-content').hide();
                    $('div[data-items-id=\"$dataItemId\"]').find('.edit_content').show().load('/plugins/processmaker/ajax/task.php', 
                                   {'cases_id': {$pmTask->fields['plugin_processmaker_cases_id']}, 
                                    'tabnum': {$pmTask->fields['items_id']}}, function() {
                                            $('div[data-items-id=\"$dataItemId\"]')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    });
                    $('div[data-items-id=\"$dataItemId\"] .close-edit-content').show().toggleClass('d-none')
                                                    .click(function() {
                                                        $(this).hide();
                                                        $('div[data-items-id=\"$dataItemId\"] .edit_content').empty().hide();
                                                        $('div[data-items-id=\"$dataItemId\"] .read-only-content').show();
                                                        $('div[data-items-id=\"$dataItemId\"]').width('');
                                                    });
                ");
            }
            // will add a class to the div.h_content
            echo Html::scriptBlock("
                      //$('div[data-items-id=\"$dataItemId\"]').removeClass('ITILTask');
                      $('div[data-items-id=\"$dataItemId\"]').addClass('Case-".$pmTask->fields['plugin_processmaker_cases_id']."');
                ");

            // in order to set NavigationList
            Session::initNavigateListItems('PluginProcessmakerCase',
                        //TRANS : %1$s is the itemtype name,
                        //        %2$s is the name of the item (used for headings of a list)
                                  sprintf('%1$s = %2$s',
                                          $params['options']['parent']->getTypeName(1), $params['options']['parent']->fields["name"]));
         }
      }
   }

   static function show_in_timeline_processmaker($params) {
      global $CFG_GLPI;
      foreach($params['timeline'] as $key => $timelineObject) {
         if (is_subclass_of($timelineObject['type'], 'CommonITILTask')) {
            $pmTask = new PluginProcessmakerTask($timelineObject['type']);
            $is_pmtask = $pmTask->getFromDB($timelineObject['item']['id']);
            if (!$is_pmtask && $timelineObject['item']['state'] == Planning::INFO) {
               // look if it is a meta task for this process
               // means a re-assign or an un-claim task info
               $pm_process = new PluginProcessmakerProcess;
               $restrict=[
                                     'WHERE'  => [
                                     'taskcategories_id'  => $timelineObject['item']['taskcategories_id']
                                     ],
                           ];
               //if ($pm_process->getFromDBByQuery(" WHERE `taskcategories_id` = ".$params['item']->fields['taskcategories_id'])) {
               if ($pm_process->getFromDBByRequest($restrict)) {
                  // then look into content to get case id
                  $re = '/<input name=\'caseid\' type=\'hidden\' value=\'(?\'caseid\'\d+)\'><input name=\'taskid\' type=\'hidden\' value=\'(?\'taskid\'\d+)\'>/';
                  if (preg_match($re, $timelineObject['item']['content'], $matches)) {
                     // here we get the case id and the task id
                     $is_pmtask = $pmTask->getFromDB($matches['taskid']);
                  }
               }
            }
            if ($is_pmtask) {
               $timelineObject['item']['can_edit'] = false; // to prevent task edition
               $timelineObject['item']['can_promote'] = false; // to prevent task promote to ticket

               // replace ##ticket.url##_PluginProcessmakerCase$processmakercases by a setActiveTab to the Case panel
               $taskCat = new TaskCategory;
               $taskCat->getFromDB($timelineObject['item']['taskcategories_id']);
               $taskComment = isset($taskCat->fields['comment']) ? $taskCat->fields['comment'] : '';
               if (Session::haveTranslations('TaskCategory', 'comment')) {
                  $taskCategorytranslation = DropdownTranslation::getTranslatedValue($taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskComment);
                  $timelineObject['item']['content'] = str_replace('##processmaker.taskcomment##',
                     !is_null($taskCategorytranslation) ? $taskCategorytranslation : '' ,
                     $timelineObject['item']['content']
                  );
               } else {
                  $timelineObject['item']['content'] = str_replace('##processmaker.taskcomment##', $taskComment, $timelineObject['item']['content']);
               }
               //$tmpCase = new PluginProcessmakerCase;
               //$tmpCase->getFromDB($pmTask->fields['plugin_processmaker_cases_id']);
               $timelineObject['item']['content'] = str_replace(["\n##processmakercase.url##", '\n##processmakercase.url##', '##processmakercase.url##'], "", $timelineObject['item']['content']);
               $timelineObject['item']['content'] = nl2br($timelineObject['item']['content']);
               $params['timeline'][$key] = $timelineObject;
            }
         }
      }
      //$itemtype = $params['item']->getType();
      // if (in_array($itemtype, ['Ticket', 'Problem', 'Change'])
      //   && $params['item']->getID()
      //   && Session::getCurrentInterface() == "central") {
      //    $rand = rand();
          //$status = $params['item']->fields['status'];
          //if ($status !== CommonITILObject::CLOSED && $status !== CommonITILObject::SOLVED) {
          //    $user = User::getById(Session::getLoginUserID());
          //    $btnLayout = $user->fields["timeline_action_btn_layout"];
          //    if ($btnLayout == null) {
          //        $btnLayout = 0;
          //    }
          //    echo Html::scriptBlock("
          //     if (window.jQuery != undefined) {
          //        $(document).ready(function() {
          //              function newCasebutton(){
          //                  $('.timeline-buttons .main-actions').hide();
          //                  $('#right-actions').hide();
          //                  $(document).on('click', '#new-itilobject-form .close-new-case-form', function() {
          //                     $('#itil-footer .main-actions').show();
          //                     $('#right-actions').show();
          //                  });              
          //               }
          //              if ($btnLayout) {
          //                 $('.timeline-buttons .main-actions').append('<button class=\"ms-2 mb-2 btn btn-primary answer-action newCase\" data-bs-toggle=\"collapse\" data-bs-target=\"#new-CaseForm-block\"><i class=\"fas fa-project-diagram\"></i><span>".__('Case', 'processmaker')."</span></button>');
          //              }
          //              else {
          //                 $('.timeline-buttons .main-actions .dropdown-menu').append(\"<li><a class='dropdown-item action-task newCase' href='#' data-bs-toggle='collapse' data-bs-target='#new-CaseForm-block'><i class='fas fa-project-diagram'></i><span>".__('Add a case', 'processmaker')."</span></a></li>\");
          //              }
          //              $('.timeline-buttons .main-actions .newCase').css('background-color', '#4185F4');
          //              $('.timeline-buttons .main-actions .newCase').on('click', newCasebutton).css('cursor', 'pointer');
          //              $('#new-itilobject-form').append('<div class=\"timeline-item mb-3  new-case-form collapse \" id=\"new-CaseForm-block\" aria-expanded=\"false\" data-bs-parent=\"#new-itilobject-form\">')
          //              $('#new-CaseForm-block').load('/plugins/processmaker/ajax/case.php', {'items_id': {$params['item']->getID()}, 'itemtype': '{$params['item']->getType()}'});
          //         })
          //    };");
          //}
          // <li class='newCase'><i class='fas fa-project-diagram'></i>
          //<img src='/plugins/processmaker/pics/processmaker-xxxs.png'>

          //// will add filter for each cases in this object
          //foreach (getAllDataFromTable(PluginProcessmakerCase::GetTable(), ['itemtype' => $itemtype, 'items_id' => $params['item']->getID()]) as $row) {
          //    echo Html::scriptBlock("
          //         if (window.jQuery != undefined) {
          //             $(document).ready(function() {
          //                 $('.filter-timeline div.ITILTask').parents('li').before('<li class=\"list-group-item list-group-item-action py-1\"><div class=\"form-check form-switch trigger-filter CaseFilter\" role=\"button\"><input class=\"form-check-input\" type=\"checkbox\" id=\"timeline-filter-Case-" . $row['id'] . "\" autocomplete=\"off\" checked=\"\" data-itemtype=\"Case-" . $row['id'] . "\"><label class=\"form-check-label\" for=\"timeline-filter-Case-" . $row['id'] . "\" role=\"button\"><i class=\"fas fa-project-diagram mx-2\"></i>" . Toolbox::addslashes_deep($row['name']) . " - ". $row['id'] . "</label></div></li>');
          //         })};");
          //}
          //echo Html::scriptBlock("
          //   if (window.jQuery != undefined) {
          //       $(document).ready(function() {
          //           $('.filter-timeline div.list-group').prepend('<li class=\"list-group-item list-group-item-action py-1\"><div class=\"form-check form-switch trigger-filter FilterAll\" role=\"button\"><input class=\"form-check-input\" type=\"checkbox\" id=\"timeline-filter-filter-all\" autocomplete=\"off\" checked=\"\" ><label class=\"form-check-label\" for=\"timeline-filter-filter-filter-all\" role=\"button\"><i class=\"ti ti-checklist mx-2\"></i>All</label></div></li>')
          //           $('.filter-timeline #timeline-filter-filter-all').on('click', function (e) {
          //               $('.filter-timeline li').each(function () {
          //                   if ($(this).find('.FilterAll').length == 0 
          //                       && $(this).find('.Log').length == 0 
          //                       && $(this).find('input[type=checkbox]').prop('checked') != $('#timeline-filter-filter-all').prop('checked')) {
          //                       $(this).find('input[type=checkbox]').click();
          //                   }
          //               })
          //           })
          //       })
          //  }");
      //}
   }

   /**
    * Summary of post_show_item_processmaker
    * @param mixed $params 
    */
   public static function post_show_item_processmaker($params) {
       $itemtype = $params['item']->getType();
       if (in_array($itemtype, ['Ticket', 'Problem', 'Change'])
         && $params['item']->getID()
         && Session::getCurrentInterface() == "central") {
            $status = $params['item']->fields['status'];
            if ($status !== CommonITILObject::CLOSED && $status !== CommonITILObject::SOLVED) {
                $user = User::getById(Session::getLoginUserID());
                $btnLayout = $user->fields["timeline_action_btn_layout"];
                if ($btnLayout == null) {
                    $btnLayout = 0;
                }
                echo Html::scriptBlock("
                    $(document).ready(function() {
                        function newCasebutton(){
                            $('.timeline-buttons .main-actions').hide();
                            $('#right-actions').hide();
                            $(document).on('click', '#new-itilobject-form .close-new-case-form', function() {
                               $('#itil-footer .main-actions').show();
                               $('#right-actions').show();
                            });
                         }
                        if ($btnLayout) {
                           $('.timeline-buttons .main-actions').append('<button class=\"ms-2 mb-2 btn btn-primary answer-action newCase\" data-bs-toggle=\"collapse\" data-bs-target=\"#new-CaseForm-block\"><i class=\"fas fa-project-diagram\"></i><span>".__('Case', 'processmaker')."</span></button>');
                        }
                        else {
                           $('.timeline-buttons .main-actions .dropdown-menu').append(\"<li><a class='dropdown-item action-task newCase' href='#' data-bs-toggle='collapse' data-bs-target='#new-CaseForm-block'><i class='fas fa-project-diagram'></i><span>".__('Add a case', 'processmaker')."</span></a></li>\");
                        }
                        $('.timeline-buttons .main-actions .newCase').css('background-color', '#4185F4');
                        $('.timeline-buttons .main-actions .newCase').on('click', newCasebutton).css('cursor', 'pointer');
                        $('#new-itilobject-form').append('<div class=\"timeline-item mb-3  new-case-form collapse \" id=\"new-CaseForm-block\" aria-expanded=\"false\" data-bs-parent=\"#new-itilobject-form\">')
                        $('#new-CaseForm-block').load('/plugins/processmaker/ajax/case.php', {'items_id': {$params['item']->getID()}, 'itemtype': '{$params['item']->getType()}'});
                     })
                ;");
            }
            // will add filter for each cases in this object
            foreach (getAllDataFromTable(PluginProcessmakerCase::GetTable(), ['itemtype' => $itemtype, 'items_id' => $params['item']->getID()]) as $row) {
                echo Html::scriptBlock("
                     if (window.jQuery != undefined) {
                         $(document).ready(function() {
                             $('.filter-timeline div.ITILTask').parents('li').before('<li class=\"list-group-item list-group-item-action py-1\"><div class=\"form-check form-switch trigger-filter CaseFilter\" role=\"button\"><input class=\"form-check-input\" type=\"checkbox\" id=\"timeline-filter-Case-" . $row['id'] . "\" autocomplete=\"off\" checked=\"\" data-itemtype=\"Case-" . $row['id'] . "\"><label class=\"form-check-label\" for=\"timeline-filter-Case-" . $row['id'] . "\" role=\"button\"><i class=\"fas fa-project-diagram mx-2\"></i>" . Toolbox::addslashes_deep($row['name']) . " - ". $row['id'] . "</label></div></li>');
                     })};");
            }
            echo Html::scriptBlock("
               if (window.jQuery != undefined) {
                   $(document).ready(function() {
                       $('.filter-timeline div.list-group').prepend('<li class=\"list-group-item list-group-item-action py-1\"><div class=\"form-check form-switch trigger-filter FilterAll\" role=\"button\"><input class=\"form-check-input\" type=\"checkbox\" id=\"timeline-filter-filter-all\" autocomplete=\"off\" checked=\"\" ><label class=\"form-check-label\" for=\"timeline-filter-filter-filter-all\" role=\"button\"><i class=\"ti ti-checklist mx-2\"></i>All</label></div></li>')
                       $('.filter-timeline #timeline-filter-filter-all').on('click', function (e) {
                           $('.filter-timeline li').each(function () {
                               if ($(this).find('.FilterAll').length == 0 
                                   && $(this).find('.Log').length == 0 
                                   && $(this).find('input[type=checkbox]').prop('checked') != $('#timeline-filter-filter-all').prop('checked')) {
                                   $(this).find('input[type=checkbox]').click();
                               }
                           })
                       })
                   })
              }");
       }
   }


    /**
     * Summary of getItemUsers
     * returns an array of glpi ids and pm ids for each user type assigned to given ticket
     * @param string  $itemtype
     * @param integer $itemId   is the ID of the titem
     * @param integer $userType is 1 for ticket requesters, 2 for ticket technicians, and if needed, 3 for watchers
     * @return array of users in the returned array
     */
   public static function getItemUsers($itemtype, $itemId, $userType) {
      global $DB;
      $dbu = new DbUtils;
      $users = [ ];

      //$itemtable = $dbu->getTableForItemType($itemtype) ;
      $item = new $itemtype();
      $item_users = $item->userlinkclass;
      $item_userstable = $dbu->getTableForItemType($item_users);
      $itemlink = getForeignKeyFieldForItemType($itemtype);
      $res = $DB->request([
                     'SELECT'    => ['glpi_plugin_processmaker_users.pm_users_id as pm_users_id', 'glpi_plugin_processmaker_users.id as id'],
                     'FROM'      => $item_userstable,
                     'LEFT JOIN' => ['glpi_plugin_processmaker_users' => ['FKEY' => ['glpi_plugin_processmaker_users' => 'id', $item_userstable => 'users_id']]],
                     'WHERE'     => ['AND' => ["$item_userstable.$itemlink" => $itemId, "$item_userstable.type" => $userType]],
                     'ORDER'     => ['ORDER' => $item_userstable.'.id']
                  ]);
        //$query = "select glpi_plugin_processmaker_users.pm_users_id as pm_users_id, glpi_plugin_processmaker_users.id as id from $item_userstable
        //    left join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.id = $item_userstable.users_id
        //    where $item_userstable.$itemlink = $itemId and $item_userstable.type = $userType
        //        order by $item_userstable.id";
      //foreach ($DB->request($query) as $dbuser) {
      foreach ($res as $dbuser) {
         $users[] = [ 'glpi_id' => $dbuser['id'], 'pm_id' => $dbuser['pm_users_id'] ];
      }

        return $users;
   }

    /**
     * Summary of saveForm
     * This function posts dynaform variables to PM, using the CURL module.
     * @param array $request: is the $_REQUEST server array
     * //@param string $cookie: is the $_SERVER['HTTP_COOKIE'] string
     * @return mixed: returns false if request failed, otherwise, returns true
     */
   public function saveForm($request) {

      $logtrace = false; //isset($_SESSION['glpi_use_mode']) && ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE);
      $request = stripcslashes_deep($request);

      $ch = curl_init();

      //to be able to trace network traffic with a local proxy
      //      curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
      //      curl_setopt($ch, CURLOPT_PROXY, "localhost:8888");

      //curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
      //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($logtrace) {
         curl_setopt($ch, CURLOPT_VERBOSE, 1);
         $curl_log = fopen(GLPI_LOG_DIR . '/curl.log', 'a');
         Toolbox::logInFile("pmtrace", "curl log file open: ".$curl_log);
         fprintf($curl_log, "Test\n");
         fflush($curl_log);
         curl_setopt($ch, CURLOPT_STDERR, $curl_log);
      }

      curl_setopt($ch, CURLOPT_HTTPHEADER, ["Expect:"]);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config['ssl_verify']);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, ($this->config['ssl_verify'] > 0 ? 2 : 0));

      //curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");
      // to store cookies in memory
      // ( replace HandleHeaderLine function)
      curl_setopt($ch, CURLOPT_COOKIEFILE, "");

      // ### first call to open case and get cookies GET  ###
      curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_Open?sid=".$this->getPMSessionID()."&APP_UID=".$request['APP_UID']."&DEL_INDEX=".$request['DEL_INDEX']."&action=TO_DO&glpi_init_case=1");
      if ($logtrace) {
         Toolbox::logInFile("pmtrace", "Before curl_exec: ".$this->serverURL."/cases/cases_Open?sid=".$this->getPMSessionID()."&APP_UID=".$request['APP_UID']."&DEL_INDEX=".$request['DEL_INDEX']."&action=TO_DO&glpi_init_case=1");
      }
      $response = curl_exec($ch);
      if ($logtrace) {
         Toolbox::logInFile("pmtrace", "URL:\n".$this->serverURL."/cases/cases_Open?sid=".$this->getPMSessionID()."\nResponse:\n".$response."\n\n\n");
      }

      // change option to indicate that the next call will be POST
      curl_setopt($ch, CURLOPT_POST, 1);
      // get and format post values
      $data = http_formdata_flat_hierarchy($request);
      // check if any files are in the $_FILES global array
      // and add them to the curl POST
      $fileForm = isset($_FILES['form']['name']) ? $_FILES['form']['name'] : null;
      if (isset($fileForm) && !empty($fileForm[array_keys($fileForm)[0]][1][array_keys($fileForm[array_keys($fileForm)[0]][1])[0]])) {
         foreach ($_FILES['form']['name'] as $key => $file) {
            if (is_array($file)) {
               // it's a grid which contains documents
               foreach ($file as $row => $col) {
                  foreach ($col as $control => $filename) {
                     $cfile = new CURLFile($_FILES['form']['tmp_name'][$key][$row][$control], $_FILES['form']['type'][$key][$row][$control], $_FILES['form']['name'][$key][$row][$control]);
                     $data["form[$key][$row][$control]"] = $cfile;
                  }
               }
            } else {
               $cfile = new CURLFile($_FILES['form']['tmp_name'][$key], $_FILES['form']['type'][$key], $_FILES['form']['name'][$key]);
               $data["form[$key]"] = $cfile;
            }
         }
      }
      // to get all cookies in one variable
      //$cookies = curl_getinfo($ch, CURLINFO_COOKIELIST);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ["Expect:"]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // inject POST values
      // add agent and referer params
      //$agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
      //$referer = $this->getSiteURL();
      //curl_setopt($ch, CURLOPT_USERAGENT, $agent);
      //curl_setopt($ch, CURLOPT_REFERER, $referer);

      // ###  2nd call to save Data POST ###
      curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID']);
      $response = curl_exec ($ch);
      if ($response === false) {
         //throw new Exception(curl_error($ch), curl_errno($ch));
         Toolbox::logInFile("pmtrace", curl_error($ch).":".curl_errno($ch)."\n");
      }
      curl_close ($ch);
      if ($logtrace) {
         Toolbox::logInFile("pmtrace", "URL:\n".$this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID']."\nData:\n".print_r($data, true)."\nResponse:\n".$response."\n\n\n");
         fclose($curl_log);
      }

      return ($response ? true : false);
   }

   /**
    * Function to get current site url with protocol ( http or https )
    * @return string
    */
   function getSiteURL() {
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
      $domainName = $_SERVER['HTTP_HOST'];
      return $protocol.$domainName;
   }

   /**
    * Summary of initCaseAndShowTab
    * Is used to workaround a SESSION issue in PM server
    * PM server stores case context in SESSION variables,
    * which leads to issues when viewing two different cases
    * in two different tabs of the same browser.
    * This workaround will artificially load cases_Open page to force
    * initialization of those SESSION variables to prevent mix of values
    * when viewing tabs like map, change log, history, and dynaforms
    *
    * @param mixed $currentCase array that contains APP_UID, DEL_INDEX
    * @param mixed $iFrameUrl string which is the url of the tab panel
    * @param mixed $rand integer
    */
   public function initCaseAndShowTab($currentCase, $iFrameUrl, $rand) {
      $iFrameUrl = urlencode($iFrameUrl);

      echo "<div id='openCase-$rand'></div>";

      // will use ajax to be sure that cases_Open page is fully loaded before load of the $iFrameUrl
      // this mechanism is mandatory to have correct management of cookies, as cookies transport the session id,
      // and such the SESSION variables that contain the case context
      echo "<script type='text/javascript'>
               (function () {
                  function urldecode(url) {
                     return decodeURIComponent(url.replace(/\+/g, ' '));
                  }
                  $.ajax({ url: '".$this->serverURL."/cases/cases_Open?sid=".$this->getPMSessionID()."&APP_UID={$currentCase['APP_UID']}&DEL_INDEX={$currentCase['DEL_INDEX']}&action=TO_DO&glpi_init_case=1',
                              xhrFields: { withCredentials: true },
                              success: function (jqXHR) {
                                 //debugger;
                                 var str = urldecode('$iFrameUrl');
                                 $('#openCase-$rand').after(str);
                              },
                              error: function (jqXHR) {
                                 //  debugger;
                              },
                              cache: false,
                              crossDomain: true
                         }
                        );
               }) ();
            </script>";

   }

    /**
     * Summary of plugin_item_get_data_processmaker
     * @param mixed $item
     */
   public static function plugin_item_get_data_processmaker($item) {
      global $_SESSION, $CFG_GLPI;
      if (isset($item->data) && isset($item->data['tasks'])) {
         $pmtask_itemtype = $item->obj->getType().'Task';
         $pmtask = new PluginProcessmakerTask($pmtask_itemtype);
         foreach ($item->data['tasks'] as &$task) {
            $pmtask_items_id = $task['##task.id##'];

            // for each task, we must check if it is in our task table
            // and if yes, then process the content
            $restrict=[
                                   'WHERE'  => [
                                   'itemtype'  => $pmtask_itemtype,
                                   'items_id'  => $pmtask_items_id
                                   ],
                         ];
            //if ($pmtask->getFromDBByQuery("WHERE itemtype = '$pmtask_itemtype' AND items_id = $pmtask_items_id")) {
            if ($pmtask->getFromDBByRequest($restrict)) {

               //if (!in_array("tasks", $item->tag_descriptions)) {
               //   $item->html_tags[] = "tasks"; // to force GLPI to keep the below HTML tags, otherwise it will apply a Html::entities_deep() to the task.description
               //}

               $task['##task.description##'] = str_replace('##processmaker.taskcomment##', $task['##task.categorycomment##'], $task['##task.description##']);
               $task['##task.description##'] = nl2br($task['##task.description##']);

               //$restrict=[
               //                       'WHERE'  => [
               //                       'itemtype'  => $pmtask_itemtype,
               //                       'items_id'  => $pmtask_items_id
               //                       ],
               //             ];
               //$pmtask->getFromDBByRequest($restrict);
               //$caseurl = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=PluginProcessmakerCase_".$pmtask->fields['plugin_processmaker_cases_id']);
               $caseurl = $CFG_GLPI["url_base"]."/index.php?redirect=".urlencode(Plugin::getWebDir('processmaker')."/front/case.form.php?id=".$pmtask->fields['plugin_processmaker_cases_id']);

               $caseurl = "<a href='$caseurl'>$caseurl</a>";
               $task['##task.description##'] = str_replace('##processmakercase.url##', $caseurl, $task['##task.description##']);
            }
         }
      }

   }


      /**
       * Summary of plugin_item_get_pdfdata_processmaker
       * @param mixed $item
       */
   public static function plugin_item_get_pdfdata_processmaker($item) {
      global $PM_SOAP;

      if (isset($item->datas)) {
         $taskCat = new TaskCategory;
         $dbu = new DbUtils;
         // save current translations
         if (isset($_SESSION['glpi_dropdowntranslations'])) {
            $trans = $_SESSION['glpi_dropdowntranslations'];
         }
         // load available translations for this user
         $_SESSION['glpi_dropdowntranslations'] = DropdownTranslation::getAvailableTranslations($_SESSION['glpilanguage']);

         $taskCat->getFromDB($item->datas['taskcategories_id']);
         $ancestors = $dbu->getAncestorsOf('glpi_taskcategories', $item->datas['taskcategories_id']);
         if (in_array($PM_SOAP->config['taskcategories_id'], $ancestors)) {
            $loc_completename = DropdownTranslation::getTranslatedValue($taskCat->getID(), 'TaskCategory', 'completename', $_SESSION['glpilanguage'], $taskCat->fields['completename']);
            $loc_comment = DropdownTranslation::getTranslatedValue($taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskCat->fields['comment']);
            $item->datas['content'] = $loc_completename."\n\n".str_replace("##processmaker.taskcomment##", $loc_comment, $item->datas['content']);
            $item->datas['content'] = str_replace("##processmakercase.url##", '', $item->datas['content']);
         }
         // restore default translations
         if (isset($trans)) {
            $_SESSION['glpi_dropdowntranslations'] = $trans;
         } else {
            unset($_SESSION['glpi_dropdowntranslations']);
         }
      }

   }


      /**
     * Summary of getProcessesWithCategoryAndProfile
     * @param mixed $category
     * @param mixed $type
     * @param mixed $profile
     * @param mixed $entity
     */
   public static function getProcessesWithCategoryAndProfile($category, $type, $profile, $entity) {
      global $DB;
      $dbu = new DbUtils;
      $processList = [ ];
      $entityAncestors = $dbu->getAncestorsOf($dbu->getTableForItemType('Entity'), $entity);

      $entities = ['entities_id' => $entity];
      if (count($entityAncestors) > 0) {
         $entities = [
            'OR' => [
               'entities_id' => $entity,
               'AND' => [
                  'entities_id' => $entityAncestors,
                  'is_recursive' => 1
                  ]
               ]
            ];
      }

      if ($category > 0) {
         $query = [
                  'FIELDS'       => ['glpi_plugin_processmaker_processes.id', 'glpi_plugin_processmaker_processes.name'],
                  'DISTINCT'     => true,
                  'FROM'         => 'glpi_plugin_processmaker_processes',
                  'INNER JOIN'   => [
                     'glpi_plugin_processmaker_processes_profiles' => [
                        'FKEY' => [
                           'glpi_plugin_processmaker_processes_profiles' => 'plugin_processmaker_processes_id',
                           'glpi_plugin_processmaker_processes'          => 'id',
                           ['AND' => [
                              'glpi_plugin_processmaker_processes.itilcategories_id' => ['!=', 0]
                              ]
                           ]
                        ]
                     ]
                  ],
                  'WHERE'        => [
                     'AND' => [
                        'is_active'         => 1,
                        'itilcategories_id' => $category,
                        'type'              => $type,
                        'profiles_id'       => $profile,
                        $entities
                     ]
                  ]
            ];

         $res = $DB->request($query);
         foreach ($res as $row) {
            $processList[] = $row;
         }
         $processList = array_map("unserialize", array_unique(array_map("serialize", $processList)));
      }

      return $processList;

   }

   /**
    * Summary of startNewCase
    * @param mixed $processes_id integer: GLPI process id
    * @param mixed $itemtype string: item type 'Ticket', 'Change' or 'Problem'
    * @param mixed $items_id integer: id to thte item
    * @param mixed $users_id integer: GLPI user id
    * @return mixed
    */
   public function startNewCase($processes_id, $itemtype, $items_id, $users_id = null) {
      global $DB, $CFG_GLPI;

      $requesters = PluginProcessmakerProcessmaker::getItemUsers($itemtype, $items_id, CommonITILActor::REQUESTER); // 1 for requesters
      if (!key_exists(0, $requesters)) {
         $requesters[0]['glpi_id'] = 0;
         $requesters[0]['pm_id'] = 0;
      }

      // get item info to retreive title, description and duedate
      $item = new $itemtype;
      $item->getFromDB($items_id);

      //if ($item->countUsers(CommonITILActor::ASSIGN) == 0
      //   || !$item->isUser(CommonITILActor::ASSIGN, $users_id)) {
      //   $item->update([ 'id' => $items_id, '_itil_assign' => [ '_type' => 'user', 'users_id' => $users_id ]  ]);
      //}

      if (!isset($item->fields['time_to_resolve']) || $item->fields['time_to_resolve'] == null) {
         $item->fields['time_to_resolve'] = "";
      }

      $resultCase = $this->newCase($processes_id,
                                    ['GLPI_ITEM_CAN_BE_SOLVED'        => 0,
                                     'GLPI_TICKET_ID'                 => $items_id,
                                     'GLPI_ITEM_ID'                   => $items_id,
                                     'GLPI_ITEM_TYPE'                 => $itemtype,
                                     'GLPI_ITEM_STATUS'               => $item->fields['status'],
                                     'GLPI_TICKET_REQUESTER_GLPI_ID'  => $requesters[0]['glpi_id'],
                                     'GLPI_ITEM_REQUESTER_GLPI_ID'    => $requesters[0]['glpi_id'],
                                     'GLPI_TICKET_REQUESTER_PM_ID'    => $requesters[0]['pm_id'],
                                     'GLPI_ITEM_REQUESTER_PM_ID'      => $requesters[0]['pm_id'],
                                     'GLPI_TICKET_TITLE'              => $item->fields['name'],
                                     'GLPI_ITEM_TITLE'                => $item->fields['name'],
                                     'GLPI_TICKET_DESCRIPTION'        => $item->fields['content'],
                                     'GLPI_ITEM_DESCRIPTION'          => $item->fields['content'],
                                     'GLPI_ITEM_OPENING_DATE'         => $item->fields['date'],
                                     'GLPI_TICKET_DUE_DATE'           => $item->fields['time_to_resolve'],
                                     'GLPI_ITEM_DUE_DATE'             => $item->fields['time_to_resolve'],
                                     'GLPI_ITEM_ITIL_CATEGORY_ID'     => $item->fields['itilcategories_id'],

                                     'GLPI_TICKET_URGENCY'            => $item->fields['urgency'],
                                     'GLPI_ITEM_URGENCY'              => $item->fields['urgency'],
                                     'GLPI_ITEM_IMPACT'               => $item->fields['impact'],
                                     'GLPI_ITEM_PRIORITY'             => $item->fields['priority'],
                                     // Specific to Tickets and Changes
                                     // GLPI_ITEM_GLOBAL_VALIDATION will be '' when Problem, else it will be the global_validation field
                                     'GLPI_TICKET_GLOBAL_VALIDATION'  => $itemtype == 'Problem' ? '' : $item->fields['global_validation'],
                                     'GLPI_ITEM_GLOBAL_VALIDATION'    => $itemtype == 'Problem' ? '' : $item->fields['global_validation'],
                                     'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $users_id,
                                     'GLPI_ITEM_TECHNICIAN_GLPI_ID'   => $users_id,
                                     'GLPI_TICKET_TECHNICIAN_PM_ID'   => PluginProcessmakerUser::getPMUserId($users_id),
                                     'GLPI_ITEM_TECHNICIAN_PM_ID'     => PluginProcessmakerUser::getPMUserId($users_id),
                                     'GLPI_URL'                       => $CFG_GLPI['url_base'],
                                     // Specific to Tickets
                                     // GLPI_TICKET_TYPE will contains 1 (= incident) or 2 (= request), or '' if itemtype is not Ticket
                                     'GLPI_TICKET_TYPE'               => $itemtype == 'Ticket' ? $item->fields['type'] : '',
                                     'GLPI_TICKET_REQUESTTYPES_ID'    => $itemtype == 'Ticket' ? $item->fields['requesttypes_id'] : ''
                                    ]);

      if ($resultCase->status_code === 0) {
         $caseInfo = $this->getCaseInfo($resultCase->caseId);

         // save info to DB
         $locCase = new PluginProcessmakerCase;
         $locCase->add(['id'                               => $resultCase->caseNumber,
                        'itemtype'                         => $itemtype,
                        'items_id'                         => $items_id,
                        'case_guid'                        => $resultCase->caseId,
                        'case_status'                      => $caseInfo->caseStatus,
                        'name'                             => $caseInfo->caseName,
                        'entities_id'                      => $item->fields['entities_id'],
                        'plugin_processmaker_processes_id' => $processes_id,
                        'plugin_processmaker_cases_id'     => 0
                       ],
                      [], true);

         if ($caseInfo->currentUsers[0]->userId == "") { // in case the task is "to be claimed"
             $users_id = 0;
         }
         $this->add1stTask($locCase->getID(), $itemtype, $items_id, $caseInfo, ['userId' => $users_id]);
      }

      return $resultCase;
   }




   /**
    * Summary of derivateCase
    * in $request must be present
    * 'UID', 'APP_UID' and DEL_INDEX'
    *
    * @param mixed $myCase
    * @param mixed $request
    * @param mixed $users_id
   */
   public function derivateCase($myCase, $request, $users_id = null) {
      global $PM_DB, $CFG_GLPI, $DB;

      $logtrace = false; //isset($_SESSION['glpi_use_mode']) && ($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE);


      $itemtype = $myCase->getField('itemtype');
      $items_id = $myCase->getField('items_id');
      $item = new $itemtype;
      $item->getFromDB($items_id);

      if (!isset($users_id)) {
         $users_id = Session::getLoginUserID();
      }

      if ($logtrace) {
         Toolbox::logInFile("pmtrace", Toolbox::backtrace(false) . "derivateCase: myCase = " . print_r($myCase, true) . "\nrequest = " . print_r($request, true) . "\nusers_id = " . print_r($users_id, true));
      }

      // dynaform has been saved by pm itself
      // save the dynaform variables into the current case
      if (isset($request['UID']) && isset($request['APP_UID']) && isset($request['__DynaformName__'])) {
            $resultSave = $this->saveForm($request);
      }

      // now derivate the case !!!
      $pmRouteCaseResponse = $this->routeCase($myCase->fields['case_guid'], $request['DEL_INDEX']);
      if ($logtrace) {
         Toolbox::logInFile("pmtrace", "routeCase: pmRouteCaseResponse = " . print_r($pmRouteCaseResponse, true));
      }

      // will list input and output documents in order to add them as URL in item and documents
      $myCase->addDocuments(
         $this->inputDocumentList(
            $this->getPMSessionID(),
            $myCase->fields['case_guid']),
         $item,
         $users_id,
         false);

      $myCase->addDocuments(
         $this->outputDocumentList(
            $this->getPMSessionID(),
            $myCase->fields['case_guid']),
         $item,
         $users_id,
         true);


      if ($pmRouteCaseResponse->status_code != 0) {
         Session::addMessageAfterRedirect(sprintf(__('Unable to derivate case! Retry in a moment, or ask your administrator.<hr>Error code: %u<br/>Error message: %s<br/>', 'processmaker'),
                                                  $pmRouteCaseResponse->status_code,
                                                  $pmRouteCaseResponse->message),
                                          true,
                                          ERROR);
         return;
      }

      // list of case variables that will be requested from current PM case
      $casevariables = [
         "GLPI_ITEM_TASK_CONTENT",
         "GLPI_ITEM_APPEND_TO_TASK",
         //"GLPI_NEXT_GROUP_TO_BE_ASSIGNED",
         //"GLPI_ITEM_TASK_GROUP",
         "GLPI_ITEM_TITLE",
         "GLPI_TICKET_FOLLOWUP_CONTENT",
         "GLPI_ITEM_FOLLOWUP_CONTENT",
         "GLPI_TICKET_FOLLOWUP_IS_PRIVATE",
         "GLPI_ITEM_FOLLOWUP_IS_PRIVATE",
         "GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID",
         "GLPI_ITEM_FOLLOWUP_REQUESTTYPES_ID",
         "GLPI_ITEM_TASK_ENDDATE",
         "GLPI_ITEM_TASK_STARTDATE",
         "GLPI_ITEM_TASK_REMINDER",
         "GLPI_ITEM_SOLVED_TASK_ENDDATE",
         "GLPI_ITEM_SOLVED_TASK_STARTDATE",
         "GLPI_ITEM_SOLVED_TASK_SETINFO",
         "GLPI_ITEM_SET_STATUS",
         "GLPI_ITEM_STATUS",
         "GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID",
         "GLPI_ITEM_SET_SOLUTION_TYPE_ID",
         "GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION",
         "GLPI_ITEM_INITIAL_DUE_DATE",
         "GLPI_ITEM_DUE_DATE",
         "GLPI_SEND_EMAIL",
         "GLPI_ITEM_INFORMATION_TASK",
         "GLPI_TICKET_GLOBAL_VALIDATION",
         "GLPI_ITEM_GLOBAL_VALIDATION",
         "GLPI_TICKET_REQUESTTYPES_ID"
        ];

      // list of case variables that will not be reset by plugin
      $casevariablesnottoreset = [
         'GLPI_TICKET_GLOBAL_VALIDATION',
         'GLPI_ITEM_GLOBAL_VALIDATION'
         ];

      // now tries to get some variables to setup content for new task and to append text to solved task
      $casevariablevalues = $myCase->getVariables($casevariables);

      $information_task = '';
      if (array_key_exists('GLPI_ITEM_INFORMATION_TASK', $casevariablevalues) && $casevariablevalues['GLPI_ITEM_INFORMATION_TASK'] != '') {
         $information_task = $casevariablevalues['GLPI_ITEM_INFORMATION_TASK'];
      }

      $sendemail = '';
      if (array_key_exists('GLPI_SEND_EMAIL', $casevariablevalues) && $casevariablevalues['GLPI_SEND_EMAIL'] != '') {
         $sendemail = json_decode($casevariablevalues['GLPI_SEND_EMAIL'], true);
      }

      $itemSetStatus = '';
      if (array_key_exists('GLPI_ITEM_SET_STATUS', $casevariablevalues)) {
         $itemSetStatus = $casevariablevalues['GLPI_ITEM_SET_STATUS'];
      }
      if (array_key_exists('GLPI_ITEM_STATUS', $casevariablevalues)) {
         $itemSetStatus = $casevariablevalues['GLPI_ITEM_STATUS'];
      }

      $itemglobalvalidation = '';
      if (array_key_exists('GLPI_TICKET_GLOBAL_VALIDATION', $casevariablevalues)) {
         $itemglobalvalidation = $casevariablevalues['GLPI_TICKET_GLOBAL_VALIDATION'];
      }
      if (array_key_exists('GLPI_ITEM_GLOBAL_VALIDATION', $casevariablevalues)) {
         $itemglobalvalidation = $casevariablevalues['GLPI_ITEM_GLOBAL_VALIDATION'];
      }

      $txtItemTitle  = '';
      if (array_key_exists('GLPI_ITEM_TITLE', $casevariablevalues)) {
         $txtItemTitle = $casevariablevalues['GLPI_ITEM_TITLE'];
      }

      $txtToAppendToTask  = '';
      if (array_key_exists('GLPI_ITEM_APPEND_TO_TASK', $casevariablevalues)) {
         $txtToAppendToTask = $casevariablevalues['GLPI_ITEM_APPEND_TO_TASK'];
      }

      $txtTaskContent = '';
      if (array_key_exists('GLPI_ITEM_TASK_CONTENT', $casevariablevalues)) {
         $txtTaskContent = $casevariablevalues['GLPI_ITEM_TASK_CONTENT'];
      }

      $txtRequesttypes_id = '';
      if (array_key_exists('GLPI_TICKET_REQUESTTYPES_ID', $casevariablevalues)) {
         $txtRequesttypes_id = $casevariablevalues['GLPI_TICKET_REQUESTTYPES_ID'];
      }

      //$groupId = 0;
      //if (array_key_exists('GLPI_NEXT_GROUP_TO_BE_ASSIGNED', $casevariablevalues)) {
      //   $groupId = $casevariablevalues['GLPI_NEXT_GROUP_TO_BE_ASSIGNED'];
      //}
      //if (array_key_exists('GLPI_ITEM_TASK_GROUP', $casevariablevalues)) {
      //   $groupId = $casevariablevalues['GLPI_ITEM_TASK_GROUP'];
      //}

      $taskStartDate = '';
      $taskEndDate = '';
      if (array_key_exists('GLPI_ITEM_TASK_ENDDATE', $casevariablevalues)) {
         $taskEndDate = $casevariablevalues['GLPI_ITEM_TASK_ENDDATE'];
      }
      if (array_key_exists('GLPI_ITEM_TASK_STARTDATE', $casevariablevalues)) {
         $taskStartDate = $casevariablevalues['GLPI_ITEM_TASK_STARTDATE'];
         if ($taskEndDate == '') {
            // at least
            $taskEndDate = $taskStartDate;
         }
      }

      $taskReminder = '';
      if (array_key_exists('GLPI_ITEM_TASK_REMINDER', $casevariablevalues)) {
         $taskReminder = $casevariablevalues['GLPI_ITEM_TASK_REMINDER'];
      }

      $solvedTaskStartDate = '';
      $solvedTaskEndDate = '';
      if (array_key_exists('GLPI_ITEM_SOLVED_TASK_ENDDATE', $casevariablevalues)) {
         $solvedTaskEndDate = $casevariablevalues['GLPI_ITEM_SOLVED_TASK_ENDDATE'];
      }
      if (array_key_exists('GLPI_ITEM_SOLVED_TASK_STARTDATE', $casevariablevalues)) {
         $solvedTaskStartDate = $casevariablevalues['GLPI_ITEM_SOLVED_TASK_STARTDATE'];
         if ($solvedTaskEndDate == '') {
            // at least
            $solvedTaskEndDate = $solvedTaskStartDate;
         }
      }

      $solvedTaskSetToInformation = '';
      if (array_key_exists('GLPI_ITEM_SOLVED_TASK_SETINFO', $casevariablevalues)) {
         $solvedTaskSetToInformation = $casevariablevalues['GLPI_ITEM_SOLVED_TASK_SETINFO'];
      }

      $createFollowup = false; // by default
      if ((array_key_exists('GLPI_TICKET_FOLLOWUP_CONTENT', $casevariablevalues) && $casevariablevalues['GLPI_TICKET_FOLLOWUP_CONTENT'] != '')
       || (array_key_exists('GLPI_ITEM_FOLLOWUP_CONTENT', $casevariablevalues) && $casevariablevalues['GLPI_ITEM_FOLLOWUP_CONTENT'] != '')) {
         $createFollowup = true;
      }

      $item_duedate = '';
      if (array_key_exists('GLPI_ITEM_INITIAL_DUE_DATE', $casevariablevalues)) {
         $item_duedate = $casevariablevalues['GLPI_ITEM_INITIAL_DUE_DATE'];
      }
      if (array_key_exists('GLPI_ITEM_DUE_DATE', $casevariablevalues)) {
         $item_duedate = $casevariablevalues['GLPI_ITEM_DUE_DATE'];
      }
      $re = '/^(?\'date\'[0-9]{4}-[0-1][0-9]-[0-3][0-9])( (?\'time\'[0-2][0-9]:[0-5][0-9]:[0-5][0-9]))*$/';
      if (preg_match($re, $item_duedate, $matches) && !array_key_exists('time', $matches)) {
         $item_duedate .= " 23:59:59";
      }

      // reset those variables
      $resetcasevariables = [];
      foreach ($casevariables as $val) {
         if (!empty($casevariablevalues[$val]) && !in_array($val, $casevariablesnottoreset)) {
            $resetcasevariables[$val] = '';
         }
      }
      $resultSave = $myCase->sendVariables($resetcasevariables);

      // now manage tasks associated with item
      // switch own task to 'done' and create a new one
      $glpi_task = $this->solveTask($myCase->getID(),
                                    $request['DEL_INDEX'],
                                    ['txtToAppend'   => $txtToAppendToTask,
                                     'users_id_tech' => $users_id,
                                     'begin'         => $solvedTaskStartDate,
                                     'end'           => $solvedTaskEndDate,
                                     'toInformation' => $solvedTaskSetToInformation
                                    ]
                                   );

      // create a followup if requested
      if ($createFollowup) { // && $itemtype == 'Ticket') {
         $this->addItemFollowup($itemtype, $items_id, $casevariablevalues, $myCase->getID(), $glpi_task->getID());
      }

      if ($txtItemTitle != '') {
         // we are going to change the title of current GLPI Item
         $this->setItemTitle($itemtype, $items_id, $txtItemTitle);
      }

      if ($item_duedate != '') {
         // we are going to change the due date (time to resolve) of current GLPI Item
         $this->setItemDuedate($itemtype, $items_id, $item_duedate);
      }

      //if (array_key_exists('GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID', $casevariablevalues)
      //    || array_key_exists('GLPI_ITEM_SET_SOLUTION_TYPE_ID', $casevariablevalues)
      //    || array_key_exists('GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION', $casevariablevalues)) {
      //   // case requests to add a solution to ticket
      //   $this->setItemSolution($itemtype, $items_id, $casevariablevalues);
      //}

      //if ($itemSetStatus != '') {
      //   $this->setItemStatus($itemtype, $items_id, $itemSetStatus);
      //}

      if ($itemglobalvalidation != '') {
         $this->setItemGlobalValidation($itemtype, $items_id, $itemglobalvalidation);
      }

      if ($txtRequesttypes_id != '') {
         $this->setTicketRequestType($itemtype, $items_id, $txtRequesttypes_id);
      }

      // get the new case info
      $caseInfo = $myCase->getCaseInfo($request['DEL_INDEX']); // not sure that it should passed this

      // create an information task if requested
      if ($information_task != '' && $glpi_task) {
         // create an information task and add comment
         // $information_task is the content of the task
         $pm_process = $myCase->getProcess();
         $taskCat = new TaskCategory;
         $taskCat->getFromDB($glpi_task->fields['taskcategories_id']);
         // we may replace ##casename## by the name of the case, and ##taskname## by the task name
         $search = ['##casename##',
                    '##taskname##'
                   ];
         $replace = [$caseInfo->caseName." (".$myCase->getID().")",
                     DropdownTranslation::getTranslatedValue($glpi_task->fields['taskcategories_id'], 'TaskCategory', 'name', $_SESSION['glpilanguage'], $taskCat->fields['name'])
                    ];
         $info = str_replace($search, $replace, $information_task);

         $info .= "<input name='caseid' type='hidden' value='".$myCase->getID()."'><input name='taskid' type='hidden' value='".$glpi_task->getID()."'>";

         // unescape some chars and replace CRLF, CR or LF by <br/>
         $info = str_replace(["\\'", '\\"', '\r\n', '\r', '\n'], ["'", '"', '<br>', '<br>', '<br>'], $info);

         $foreignkey = getForeignKeyFieldForItemType($glpi_task->getItilObjectItemType());
         $glpi_task->add([
            $foreignkey => $glpi_task->fields[$foreignkey],
            'is_private' => 1,
            'taskcategories_id' => $pm_process->fields['taskcategories_id'],
            'content' => $DB->escape($info),
            'users_id' => $this->taskWriter,
            'state' => Planning::INFO,
            'users_id_tech' => Session::getLoginUserID(),
         ]);

      }

      // now create the new tasks if any
      if (property_exists($pmRouteCaseResponse, 'routing')) {

         // we may have several task to create
         foreach ($pmRouteCaseResponse->routing as $route) {

            // must check if task has started a sub-process
            $locTaskCat = new PluginProcessmakerTaskCategory;
            if ($locTaskCat->getFromGUID($route->taskId) && $locTaskCat->fields['is_subprocess']) {
               // look for APP_UID
               $res = $PM_DB->request([
                                 'SELECT' => [
                                    'APPUID'
                                 ],
                                 'FROM' => 'SUB_APPLICATION',
                                 'WHERE' => [
                                    'AND' => [
                                       'APP_PARENT'       => $myCase->fields['case_guid'],
                                       'DEL_INDEX_PARENT' => $route->delIndex,
                                       'SA_STATUS'        => 'ACTIVE'
                                    ]
                                 ]
                              ]);
               //$res = $PM_DB->query("SELECT APP_UID FROM SUB_APPLICATION WHERE APP_PARENT='{$myCase->fields['case_guid']}' AND DEL_INDEX_PARENT={$route->delIndex} AND SA_STATUS='ACTIVE'"); // AND DEL_THREAD_PARENT={$route->delThread} seems like it is not set to correct threadIndex
               //if ($row = $res->next() && $PM_DB->numrows($res) == 1) {
               if ($res->numrows() == 1 && $row = $res->current()) {
                  // then new task is a sub-process,
                  //$row = $PM_DB->fetch_assoc($res);

                  // now need to get the PRO_UID
                  $sub_caseInfo = self::getCaseInfo($row['APP_UID']);

                  $locProc = new PluginProcessmakerProcess;
                  $locProc->getFromGUID($sub_caseInfo->processId);
                  $subCase = new PluginProcessmakerCase;
                  $subCase->add(['id'       => $sub_caseInfo->caseNumber,
                                 'case_guid'=> $sub_caseInfo->caseId,
                                 'itemtype' => $itemtype,
                                 'items_id' => $items_id,
                                 'name' => $sub_caseInfo->caseName,
                                 'entities_id' => $item->fields['entities_id'],
                                 'case_status' => $sub_caseInfo->caseStatus,
                                 'plugin_processmaker_processes_id' => $locProc->getID(),
                                 'plugin_processmaker_cases_id' => $myCase->getID()]);

                  // then create associated task
                  if (property_exists($sub_caseInfo, 'currentUsers')) {
                     // there should be only one task
                     $sub_route = $sub_caseInfo->currentUsers[0];
                     $this->addTask($subCase->getID(), $itemtype,
                                                $items_id,
                                                $sub_caseInfo,
                                                $sub_route->delIndex,
                                                PluginProcessmakerUser::getGLPIUserId($sub_route->userId),
                                                $sub_route->taskId,
                                                $sub_route->delThread,
                                                [ 'txtTaskContent' => $txtTaskContent,
                                                  'start_date'     => $taskStartDate,
                                                  'end_date'       => $taskEndDate,
                                                  'reminder'       => $taskReminder]
                                             );

                     // if end date was specified, then must change due date of the PM task
                     if ($taskEndDate != '') {
                        $PM_DB->update(
                                 'APP_DELEGATION',
                                 [
                                 'DEL_TASK_DUE_DATE' => $taskEndDate
                                 ],
                                 ['AND' => [
                                    'APP_UID'   => $sub_caseInfo->caseId,
                                    'DEL_INDEX' => $sub_route->delIndex
                                    ]
                                 ]
                              );
                        //$PM_DB->query("UPDATE APP_DELEGATION SET DEL_TASK_DUE_DATE='$taskEndDate' WHERE APP_UID='".$sub_caseInfo->caseId."' AND DEL_INDEX=".$sub_route->delIndex);
                     }

                  }

                  // must also send to new sub-process some GLPI variables
                  // like any newly started cases
                  // get the value of GLPI_ITEM_CAN_BE_SOLVED to transmit it to sub-case
                  $requesters = PluginProcessmakerProcessmaker::getItemUsers($itemtype, $items_id, CommonITILActor::REQUESTER); // 1 for requesters
                  if (!key_exists(0, $requesters)) {
                     $requesters[0]['glpi_id'] = 0;
                     $requesters[0]['pm_id'] = 0;
                  }

                  $glpi_variables =  ['GLPI_ITEM_CAN_BE_SOLVED'        => 0,
                                      'GLPI_TICKET_ID'                 => $items_id,
                                      'GLPI_ITEM_ID'                   => $items_id,
                                      'GLPI_ITEM_TYPE'                 => $itemtype,
                                      'GLPI_ITEM_STATUS'               => $item->fields['status'],
                                      'GLPI_TICKET_REQUESTER_GLPI_ID'  => $requesters[0]['glpi_id'],
                                      'GLPI_ITEM_REQUESTER_GLPI_ID'    => $requesters[0]['glpi_id'],
                                      'GLPI_TICKET_REQUESTER_PM_ID'    => $requesters[0]['pm_id'],
                                      'GLPI_ITEM_REQUESTER_PM_ID'      => $requesters[0]['pm_id'],
                                      'GLPI_TICKET_TITLE'              => $item->fields['name'],
                                      'GLPI_ITEM_TITLE'                => $item->fields['name'],
                                      'GLPI_TICKET_DESCRIPTION'        => $item->fields['content'],
                                      'GLPI_ITEM_DESCRIPTION'          => $item->fields['content'],
                                      'GLPI_ITEM_OPENING_DATE'         => $item->fields['date'],
                                      'GLPI_TICKET_DUE_DATE'           => $item->fields['time_to_resolve'],
                                      'GLPI_ITEM_DUE_DATE'             => $item->fields['time_to_resolve'],
                                      'GLPI_ITEM_ITIL_CATEGORY_ID'     => $item->fields['itilcategories_id'],
                                      'GLPI_TICKET_URGENCY'            => $item->fields['urgency'],
                                      'GLPI_ITEM_URGENCY'              => $item->fields['urgency'],
                                      'GLPI_ITEM_IMPACT'               => $item->fields['impact'],
                                      'GLPI_ITEM_PRIORITY'             => $item->fields['priority'],
                                      // Specific to Tickets and Changes
                                      // GLPI_ITEM_GLOBAL_VALIDATION will be '' when Problem, else it will be the global_validation field
                                      'GLPI_TICKET_GLOBAL_VALIDATION'  => $itemtype == 'Problem' ? '' : $item->fields['global_validation'],
                                      'GLPI_ITEM_GLOBAL_VALIDATION'    => $itemtype == 'Problem' ? '' : $item->fields['global_validation'],
                                      'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $users_id,
                                      'GLPI_ITEM_TECHNICIAN_GLPI_ID'   => $users_id,
                                      'GLPI_TICKET_TECHNICIAN_PM_ID'   => PluginProcessmakerUser::getPMUserId($users_id),
                                      'GLPI_ITEM_TECHNICIAN_PM_ID'     => PluginProcessmakerUser::getPMUserId($users_id),
                                      'GLPI_URL'                       => $CFG_GLPI['url_base'],
                                     // Specific to Tickets
                                     // GLPI_TICKET_TYPE will contains 1 (= incident) or 2 (= request)
                                     'GLPI_TICKET_TYPE'               => $itemtype == 'Ticket' ? $item->fields['type'] : '',
                                     'GLPI_TICKET_REQUESTTYPES_ID'    => $itemtype == 'Ticket' ? $item->fields['requesttypes_id'] : ''
                                      ];
                  $subCase->sendVariables($glpi_variables);

                  // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
                  $subCase->update([
                     'id'          => $subCase->getID(),
                     'case_status' => $sub_caseInfo->caseStatus,
                     'name'        => $sub_caseInfo->caseName,
                     'date_mod'    => $_SESSION["glpi_currenttime"]
                     ]);

               }
            } else {
               $this->addTask($myCase->getID(),
                                 $itemtype,
                                 $items_id,
                                 $caseInfo,
                                 $route->delIndex,
                                 PluginProcessmakerUser::getGLPIUserId($route->userId),
                                 $route->taskId,
                                 $route->delThread,
                                 ['txtTaskContent' => $txtTaskContent,
                                  'start_date'     => $taskStartDate,
                                  'end_date'       => $taskEndDate,
                                  'reminder'       => $taskReminder]
                             );
               // if end date was specified, then must change due date of the PM task
               if ($taskEndDate != '') {
                  $PM_DB->update(
                           'APP_DELEGATION',
                           [
                           'DEL_TASK_DUE_DATE' => $taskEndDate
                           ],
                           ['AND' => [
                              'APP_UID'   => $caseInfo->caseId,
                              'DEL_INDEX' => $route->delIndex
                              ]
                           ]);
                  //$PM_DB->query("UPDATE APP_DELEGATION SET DEL_TASK_DUE_DATE='$taskEndDate' WHERE APP_UID='".$caseInfo->caseId."' AND DEL_INDEX=".$route->delIndex);
               }
            }

         }

      } else {
         // must check if current case is a sub-process, and if it has ended, then must reflect parent case into the current item.
         if ($myCase->fields['plugin_processmaker_cases_id'] != 0) {
            // current case is a sub-case of $myCase->fields['plugin_processmaker_cases_id']
            $parentCase = new PluginProcessmakerCase;
            $parentCase->getFromDB($myCase->fields['plugin_processmaker_cases_id']);
            $parentCaseInfo = $parentCase->getCaseInfo();
            // then create associated task
            if (property_exists($parentCaseInfo, 'currentUsers')) {
               // we may have several new task
               foreach ($parentCaseInfo->currentUsers as $open_task) {
                  // must check if $open_task is not is_subprocess and is not already existing in the item
                  $locTaskCat = new PluginProcessmakerTaskCategory;
                  $locTask = new PluginProcessmakerTask($itemtype.'Task');

                  $locTaskRestrict=[
                                         'WHERE'  => [
                                         'plugin_processmaker_cases_id'  => $parentCase->getID(),
                                         'plugin_processmaker_taskcategories_id'  => $locTaskCat->getID(),
                                         'del_index' => $open_task->delIndex
                                         ],
                               ];
                  if ($locTaskCat->getFromGUID($open_task->taskId)
                  && !$locTaskCat->fields['is_subprocess']
                  && !$locTask->getFromDBByRequest($locTaskRestrict)) {
                     $this->addTask($parentCase->getID(), $itemtype,
                                            $items_id,
                                            $parentCaseInfo,
                                            $open_task->delIndex,
                                            PluginProcessmakerUser::getGLPIUserId($open_task->userId),
                                            $open_task->taskId,
                                            $open_task->delThread,
                                            [ 'txtTaskContent' => $txtTaskContent,
                                              'start_date'     => $taskStartDate,
                                              'end_date'       => $taskEndDate,
                                              'reminder'       => $taskReminder]
                                          );

                     // if end date was specified, then must change due date of the PM task
                     if ($taskEndDate != '') {
                        $PM_DB->update('APP_DELEGATION', ['DEL_TASK_DUE_DATE' => $taskEndDate], ['AND' => ['APP_UID' => $parentCaseInfo->caseId, 'DEL_INDEX' => $open_task->delIndex]]);
                           //$PM_DB->query("UPDATE APP_DELEGATION SET DEL_TASK_DUE_DATE='$taskEndDate' WHERE APP_UID='".$sub_caseInfo->caseId."' AND DEL_INDEX=".$open_task->delIndex);
                     }
                  }
               }
            }
            // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
            $parentCase->update([
               'id'          => $parentCase->getID(),
               'case_status' => $parentCaseInfo->caseStatus,
               'name'        => $parentCaseInfo->caseName
               ]);
         }
      }

      // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
      $myCase->update([
         'id'          => $myCase->getID(),
         'case_status' => $caseInfo->caseStatus,
         'name'        => $caseInfo->caseName
         ]);

      if (array_key_exists('GLPI_ITEM_SET_SOLUTION_TEMPLATE_ID', $casevariablevalues)
          || array_key_exists('GLPI_ITEM_SET_SOLUTION_TYPE_ID', $casevariablevalues)
          || array_key_exists('GLPI_ITEM_APPEND_TO_SOLUTION_DESCRIPTION', $casevariablevalues)) {
         // case requests to add a solution to ticket
         $this->setItemSolution($itemtype, $items_id, $casevariablevalues);
      }

      if ($itemSetStatus != '') {
         $this->setItemStatus($itemtype, $items_id, $itemSetStatus);
      }

      // send email if requested
      if (is_array($sendemail)) {
         NotificationEvent::raiseEvent('send_email',
                                       $myCase,
                                       ['glpi_send_email' => $sendemail,
                                        'case'            => $myCase
                                       ]);
      }

   }


    /**
     * Summary of getPMGroups
     * @return array
     */
   public static function getPMGroups() {
      global $PM_DB;
      $pmGroupList = [];
      $res = $PM_DB->request(
                        'CONTENT', [
                        'AND' => [
                           'CONTENT.CON_CATEGORY' => 'GRP_TITLE',
                           'CONTENT.CON_LANG'     => 'en'
                        ]
                        ]);
      //foreach ($PM_DB->request("SELECT * FROM CONTENT WHERE CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG='en'") as $dbgroup) {
      foreach ($res as $dbgroup) {
         $pmGroupList[$dbgroup['CON_VALUE']] = $dbgroup;
      }
      return $pmGroupList;
   }


    /**
     * Summary of displayMessage
     * Show a html message bottom-right of screen
     * @param string $html_message message to be shown
     * @param string $title        if '' then title bar is not shown (default '')
     * @param string $msgtype      the type of the message (ERROR | WARNING | INFO)
     * @return void
     **/
   static private function displayMessage($html_message, $title = '', $msgtype = 'info_msg') {

      //set title and css class
      switch ($msgtype) {
         case ERROR:
            $title = __s('Error');
            $class = 'err_msg';
            break;
         case WARNING:
            $title = __s('Warning');
            $class = 'warn_msg';
            break;
         case INFO:
            $title = _sn('Information', 'Information', 1);
            $class = 'info_msg';
            break;
      }

      echo "<div id=\"message_after_redirect_$msgtype\" title=\"$title\">";
      echo $html_message;
      echo "</div>";

      $scriptblock = "
               $(function() {
                  var _of = window;
                  var _at = 'right-20 bottom-20';
                  //calculate relative dialog position
                  $('.message_after_redirect').each(function() {
                     var _this = $(this);
                     if (_this.attr('aria-describedby') != 'message_after_redirect_$msgtype') {
                        _of = _this;
                        _at = 'right top-' + (10 + _this.outerHeight());
                     }
                  });

                  $('#message_after_redirect_$msgtype').dialog({
                     dialogClass: 'message_after_redirect $class',
                     minHeight: 40,
                     minWidth: 200,
                     position: {
                        my: 'right bottom',
                        at: _at,
                        of: _of,
                        collision: 'none'
                     },
                     autoOpen: false,
                     show: {
                       effect: 'slide',
                       direction: 'down',
                       'duration': 800
                     }
                  })
                  .dialog('open');";

      //do not autoclose errors
      if ($msgtype != ERROR) {
         $scriptblock .= "

                  // close dialog on outside click
                  $(document.body).on('click', function(e){
                     if ($('#message_after_redirect_$msgtype').dialog('isOpen')
                         && !$(e.target).is('.ui-dialog, a')
                         && !$(e.target).closest('.ui-dialog').length) {
                        $('#message_after_redirect_$msgtype').dialog('close');
                        // redo focus on initial element
                        e.target.focus();
                     }
                  });";
      }

      $scriptblock .= "

               });
            ";

      echo Html::scriptBlock($scriptblock);
   }

   /**
    * Summary of underMaintenance
    * Shows a nice(?) under maintenance message
    */
   static function showUnderMaintenance() {
      global $CFG_GLPI;
      echo "<div class='center'>";
      echo Html::image(Plugin::getWebDir('processmaker').'/pics/under_maintenance.png');
      echo "<p style='font-weight: bold;'>";
      echo __('ProcessMaker plugin is under maintenance, please retry later, thank you.', 'processmaker');
      echo "</p>";
      echo "</div>";
   }


   /**
    * Summary of getGLPIGroupIDForSelfServiceTask
    * Gets GLPI group ID that is assigned to a task for a case when task is Self-Service
    * @param string $caseId is the UID of the case
    * @param string $pmTaskId is the UID of the task must be currently in SELF-SERVICE
    * @return bool|array false if not found, otherwise is an array containing the 'name', GLPI 'id' and the PM 'uid' of the group
    */
   public function getGLPIGroupIdForSelfServiceTask ($caseId, $pmTaskId) {
      global $PM_DB;

      $groupname = NULL;

      // we must differentiate if rule assignement is self-service or self-service value based
      //$query = "SELECT TAS_GROUP_VARIABLE
      //          FROM TASK
      //          WHERE TAS_UID='$pmTaskId'
      //             AND TAS_ASSIGN_TYPE='SELF_SERVICE';";
      $query = ['SELECT' => 'TAS_GROUP_VARIABLE',
                'FROM'   => 'TASK',
                'WHERE'  => ['TAS_UID'         => $pmTaskId,
                             'TAS_ASSIGN_TYPE' => 'SELF_SERVICE']];
      //$req = $PM_DB->query($query);
      //$row = $PM_DB->fetchAssoc($req);
      foreach ($PM_DB->request($query) as $row) {
         // there is only one row

         if ($row['TAS_GROUP_VARIABLE'] == '') {
            // Assignement is Self-Service
            // the group title can be found in the GROUPWF table when the assignement rule is Self-Service
            // TU_RELATION=2 is groups and TU_TYPE=1 means normal (= not adhoc)
            $queryname = "SELECT GROUPWF.GRP_TITLE AS 'GRP_TITLE', GROUPWF.GRP_UID AS 'GRP_UID' FROM TASK_USER
                       JOIN GROUPWF ON GROUPWF.GRP_UID = TASK_USER.USR_UID
                       WHERE TASK_USER.TAS_UID = '$pmTaskId'
                          AND TASK_USER.TU_RELATION = 2
                          AND TASK_USER.TU_TYPE = 1
                       LIMIT 1;";
         } else {
            // Assignement is Self-Service Value Based
            // in this case we must retreive the group from the case variable which is in if the TASK table
            $casevariable = substr($row['TAS_GROUP_VARIABLE'], 2);
            $vars = $this->getVariables($caseId, [$casevariable]);
            if (array_key_exists($casevariable, $vars) && $vars[$casevariable] != '') {
               // here is the UID of the group that needs to be assigned to the task
               // then we need the name of the group
               // the group title can be found in the GROUPWF table
               $queryname = "SELECT GRP_TITLE, GRP_UID
                          FROM GROUPWF
                          WHERE GRP_UID = '{$vars[$casevariable]}';";
            }
         }
      }

      // as there is a LIMIT of 1
      // or
      // as there is only one group per guid
      // then we should have at maximun 1 record
      if (isset($queryname)) {
         foreach ($PM_DB->request($queryname) as $onlyrec) {
            $groupname = $onlyrec;
         }
      }

      if (isset($groupname)) {
         return ['name' => $groupname['GRP_TITLE'],
                 'id'   => self::getGLPIGroupId($groupname['GRP_TITLE']),
                 'uid'  => $groupname['GRP_UID']
                ];
      }

      return false;
   }

   //public function loadJS($filepath) {
   //    //echo Html::scriptBlock("glpiPmLoadJS('" . Plugin::getWebDir('processmaker') . "$filepath?v=" . PROCESSMAKER_VERSION . "')");
   //}

}