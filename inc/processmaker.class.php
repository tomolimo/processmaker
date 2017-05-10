<?php

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


$pmHideSolution = false;


if (!function_exists('http_formdata_flat_hierarchy')) {
   /**
   * Summary of http_formdata_flat_hierarchy
   * @param mixed $data
   * @return array
   */
   function http_formdata_flat_hierarchy($data) {
      $vars=array();
      foreach ($data as $key=>$value) {
         if (is_array($value)) {
            $temp = array();
            foreach ($value as $k2 => $val2) {
               $temp[ $key.'['.$k2.']' ] = $val2;
            }
            $vars = array_merge( $vars, http_formdata_flat_hierarchy($temp) );
         } else {
            $vars[$key]=$value;
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

      $value = is_array($value) ?
                array_map('stripcslashes_deep', $value) :
                stripcslashes($value);

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

   private $taskWriter = 0;
   private $pm_group_guid = ''; // guid for default user group in Process Maker is used for all GLPI user synchronization into ProcessMaker
   var $lasterror;
   var $lang;


   /**
   * Return the table used to store this object
   *
   * @return string
   **/
   static function getTable() {

      return "glpi_plugin_processmaker_processes";
   }

   /**
   * Summary of addTicketFollowup
   * @param mixed   $itemId
   * @param mixed   $txtForFollowup
   * @param integer $users_id       optional, if null will uses logged-in user
   */
   public function addTicketFollowup( $itemId, $txtForFollowup, $users_id=null ) {
      $fu = new TicketFollowup();
      $fu->getEmpty(); // to get default values
      $input = $fu->fields;
      if (isset( $txtForFollowup['GLPI_TICKET_FOLLOWUP_CONTENT'] )) {
         $input['content']=$txtForFollowup['GLPI_TICKET_FOLLOWUP_CONTENT'];
      }
      if (isset( $txtForFollowup['GLPI_TICKET_FOLLOWUP_IS_PRIVATE'] )) {
         $input['is_private']=$txtForFollowup['GLPI_TICKET_FOLLOWUP_IS_PRIVATE'];
      }
      if (isset( $txtForFollowup['GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID'] )) {
         $input['requesttypes_id']=$txtForFollowup['GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID'];
      }
      $input['tickets_id']=$itemId;
      $input['users_id']= (isset($users_id) ? $users_id : Session::getLoginUserID( true )); // $this->taskWriter;

      $fu->add( $input );
   }


   /**
   * Summary of openSoap
   * @return true if open succeeded, and pmSoapClient is initialized
   *         false otherwise
   */
   private function openSoap( ) {

      try {
         if ($this->pmSoapClient == null) {
            $this->lang = substr( $_SESSION["glpilanguage"], 0, 2);
            if (strlen( $this->lang ) <> 2) {
               $this->lang = "en"; // by default
            }
            $this->config = PluginProcessmakerConfig::getInstance();
            $this->pmWorkspace = $this->config->fields['pm_workspace'];
            $this->serverURL = trim($this->config->fields['pm_server_URL'], '/').'/sys'.$this->config->fields['pm_workspace'].'/'.$this->lang.'/'.$this->config->fields['pm_theme'];
            $this->taskWriter = $this->config->fields['users_id'];
            $this->pm_group_guid = $this->config->fields['pm_group_guid'];
            $this->pmSoapClient = new SoapClient($this->serverURL."/services/wsdl2", array( 'soap_version'   => SOAP_1_2, 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP) );
         }

         return true;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         $this->lasterror = $e;
         return false; //null ;
      }
   }


   /**
   * Summary of login
   * @param mixed $admin_or_user if true will be admin, otherwise is user name (or user id), or current user
   * @return true if login has been correctly done with current GLPI user, or if a PM session was already open
   *         false if an exception occured (like SOAP error or PM login error)
   */
   function login( $admin_or_user=false ) {
      global $DB, $PM_DB;
      try {
         $locSession = new stdClass; // by default empty object
         if ($this->openSoap( )) {
            $cookie_lifetime = ini_get('session.cookie_lifetime');
            if ($cookie_lifetime == 0) {
               $cookie_lifetime = 15 * 60; //= 15 minutes
            }
            if ($admin_or_user === true) { // admin rights has been requested, then force new login
                $config = PluginProcessmakerConfig::getInstance();
                $locSession = $this->pmSoapClient->login( array( 'userid' => $config->fields['pm_admin_user'], 'password' => Toolbox::decrypt($config->fields['pm_admin_passwd'], GLPIKEY)) );
               if (is_object( $locSession ) && $locSession->status_code == 0) {
                  $_SESSION["pluginprocessmaker"]["session"]["admin"] = true;
                  $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message;
                  $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp;
                  $this->pmAdminSession = true;
                  return true;
               }
            } else if (empty($_SESSION["pluginprocessmaker"]["session"]["date"]) || ($_SESSION["pluginprocessmaker"]["session"]["admin"] == true)
                || $this->pmAdminSession == true
                       || date_add( date_create( $_SESSION["pluginprocessmaker"]["session"]["date"] ), new DateInterval( "PT".$cookie_lifetime."S" ) ) < date_create( date( "Y-m-d H:i:s" ) ) ) {
               // get the password of the user
               $pmusr = new PluginProcessmakerUser;
               $gusr = new User;
               if (is_numeric($admin_or_user)) {
                  $gusr->getFromDB( $admin_or_user );
               } else {
                  $gusr->getFromDBbyName( $admin_or_user !== false ? $admin_or_user : $_SESSION["glpiname"]);
               }
               $pmusr->getFromDB( $gusr->getID() );
               if (!isset($pmusr->fields['password']) || $pmusr->fields['password'] == "") {
                  $pass = md5(Toolbox::encrypt( $gusr->getID().$gusr->getName().time(), GLPIKEY) );
                  $pmusr->update( array('id' => $pmusr->getID(), 'password' => $pass) );
                  //$DB->query( "UPDATE glpi_plugin_processmaker_users SET password = '".$pass."' WHERE glpi_users_id = ".$pmusr->getID().";" ) ;
                  //$pmusr->update( array( $pmusr->getIndexName() => $pmusr->getID(), 'password' => $pass) ) ;
                  // and must be updated also in PM db
                  $PM_DB->query("UPDATE RBAC_USERS SET USR_PASSWORD='".$pass."' WHERE USR_UID='".$pmusr->fields['pm_users_id']."' ");
                  $PM_DB->query("UPDATE USERS SET USR_PASSWORD='".$pass."' WHERE USR_UID='".$pmusr->fields['pm_users_id']."' ");
               }
               $locSession = $this->pmSoapClient->login( array( 'userid' => $gusr->fields['name'], 'password' => 'md5:'.$pmusr->fields['password']) );
               if (is_object( $locSession ) && $locSession->status_code == 0) {
                  $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message;
                  $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp;
                  $_SESSION["pluginprocessmaker"]["session"]["admin"] = false;
                  $this->pmAdminSession = false;
                  return true;
               }
            } else {
               return true; // means a session is already existing in $_SESSION["pluginprocessmaker"]["session"]
            }
         }

         $this->pmAdminSession = false;
         unset($_SESSION["pluginprocessmaker"]["session"]);
         Toolbox::logDebug( "Processmaker Plugin: $admin_or_user - Soap problem: ". print_r( $locSession, true ) );
         $this->lasterror = $locSession;
         return false;
      } catch (Exception $e) {
         $this->pmAdminSession = false;
         unset($_SESSION["pluginprocessmaker"]["session"]);
         Toolbox::logDebug( $e );
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
   function processList( ) {
      try {
         $pmProcessList = $this->pmSoapClient->processList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );
         if (isset( $pmProcessList->processes )) {
            if (is_array( $pmProcessList->processes )) {
               return $pmProcessList->processes;
            } else {
               return array( 0 => $pmProcessList->processes );
            }
         }

         return false;

      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   * @param $caseId: The case ID, which can be obtained with the caseList() function
   * @param $delIndex: The delegation index, which is a positive integer to identify the current task of the case. If empty then use current delIndex.
   * @return a getCaseInfoResponse object, or false exception occured
   */
   function getCaseInfo( $caseId, $delIndex='') {
      try {
         $pmCaseInfo = $this->pmSoapClient->getCaseInfo( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex) );
         switch ($pmCaseInfo->caseStatus) {
            case 'DRAFT' :
            case 'TO_DO':
               //                case 'CANCELLED' :
               if (is_object( $pmCaseInfo->currentUsers )) {
                   $pmCaseInfo->currentUsers = array( 0 => $pmCaseInfo->currentUsers );
               }
               if ($pmCaseInfo->currentUsers[0]->delThreadStatus == 'PAUSE') {
                   $pmCaseInfo->caseStatus = "PAUSED";
               }
                break;
         }
         return $pmCaseInfo;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }

   }

   /**
   * Summary of routeCase
   *       routes a case (i.e., moves the case to the next task in the process according to its routing rules).
   *       Embedded routeCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#routeCase.28.29)
   *       A session must be open before with login()
   * @param $caseId The case ID, which can be obtained with the caseList() function
   * @param $delIndex The delegation index, which is a positive integer to identify the current task of the case. If empty then use current delIndex.
   * @return a routeCaseResponse object, or false exception occured. routing is normalized to be always an array of routeListStruct
   */
   function routeCase( $caseId, $delIndex) {
      try {
         $pmRouteCaseResponse = $this->pmSoapClient->routeCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex) );
         if ($pmRouteCaseResponse->status_code != 0) {
             Toolbox::logDebug( 'routeCase res:', $pmRouteCaseResponse );
         }

         if (property_exists( $pmRouteCaseResponse, 'routing' ) && is_object( $pmRouteCaseResponse->routing )) {
             $pmRouteCaseResponse->routing = array( 0 => $pmRouteCaseResponse->routing);
         }

         return $pmRouteCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function triggerList( ) {
      try {
         $pmTriggerList = $this->pmSoapClient->triggerList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );
         if (is_array(  $pmTriggerList->triggers  )) {
             return  $pmTriggerList->triggers;
         } else {
            return array( 0 => $pmTriggerList->triggers );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function taskList( ) {
      try {
         $pmTaskList = $this->pmSoapClient->taskList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );

         if (is_array(  $pmTaskList->tasks  )) {
             return  $pmTaskList->tasks;
         } else {
            return array( 0 => $pmTaskList->tasks );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function taskCase( $caseId ) {
      try {
         $pmTaskCase = $this->pmSoapClient->taskCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId ) );

         if (is_array(  $pmTaskCase->taskCases  )) {
             return  $pmTaskCase->taskCases;
         } else {
            return array( 0 => $pmTaskCase->taskCases );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }

   /**
   * Summary of claimCase
   * @param mixed $caseId
   * @param mixed $delIndex
   * @return mixed
   */
   function claimCase( $caseId, $delIndex) {
      try {
         $pmClaimCase = $this->pmSoapClient->claimCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'guid' => $caseId, 'delIndex' => $delIndex) );
         return $pmClaimCase;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }

   /**
   * Summary of unpauseCase
   *      Unpauses a specified case.
   *      Embedded UnpauseCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#UnpauseCase.28.29)
   *      A session must be open before with login()
   *      Normalizes output to an array, even when only one element is returned by PM
   * @param $caseId The unique ID of the case.
   * @param $delIndex The delegation index of the current task in the case.
   * @param $userId The unique ID of the user who will unpause the case.
   * @return an array of UnpauseCaseStruct, or false when exception occured
   */
   function unpauseCase( $caseId, $delIndex, $userId ) {
      try {
         $pmUnpauseCase = $this->pmSoapClient->unpauseCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseId, 'delIndex' => $delIndex, 'userUid' => $userId) );

         if (is_array( $pmUnpauseCase->processes )) {
             return  $pmUnpauseCase->processes;
         } else {
            return array( 0 => $pmUnpauseCase->processes );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function caseList( ) {
      try {
         $pmCaseList = $this->pmSoapClient->caseList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );

         if (is_array(  $pmCaseList->cases  )) {
             return  $pmCaseList->cases;
         } else {
            return array( 0 => $pmCaseList->cases );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }

   /**
   * Summary of reassignCase
   *      reassigns a case to a different user. Note that the logged-in user needs to have the PM_REASSIGNCASE permission in his/her role in order to be able to reassign the case.
   *      Embedded caseList() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#reassignCase.28.29)
   *      A session must be open before with login()
   * @param $caseId The case ID, which can be obtained with the caseList() function.
   * @param $delIndex The current delegation index number of the case, which can be obtained with the caseList() function.
   * @param $userIdSource The user who is currently assigned the case, which can be obtained with the caseList() function.
   * @param $userIdTarget The target user who will be newly assigned to the case, which can be obtained with userList(). The case can only be reassigned to a user who is one of the assigned users or ad-hoc users to the current task in the case.
   * @return A pmResponse object, or false when exception occured
   */
   function reassignCase( $caseId, $delIndex, $userIdSource, $userIdTarget ) {
      try {
         $pmResults = $this->pmSoapClient->reassignCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex, 'userIdSource' => $userIdSource, 'userIdTarget'=> $userIdTarget) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }


   /**
   * Summary of deleteCase
   *      Deletes a case
   *      Embedded deleteCase() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#deleteCase.28.29)
   *      A session must be open before with login()
   *      Beware that at any time you may delete a case!!!
   * @param $caseUid The case ID, which can be obtained with the caseList() function.
   * @return A deleteCaseResponse object, or false when exception occured
   */
   function deleteCase( $caseUid ) {
      try {
         $deleteCaseResponse = $this->pmSoapClient->deleteCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseUid) );
         return $deleteCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function cancelTask( $caseUid, $delIndex, $userUid ) {
      try {
         $cancelTaskResponse = $this->pmSoapClient->cancelCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseUid, 'delIndex' => $delIndex, 'userUid' => $userUid) );
         return $cancelTaskResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function cancelCase( $caseUid ) {
      try {
         $pmCaseInfo = $this->getCaseInfo( $caseUid );
         if ($pmCaseInfo->status_code == 0) {
            foreach ($pmCaseInfo->currentUsers as $pmUser) {
               $pmCancelTask = $this->cancelTask( $caseUid, $pmUser->delIndex, $pmUser->userId );
               if ($pmCancelTask->status_code != 0) {
                   return $pmCancelTask;
               }
            }
         }
         return $pmCancelTask;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   * @param $processId The ID of the process where the case should start, which can be obtained with processList()
   * @param $userId The ID of the user who initiates the case, which can be obtained with userList().
   * @param $vars an array of associative variables (name => value) that will be injected into the case as case variables
   * @return A newCaseResponse object, or false when exception occured
   */
   function newCaseImpersonate( $processId, $userId, $vars = null ) {
      try {
         $this->getFromDB( $processId );

         if ($vars !== null) {
            $aVars = array();
            foreach ($vars as $key => $val) {
               $obj = new variableStruct();
               $obj->name = $key;
               $obj->value = $val;
               $aVars[] = $obj;
            }
         } else {
            $aVars = '';
         }

         $newCaseResponse = $this->pmSoapClient->newCaseImpersonate( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'processId'=> $this->fields['process_guid'], 'userId' => $userId, 'taskId'=>'', 'variables'=> $aVars) );
         return $newCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   * @param string $processId The ID of the process which will be instantied into a case, which can be obtained with processList()
   * @param array  $vars      an array of associative variables (name => value) that will be injected into the case as case variables
   * @return A newCaseResponse object, or false when exception occured
   */
   function newCase( $processId, $vars = array() ) {
      try {
         $this->getFromDB( $processId );

         $aVars = array();
         foreach ($vars as $key => $val) {
            $obj = new variableStruct();
            $obj->name = $key;
            $obj->value = $val;
            $aVars[] = $obj;
         }

         $newCaseResponse = $this->pmSoapClient->newCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'processId'=> $this->fields['process_guid'], 'taskId'=>'', 'variables'=> $aVars) );

         return $newCaseResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }

   /**
   * Summary of sendVariables
   *      Sends variables to a case.
   *      Embedded sendVariables() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#sendVariables.28.29)
   *      A session must be open before with login()
   * @param string $caseId The uID of the case
   * @param array  $vars   an array of associative variables (name => value) that will be injected into the case as case variables
   * @return A pmResponse object, or false when exception occured
   */
   function sendVariables( $caseId, $vars = array() ) {
      if (count( $vars ) == 0) { // nothing to send
          return true;
      }
      try {
         $aVars = array();
         foreach ($vars as $key => $val) {
            $obj = new variableStruct();
            $obj->name = $key;
            if (is_array( $val )) {
                $obj->value = join( "|", $val );
            } else {
               $obj->value = $val;
            }
            $aVars[] = $obj;
         }

         $pmResponse = $this->pmSoapClient->sendVariables( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'variables'=> $aVars) );

         return $pmResponse;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
         return false;
      }
   }



   /**
   * Summary of getVariables
   *      Gets variables from a case.
   *      Embedded getVariables() PM web service call (definition: http://wiki.processmaker.com/index.php/2.0/ProcessMaker_WSDL_Web_Services#getVariables.28.29)
   *      A session must be open before with login()
   * @param string $caseId The uID of the case
   * @param array  $vars   an array of variable name that will be read from the case as case variables Normalizes output to an array, even when only one element is returned by PM Normalizes output to an array, even when only one element is returned by PM
   *      Normalizes output to an array, even when only one element is returned by PM
   * @return array: an associative array (variable_name => value), or false when exception occured. The return array can be empty if requested variables are not found.
   */
   function getVariables( $caseId, $vars = array() ) {
      try {
         $aVars = array();
         foreach ($vars as $key => $name) {
            $obj = new getVariableStruct();
            $obj->name = $name;
            $aVars[] = $obj;
         }

         $pmvariableListResponse = $this->pmSoapClient->getVariables( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'variables'=> $aVars) );

         $variablesArray = array();

         if ($pmvariableListResponse->status_code == 0 && isset( $pmvariableListResponse->variables )) {
            if (is_array( $pmvariableListResponse->variables )) {
               foreach ($pmvariableListResponse->variables as $variable) {
                   $variablesArray[$variable->name] = $variable->value;
               } } else {
               $variablesArray[$pmvariableListResponse->variables->name] = $pmvariableListResponse->variables->value;
               }
         }

         return $variablesArray;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function groupList( ) {
      try {
         $pmGroupList = $this->pmSoapClient->groupList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );

         if (is_array(  $pmGroupList->groups  )) {
             return  $pmGroupList->groups;
         } else {
            return array( 0 => $pmGroupList->groups );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function assignUserToGroup( $userId, $groupId) {
      try {
         $pmResults = $this->pmSoapClient->assignUserToGroup(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"],
                                                             'userId' => $userId,
                                                             'groupId' => $groupId
                                                             ) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function createGroup( $name ) {
      try {
         $pmResults = $this->pmSoapClient->createGroup(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"],
                                                             'name' => $name ) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function updateGroup( $group_id, $groupStatus ) {
      global $PM_DB;
      $query = "UPDATE GROUPWF SET GRP_STATUS='$groupStatus' WHERE GRP_UID='$group_id';";
      $PM_DB->query( $query );
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
   function userList( ) {
      try {
         $pmUserList = $this->pmSoapClient->userList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) );

         if (is_array(  $pmUserList->users  )) {
             return  $pmUserList->users;
         } else {
            return array( 0 => $pmUserList->users );
         }
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function createUser( $userId, $firstname, $lastname, $email, $role, $password, $status) {
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

         $pmResults = $this->pmSoapClient->createUser(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"],
                                                             'userId' => $userId,
                                                             'firstname'=> $firstname,
                                                             'lastname' => $lastname,
                                                             'email' => $email,
                                                             'role' => $role,
                                                             'password' => $password,
                                                             'status' => $status ) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function updateUser( $userUid, $userName, $firstName, $lastName, $status) {
      try {
         if ($firstName == null || $firstName == "") {
            $firstName = $userName;
         }
         if ($lastName == null || $lastName == "") {
            $lastName = $userName;
         }

         $pmResults = $this->pmSoapClient->updateUser(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"],
                                                             'userUid' => $userUid,
                                                             'userName' => $userName,
                                                             'firstName'=> $firstName,
                                                             'lastName' => $lastName,
                                                             'status' => $status
                                                             ) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
   function executeTrigger( $caseId, $triggerIndex, $delIndex ) {
      try {
         $pmResults = $this->pmSoapClient->executeTrigger(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'triggerIndex'=> $triggerIndex, 'delIndex' => $delIndex ) );
         return $pmResults;
      } catch (Exception $e) {
         Toolbox::logDebug( $e );
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
      global $LANG;

      switch ($name) {
         case 'pmusers' :
             return array('description' => $LANG['processmaker']['cron']['pmusers'] );
         case 'pmorphancases' :
            return array('description' => $LANG['processmaker']['cron']['pmorphancases']['description'], 'parameter' => $LANG['processmaker']['cron']['pmorphancases']['parameter']  );
         case 'pmtaskactions' :
            return array('description' => $LANG['processmaker']['cron']['pmtaskactions'] );
      }
      return array();
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
   static function cronPMTaskActions($task) {
      global $DB, $CFG_GLPI, $PM_DB;

      if (!isset($PM_DB)) {
         $PM_DB = new PluginProcessmakerDB;
      }

      $actionCode = 0; // by default
      $error = false;
      $task->setVolume(0); // start with zero

      $pm = new self;
      $existingpmsession = isset($_SESSION["pluginprocessmaker"]["session"]);
      $formerusers_id = 0;
      // get the list of taskactions to be done
      foreach ($DB->request( getTableForItemType('PluginProcessmakerCrontaskaction'), ' `state` = '.PluginProcessmakerCrontaskaction::DATAS_READY ) as $taskaction) {

         try {

            if ($formerusers_id != $taskaction['users_id']) {
               unset($_SESSION["pluginprocessmaker"]["session"]); // to reset previous user login if any
            }

            $pm->login($taskaction['users_id']);

            $postdatas = json_decode($taskaction['postdatas'], true);

            if ($taskaction['toclaim']) {
               // must do a claim before solving task
               if (!$pm->claimCase( $postdatas['APP_UID'], $postdatas['DEL_INDEX'] )) {
                  throw new Exception("Can't claim case");
               }

               $donotif = $CFG_GLPI["use_mailing"];
               $CFG_GLPI["use_mailing"] = false;

               // now manage tasks associated with item
               $pm->claimTask( $postdatas['APP_UID'], $postdatas['DEL_INDEX'], $taskaction['users_id'] );

               $CFG_GLPI["use_mailing"] = $donotif;

            }
            $myCase = new PluginProcessmakerCase;
            if ($myCase->getFromDB( $postdatas['APP_UID'] )) {

               //$cookies = json_decode($taskaction['cookies'], true) ;
               $pm->derivateCase( $myCase, $postdatas, $taskaction['users_id'] );
            }

            $tkaction = new PluginProcessmakerCrontaskaction;
            $tkaction->update( array( 'id' => $taskaction['id'], 'state' => PluginProcessmakerCrontaskaction::DONE ) );

            $task->addVolume(1);
            $task->log( "Applied task action id: '".$taskaction['id']."'" );

         } catch (Exception $e) {
            $task->log( "Can't apply task action id: '".$taskaction['id']."'" );
            $error = true;
         }

         $formerusers_id = $taskaction['users_id'];
      }

      if ($existingpmsession) {
         unset($_SESSION["pluginprocessmaker"]["session"]); // reset the one created during the foreach
         if (!Session::isCron()) {
            $pm->login(); // re-log default user
         }
      }

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
      global $PM_DB, $DB;

      if (!isset($PM_DB)) {
         $PM_DB = new PluginProcessmakerDB;
      }

      // get list of case assigned to GLPi items
      $draftCases = array(0);
      $query = "SELECT case_num FROM glpi_plugin_processmaker_cases WHERE case_status = 'DRAFT';";
      foreach ($DB->request( $query ) as $row) {
         $draftCases[] = $row['case_num'];
      }

      $actionCode = 0; // by default
      $error = false;
      $task->setVolume(0); // start with zero

      if (count($draftCases) > 0) {
         $pm = new self;
         $pm->login(true);
         $query = "SELECT * FROM APPLICATION
                  WHERE APP_DATA LIKE '%s:24:\"GLPI_SELFSERVICE_CREATED\";s:1:\"1\"%'
                     AND APP_STATUS = 'DRAFT'
                     AND DATEDIFF( NOW(), APP_UPDATE_DATE) > ".$task->fields['param']."
                     AND APP_NUMBER NOT IN (".implode(',', $draftCases).");
                ";
         foreach ($PM_DB->request( $query ) as $row) {
            $ret = $pm->deleteCase( $row['APP_UID'] );
            $task->addVolume(1);
            if ($ret !== false) {
               $task->log( "Deleted case num: '".$row['APP_NUMBER']."'" );
               $actionCode = 1;
            } else {
               $task->log( "Can't delete case num: '".$row['APP_NUMBER']."'" );
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
      global $DB, $PM_DB;

      if (!isset($PM_DB)) {
         $PM_DB = new PluginProcessmakerDB;
      }

      $actionCode = 0; // by default
      $error = false;
      $task->setVolume(0); // start with zero

      // start a processmaker session
      $myProcessMaker = new PluginProcessmakerProcessmaker();
      if (!$myProcessMaker->login( true )) {
         $task->log( "Error PM: '".print_r($myProcessMaker->lasterror, true)."'" );
         return -1;
      }

      $pmGroupList = $myProcessMaker->groupList( );
      foreach ($pmGroupList as $pmGroup) {
         if ($pmGroup->guid == $myProcessMaker->pm_group_guid) {
            break; // to get the name :)
         }
      }

      $pmUserList = array();
      foreach ($myProcessMaker->userList() as $pmuser) {
         $pmUserList[ strtolower($pmuser->name)] = array( 'name' => $pmuser->name, 'guid' => $pmuser->guid,  'status' => $pmuser->status );
      }

      // get the complete user list from GLPI DB
      $glpiUserList = array();
      foreach ($DB->request("SELECT glpi_users.id, glpi_users.name, glpi_users.realname, glpi_users.firstname, glpi_users.is_active, glpi_users.is_deleted, glpi_plugin_processmaker_users.pm_users_id as pmUserId
                              FROM glpi_users
                              LEFT JOIN glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.id = glpi_users.id
                              WHERE name not like '*%'") as $dbgroup) {
         $glpiUserList[ strtolower($dbgroup['name'])] = $dbgroup;
      }

      $arrayDiff = array_diff_key( $glpiUserList, $pmUserList );

      foreach ($arrayDiff as $user) {
         if ($user['is_active'] != 0 && $user['is_deleted'] != 1) {
            $status = "ACTIVE";
            $task->addVolume(1);
            $pass = substr( Toolbox::encrypt($user['id'].$user['name'].time(), GLPIKEY), 0, 20);
            $pmResult = $myProcessMaker->createUser( $user['name'], $user['firstname'], $user['realname'], "", "PROCESSMAKER_OPERATOR", $pass, $status);
            if ($pmResult->status_code == 0) {
               $task->log( "Added user: '".$user['name']."'" );

               // then assign user to group
               $pmResult2 = $myProcessMaker->assignUserToGroup( $pmResult->userUID, $pmGroup->guid );
               if ($pmResult2->status_code == 0) {
                   $task->log( "Added user: '".$user['name']."' to '".$pmGroup->name."' group" );
               } else {
                  $task->log( "Error PM: '".$pmResult2->message."'" );
               }

               // insert into DB the link between glpi users and pm user
               $pmuser = new PluginProcessmakerUser;
               if ($pmuser->getFromDB( $user['id'] )) {
                  $pmuser->update( array( 'id' => $user['id'], 'pm_users_id' => $pmResult->userUID, 'password' => md5( $pass ) ) );
               } else {
                  $pmuser->add( array( 'id' => $user['id'], 'pm_users_id' => $pmResult->userUID, 'password' => md5( $pass ) ) );
               }
               $actionCode = 1;

            } else {
               $task->log( "Error adding user: '".$user['name']."'" );
               $task->log( "Error PM: '".$pmResult->message."'" );
               $actionCode = -1;
               $error = true;
            }
         } else {
            unset( $glpiUserList[$user['name']] );
         }
      }

      if (!$error) {

         // now should refresh the existing users
         $arrayIntersect = array_intersect_key( $glpiUserList, $pmUserList );
         foreach ($arrayIntersect as $user) {
            if ($user['pmUserId'] == null || ($user['pmUserId'] != $pmUserList[strtolower($user['name'])]['guid'])) { //must be inserted into DB
               // insert into DB the link between glpi users and pm user
               $pmuser = new PluginProcessmakerUser;
               if ($pmuser->getFromDB( $user['id'] )) {
                  $ret = $pmuser->update( array( 'id' => $user['id'], 'pm_users_id' => $pmUserList[strtolower($user['name'])]['guid'] ) );
               } else {
                  $ret = $pmuser->add( array( 'id' => $user['id'], 'pm_users_id' => $pmUserList[strtolower($user['name'])]['guid'] ) );
               }

               //$query = "REPLACE INTO glpi_plugin_processmaker_users (glpi_users_id, pm_users_id) VALUES (".$user['id'].", '". $pmUserList[strtolower($user['name'])]['guid']."');" ;
               //$DB->query( $query ) or
               if (!$ret) {
                  $task->log( "Cannot update user: '".$user['id']."' into glpi_plugin_processmaker_users!" );
               }

            }
            if ($user['is_active'] == 0 || $user['is_deleted'] == 1) {
                $status = "INACTIVE";
            } else {
               $status = "ACTIVE";
            }
            if ($status != $pmUserList[strtolower($user['name'])]['status']) {
               $task->addVolume(1);
               $pmResult = $myProcessMaker->updateUser( $pmUserList[strtolower($user['name'])]['guid'], $user['name'], $user['firstname'], $user['realname'], $status );
               if ($pmResult->status_code == 0) {
                  $task->log( "Updated user: '".$user['name']."', status: '".$pmUserList[strtolower($user['name'])]['status']."' -> '".$status."'" );
                  $actionCode = 1;
               } else {
                  $task->log( "Error updating user: '".$user['name']."'" );
                  $task->log( "Error PM: '".$pmResult->message."'" );
                  $actionCode = -1;
                  $error = true;
               }
            }

         }
      }

      // now we should desactivate PM users who are not in glpi user list
      //if( !$error ) {
      //    $status = "INACTIVE" ;
      //    $arrayDiff = array_diff_key( $pmUserList , $glpiUserList ) ;
      //    foreach( $arrayDiff as $user ){
      //        $task->addVolume(1);
      //        if( $user['status'] == 'ACTIVE' && $user['name'] != 'admin' && $user['name'] != 'glpi'){
      //            $pmResult = $myProcessMaker->updateUser( $user['guid'], $user['name'], null, null, $status ) ;
      //            if( $pmResult->status_code == 0) {
      //                $task->log( "Updated user: '".$user['name']."', status: '".$user['status']."' -> '".$status."'" ) ;
      //                $actionCode = 1 ;
      //            } else {
      //                $task->log( "Error updating user: '".$user['name']."'" ) ;
      //                $task->log( "Error PM: '".$pmResult->message."'" ) ;
      //                $actionCode = -1 ;
      //                $error = true ;
      //            }
      //        }
      //    }
      //}

      // so now treat GLPI groups
      $glpiGroupList = array();
      foreach ($DB->request("SELECT id, name, is_task, is_usergroup FROM glpi_groups WHERE is_task=1 AND is_usergroup=1") as $dbgroup) {
         $glpiGroupList[$dbgroup['name']] = $dbgroup;
      }

      $pmGroupList = self::getPMGroups(); // array();

      // here we can compare group lists like done for the users
      $arrayDiff = array_diff_key( $glpiGroupList, $pmGroupList );

      // then for each group we must check if it exists, and if not create a real PM group
      foreach ($arrayDiff as $group) {
         // it is not existing in PM
         // then create
         $pmResult = $myProcessMaker->createGroup( $group['name'] );
         if ($pmResult->status_code == 0) {
            $task->addVolume(1);
            $task->log( "Added group: '".$group['name']."'" );
         }
      }

      // review and update all users in each group
      $pmGroupList = self::getPMGroups(); // array();

      // now should refresh the existing users into groups
      $arrayDiff = array_intersect_key( $glpiGroupList, $pmGroupList );
      foreach ($arrayDiff as $group) {
         // for each group will delete users and re-create them
         // not really optimized, but this way we are sure that groups are synchronized
         // must be redesigned
         $query = "DELETE FROM GROUP_USER WHERE GROUP_USER.GRP_UID='".$pmGroupList[$group['name']]['CON_ID']."';";
         $PM_DB->query( $query );
         // and insert all users from real GLPI group
         foreach ($DB->request("SELECT glpi_groups_users.users_id, glpi_plugin_processmaker_users.pm_users_id
                                   FROM glpi_groups
                                   JOIN glpi_groups_users ON glpi_groups_users.groups_id=glpi_groups.id
                                   JOIN glpi_plugin_processmaker_users ON glpi_plugin_processmaker_users.id=glpi_groups_users.users_id
                                   WHERE glpi_groups.name='".$group['name']."'") as $user ) {
            $query = "INSERT INTO GROUP_USER (`GRP_UID`, `USR_UID`) VALUES ( '".$pmGroupList[$group['name']]['CON_ID']."', '".$user['pm_users_id']."' )";
            $PM_DB->query( $query );
         }
         $task->addVolume(1);
         $task->log( "Updated users into PM group: '".$group['name']."'" );
      }

      // now should renew the duedate of the users
      $PM_DB->query("UPDATE USERS SET USR_DUE_DATE='2035-12-31' WHERE USR_DUE_DATE<>'2035-12-31'; ");
      $PM_DB->query("UPDATE RBAC_USERS SET USR_DUE_DATE='2035-12-31' WHERE USR_DUE_DATE<>'2035-12-31'; ");

      if ($error) {
          return -1;
      } else {
         return $actionCode;
      }
   }


   public static function plugin_pre_item_add_processmaker($parm) {
      global $PM_DB;

      if (isset($parm->input['processmaker_caseid'])) {
         // a case is already started for this ticket, then change ticket title and ticket type and ITILCategory

         $myProcessMaker = new PluginProcessmakerProcessmaker( );
         $myProcessMaker->login( );
         $caseInfo = $myProcessMaker->getCaseInfo( $parm->input['processmaker_caseid'], $parm->input['processmaker_delindex']);
         $parm->input['name'] = $PM_DB->escape($caseInfo->caseName );

         $caseInitialDueDate = $myProcessMaker->getVariables(  $parm->input['processmaker_caseid'], array( 'GLPI_ITEM_TITLE', 'GLPI_ITEM_INITIAL_DUE_DATE' ));
         if (array_key_exists( 'GLPI_ITEM_INITIAL_DUE_DATE', $caseInitialDueDate )) {
            $parm->input['due_date'] = $caseInitialDueDate[ 'GLPI_ITEM_INITIAL_DUE_DATE' ]." 23:59:59";
         }
         $txtItemTitle = $caseInfo->caseName;
         if (array_key_exists( 'GLPI_ITEM_TITLE', $caseInitialDueDate )) {
            $txtItemTitle = $caseInitialDueDate[ 'GLPI_ITEM_TITLE' ];
            // reset those variables
            $resultSave = $myProcessMaker->sendVariables( $parm->input['processmaker_caseid'], array( "GLPI_ITEM_TITLE" => '') );
         }
         $parm->input['name'] = $PM_DB->escape($txtItemTitle );

         $procDef = new PluginProcessmakerProcess;
         $procDef->getFromDBbyExternalID( $caseInfo->processId );
         if (isset($parm->input['type'])) {
            $parm->input['type'] = $procDef->fields['type'];
         }

         if (isset($parm->input['itilcategories_id'])) {
            $parm->input['itilcategories_id'] = $procDef->fields['itilcategories_id'];
         }

      }
   }

   public static function plugin_item_add_processmaker($parm) {
      global $DB, $GLOBALS;

      if (isset($parm->input['processmaker_caseid'])) {
         // a case is already started for this ticket, then bind them together
         $itemType = $parm->getType(); //$myCase->getField('itemtype');
         $itemId = $parm->fields['id']; //$myCase->getField('items_id');
         $caseId = $parm->input['processmaker_caseid'];

         $myCase = new PluginProcessmakerCase;

         //can't use std add due to forced case id
         $process = new PluginProcessmakerProcess;
         $process->getFromDBbyExternalID( $parm->input['processId'] );
         $query = "INSERT INTO glpi_plugin_processmaker_cases (items_id, itemtype, id, case_num, processes_id) VALUES ($itemId, '$itemType', '$caseId', ".$parm->input['processmaker_casenum'].", '".$process->getID()."');";
         $res = $DB->query($query);

         $myCase->getFromDB( $caseId ); // reloads case from DB

         $myProcessMaker = new PluginProcessmakerProcessmaker( );
         $myProcessMaker->login( );

         $caseInfo = $myProcessMaker->getCaseInfo(  $myCase->getID(), $parm->input['processmaker_delindex']);

         // here we create a fake task that will be used to store the creator of the case
         // this is due for traceability only
         $myProcessMaker->add1stTask( $myCase->fields['itemtype'], $myCase->fields['items_id'], $caseInfo, array( 'notif' => false) ); // no notif
         // route case
         $pmRouteCaseResponse = $myProcessMaker->routeCase( $myCase->getID(), $parm->input['processmaker_delindex'] );

         // gets new case status
         $caseInfo = $myProcessMaker->getCaseInfo(  $myCase->getID(), $parm->input['processmaker_delindex']);
         // now manage tasks associated with item
         // create new tasks
         if (property_exists( $pmRouteCaseResponse, 'routing' )) {
            // now tries to get some variables to setup content for new task and to append text to solved task
            $txtForTasks = $myProcessMaker->getVariables( $myCase->getID(), array( "GLPI_ITEM_APPEND_TO_TASK",
                                                                                  "GLPI_ITEM_SET_STATUS" ) );
            $itemSetStatus = '';
            if (array_key_exists( 'GLPI_ITEM_SET_STATUS', $txtForTasks )) {
               $itemSetStatus = $txtForTasks[ 'GLPI_ITEM_SET_STATUS' ];
            }
            if (array_key_exists( 'GLPI_ITEM_APPEND_TO_TASK', $txtForTasks )) {
               $txtToAppendToTask = $txtForTasks[ 'GLPI_ITEM_APPEND_TO_TASK' ];
            } else {
               $txtToAppendToTask  = '';
            }

            // reset those variables
            $resultSave = $myProcessMaker->sendVariables( $myCase->getID(), array( "GLPI_ITEM_APPEND_TO_TASK" => '',
                                                                                   "GLPI_ITEM_SET_STATUS" => '' ) );

            // routing has been done, then solve 1st task
            $myProcessMaker->solveTask(  $myCase->getID(), $parm->input['processmaker_delindex'], array( 'txtToAppend' => $txtToAppendToTask, 'notif' => false) );

            // and create GLPI tasks for the newly created PM tasks.
            foreach ($pmRouteCaseResponse->routing as $route) {
               $myProcessMaker->addTask( $myCase->fields['itemtype'],
                                      $myCase->fields['items_id'],
                                      $caseInfo, $route->delIndex,
                                      PluginProcessmakerUser::getGLPIUserId( $route->userId ),
                                      0,
                                      $route->taskId );
            }

            if ($itemSetStatus != '') {
               $myProcessMaker->setItemStatus($myCase->fields['itemtype'], $myCase->fields['items_id'], $itemSetStatus );
            }
         }

         // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
         $myCase->update( array( 'id' => $myCase->getID(), 'case_status' => $caseInfo->caseStatus ) );
      } else {//if( array_key_exists('_head', $parm->input) ) {
              // this ticket have been created via email receiver.
         $ptnProcessToStart = "/##TicketProcess:\s*#([0-9a-f]{32})\s*##/i";
         $str = $parm->input['content'];
         if (preg_match($ptnProcessToStart, $str, $matches) > 0) {
            // and it is requested to start a case of process
            $processId = $matches[1];
            $hasCase = self::getCaseIdFromItem( 'Ticket', $parm->fields['id'] );
            if ($hasCase === false && $processId > 0) {
               // check writer
               $writer = new User;
               $writer->getFromDB( $parm->fields['users_id_recipient'] );
               $myProcessMaker = new PluginProcessmakerProcessmaker();
               $myProcessMaker->login( $writer->fields['name'] );
               $locProcess = new PluginProcessmakerProcess;
               if ($locProcess->getFromDBbyExternalID( $processId )) {
                  $found = false;
                  // check rights of writer to start this $processId on the Ticket entity
                  foreach (Profile_User::getUserProfiles( $writer->getID() ) as $profID) {
                     if (in_array( $parm->fields['entities_id'], PluginProcessmakerProcess::getEntitiesForProfileByProcess( $locProcess->getID(), $profID, true) )) {
                        $found=true;
                        break;
                     }
                  }
                  if ($found) {
                     $resultCase = $myProcessMaker->startNewCase( $locProcess->getID(), 'Ticket', $parm->fields['id'], $writer->getID()  );
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
   public static function addWatcher( $itemType, $itemId, $techId ) {
      global $CFG_GLPI; // currently use $CFG_GLPI to disable notif
      //$plug = new Plugin;
      if ($techId && $itemType != '' && $itemId > 0) { //!$plug->isActivated('arbehaviours') &&
         $glpi_item = getItemForItemtype( $itemType );
         $glpi_item->getFromDB( $itemId );

         // then we should check if this user has rights on the item, if not then we must add it to the watcher list!
         $glpi_item = getItemForItemtype( $itemType );
         $glpi_item->getFromDB( $itemId );
         $user_entities = Profile_User::getUserEntities( $techId, true, true );
         $user_can_view = in_array( $glpi_item->fields['entities_id'], $user_entities );
         if (!$glpi_item->isUser( CommonITILActor::REQUESTER, $techId )
               && !$glpi_item->isUser( CommonITILActor::OBSERVER, $techId )
               && !$glpi_item->isUser( CommonITILActor::ASSIGN, $techId )
               && !$user_can_view ) {
            // then we must add this tech user to watcher list
            $glpi_item_user = getItemForItemtype( $glpi_item->getType() . "_User" );
            $donotif = $CFG_GLPI["use_mailing"];
            $CFG_GLPI["use_mailing"] = false;
            $glpi_item_user->add( array( $glpi_item::getForeignKeyField() => $glpi_item->getId(), 'users_id' => $techId, 'type' => CommonITILActor::OBSERVER, '_disablenotif' => true ) ); // , '_no_notif' => true
            $CFG_GLPI["use_mailing"]= $donotif;
            return true;
         }
      }
      return false;
   }



   /**
   * Summary of addTask
   *      adds a GLPI task to given item
   * @param $itemType string item type to which a task will be added
   * @param $itemId integer item id to which a task will be added
   * @param $caseInfo mixed getCaseInfoResponse object (see: getCaseInfo() function)
   * @param $delIndex integer index of the delegation
   * @param $techId integer GLPI user id to which a task must be assigned, if == 0 will use $groupId and/or $pmTaskId
   * @param groupId string PM group guid to assign to task, used when                      $techId  == 0
   * @param pmTaskId string PM task guid, used when                                        $groupId == 0 AND $techID == 0
   * @param $options array of options, default values are
   *            'txtTaskContent' => '',
   *            'start_date'     => '',
   *            'end_date'       => '',
   *            'notif'          => true
   * @return
   */
   public function addTask( $itemType, $itemId,  $caseInfo, $delIndex, $techId, $groupId, $pmTaskId, $options=array() ) {
      global $DB, $PM_DB, $LANG, $CFG_GLPI, $_SESSION;

      $default_options = array(
        'txtTaskContent' => '',
        'start_date'     => '',
        'end_date'       => '',
        'notif'          => true
        );
      foreach ($default_options as $key => $opt) {
         if (!isset($options[$key])) {
            $options[$key] = $opt;
         }
      }

      $glpi_task = getItemForItemtype( "{$itemType}Task" );
      $glpi_task->getEmpty();

      $input = array(); // will contain all data for the Task

      $input[getForeignKeyFieldForItemType($itemType)] = $itemId;
      // search for task category
      //
      $pmtaskcat = new PluginProcessmakerTaskCategory;
      $pmtaskcat->getFromDBbyExternalID( $pmTaskId );
      $input['taskcategories_id'] = $pmtaskcat->fields['taskcategories_id'];
      // load process information
      $pmProcess = new PluginProcessmakerProcess;
      $pmProcess->getFromDB( $pmtaskcat->fields['processes_id'] );

      if ($options['start_date'] == '') {
         $options['start_date'] = new DateTime( $_SESSION["glpi_currenttime"] );
      } else {
         $options['start_date'] = new DateTime( $options['start_date'] );
      }

      $input['begin'] = $options['start_date']->format("Y-m-d H:i:s");

      if ($options['end_date'] == '' || $options['end_date'] <= $input['begin']) {
         $options['end_date'] = clone $options['start_date'];
         $options['end_date']->add( new DateInterval('PT15M') );
      } else {
         $options['end_date'] = new DateTime( $options['end_date'] );
      }
      $input['end'] = $options['end_date']->format("Y-m-d H:i:s");
      $input['plan']['begin'] = $input['begin'];
      $temp = $options['start_date']->diff( $options['end_date'] );
      $input['plan']['_duration'] = $temp->d * DAY_TIMESTAMP + $temp->h * HOUR_TIMESTAMP + $temp->i * MINUTE_TIMESTAMP + $temp->s;
      if ($input['plan']['_duration'] == 0) {
         $input['plan']['_duration'] = 60; // at least
      }

      $input['users_id'] = $this->taskWriter;

      // manage groups
      if ($techId == 0) { // then we must look-up DB to get the pseudo-group that will be assigned to the task
         $groupname='';
         if ($groupId == 0) {
            $query = "SELECT CONTENT.CON_VALUE FROM TASK_USER
                            JOIN CONTENT ON CONTENT.CON_ID=TASK_USER.USR_UID AND CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG = 'en'
                            WHERE TASK_USER.TAS_UID='$pmTaskId' AND TASK_USER.TU_RELATION=2 LIMIT 1;";
         } else {
            $query = "SELECT CON_VALUE FROM CONTENT
                            WHERE CONTENT.CON_ID='$groupId' AND CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG='en' ;";
         }
         // as there is a LIMIT of 1
         // or
         // as there is only one group per guid
         // then we should have at maximun 1 record
         foreach ($PM_DB->request($query) as $onlyrec) {
            $groupname = $onlyrec['CON_VALUE'];
         }

         $groups_id_tech = 0;
         $query = "SELECT id AS glpi_group_id FROM glpi_groups WHERE name LIKE '$groupname';";
         $res = $DB->query($query);
         if ($DB->numrows($res) > 0) {
            $row = $DB->fetch_array( $res );
            $groups_id_tech = $row['glpi_group_id'];
         }

      } else {
         // adds the user tech to ticket watcher if neccessary
         self::addWatcher( $itemType, $itemId, $techId );
      }

      // manage task description
      $input['content'] = ""; // by default empty :)
      if ($options['txtTaskContent'] != '') {
         $input['content'] = $options['txtTaskContent'];
      } else if (!$pmProcess->fields["hide_case_num_title"]) {
         $input['content'] = $LANG['processmaker']['item']['task']['case'].$caseInfo->caseName;
      }

      if ($pmProcess->fields["insert_task_comment"]) {
         if ($input['content'] != '') {
            $input['content'] .= "\n";
         }
         $input['content'] .= $LANG['processmaker']['item']['task']['comment'];
      }
      if ($input['content'] != '') {
         $input['content'] .= "\n";
      }
      $input['content'] .= $LANG['processmaker']['item']['task']['manage'];

      $input['is_private'] = 0;
      $input['actiontime'] = 0;
      $input['state'] = 1; // == TO_DO
      $input['users_id_tech'] = 0; // by default as it can't be empty
      if ($techId) {
         $input['users_id_tech'] = $techId;
      } else if ($groups_id_tech) {
         $input['groups_id_tech'] = $groups_id_tech;
      }

      $donotif = $CFG_GLPI["use_mailing"];
      if (!$options['notif']) {
         //$input['_no_notif'] = true;
         $CFG_GLPI["use_mailing"] = false;
      }
      $glpi_task->add( Toolbox::addslashes_deep( $input ) );
      $CFG_GLPI["use_mailing"] = $donotif;

      if ($glpi_task->getId() > 0) {
         // stores link to task in DB
         $query = "INSERT INTO glpi_plugin_processmaker_tasks (items_id, itemtype, case_id, del_index) VALUES (".$glpi_task->getId().", '".$glpi_task->getType()."', '".$caseInfo->caseId."', ".$delIndex.");";
         $DB->query( $query );
      }
   }

   /**
   * Summary of add1stTask
   *      adds a GLPI task to ticket
   * @param $intemType string itemtype of object (Ticket, Problem, ....)
   * @param $itemId integer item id to which a task will be added
   * @param $caseInfo mixed getCaseInfoResponse object (see: getCaseInfo() function)
   * @param $options array of options, defaults are:
   *           'txtTaskContent' => '', is content of the task
   *           'userId'         => false, is user id to be assigned to task
   *           'notif'          => true, if true notifications will be sent
   * @return
   */
   public function add1stTask ( $itemType, $itemId, $caseInfo, $options=array() ) {

      $default_options = array(
        'txtTaskContent' => '',
        'userId'         => false,
        'notif'          => true
        );
      foreach ($default_options as $key => $opt) {
         if (!isset($options[$key])) {
            $options[$key] = $opt;
         }
      }
      $start_date = new DateTime( $_SESSION["glpi_currenttime"] );
      $official_date_time = $_SESSION["glpi_currenttime"];
      $_SESSION["glpi_currenttime"] = $start_date->sub(new DateInterval("PT1S"))->format("Y-m-d H:i:s");
      $userId = $options['userId'] ? $options['userId'] : Session::getLoginUserID();
      unset($options['userId']); // unset it as it's not in the options of addTask

      $this->addTask( $itemType,
                      $itemId,
                      $caseInfo,
                      $caseInfo->currentUsers[0]->delIndex,
                      $userId,
                      0,
                      $caseInfo->currentUsers[0]->taskId,
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
   public function setItemStatus( $itemtype, $itemId, $newstatus ) {
      $item = getItemForItemtype( $itemtype );
      if ($item->getFromDB( $itemId )) { //&& $itemtype::isAllowedStatus( $item->fields['status'], $newstatus )) {
          //$item->fields['status'] = $newstatus ;
          $item->update( array('id' => $item->getID(), 'status' => $newstatus) );
      }
   }


   /**
    * Summary of computeTaskDuration
    * @param mixed $task 
    * @param mixed $entity 
    * @return mixed
    */
   function computeTaskDuration( $task, $entity ) {

      if (isset($task->fields['id']) && !empty($task->fields['begin'])) {
         $calendars_id = Entity::getUsedConfig('calendars_id', $entity);
         $calendar     = new Calendar();

         // Using calendar
         if ($calendars_id>0 && $calendar->getFromDB($calendars_id)) {
            return max(0, $calendar->getActiveTimeBetween($task->fields['begin'],
                                                   $_SESSION["glpi_currenttime"]));
         }
         // Not calendar defined
         return max(0, strtotime($_SESSION["glpi_currenttime"])-strtotime($task->fields['begin']));
      }
      return 0;
   }

   /**
    * Summary of reassignTask
    * @param mixed $caseId
    * @param mixed $delIndex
    * @param mixed $newDelIndex
    * @param mixed $newTech
   */
   public function reassignTask ( $caseId, $delIndex, $newDelIndex, $newTech) {
      global $DB, $CFG_GLPI; // $CFG_GLPI is only used to _disablenotif

      $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
      $res = $DB->query($query);
      if ($DB->numrows($res) > 0) {
         $row = $DB->fetch_array( $res );
         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB( $row['items_id'] );

         $itemType = str_replace( 'Task', '', $row['itemtype'] );
         $foreignkey = getForeignKeyFieldForItemType( $itemType );

         //$glpi_item = getItemForItemtype( $itemType );
         //$glpi_item->getFromDB( $glpi_task->fields[ getForeignKeyFieldForItemType( $itemType ) ] ) ;

         //$plug = new Plugin;
         //if( !$plug->isActivated('arbehaviours') ) { // check is done during Task update in this plugin
         //    $user_entities = Profile_User::getUserEntities( $newTech, true, true ) ;
         //    $user_can_view = in_array( $glpi_item->fields['entities_id'], $user_entities );
         //    if( !$glpi_item->isUser( CommonITILActor::REQUESTER, $newTech ) && !$glpi_item->isUser( CommonITILActor::OBSERVER, $newTech ) && !$glpi_item->isUser( CommonITILActor::ASSIGN, $newTech ) && !$user_can_view ) {
         //        // then we must add this tech user to watcher list
         //        $glpi_item_user = getItemForItemtype( "{$itemType}_User" );
         //        $donotif = $CFG_GLPI["use_mailing"] ;
         //        $CFG_GLPI["use_mailing"] = false;
         //        $glpi_item_user->add( array( $glpi_item->getForeignKeyField() => $glpi_item->getId() , 'users_id' => $newTech, 'type' => CommonITILActor::OBSERVER ) ) ; // , '_no_notif' => true
         //        $CFG_GLPI["use_mailing"] = $donotif;
         //    }
         //}

         self::addWatcher( $itemType, $glpi_task->fields[ $foreignkey ], $newTech );

         $glpi_task->update( array( 'id' => $row['items_id'], $foreignkey => $glpi_task->fields[ $foreignkey ],  'users_id_tech' => $newTech ));

         // this is now done into GLPI core
         //if( !$user_can_view ) {
         //    // to cheat the entity rigths, passes default user_entity to raiseEvent(), to be sure that task_tech will receive a notification.
         //    // drawback: notifications that are entity based could be missing.
         //    // tip: $user_entities[0] is the user's default entity
         //    NotificationEvent::raiseEvent('update_task', $glpi_item, array( 'entities_id' => $user_entities[0], 'task_id' => $glpi_task->getId(), 'is_private' => 0 ) ) ;
         //}

         // then update the delIndex
         $query = "UPDATE glpi_plugin_processmaker_tasks SET del_index = $newDelIndex WHERE case_id='$caseId' and del_index=$delIndex; ";
         $res = $DB->query($query);
      }
   }

   /**
    * Summary of solveTask
    * @param string $caseId
    * @param int    $delIndex
    * @param array  $options
    *                'txtToAppend' => ''   : text to append to solved task
    *                'notif'       => true : if true will send notifications
    *                'users_id_tech'   => is the users_id of the tech that solved the task
   */
   public function solveTask( $caseId, $delIndex, $options=array() ) {
      global $DB, $CFG_GLPI;

      // change current glpi_currenttime to be sure that date_mode for solved task will not be identical than date_mode of the newly started task
      $start_date = new DateTime( $_SESSION["glpi_currenttime"] );
      $official_date_time = $_SESSION["glpi_currenttime"];
      $_SESSION["glpi_currenttime"] = $start_date->sub(new DateInterval("PT1S"))->format("Y-m-d H:i:s");

      $default_options = array(
        'txtToAppend' => '',
        'notif'       => true,
        'users_id_tech' => null
        );
      foreach ($default_options as $key => $opt) {
         if (!isset($options[$key])) {
            $options[$key] = $opt;
         }
      }

      $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
      $res = $DB->query($query);
      if ($DB->numrows($res) > 0) {
         $row = $DB->fetch_array( $res );

         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB( $row['items_id'] );
         $hostItem = getItemForItemtype( $glpi_task->getItilObjectItemType() );
         $itemFKField = getForeignKeyFieldForItemType( $glpi_task->getItilObjectItemType() );
         $hostItem->getFromDB( $glpi_task->fields[ $itemFKField ] );
         $duration = $this->computeTaskDuration( $glpi_task, $hostItem->fields['entities_id'] );
         if ($options['txtToAppend'] != "") {
            $options['txtToAppend'] = "\n<hr>".$options['txtToAppend'];
         }
         $params = array( 'id' => $row['items_id'],
                        'state' => 2,
                        'end' => $_SESSION["glpi_currenttime"],
                        $itemFKField => $hostItem->getID(),
                        'actiontime' => $duration,
                        'users_id_tech' => (isset($options['users_id_tech']) ? $options['users_id_tech'] : Session::getLoginUserID()),
                        'groups_id_tech' => 0,
                        'content' => $DB->escape($glpi_task->fields[ 'content' ].$options['txtToAppend'])
                        );
         $donotif = $CFG_GLPI["use_mailing"];
         if (!$options['notif']) {
            $CFG_GLPI["use_mailing"] = false;
            //               $params['_no_notif']=true;
         }
         $glpi_task->update( $params );
         $CFG_GLPI["use_mailing"]= $donotif;
      }

      // restore current glpi time
      $_SESSION["glpi_currenttime"] = $official_date_time;

   }

   /**
    * Summary of claimTask
    * will unassign group, and assign tech
    * @param mixed $caseId
    * @param mixed $delIndex
    * @param mixed $users_id_tech optional is the id of the tech
    *                  who's claimed the task, default current logged-in user
   */
   public function claimTask( $caseId, $delIndex, $users_id_tech=null ) {
      global $DB;

      $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
      $res = $DB->query($query);
      if ($DB->numrows($res) > 0) {
         $row = $DB->fetch_array( $res );
         $glpi_task = new $row['itemtype'];
         $glpi_task->getFromDB( $row['items_id'] );

         $itemType = str_replace( 'Task', '', $row['itemtype'] );
         $glpi_item = getItemForItemtype( $itemType );
         $glpi_item->getFromDB( $glpi_task->fields[ getForeignKeyFieldForItemType( $itemType ) ] );

         $glpi_task->update( array( 'id' => $row['items_id'],
                                    $glpi_item->getForeignKeyField() => $glpi_item->getId(),
                                    'users_id_tech' => (isset($users_id_tech)?$users_id_tech: Session::getLoginUserID()),
                                    'groups_id_tech' => 0 ));
      }
   }


    /**
     * Summary of getCaseIdFromItem
     *      get case id for an id item_id of type item_type (if a case if attached to it)
     * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
     * @param integer $item_id,   the id for the item
     * @return string the case guid, false if no case is attached to item, or if an error occurred
     */
   public static function getCaseIdFromItem ($item_type, $item_id ) {
      global $DB;

      $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE `itemtype` = '$item_type' AND `items_id` = $item_id ;";
        $res = $DB->query($query);
      if ($DB->numrows($res) > 0) {
         // case is existing for this ticket
         // then get info from db
         $row = $DB->fetch_array($res);

         return $row['id'];
      }

      return false;
   }

    /**
     * Summary of getCaseFromItem
     *      get case infos for an id item_id of type item_type (if a case if attached to it)
     * @param string  $item_type, the type for the item ("Ticket", "Problem", ...)
     * @param integer $item_id,   the id for the item
     * @return getCaseInfoResponse object, false if no case is attached to item, or if an error occurred
     */
   public function getCaseFromItem( $item_type, $item_id ) {
      global $DB;

      $caseId = self::getCaseIdFromItem( $item_type, $item_id );
      if ($caseId !== false) {
         $caseInfo = $this->getCaseInfo( $caseId );
         if ($caseInfo !== false && $caseInfo->status_code == 0) {
             return $caseInfo;
         } else {
            return false; // means any error
         }
      } else {
         return false; // means no case
      }
   }

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
     * Summary of pre_show_item_processmakerticket
     * @param $parm
     */
    //private $pmCurrentCaseID = null ;

   public static function pre_show_item_processmakerticket($params) {
      //global $LANG;

      //$plug = new Plugin;

      if (!is_array($params['item']) && is_subclass_of( $params['item'], 'CommonITILTask')) {
         // must check if Task is bound to a PM task
         $pmTask = new PluginProcessmakerTask($params['item']->getType());
         if ($pmTask->getFromDBByQuery("WHERE itemtype='".$params['item']->getType()."' and items_id=".$params['item']->getId())) {
            //echo 'Test' ;
            $params['item']->fields['can_edit'] = false; // to prevent task edition

            // replace ##ticket.url##_PluginProcessmakerCase$processmakercases by a setActiveTab to the Case panel
            $taskCat = new TaskCategory;
            $taskCat->getFromDB( $params['item']->fields['taskcategories_id'] );
            $taskComment = isset($taskCat->fields['comment']) ? $taskCat->fields['comment'] : '';
            if (Session::haveTranslations('TaskCategory', 'comment')) {
               $params['item']->fields['content'] = str_replace( '##processmaker.taskcomment##',
                  DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskComment ), $params['item']->fields['content'] );
            } else {
               $params['item']->fields['content'] = str_replace( '##processmaker.taskcomment##', $taskComment, $params['item']->fields['content'] );
            }
            $taskJSId = "viewitem".$params['item']->getType().$params['item']->getId().$params['options']['rand'];
            echo Html::scriptBlock( "
                  $('#$taskJSId').on('click', function ( ) {
                     //debugger;
                     var tabindex = $('#tabspanel').next('div').find('a[href*=\"_glpi_tab=PluginProcessmaker\"]').parent().index();
                     $('#tabspanel').next('div').tabs( 'option', 'active', tabindex );
                  } ).css('cursor', 'pointer') ;
                 ");
            $params['item']->fields['content'] = str_replace( '##ticket.url##_PluginProcessmakerCase$processmakercases', "", $params['item']->fields['content'] ); //"<a href=\"javascript:pmActiveTab( );\">".$LANG['processmaker']['item']['task']['manage_text']."</a>"

            if (isset( $params['item']->fields['tr_id'] )) {
               $trID = $params['item']->fields['tr_id'];
               //$params['item']->fields['content'] .= "<script>var loc$trID = document.getElementById('$trID'); loc$trID.style.cursor = 'pointer'; if (loc$trID.addEventListener) { loc$trID.addEventListener('click', function(){tabpanel.setActiveTab('PluginProcessmakerCase\$processmakercases');}, false);} else {loc$trID.attachEvent('onclick', function(){tabpanel.setActiveTab('PluginProcessmakerCase\$processmakercases');});  } </script>";
            }
         }
      }

      //if( ($plug = new Plugin)  && !$plug->isActivated('arbehaviours') ) {
      //   if ($params['item']->getID() && is_subclass_of( $params['item'], 'CommonITILObject')) {
      //        // then we are in a ticket
      //        if (isset($_REQUEST['glpi_tab']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk"  ) {
      //            $data     = self::multiexplode(array('$','_'), $_REQUEST['glpi_tab']);
      //            $itemtype = $data[0];
      //            // Default set
      //            $tabnum   = 1;
      //            if (isset($data[1])) {
      //                $tabnum = $data[1];
      //            }
      //            elseif( $itemtype == -1 )
      //                $tabnum = -1 ;

      //            if( $data[0] == "processmaker" && $tabnum == 1 ) {

      //            }
      //            if( ($data[0] == "Ticket" && $tabnum == 2) || $tabnum == -1) {
      //                // then we are showing the Solution tab
      //                // then we must prevent solving of ticket if a case is running
      //               if( !PluginProcessmakerCase::canSolve( $params['item'] ) ) {
      //                    // then output a new div to hide solution
      //                    $pmHideSolution = true ;
      //                    echo "<div id='toHideSolution' style='display: none;'>" ;
      //                }
      //            }
      //        }
      //    }
      //}
   }

    /**
     * Summary of pre_show_tab_arbehaviours
     * @param array $params info about tab to be shown
     * @return void
     */
   static function pre_show_tab_processmaker($params) {
      global $LANG, $pmHideSolution;
      $plugin = new Plugin();
      $itemtype = $params['item']->getType();
      switch ($itemtype) {
         case 'Ticket':
         case 'Problem':
         case 'Change':
            if ($params['options']['id']) {
               // then we are in an ITIL Object
               if (isset($_SESSION['glpiactiveprofile']['interface']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk") {
                  $tabnum = $params['options']['tabnum'];
                  // tabnum 1 : Processing Ticket,  tabnum 2 : Solution

                  if ($tabnum == 2 or $tabnum == 1) {
                        // we must check if we can solve item even if PM case is still running (ex: PIR tasks for Change Management)
                        $pmCanSolve = PluginProcessmakerCase::canSolve( $params );
                     if (!$pmCanSolve) {
                        // don't display message if arbehaviours is install
                        if (!($plugin->isInstalled('arbehaviours') && $plugin->isActivated('arbehaviours'))) {
                           $messageOne = $LANG['processmaker']['item']['preventsolution'][1];
                           $messageTwo = $LANG['processmaker']['item']['preventsolution'][2];
                           // output explicit message to explain why it's not possible to add solution

                           $message = "<div style='margin-bottom: 20px;' class='box'>
                                    <div class='box-tleft'>
                                        <div class='box-tright'>
                                            <div class='box-tcenter'>
                                            </div>
                                        </div>
                                    </div>
                                    <div class='box-mleft'>
                                        <div class='box-mright'>
                                            <div class='box-mcenter'>
                                                <h3>
                                                    <span class='red'>".$messageOne."
                                                        <br>
                                                    </span>
                                                </h3>
                                                <h3>
                                                <span >".$messageTwo."
                                                    </span>
                                                </h3>
                                            </div>
                                         </div>
                                     </div>
                                     <div class='box-bleft'>
                                        <div class='box-bright'>
                                            <div class='box-bcenter'>
                                            </div>
                                        </div>
                                     </div>
                                  </div>  ";
                        }

                        $pmHideSolution = true;
                        $itemtype = strtolower($itemtype);
                        if ($tabnum == 1 && isset($_SESSION['glpiactiveprofile'][$itemtype.'_status'])) {
                           // don't display message if arbehaviours is install
                           if (!($plugin->isInstalled('arbehaviours') && $plugin->isActivated('arbehaviours'))) {
                              self::displayMessage($message, '', WARNING);

                              //save current  $_SESSION['glpiactiveprofile'][$itemtype.'_status'']
                              $_SESSION['glpiactiveprofile'][$itemtype.'_status_save'] = $_SESSION['glpiactiveprofile'][$itemtype.'_status'];
                              // for all $params['options']['itemtype']. status, disable solved ( life cycles )
                              foreach ($_SESSION['glpiactiveprofile'][$itemtype.'_status'] as $key => $value) {
                                 $_SESSION['glpiactiveprofile'][$itemtype.'_status'][$key][CommonITILObject::SOLVED] = 0;
                              }
                           }
                        } else {
                           // then output a new div and hide solution for content
                           echo $message;
                           echo "<div id='toHideSolution' style='display: none;'>";
                        }
                     }
                  }
               }
            }

      }
   }


   public static function post_show_tab_processmaker($params) {
      global $pmHideSolution;

      $itemtype = $params['item']->getType();
      switch ($itemtype) {

         case 'Ticket':
         case 'Problem':
         case 'Change':
            if ($params['options']['id']) {
               // then we are in an itil object
               if (isset($_SESSION['glpiactiveprofile']['interface']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk") {
                  $tabnum = $params['options']['tabnum'];

                  if ($tabnum == 2 or $tabnum == 1) {
                     // then we are showing the Solution tab or Processing Ticket tab

                     if ($pmHideSolution) {
                        echo "</div>";

                     }
                     $itemtype = strtolower($itemtype);
                     // replace $_SESSION['glpiactiveprofile'][$itemtype.'_status'] with saved value
                     if ($tabnum == 1 && isset($_SESSION['glpiactiveprofile'][$itemtype.'_status_save'])) {
                        $_SESSION['glpiactiveprofile'][$itemtype.'_status'] = $_SESSION['glpiactiveprofile'][$itemtype.'_status_save'];
                     }

                  }
               }

            }
            break;

      }

   }


    ///**
    // * Summary of post_show_item_processmakerticket
    // * @param $parm
    // */
    //public static function post_show_item_processmakerticket($parm) {
    //    global $LANG, $pmHideSolution;
    //    if( ($plug = new Plugin)  && !$plug->isActivated('arbehaviours') ) {
    //        if ($parm->getID() && in_array($parm->getType(), array('Ticket'))) {
    //            // then we are in a ticket
    //            if (isset($_REQUEST['glpi_tab']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk" ) {
    //                $data     = explode('$', $_REQUEST['glpi_tab']);
    //                $itemtype = $data[0];
    //                // Default set
    //                $tabnum   = 1;
    //                if (isset($data[1])) {
    //                    $tabnum = $data[1];
    //                }
    //                elseif ($itemtype == -1 )
    //                    $tabnum = -1 ;

    //                if( $tabnum == 2 || $tabnum == -1 ) {
    //                    // then we are showing the Solution tab
    //                    // if a case is running
    //                    // then we must prevent solution to be input
    //                    if( $pmHideSolution ) { //isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) && $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1 ) { //if( $pmInfo !== null && ( $pmInfo->caseStatus != 'COMPLETED' && $pmInfo->caseStatus != 'CANCELLED' )) {

    //                        echo "</div>";
    //                        if( $tabnum == -1 )
    //                            echo "</div>";
    //                        echo "<div style='margin-bottom: 20px;' class='box'>
    //                                <div class='box-tleft'>
    //                                    <div class='box-tright'>
    //                                        <div class='box-tcenter'>
    //                                        </div>
    //                                    </div>
    //                                </div>
    //                                <div class='box-mleft'>
    //                                    <div class='box-mright'>
    //                                        <div class='box-mcenter'>
    //                                            <h3>
    //                                                <span class='red'>".$LANG['processmaker']['item']['preventsolution'][1]."
    //                                                    <br>
    //                                                </span>
    //                                            </h3>
    //                                            <h3>
    //                                            <span >".$LANG['processmaker']['item']['preventsolution'][2]."
    //                                                </span>
    //                                            </h3>
    //                                        </div>
    //                                     </div>
    //                                 </div>
    //                                 <div class='box-bleft'>
    //                                    <div class='box-bright'>
    //                                        <div class='box-bcenter'>
    //                                        </div>
    //                                    </div>
    //                                 </div>
    //                              </div>  ";
    //                    }
    //                }
    //            }
    //        }
    //    }
    //}

    ///**
    // * Summary of canedit_item_processmakertickettask
    // * @param $parm
    // */
    //public static function canedit_item_processmakertickettask($parm) {
    //    global $DB, $LANG, $_SESSION ;
    //    // must check if Task is bound to a PM task
    //    $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE itemtype='".$parm->getType()."' and items_id=".$parm->getId().";" ;
    //    $res = $DB->query($query) ;
    //    if( $DB->numrows($res) > 0) {
    //        $parm->fields['plugin_canedit'] = false ; // to prevent task edition
    //        // replace ##ticket.url##_PluginProcessmakerCase$processmakercases by a setActiveTab to the Case panel
    //        $taskCat = new TaskCategory ;
    //        $taskCat->getFromDB( $parm->fields['taskcategories_id'] ) ;
    //        $taskComment = (isset($taskCat->fields['comment'])?$taskCat->fields['comment']:'') ;
    //        if( Session::haveTranslations('TaskCategory', 'comment') ) {
    //           $parm->fields['content'] = str_replace( '##processmaker.taskcomment##', DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskComment ), $parm->fields['content'] ) ;
    //        } else {
    //           $parm->fields['content'] = str_replace( '##processmaker.taskcomment##', $taskComment, $parm->fields['content'] ) ;
    //        }
    //        $parm->fields['content'] = str_replace( '##ticket.url##_PluginProcessmakerCase$processmakercases', "<a href=\"javascript:tabpanel.setActiveTab('PluginProcessmakerCase\$processmakercases');\">".$LANG['processmaker']['item']['task']['manage_text']."</a>", $parm->fields['content'] ) ;
    //        if( isset( $parm->fields['tr_id'] ) ) {
    //            $trID = $parm->fields['tr_id'] ;
    //            $parm->fields['content'] .= "<script>var loc$trID = document.getElementById('$trID'); loc$trID.style.cursor = 'pointer'; if (loc$trID.addEventListener) { loc$trID.addEventListener('click', function(){tabpanel.setActiveTab('PluginProcessmakerCase\$processmakercases');}, false);} else {loc$trID.attachEvent('onclick', function(){tabpanel.setActiveTab('PluginProcessmakerCase\$processmakercases');});  } </script>";
    //        }
    //    }
    //}

    /**
     * Summary of getItemUsers
     * returns an array of glpi ids and pm ids for each user type assigned to given ticket
     * @param string  $itemtype
     * @param integer $itemId   is the ID of the titem
     * @param integer $userType is 1 for ticket requesters, 2 for ticket technicians, and if needed, 3 for watchers
     * @return array of users in the returned array
     */
   public static function getItemUsers( $itemtype, $itemId, $userType ) {
        global $DB;

      $users = array( );

      //$itemtable = getTableForItemType( $itemtype ) ;
      $item = new $itemtype();
      $item_users = $item->userlinkclass;
      $item_userstable = getTableForItemType( $item_users );
      $itemlink = getForeignKeyFieldForItemType( $itemtype );

        $query = "select glpi_plugin_processmaker_users.pm_users_id as pm_users_id, glpi_plugin_processmaker_users.id as id from $item_userstable
				left join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.id = $item_userstable.users_id
				where $item_userstable.$itemlink = $itemId and $item_userstable.type = $userType
                order by $item_userstable.id";
      foreach ($DB->request( $query ) as $dbuser) {
         $users[] = array( 'glpi_id' => $dbuser['id'], 'pm_id' => $dbuser['pm_users_id'] );
      }

        return $users;
   }

    /**
     * Summary of saveForm
     * This function posts dynaform variables to PM, using the CURL module.
     * @param mixed $request: is the $_REQUEST server array
     * //@param string $cookie: is the $_SERVER['HTTP_COOKIE'] string
     * @return mixed: returns false if request failed, otherwise, returns true
     */
   public function saveForm( $request ) {
      //, $cookie ) {

      if (!function_exists( 'HandleHeaderLine' )) {
         function HandleHeaderLine( $curl, $header_line ) {
              //global $cookies;
            $temp = explode( ": ", $header_line );
            if (is_array( $temp ) && $temp[0] == 'Set-Cookie') {
               $temp2 = explode( "; ", $temp[1]);
               //$cookies .= $temp2[0].'; ' ;
               curl_setopt($curl, CURLOPT_COOKIE, $temp2[0]."; " );
            }
            return strlen($header_line);
         }
      }
      $request = stripcslashes_deep( $request );

      $data = http_formdata_flat_hierarchy( $request );

      $ch = curl_init();

      //to be able to trace network traffic with a local proxy
        // curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1 ) ;
      //curl_setopt($ch, CURLOPT_PROXY, "localhost:8888");
        curl_setopt($ch, CURLOPT_HEADER, 1);
      //		curl_setopt($ch, CURLOPT_VERBOSE, 1);
      //		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_Open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$request['APP_UID']."&DEL_INDEX=".$request['DEL_INDEX']."&action=TO_DO" );
      $response = curl_exec ($ch);
        //Toolbox::logInFile( "pmtrace", "URL:\n".$this->serverURL."/cases/cases_Open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."\nResponse:\n".$response."\n\n\n" ) ;

      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

      curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID'] );

      $response = curl_exec ($ch);

      curl_close ($ch);
        //Toolbox::logInFile( "pmtrace", "URL:\n".$this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID']."\nData:\n".print_r($data, true )."\nResponse:\n".$response."\n\n\n" ) ;

      return ($response ? true : false);

      //$n = preg_match("/HTTP\/1.1 302 /", $response, $matches);

      //return ($n < 1 ? false : true) ;
   }

    /**
     * Summary of plugin_item_get_datas_processmaker
     * @param mixed $item
     */
   public static function plugin_item_get_datas_processmaker($item) {
      global $_SESSION;
      if (isset( $item->datas ) && isset( $item->datas['tasks'] )) {
         foreach ($item->datas['tasks'] as &$task) { // we must check if task category is PM task category or not, if yes then we add task category comment to datas
            $task['##task.description##'] = str_replace( '##processmaker.taskcomment##', $task['##task.categorycomment##'], $task['##task.description##'] );
         }
      }

   }


      /**
       * Summary of plugin_item_get_pdfdatas_processmaker
       * @param mixed $item
       */
   public static function plugin_item_get_pdfdatas_processmaker($item) {
      if (isset( $item->datas )) {
         $config = PluginProcessmakerConfig::getInstance();
         $taskCat = new TaskCategory;

         // save current translations
         if (isset( $_SESSION['glpi_dropdowntranslations'] )) {
            $trans = $_SESSION['glpi_dropdowntranslations'];
         }
         // load available translations for this user
         $_SESSION['glpi_dropdowntranslations'] = DropdownTranslation::getAvailableTranslations($_SESSION['glpilanguage']);

         $taskCat->getFromDB( $item->datas['taskcategories_id'] );
         $ancestors = getAncestorsOf( 'glpi_taskcategories', $item->datas['taskcategories_id']);
         if (in_array( $config->fields['taskcategories_id'], $ancestors)) {
            $loc_completename = DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'completename', $_SESSION['glpilanguage'], $taskCat->fields['completename'] );
            $loc_comment = DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskCat->fields['comment'] );
            $item->datas['content'] = $loc_completename."\n\n".str_replace( "##processmaker.taskcomment##\n##ticket.url##_PluginProcessmakerCase\$processmakercases", $loc_comment, $item->datas['content']);
         }
         // restore default translations
         if (isset( $trans )) {
            $_SESSION['glpi_dropdowntranslations'] = $trans;
         } else {
            unset( $_SESSION['glpi_dropdowntranslations']  );
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
   public static function getProcessesWithCategoryAndProfile( $category, $type, $profile, $entity ) {
      global $DB;

      $processList = array( );
      $entityAncestors = implode( ", ", getAncestorsOf( getTableForItemType( 'Entity' ), $entity ) );
      if (strlen( $entityAncestors ) > 0) {
         $entityAncestors = " OR (entities_id IN ($entityAncestors) AND is_recursive = 1) ";
      }

      $query ="SELECT DISTINCT glpi_plugin_processmaker_processes.id, glpi_plugin_processmaker_processes.name FROM glpi_plugin_processmaker_processes
            INNER JOIN glpi_plugin_processmaker_processes_profiles ON glpi_plugin_processmaker_processes_profiles.processes_id=glpi_plugin_processmaker_processes.id
            WHERE is_active = 1 AND itilcategories_id = $category AND `type` = $type AND profiles_id = $profile  AND (entities_id = $entity $entityAncestors)";

      foreach ($DB->request( $query ) as $row) {
         $processList[] = $row;
      }

      return $processList;

   }

   public function startNewCase( $processId, $itemType, $itemId, $userId=null ) {
      global $DB, $CFG_GLPI;

      $requesters = PluginProcessmakerProcessmaker::getItemUsers( $itemType, $itemId, CommonITILActor::REQUESTER); // 1 for requesters
      if (!key_exists( 0, $requesters )) {
         $requesters[0]['glpi_id'] = 0;
         $requesters[0]['pm_id'] = 0;
      }

      $impersonateCase = false;
      if (!$userId) {
         // then we are going to take the item creator to set it as case creator and
         $impersonateCase = true;

      }

      // get item info to retreive title, description and duedate
      $locItem = new $itemType; // $_POST['itemtype'] ; //Ticket();
      $locItem->getFromDB( $itemId ); // $_POST['id'] ) ;

      if ($locItem->countUsers(CommonITILActor::ASSIGN) == 0
         || !$locItem->isUser(CommonITILActor::ASSIGN, $userId) ) {
         $locItem->update( array( 'id' => $itemId, '_itil_assign' => array( '_type' => 'user', 'users_id' => $userId )  ) );
      }

      if (!isset($locItem->fields['due_date']) || $locItem->fields['due_date'] == null) {
         $locItem->fields['due_date'] = "";
      }

      $resultCase = $this->newCase( $processId,
                                     array('GLPI_ITEM_CAN_BE_SOLVED'        => 0,
                                           'GLPI_TICKET_ID'                 => $itemId,
                                           'GLPI_ITEM_TYPE'                 => $itemType,
                                           'GLPI_TICKET_REQUESTER_GLPI_ID'  => $requesters[0]['glpi_id'],
                                           'GLPI_TICKET_REQUESTER_PM_ID'    => $requesters[0]['pm_id'],
                                           'GLPI_TICKET_TITLE'              => $locItem->fields['name'],
                                           'GLPI_TICKET_DESCRIPTION'        => $locItem->fields['content'],
                                           'GLPI_TICKET_DUE_DATE'           => $locItem->fields['due_date'],
                                           'GLPI_ITEM_ITIL_CATEGORY_ID'     => $locItem->fields['itilcategories_id'],
                                           'GLPI_TICKET_URGENCY'            => $locItem->fields['urgency'],
                                           'GLPI_ITEM_IMPACT'               => $locItem->fields['impact'],
                                           'GLPI_ITEM_PRIORITY'             => $locItem->fields['priority'],
                                           'GLPI_TICKET_GLOBAL_VALIDATION'  => $locItem->fields['global_validation'] ,
                                           'GLPI_TICKET_TECHNICIAN_GLPI_ID' => $userId,
                                           'GLPI_TICKET_TECHNICIAN_PM_ID'   => PluginProcessmakerUser::getPMUserId( $userId ),
                                           'GLPI_URL'                       => $CFG_GLPI['url_base'].$CFG_GLPI['root_doc']
                                           ) );

      if ($resultCase->status_code === 0) {
          $caseInfo = $this->getCaseInfo( $resultCase->caseId );

          //$query = "UPDATE APPLICATION SET APP_STATUS='TO_DO' WHERE APP_UID='".$resultCase->caseId."' AND APP_STATUS='DRAFT'" ;
          //$res = $DB->query($query) ;
          // save info to DB
          $locCase = new PluginProcessmakerCase;
          $locCase->add( array( 'id' => $resultCase->caseId,
                                'items_id' => $itemId,
                                'itemtype' => $itemType,
                                'case_num' => $resultCase->caseNumber,
                                'case_status' => $caseInfo->caseStatus,
                                'processes_id' => $processId //$caseInfo->processId
                        ), true );
          //$query = "INSERT INTO glpi_plugin_processmaker_cases (items_id, itemtype, id, case_num, case_status, processes_id) VALUES ($itemId, '$itemType', '".$resultCase->caseId."', ".$resultCase->caseNumber.", '".$caseInfo->caseStatus."', '".$caseInfo->processId."');" ;
          //$res = $DB->query($query) ;

         $this->add1stTask($itemType, $itemId, $caseInfo, array( 'userId' => $userId ) );
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
   public function derivateCase( $myCase, $request, $users_id=null ) {
      //$cookies,
      global $PM_DB;

      // save the case variables
      $resultSave = $this->saveForm( $request );

      // now derivate the case !!!
      $pmRouteCaseResponse = $this->routeCase( $myCase->getID(), $request['DEL_INDEX']);

      // now tries to get some variables to setup content for new task and to append text to solved task
      $infoForTasks = $this->getVariables( $myCase->getID(), array( "GLPI_ITEM_TASK_CONTENT",
                                                                             "GLPI_ITEM_APPEND_TO_TASK",
                                                                             "GLPI_NEXT_GROUP_TO_BE_ASSIGNED",
                                                                             "GLPI_ITEM_TITLE",
                                                                             "GLPI_TICKET_FOLLOWUP_CONTENT",
                                                                             "GLPI_TICKET_FOLLOWUP_IS_PRIVATE",
                                                                             "GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID",
                                                                             "GLPI_ITEM_TASK_ENDDATE",
                                                                             "GLPI_ITEM_TASK_STARTDATE",
                                                                             "GLPI_ITEM_SET_STATUS"
                                                                             ) );
      $itemSetStatus = '';
      if (array_key_exists( 'GLPI_ITEM_SET_STATUS', $infoForTasks )) {
         $itemSetStatus = $infoForTasks[ 'GLPI_ITEM_SET_STATUS' ];
      }

      $txtItemTitle  = '';
      if (array_key_exists( 'GLPI_ITEM_TITLE', $infoForTasks )) {
         $txtItemTitle = $infoForTasks[ 'GLPI_ITEM_TITLE' ];
      }

      $txtToAppendToTask  = '';
      if (array_key_exists( 'GLPI_ITEM_APPEND_TO_TASK', $infoForTasks )) {
         $txtToAppendToTask = $infoForTasks[ 'GLPI_ITEM_APPEND_TO_TASK' ];
      }

      $txtTaskContent = '';
      if (array_key_exists( 'GLPI_ITEM_TASK_CONTENT', $infoForTasks )) {
         $txtTaskContent = $infoForTasks[ 'GLPI_ITEM_TASK_CONTENT' ];
      }

      $groupId = 0;
      if (array_key_exists( 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED', $infoForTasks )) {
         $groupId = $infoForTasks[ 'GLPI_NEXT_GROUP_TO_BE_ASSIGNED' ];
      }

      $taskStartDate = '';
      $taskEndDate = '';
      if (array_key_exists( 'GLPI_ITEM_TASK_ENDDATE', $infoForTasks )) {
         $taskEndDate = $infoForTasks[ 'GLPI_ITEM_TASK_ENDDATE' ];
      }
      if (array_key_exists( 'GLPI_ITEM_TASK_STARTDATE', $infoForTasks )) {
         $taskStartDate = $infoForTasks[ 'GLPI_ITEM_TASK_STARTDATE' ];
         if ($taskEndDate == '') {
            // at least
            $taskEndDate = $taskStartDate;
         }
      }

      $createFollowup = false; // by default
      if (array_key_exists( 'GLPI_TICKET_FOLLOWUP_CONTENT', $infoForTasks ) && $infoForTasks[ 'GLPI_TICKET_FOLLOWUP_CONTENT' ] != '') {
         //&& array_key_exists( 'GLPI_TICKET_FOLLOWUP_IS_PRIVATE', $infoForTasks )
         //&& array_key_exists( 'GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID', $infoForTasks )
         $createFollowup = true;
      }

      // reset those variables
      $resultSave = $this->sendVariables( $myCase->getID(), array( "GLPI_ITEM_APPEND_TO_TASK" => '',
                                                           "GLPI_ITEM_TASK_CONTENT" => '',
                                                           "GLPI_NEXT_GROUP_TO_BE_ASSIGNED" => '',
                                                           "GLPI_ITEM_TITLE" => '',
                                                           "GLPI_TICKET_FOLLOWUP_CONTENT" => '',
                                                           "GLPI_TICKET_FOLLOWUP_IS_PRIVATE" => '',
                                                           "GLPI_TICKET_FOLLOWUP_REQUESTTYPES_ID" => '',
                                                           "GLPI_ITEM_TASK_ENDDATE" => '',
                                                           "GLPI_ITEM_TASK_STARTDATE" => '',
                                                           "GLPI_ITEM_SET_STATUS" => '')  );

      // print_r( $pmRouteCaseResponse ) ;
      // die() ;

      // now manage tasks associated with item
      $itemType = $myCase->getField('itemtype');
      $itemId = $myCase->getField('items_id');

      // switch own task to 'done' and create a new one
      $this->solveTask(  $myCase->getID(), $request['DEL_INDEX'], array( 'txtToAppend' => $txtToAppendToTask, 'users_id_tech' => $users_id ) );

      // create a followup if requested
      if ($createFollowup && $itemType == 'Ticket') {
         $this->addTicketFollowup( $itemId, $infoForTasks );
      }
      $caseInfo = $this->getCaseInfo(  $myCase->getID(), $request['DEL_INDEX']);
      if (property_exists( $pmRouteCaseResponse, 'routing' )) {
         foreach ($pmRouteCaseResponse->routing as $route) {
            $this->addTask( $itemType,
                                      $itemId,
                                      $caseInfo,
                                      $route->delIndex,
                                      PluginProcessmakerUser::getGLPIUserId( $route->userId ),
                                      $groupId,
                                      $route->taskId,
                                      array( 'txtTaskContent' => $txtTaskContent,
                                             'start_date'     => $taskStartDate,
                                             'end_date' => $taskEndDate)
                                    );

            // if end date was specicied, then must change due date of the PM task
            if ($taskEndDate != '') {
               $PM_DB->query( "UPDATE APP_DELEGATION SET DEL_TASK_DUE_DATE='$taskEndDate' WHERE APP_UID='".$caseInfo->caseId."' AND DEL_INDEX=".$route->delIndex);
            }
         }
      }

      if ($txtItemTitle != '') {
         // we are going to change the title of current GLPI Item
         $item = new $itemType;
         $item->getFromDB( $itemId );
         $item->update( array('id' => $itemId, 'name' => $txtItemTitle) );
      }

      if ($itemSetStatus != '') {
         $this->setItemStatus($itemType, $itemId, $itemSetStatus );
      }

      // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
      $myCase->update( array( 'id' => $myCase->getID(), 'case_status' => $caseInfo->caseStatus ) );
   }


    /**
     * Summary of getPMGroups
     * @return array
     */
   public static function getPMGroups( ) {
      global $PM_DB;
      $pmGroupList = array();
      foreach ($PM_DB->request("SELECT * FROM CONTENT WHERE CONTENT.CON_CATEGORY='GRP_TITLE' AND CONTENT.CON_LANG='en'") as $dbgroup) {
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
   static private function displayMessage($html_message, $title='',$msgtype='info_msg') {

      //set title and css class
      switch ($msgtype) {
         case ERROR:
            $title = __('Error');
            $class = 'err_msg';
            break;
         case WARNING:
            $title = __('Warning');
            $class = 'warn_msg';
            break;
         case INFO:
            $title = __('Information');
            $class = 'info_msg';
            break;
      }

      echo "<div id=\"message_after_redirect_$msgtype\" title=\"$title\">";
      echo $html_message;
      echo "</div>";

      $scriptblock = "
               $(document).ready(function() {
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
}
