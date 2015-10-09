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


///**
// * Summary of pmResponse
// *      class used to define most of Processmaker web service function call returns.
// */
//class pmResponse {
//    public $status_code ;
//    public $message ;
//    public $time_stamp ;
    
//    /**
//     * Summary of __construct
//     * @param $status
//     * @param $message
//     * @param $time_st
//     */
//    function __construct ( $status, $message, $time_st ='' ) {
//        $this->status_code = $status ;
//        $this->message = $message ;
//        if( $time_st == '' ) 
//            $this->time_stamp = date() ;
//        else
//            $this->time_stamp = $time_st ;
//    }
        
//}

    $pmHideSolution = false ;

    /**
     * Summary of http_formdata_flat_hierarchy
     * @param mixed $data 
     * @return array
     */
    function http_formdata_flat_hierarchy($data) {
        $vars=array();
        foreach($data as $key=>$value) {
            if(is_array($value)) {
                $temp = array() ;
                foreach($value as $k2 => $val2){
                    $temp[ $key.'['.$k2.']' ] = $val2 ;
                }
                $vars = array_merge( $vars, http_formdata_flat_hierarchy($temp) );
            }
            else {
                $vars[$key]=$value;
            }
        }
        return $vars;
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
    
    
//    const serverURL = "http://localhost:8080/sysworkflow/en/classic" ;
    var $serverURL ;
    var $database ;
    private $pmSoapClient = null ;
    private $pmWorkspace = "" ;
    private $pmAdminSession = false ;
    //var $pmSession = '' ;
    private $taskWriter = 0 ; 
    private $pm_group_guid = '' ; // guid for default user group in Process Maker is used for all GLPI user synchronization into ProcessMaker
    
    
    /**
     * Return the table used to store this object
     *
     * @return string
     **/
    function getTable() {

        return "glpi_plugin_processmaker_processes";
    }
    
    
    
    
    /**
     * Summary of openSoap
     * @return true if open succeeded, and pmSoapClient is initialized
     *         false otherwise
     */
    private function openSoap( ) {
        //global $DB;
        try {
            if( $this->pmSoapClient == null ) {
                $lang = substr( $_SESSION["glpilanguage"], 0, 2) ;
                if( strlen( $lang ) <> 2 ) 
                    $lang ="en" ; // by default
                //$row = new PluginProcessmakerConfig ;
                $row = PluginProcessmakerConfig::getInstance() ;
                //$res = $DB->query("SELECT * from glpi_plugin_processmaker_configs") ;
                //$row = $DB->fetch_array( $res ) ;
                $this->pmWorkspace = $row->fields['pm_workspace'] ;
                $this->serverURL = $row->fields['pm_server_URL'].'/sys'.$row->fields['pm_workspace'].'/'.$lang.'/'.$row->fields['pm_theme'] ;
                $this->database = 'wf_'.$row->fields['pm_workspace'] ;
                $this->taskWriter = $row->fields['users_id'] ;
                $this->pm_group_guid = $row->fields['pm_group_guid'] ;
                $this->pmSoapClient = new SoapClient($this->serverURL."/services/wsdl2", array( 'soap_version'   => SOAP_1_2, 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP) );                
            }
            
            return true ; //$this->pmSoapClient  ;   
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ; //null ;
        }        
    }
    

    
    //function getSearchOptions() {
    //    global $LANG;

    //    $tab = array();
    //    $tab['common'] = "Header Needed";

    //    $tab[1001]['table']     = 'glpi_plugin_processmaker_cases';
    //    $tab[1001]['field']     = 'case_status';
    //    $tab[1001]['name']      = 'Case - Status' ; //$LANG['plugin_example']["name"];

    //    //$tab[2]['table']     = 'glpi_plugin_example_dropdowns';
    //    //$tab[2]['field']     = 'name';
    //    //$tab[2]['name']      = 'Dropdown';

    //    //$tab[3]['table']     = 'glpi_plugin_example_examples';
    //    //$tab[3]['field']     = 'serial';
    //    //$tab[3]['name']      = 'Serial';
    //    //$tab[3]['usehaving'] = true;
    //    //$tab[3]['searchtype'] = 'equals';

    //    //$tab[30]['table']     = 'glpi_plugin_example_examples';
    //    //$tab[30]['field']     = 'id';
    //    //$tab[30]['name']      = $LANG["common"][2];

    //    return $tab;
    //}

    //function useSession( $session, $lang = "en" ) {
    //    try {
    //        if( $this->openSoap( $lang ) ) {                
    //            $this->pmSession = $session ;              
    //        }
            
    //        return $this->pmSession ;
    //    }
    //    catch ( Exception $e ) {
    //        Toolbox::logDebug( $e );
    //    }
    //}
    
    
    //function openSession($username = "glpi", $password = "md5:2b0a4830b22f1f5ef5f8c239f9c8f07e", $lang="en" ) {
    //    try {
    //        if( $this->openSoap( $lang ) ) {                
    //            $locSession = $this->pmSoapClient->login( array( 'userid' => $username, 'password' => $password) ) ;
    //            if( $locSession->status_code == 0 )
    //                $this->pmSession = $locSession->message ;              
    //            }
            
    //        return $this->pmSession ;
    //    }
    //    catch ( Exception $e ) {
    //        Toolbox::logDebug( $e );
    //    }
    //}
    
    /**
     * Summary of login
     * 
     * @return true if login has been correctly done with current GLPI user, or if a PM session was already open
     *         false if an exception occured (like SOAP error or PM login error)
     */
    function login( $admin=false ) { 
		//unset($_SESSION["pluginprocessmaker"]["session"]["date"]) ;
        try {
            if( $this->openSoap( ) ) {
                if( $admin ) { // in case admin, then force new login
                    $locSession = $this->pmSoapClient->login( array( 'userid' => 'glpi', 'password' => 'md5:2b0a4830b22f1f5ef5f8c239f9c8f07e') ) ;
                    if( is_object( $locSession ) && $locSession->status_code == 0 )  {
                        $_SESSION["pluginprocessmaker"]["session"]["admin"] = true ;
                        $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message ;                    
                        $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp ;
                        $this->pmAdminSession = true ;
                        return true ;
                    }
                } elseif( empty($_SESSION["pluginprocessmaker"]["session"]["date"]) || ($_SESSION["pluginprocessmaker"]["session"]["admin"] == true)
                    || $this->pmAdminSession == true 
                    || date_add( date_create( $_SESSION["pluginprocessmaker"]["session"]["date"] ), new DateInterval( "PT10H0M0S" ) ) < date_create( date( "Y-m-d H:i:s" ) )
                    ){ 
                    $locSession = $this->pmSoapClient->login( array( 'userid' => $_SESSION["glpiname"], 'password' => 'md5:37d442efb43ebb80ec6f9649b375ab72') ) ;
                    if( is_object( $locSession ) && $locSession->status_code == 0 )  {
                        $_SESSION["pluginprocessmaker"]["session"]["id"] = $locSession->message ;                    
                        $_SESSION["pluginprocessmaker"]["session"]["date"] = $locSession->timestamp ;
                        $_SESSION["pluginprocessmaker"]["session"]["admin"] = false ;
                        $this->pmAdminSession = false ;                    
                        return true ;
                    }
                } else 
                    return true ; // means a session is already existing in $_SESSION["pluginprocessmaker"]["session"]                                     
            }
            
            $this->pmAdminSession = false ;                    
            unset($_SESSION["pluginprocessmaker"]["session"]) ;
            Toolbox::logDebug( "Processmaker Plugin: Soap problem" );
            return false ;            
        }
        catch ( Exception $e ) {
            $this->pmAdminSession = false ;                    
            unset($_SESSION["pluginprocessmaker"]["session"]) ;
            Toolbox::logDebug( $e );
            return false ;
        }
    }
    
    ///**
    // * Summary of logout
    // *      used to clean variable session from current logged in session
    // *      will not really do a logout from PM, but is used to force cleaning when previously user was admin
    // */
    //function logout( ) {
    //    unset($_SESSION["pluginprocessmaker"]["Session"]) ;
    //}

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
            $pmProcessList = $this->pmSoapClient->processList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;

            if( is_array( $pmProcessList->processes ) )
                return $pmProcessList->processes ;
            else
                return array( 0 => $pmProcessList->processes ) ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
            $pmCaseInfo = $this->pmSoapClient->getCaseInfo( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex) ) ;
            switch ( $pmCaseInfo->caseStatus ) {
                case 'DRAFT' :
                case 'TO_DO':
//                case 'CANCELLED' :
                    if( is_object( $pmCaseInfo->currentUsers ) )
                        $pmCaseInfo->currentUsers = array( 0 => $pmCaseInfo->currentUsers ) ;
                    if( $pmCaseInfo->currentUsers[0]->delThreadStatus == 'PAUSE' )
                        $pmCaseInfo->caseStatus = "PAUSED" ; 
                    break;     
            }                
            return $pmCaseInfo ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
            $pmRouteCaseResponse = $this->pmSoapClient->routeCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex) ) ;            
            if( $pmRouteCaseResponse->status_code != 0 )
                Toolbox::logDebug( 'routeCase res:', $pmRouteCaseResponse ) ;

            if( property_exists( $pmRouteCaseResponse, 'routing' ) && is_object( $pmRouteCaseResponse->routing ) )
                $pmRouteCaseResponse->routing = array( 0 => $pmRouteCaseResponse->routing) ;
                
            return $pmRouteCaseResponse ;            
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
            $pmTriggerList = $this->pmSoapClient->triggerList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;
            if( is_array(  $pmTriggerList->triggers  ) )
                return  $pmTriggerList->triggers  ;
            else
                return array( 0 => $pmTriggerList->triggers )  ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
            $pmTaskList = $this->pmSoapClient->taskList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;

            if( is_array(  $pmTaskList->tasks  ) )
                return  $pmTaskList->tasks ;
            else
                return array( 0 => $pmTaskList->tasks )  ;
        }
        catch ( Exception $e ) {
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
     * @return an array of taskListStruct objects, or false when exception occured
     */
    function taskCase( $caseId ) {
        try {
            $pmTaskCase = $this->pmSoapClient->taskCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId ) ) ;

            if( is_array(  $pmTaskCase->taskCases  ) )
                return  $pmTaskCase->taskCases ;
            else
                return array( 0 => $pmTaskCase->taskCases )  ;
        }
        catch ( Exception $e ) {
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
    function claimCase( $caseId, $delIndex){
        try {
            $pmClaimCase = $this->pmSoapClient->claimCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'guid' => $caseId, 'delIndex' => $delIndex) ) ;
            return $pmClaimCase ;
        }
        catch ( Exception $e ) {
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
            $pmUnpauseCase = $this->pmSoapClient->unpauseCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseId, 'delIndex' => $delIndex, 'userUid' => $userId) ) ;

            if( is_array( $pmUnpauseCase->processes ) )
                return  $pmUnpauseCase->processes ;
            else
                return array( 0 => $pmUnpauseCase->processes )  ;
        }
        catch ( Exception $e ) {
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
            $pmCaseList = $this->pmSoapClient->caseList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;

            if( is_array(  $pmCaseList->cases  ) )
                return  $pmCaseList->cases ;
            else
                return array( 0 => $pmCaseList->cases )  ;
        }
        catch ( Exception $e ) {
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
            $pmResults = $this->pmSoapClient->reassignCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'delIndex' => $delIndex, 'userIdSource' => $userIdSource, 'userIdTarget'=> $userIdTarget) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
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
            $deleteCaseResponse = $this->pmSoapClient->deleteCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseUid) ) ;
            return $deleteCaseResponse ;
        }
        catch ( Exception $e ) {
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
            $cancelTaskResponse = $this->pmSoapClient->cancelCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseUid' => $caseUid, 'delIndex' => $delIndex, 'userUid' => $userUid) ) ;
            return $cancelTaskResponse ;
        }
        catch ( Exception $e ) {
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
            $pmCaseInfo = $this->getCaseInfo( $caseUid ) ;
            if( $pmCaseInfo->status_code == 0 ){                    
                foreach( $pmCaseInfo->currentUsers as $pmUser){
                    $pmCancelTask = $this->cancelTask( $caseUid, $pmUser->delIndex, $pmUser->userId ) ;
                    if( $pmCancelTask->status_code != 0 )
                        return $pmCancelTask;
                }
            }
            return $pmCancelTask ;
        }
        catch ( Exception $e ) {
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
            $this->getFromDB( $processId ) ;
                
            if( $vars !== null ) {
                $aVars = array();
                foreach ($vars as $key => $val)
                { 
                    $obj = new variableStruct();
                    $obj->name = $key;
                    $obj->value = $val;
                    $aVars[] = $obj;	 
                }
            } else $aVars = '' ;
                
            $newCaseResponse = $this->pmSoapClient->newCaseImpersonate( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'processId'=> $this->fields['process_guid'], 'userId' => $userId, 'taskId'=>'', 'variables'=> $aVars) ) ;
            return $newCaseResponse ;
        }
        catch ( Exception $e ) {
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
     * @param array $vars an array of associative variables (name => value) that will be injected into the case as case variables
     * @return A newCaseResponse object, or false when exception occured
     */
    function newCase( $processId, $vars = array() ) {
        try {
            $this->getFromDB( $processId ) ;
                                
            $aVars = array();
            foreach ($vars as $key => $val)
            { 
                $obj = new variableStruct();
                $obj->name = $key;
                $obj->value = $val;
                $aVars[] = $obj;	 
            }
                
            $newCaseResponse = $this->pmSoapClient->newCase( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'processId'=> $this->fields['process_guid'], 'taskId'=>'', 'variables'=> $aVars) ) ;

            return $newCaseResponse ;
        }
        catch ( Exception $e ) {
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
     * @param array $vars an array of associative variables (name => value) that will be injected into the case as case variables
     * @return A pmResponse object, or false when exception occured
     */
    function sendVariables( $caseId, $vars = array() ) {
        if( count( $vars ) == 0 ) // nothing to send
            return true ;
        try {
            $aVars = array();
            foreach ($vars as $key => $val)
            {                 
                $obj = new variableStruct();
                $obj->name = $key;
                if( is_array( $val ) ) 
                    $obj->value = join( "|", $val ) ; 
                else 
                    $obj->value = $val;
                $aVars[] = $obj;	 
            }
                
            $pmResponse = $this->pmSoapClient->sendVariables( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'variables'=> $aVars) ) ;
            
            return $pmResponse  ;
        }
        catch ( Exception $e ) {
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
     * @param array $vars an array of variable name that will be read from the case as case variables
     *      Normalizes output to an array, even when only one element is returned by PM
     * @return an associative array (variable_name => value), or false when exception occured. The return array can be empty if requested variables are not found.
     */
    function getVariables( $caseId, $vars = array() ) {
        try {
            $aVars = array();
            foreach ($vars as $key => $name)
            { 
                $obj = new getVariableStruct();
                $obj->name = $name;
                $aVars[] = $obj;	 
            }
            
            $pmvariableListResponse = $this->pmSoapClient->getVariables( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'variables'=> $aVars) ) ;
            
            $variablesArray = array()  ;
            
            if ($pmvariableListResponse->status_code == 0 && isset( $pmvariableListResponse->variables )) {
                if( is_array( $pmvariableListResponse->variables ) )
                    foreach ($pmvariableListResponse->variables as $variable)
                        $variablesArray[$variable->name] = $variable->value;
                else
                    $variablesArray[$pmvariableListResponse->variables->name] = $pmvariableListResponse->variables->value;
            }
            
            return $variablesArray  ;
        }
        catch ( Exception $e ) {
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
            $pmGroupList = $this->pmSoapClient->groupList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;

            if( is_array(  $pmGroupList->groups  ) )
                return  $pmGroupList->groups ;
            else
                return array( 0 => $pmGroupList->groups )  ;
        }
        catch ( Exception $e ) {
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
                                                                ) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
                                                                'name' => $name ) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
        }
    }

    /**
     * Summary of updateGroup
     *      updates group directly into Processmaker DB
     * @param $group_id: guid of the pm group
     * @param $groupStatus: new status to be set to $group_id, could be 'ACTIVE' or 'INACTIVE'
     * @return true if group status has been modified, false otherwise
     */
    function updateGroup( $group_id, $groupStatus ){
        global $DB ;
        $query = "UPDATE wf_$this->pmWorkspace.groupwf SET GRP_STATUS='$groupStatus' WHERE GRP_UID=$group_id;";
        $DB->query( $query ) ;
        if( $DB->affected_rows != 1 )
            return false;
        else
            return true;
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
            $pmUserList = $this->pmSoapClient->userList( array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"]) ) ;

            if( is_array(  $pmUserList->users  ) )
                return  $pmUserList->users ;
            else
                return array( 0 => $pmUserList->users )  ;
        }
        catch ( Exception $e ) {
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
            if( $firstname == null || $firstname == "" ) $firstname = $userId ;
            if( $lastname == null || $lastname == "" ) $lastname = $userId ;
            if( $email == "" ) $email = $userId."@DoNotReply.com" ;
                
            $pmResults = $this->pmSoapClient->createUser(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 
                                                                'userId' => $userId, 
                                                                'firstname'=> $firstname, 
                                                                'lastname' => $lastname, 
                                                                'email' => $email, 
                                                                'role' => $role, 
                                                                'password' => $password,
                                                                'status' => $status ) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
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
            if( $firstName == null || $firstName == "" ) $firstName = $userName ;
            if( $lastName == null || $lastName == "" ) $lastName = $userName ;

            $pmResults = $this->pmSoapClient->updateUser(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 
                                                                'userUid' => $userUid, 
                                                                'userName' => $userName, 
                                                                'firstName'=> $firstName, 
                                                                'lastName' => $lastName, 
                                                                'status' => $status                                                                    
                                                                ) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
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
            $pmResults = $this->pmSoapClient->executeTrigger(array( 'sessionId' => $_SESSION["pluginprocessmaker"]["session"]["id"], 'caseId' => $caseId, 'triggerIndex'=> $triggerIndex, 'delIndex' => $delIndex ) ) ;
            return $pmResults ;
        }
        catch ( Exception $e ) {
            Toolbox::logDebug( $e );
            return false ;
        }
    }
    
  
    
    /**
     * summary of cronInfo
     *      Gives localized information about 1 cron task
     * @param $name of the task
     * @return array of strings
     * TODO: localization
     */
    static function cronInfo($name) {
        global $LANG;

        switch ($name) {
            case 'pmusers' :
                return array('description' => $LANG['processmaker']['cron']['pmusers'] );
            case 'pmnotifications' :
                return array('description' => $LANG['processmaker']['cron']['pmnotifications'] );
        }
        return array();
    }

    ///**
    //* summary of cronPMNotifications
    //*       Execute 1 task managed by the plugin
    //* @param: $task CronTask class for log / stat
    //* @return integer
    //*    >0 : done
    //*    <0 : to be run again (not finished)
    //*     0 : nothing to do
    //*/
    //static function cronPMNotifications($task) {
    //    global $DB, $GLOBALS, $CFG_GLPI ;
        
    //    $actionCode = 0; // by default
    //    $error = false ;
    //    $task->setVolume(0); // start with zero

    //    if ( $CFG_GLPI["use_mailing"]) {
            
    //        // will simulate a real session
    //        $oldGlpiCronUserRunning = $_SESSION["glpicronuserrunning"] ;

    //        // get the complete user list from GLPI DB
    //        $taskNotificationList = array() ;
    //        foreach($DB->request("SELECT * FROM glpi_plugin_processmaker_tasksnotifications ORDER BY id") as $locTicketNotification) {
    //            $locTicketTask = new TicketTask;
    //            if( $locTicketTask->getFromDB( $locTicketNotification['task_id'] ) ){
    //                $locTec = new User ;
    //                $locTec->getFromDB( $locTicketNotification['tech_id'] ) ;
    //                if( $locTec->getFromDB( $locTicketNotification['tech_id'] ) && substr( $locTec->fields['realname'], 0, 1) != "*" ){
    //                    $_SESSION["glpicronuserrunning"] = $locTec->fields['realname']." ".$locTec->fields['firstname'] ;
    //                } 
    //                switch( $locTicketNotification['action'] ) {
    //                    case 'INSERT' :
    //                        // in this case, we need the ticket in $locTicketTask->input['_job']
    //                        $item = new Ticket();
    //                        $item->getFromDB( $locTicketTask->fields['tickets_id'] );
    //                        $locTicketTask->input['_job']=$item;
                            
    //                        $locTicketTask->post_addItem( ) ;
    //                        break;
    //                    case 'UPDATE' :
    //                        // in this case we need to add updates array to $locTicketTask
    //                        $locTicketTask->updates[] = 'actiontime' ;
    //                        $locTicketTask->updates[] = 'state' ;
    //                        $locTicketTask->updates[] = 'begin' ;
    //                        $locTicketTask->updates[] = 'end' ;
    //                        $locTicketTask->updates[] = 'users_id_tech' ;
    //                        $locTicketTask->post_updateItem( ) ;
    //                        break ;
    //                }
    //                $task->addVolume(1);
    //                //$task->log( "Notification sent" ) ;  
    //                $_SESSION["glpicronuserrunning"] = $oldGlpiCronUserRunning ;
    //            }
    //            $query = "DELETE FROM glpi_plugin_processmaker_tasksnotifications WHERE id=".$locTicketNotification['id'].";" ;
    //            $DB->query( $query ) ;                
    //        }

    //    } else {
    //        $task->log( "Notification are disabled!" ) ;                
    //        // we must delete all existing notifications requests
    //        $query = "DELETE FROM glpi_plugin_processmaker_tasksnotifications;" ;
    //        $DB->query( $query ) ;                            
    //    }
            
        
    //    if($error)
    //        return -1 ;
    //    else
    //        return $actionCode;
        
    //}    
        
    
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
       global $DB, $GLOBALS ;
       
   	    $actionCode = 0; // by default
   	    $error = false ;
   	    $task->setVolume(0); // start with zero
           
        // start a processmaker session
        $myProcessMaker = new PluginProcessmakerProcessmaker();
        $myProcessMaker->login( true ) ; //openSession() ;
        
        $pmGroupList = $myProcessMaker->groupList( ) ;        
        foreach( $pmGroupList as $pmGroup ) {
            if( $pmGroup->guid == $myProcessMaker->pm_group_guid ) break ; // to get the name :)
        }               
        
        $pmUserList = array() ;
        foreach( $myProcessMaker->userList() as $pmuser) {
            $pmUserList[ strtolower($pmuser->name)] = array( 'name' => $pmuser->name, 'guid' => $pmuser->guid,  'status' => $pmuser->status ) ; 
        }
                
        // get the complete user list from GLPI DB
        $glpiUserList = array() ;
        foreach($DB->request("SELECT id, name, realname, firstname, is_active, is_deleted, glpi_plugin_processmaker_users.pm_users_id as pmUserId FROM glpi_users LEFT JOIN glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.glpi_users_id = glpi_users.id where name <> 'glpi' and name <> 'admin' and name not like '*%'") as $dbuser) {
            $glpiUserList[ strtolower($dbuser['name'])] = $dbuser ;
        }
        
        $arrayDiff = array_diff_key( $glpiUserList, $pmUserList ) ;
        
   	    foreach( $arrayDiff as $user ){
               if( $user['is_active'] != 0 && $user['is_deleted'] != 1 ) {
                   $status = "ACTIVE" ;
                   $task->addVolume(1);
                   $pmResult = $myProcessMaker->createUser( $user['name'], $user['firstname'], $user['realname'], "", "PROCESSMAKER_OPERATOR", "GLPI01", $status) ;
                   if( $pmResult->status_code == 0) {
                       $task->log( "Added user: '".$user['name']."'" ) ;
                       // then assign user to group
                       $pmResult2 = $myProcessMaker->assignUserToGroup( $pmResult->userUID, $pmGroup->guid ) ;
                       if( $pmResult2->status_code == 0  )
                           $task->log( "Added user: '".$user['name']."' to '".$pmGroup->name."' group" ) ;                       
                       else
                           $task->log( "Error PM: '".$pmResult2->message."'" ) ;
                       // insert into DB the link between glpi users and pm user
                       $query = "REPLACE INTO glpi_plugin_processmaker_users (glpi_users_id, pm_users_id) VALUES (".$user['id'].", '".$pmResult->userUID."');" ;
                       $DB->query( $query ) or $task->log( "Cannot add user: '".$user['id']."' into glpi_plugin_processmaker_users!" ) ;
                       $actionCode = 1 ;
                       
                   } else {
                       $task->log( "Error adding user: '".$user['name']."'" ) ;
                       $task->log( "Error PM: '".$pmResult->message."'" ) ;
                       $actionCode = -1 ;
                       $error = true ;
//                       break ;
                   }
               } else
                   unset( $glpiUserList[$user['name']] ) ;               
        }
           
        if( !$error ) {
            // now should refresh the existing users
            $arrayIntersect = array_intersect_key( $glpiUserList, $pmUserList ) ;
            foreach( $arrayIntersect as $user ){
                if( $user['pmUserId'] == null || ($user['pmUserId'] != $pmUserList[strtolower($user['name'])]['guid'])){ //must be inserted into DB
                    // insert into DB the link between glpi users and pm user
                    $query = "REPLACE INTO glpi_plugin_processmaker_users (glpi_users_id, pm_users_id) VALUES (".$user['id'].", '". $pmUserList[strtolower($user['name'])]['guid']."');" ;
                    $DB->query( $query ) or $task->log( "Cannot update user: '".$user['id']."' into glpi_plugin_processmaker_users!" ) ;
                }
                if( $user['is_active'] == 0 || $user['is_deleted'] == 1 )
                    $status = "INACTIVE" ;
                else
                    $status = "ACTIVE" ;
                if( $status != $pmUserList[strtolower($user['name'])]['status'] ) {
                    $task->addVolume(1);
                    $pmResult = $myProcessMaker->updateUser( $pmUserList[strtolower($user['name'])]['guid'], $user['name'], $user['firstname'], $user['realname'], $status ) ; 
                    if( $pmResult->status_code == 0) {
                        $task->log( "Updated user: '".$user['name']."', status: '".$pmUserList[strtolower($user['name'])]['status']."' -> '".$status."'" ) ;                        
                        $actionCode = 1 ;
                    } else {
                        $task->log( "Error updating user: '".$user['name']."'" ) ;
                        $task->log( "Error PM: '".$pmResult->message."'" ) ;
                        $actionCode = -1 ;
                        $error = true ;
                    }
                }
                   
            }
        }
        
        // now we should desactivate PM users who are not in glpi user list
        if( !$error ) {
            $status = "INACTIVE" ;
            $arrayDiff = array_diff_key( $pmUserList , $glpiUserList ) ;
            foreach( $arrayDiff as $user ){
                $task->addVolume(1);   
                if( $user['status'] == 'ACTIVE' && $user['name'] != 'admin' && $user['name'] != 'glpi'){
                    $pmResult = $myProcessMaker->updateUser( $user['guid'], $user['name'], null, null, $status ) ; 
                    if( $pmResult->status_code == 0) {
                        $task->log( "Updated user: '".$user['name']."', status: '".$user['status']."' -> '".$status."'" ) ;                        
                        $actionCode = 1 ;
                    } else {
                        $task->log( "Error updating user: '".$user['name']."'" ) ;
                        $task->log( "Error PM: '".$pmResult->message."'" ) ;
                        $actionCode = -1 ;
                        $error = true ;
                    }                
                }
            }
        }
        
        
        
        // so now treat GLPI pseudo-groups
        foreach($DB->request("SELECT id, name, realname, firstname, is_active, is_deleted FROM glpi_users where name like '*%'") as $dbuser) {
            $glpiPseudoUserList[$dbuser['name']] = $dbuser ;
        }
        
        foreach($DB->request("SELECT * FROM wf_workflow.content WHERE wf_workflow.content.CON_CATEGORY='GRP_TITLE' and wf_workflow.content.CON_LANG='en'") as $dbuser){
            $pmGroupList[$dbuser['CON_VALUE']] = $dbuser ;
        }
            
        // here we can compare group lists like done for the users
        $arrayDiff = array_diff_key( $glpiPseudoUserList, $pmGroupList ) ;
        
        // then for each pseudo-group we must check if it exists, and if not create a real PM group
        foreach( $arrayDiff as $pseudoGroup ) {
            // it is not existing in PM
            // then create
            $pmResult = $myProcessMaker->createGroup( $pseudoGroup['name'] ) ;
            if( $pmResult->status_code == 0) {
                $task->addVolume(1);
                $task->log( "Added group: '".$pseudoGroup['name']."'" ) ;
            }
        }
        
        // review and update all users in each pseudo-groups
        $pmGroupList = array() ; // reset groups and get the new complete list from PM DB
        foreach($DB->request("SELECT * FROM wf_workflow.content WHERE wf_workflow.content.CON_CATEGORY='GRP_TITLE' and wf_workflow.content.CON_LANG='en'") as $dbuser){
            $pmGroupList[$dbuser['CON_VALUE']] = $dbuser ;
        }
        
        // now should refresh the existing users into groups
        $arrayDiff = array_intersect_key( $glpiPseudoUserList, $pmGroupList ) ;
        foreach( $arrayDiff as $pseudoGroup ){
            // for each pseudo group will delete users and re-create them   
            // not really optimized, but this way we are sure that groups are synchronized
            // must be redesigned
            $query = "DELETE FROM wf_workflow.group_user WHERE wf_workflow.group_user.GRP_UID='".$pmGroupList[$pseudoGroup['name']]['CON_ID']."';";
            $DB->query( $query ) ;
            // and insert all users from real GLPI group 
            foreach( $DB->request("select glpi_groups_users.users_id, glpi_plugin_processmaker_users.pm_users_id from glpi_groups join glpi_groups_users on glpi_groups_users.groups_id=glpi_groups.id join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.glpi_users_id=glpi_groups_users.users_id where glpi_groups.name='".$pseudoGroup['name']."'") as $user ) {
                $query = "INSERT INTO wf_workflow.group_user (`GRP_UID`, `USR_UID`) VALUES ( '".$pmGroupList[$pseudoGroup['name']]['CON_ID']."',  '".$user['pm_users_id']."'  )" ;    
                $DB->query( $query ) ;
            }
            $task->addVolume(1);
            $task->log( "Updated users into PM group: '".$pseudoGroup['name']."'" ) ;
        }
        
        
        if($error)
            return -1 ;
        else
            return $actionCode;
   }   
    
    
	public static function plugin_pre_item_add_processmaker($parm) {
		global $DB, $GLOBALS ;
        
        if( isset($parm->input['processmaker_caseid']) ) {
            // a case is already started for this ticket, then change ticket title and ticket type and ITILCategory

            $myProcessMaker = new PluginProcessmakerProcessmaker( ) ;
            $myProcessMaker->login( ) ;
            $caseInfo = $myProcessMaker->getCaseInfo( $parm->input['processmaker_caseid'], $parm->input['processmaker_delindex']) ;
            $parm->input['name'] = $caseInfo->caseName ;

            $caseInitialDueDate = $myProcessMaker->getVariables(  $parm->input['processmaker_caseid'], array( 'GLPI_ITEM_INITIAL_DUE_DATE' )) ;
            if( array_key_exists( 'GLPI_ITEM_INITIAL_DUE_DATE', $caseInitialDueDate ) ) {
                $parm->input['due_date'] = $caseInitialDueDate[ 'GLPI_ITEM_INITIAL_DUE_DATE' ]." 23:59:59" ;
            }

            $procDef = new PluginProcessmakerProcess;
            $procDef->getFromDBbyExternalID( $caseInfo->processId ) ;     
            if( isset($parm->input['type']) ){
                $parm->input['type'] = $procDef->fields['type'] ;                
            }
            
            if( isset($parm->input['itilcategories_id']) ){
                $parm->input['itilcategories_id'] = $procDef->fields['itilcategories_id'] ;                
            }
            
        }        
	}
	
    public static function plugin_item_add_processmaker($parm) {
        global $DB, $GLOBALS ;
        
        if( isset($parm->input['processmaker_caseid']) ) {
            // a case is already started for this ticket, then bind them together
            $itemType = $parm->getType() ; //$myCase->getField('itemtype');
            $itemId = $parm->fields['id'] ; //$myCase->getField('items_id');
            $caseId = $parm->input['processmaker_caseid'] ;
            
            $myCase = new PluginProcessmakerCases ;
                      
            //can't use std add due to forced case id
            $query = "INSERT INTO glpi_plugin_processmaker_cases (items_id, itemtype, id, case_num) VALUES ($itemId, '$itemType', '$caseId', ".$parm->input['processmaker_casenum'].");" ;
            $res = $DB->query($query) ;
            
            $myCase->getFromDB( $caseId ) ; // reloads case from DB
            
            $myProcessMaker = new PluginProcessmakerProcessmaker( ) ;
            $myProcessMaker->login( ) ;
            
            // route case            
            $pmRouteCaseResponse = $myProcessMaker->routeCase( $myCase->getID(), $parm->input['processmaker_delindex'] ) ;
                                    
            // now manage tasks associated with item            
            // create new tasks
            $caseInfo = $myProcessMaker->getCaseInfo(  $myCase->getID(), $parm->input['processmaker_delindex']) ;
            if( property_exists( $pmRouteCaseResponse, 'routing' ) ) {
                foreach( $pmRouteCaseResponse->routing as $route ) {                    
                    $myProcessMaker->addTask( $myCase->fields['itemtype'], $myCase->fields['items_id'], $caseInfo, $route->delIndex, PluginProcessmakerProcessmaker::getGLPIUserId( $route->userId ), 0, $route->taskId ) ; 
                }
            }
            
            // evolution of case status: DRAFT, TO_DO, COMPLETED, CANCELLED
            $myCase->update( array( 'id' => $myCase->getID(), 'case_status' => $caseInfo->caseStatus ) ) ;                         
        }
        return ;
    }
    

	public static function plugin_pre_item_add_processmaker_followup($parm) {
		global $DB ;
	
	
	}

    
    //function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
    //    global $LANG;

    //    if (!$withtemplate) {
    //        switch ($item->getType()) {
    //            case 'Phone' :
    //                if ($_SESSION['glpishow_count_on_tabs']) {
    //                    return self::createTabEntry('Example',
    //                                                countElementsInTable($this->getTable()));
    //                }
    //                return 'Example';
    //        }
    //    }
    //    return '';
    //}

    ///**
    // * Summary of displayTabContentForItem
    // * @param CommonGLPI $item
    // * @param integer $tabnum
    // * @param integer $withtemplate
    // * @return boolean
    // */
    //static function displayTabContentForItem(CommonGLPI $item, integer $tabnum=1, integer $withtemplate=0) {

    //    if ($item->getType()=='Phone') {
    //        echo "Plugin Example on Phone";
    //    }
    //    return true;
    //}
    
    
    
    
    /**
     * Summary of addTask
     *      adds a GLPI task to given item
     * @param $itemType String item type to which a task must be added
     * @param $iteId integer item# to which a task must be added
     * @param $caseInfo getCaseInfoResponse object (see: getCaseInfo() function)
     * @param $del_index integer index of the delegation
     * @param $techId integer GLPI user id to which a task must be assigned, if == 0 then we should look-up in DB the pseudo-group to be assigned to the task
     * @return
     */
    public function addTask( $itemType, $itemId,  $caseInfo, $delIndex, $techId, $groupId, $pmTaskId, $txtTaskContent = '' ) {
        global $DB, $LANG, $CFG_GLPI, $_SESSION ;
        
        $glpi_task = getItemForItemtype( "{$itemType}Task" );
       
        $input = array() ; // will contain all data for the Task
               
        $input[getForeignKeyFieldForItemType($itemType)] = $itemId ;
        // search for task category
        //
        $pmtaskcat = new PluginProcessmakerTaskCategory ;
        $pmtaskcat->getFromDBbyExternalID( $pmTaskId ) ;
        $input['taskcategories_id'] = $pmtaskcat->fields['taskcategories_id'] ;
        // load process information
        $pmProcess = new PluginProcessmakerProcess ;
        $pmProcess->getFromDB( $pmtaskcat->fields['processes_id'] ) ;
                
        $start_date = new DateTime( ) ;
        $input['date'] = $start_date->format("Y-m-d H:i:s");
        $input['users_id'] = $this->taskWriter ;

        $user_can_view = true ; // by default
        $plug = new Plugin;

        // manage groups
        if( $techId == 0 ) { // then we must look-up DB to get the pseudo-group that will be assigned to the task
            if( $groupId == 0 ) {
                $query = "select glpi.glpi_users.id as glpi_user_id from wf_workflow.task_user 
                            join wf_workflow.content on wf_workflow.content.CON_ID=wf_workflow.task_user.USR_UID and wf_workflow.content.CON_CATEGORY='GRP_TITLE' and wf_workflow.content.CON_LANG = 'en'
                            join glpi.glpi_users on glpi.glpi_users.name=wf_workflow.content.CON_VALUE COLLATE utf8_unicode_ci
                            where wf_workflow.task_user.tas_uid='$pmTaskId' and wf_workflow.task_user.tu_relation=2 LIMIT 1;" ;
            } else {
                $query = "select glpi.glpi_users.id as glpi_user_id from wf_workflow.content 
                            join glpi.glpi_users on glpi.glpi_users.name=wf_workflow.content.CON_VALUE COLLATE utf8_unicode_ci
                            where wf_workflow.content.CON_ID='$groupId' and wf_workflow.content.CON_CATEGORY='GRP_TITLE' and wf_workflow.content.CON_LANG = 'en' ;" ;
            }

            $res = $DB->query($query) ; 
            if( $DB->numrows($res) > 0) {
                $row = $DB->fetch_array( $res ) ;
                $techId = $row['glpi_user_id'] ;                
            } 
            
        } elseif( !$plug->isActivated('arbehaviours') ) { // check is done during Task add in this plugin
            // this is a real user not a pseudo-user
            // then we should check if this user has rights on the item, if not then we must add it to the watcher list!
            $glpi_item = getItemForItemtype( $itemType );
            $glpi_item->getFromDB( $itemId ) ;
            //$glpi_tech = new User() ;
            //$glpi_tech->getFromDB( $techId ) ;
            // several possibilities
            // $techId may be requester
            $user_entities = Profile_User::getUserEntities( $techId, true, true ) ;            
            $user_can_view = in_array( $glpi_item->fields['entities_id'], $user_entities );
            if( !$glpi_item->isUser( CommonITILObject::REQUESTER, $techId ) && !$glpi_item->isUser( CommonITILObject::OBSERVER, $techId ) && !$glpi_item->isUser( CommonITILObject::ASSIGN, $techId ) && !$user_can_view ) {
                // then we must add this tech user to watcher list
                $glpi_item_user = getItemForItemtype( "{$itemType}_User" );
                $glpi_item_user->add( array( strtolower(getPlural( $itemType )).'_id' => $itemId, 'users_id' => $techId, 'type' => CommonITILObject::OBSERVER, '_no_notif' => true ) ) ;                    
            }
        }
        
        
        // manage task description
        $input['content'] = "" ; // by default empty :)
        if( $txtTaskContent != '' ) {
            $input['content'] = $txtTaskContent ;
        }
        elseif( !$pmProcess->fields["hide_case_num_title"] ) {
            $input['content'] = $LANG['processmaker']['item']['task']['case'].$caseInfo->caseName ;            
        }
        
        if( $pmProcess->fields["insert_task_comment"] ) {
            if( $input['content'] != '' ) $input['content'] .= "\n" ;
            $input['content'] .= $LANG['processmaker']['item']['task']['comment']  ;
        }
        if( $input['content'] != '' ) $input['content'] .= "\n" ;
        $input['content'] .= $LANG['processmaker']['item']['task']['manage'] ;        
        
        $input['is_private'] = 0 ;
        $input['actiontime'] = 0 ;
        $input['begin'] = $start_date->format("Y-m-d H:i:s");
        $start_date->add( new DateInterval('PT1M') ) ;
        $input['end'] = $start_date->format("Y-m-d H:i:s") ;
        $input['state'] = 1 ; // == TO_DO
        $input['users_id_tech'] = $techId ; // Session::getLoginUserID() ; //PluginProcessmakerProcessmaker::getGLPIUserId( $pmInfo['caseInfo']->currentUsers[0]->userId ) ;
        
        $glpi_task->add( Toolbox::addslashes_deep( $input ) )  ;
        
        if($glpi_task->getId() > 0 )  {        
            // task has been created then send notifications for tech with no default rigths to the item entity
            // now done in GLPI core
            //if( !$user_can_view ) { 
            //    // to cheat the entity rigths, passes default user_entity to raiseEvent(), to be sure that task_tech will receive a notification.
            //    // drawback: notifications that are entity based could be missing.
            //    // tip: $user_entities[0] is the user's default entity
            //    NotificationEvent::raiseEvent('add_task', $glpi_item, array( 'entities_id' => $user_entities[0], 'task_id' => $glpi_task->getId(), 'is_private' => 0 ) ) ; 
            //}
            
            // and store link to task in DB
            $query = "INSERT INTO glpi_plugin_processmaker_tasks (items_id, itemtype, case_id, del_index) VALUES (".$glpi_task->getId().", '".$glpi_task->getType()."', '".$caseInfo->caseId."', ".$delIndex.");" ;
            $DB->query( $query ) ;
        }        
    }
    
    /**
     * Summary of add1stTask
     *      adds a GLPI task to ticket
     * @param $ticket_id integer ticket# to which a task must be added
     * @param $caseInfo getCaseInfoResponse object (see: getCaseInfo() function)
     * @return
     */
    public function add1stTask ( $itemType, $itemId,  $caseInfo, $txtTaskContent='' ) { 
        $this->addTask( $itemType, $itemId, $caseInfo, $caseInfo->currentUsers[0]->delIndex, Session::getLoginUserID(), 0, $caseInfo->currentUsers[0]->taskId, $txtTaskContent ) ;
    }
    
    
    ///
    /**
     * Summary of computeTaskDuration
     *  Compute duration of the task
     * @return mixed
     */
    function computeTaskDuration( $task, $entity ) {

        if (isset($task->fields['id']) && !empty($task->fields['begin'])) {
            $calendars_id = EntityData::getUsedConfig('calendars_id', $entity);
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
        global $DB ;
        
        $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
        $res = $DB->query($query) ; 
        if( $DB->numrows($res) > 0) {
            $row = $DB->fetch_array( $res ) ;
            $glpi_task = new $row['itemtype'] ;
            $glpi_task->getFromDB( $row['items_id'] ) ;
            
            $itemType = str_replace( 'Task', '', $row['itemtype'] ) ;
            $glpi_item = getItemForItemtype( $itemType );
            $glpi_item->getFromDB( $glpi_task->fields[ getForeignKeyFieldForItemType( $itemType ) ] ) ;
            
            $plug = new Plugin;            
            if( !$plug->isActivated('arbehaviours') ) { // check is done during Task update in this plugin
                $user_entities = Profile_User::getUserEntities( $newTech, true, true ) ;            
                $user_can_view = in_array( $glpi_item->fields['entities_id'], $user_entities );
                if( !$glpi_item->isUser( CommonITILObject::REQUESTER, $newTech ) && !$glpi_item->isUser( CommonITILObject::OBSERVER, $newTech ) && !$glpi_item->isUser( CommonITILObject::ASSIGN, $newTech ) && !$user_can_view ) {
                    // then we must add this tech user to watcher list
                    $glpi_item_user = getItemForItemtype( "{$itemType}_User" );
                    $glpi_item_user->add( array( strtolower(getPlural( $itemType )).'_id' => $glpi_item->getId() , 'users_id' => $newTech, 'type' => CommonITILObject::OBSERVER, '_no_notif' => true ) ) ;                    
                }
            }
                        
            $glpi_task->update( array( 'id' => $row['items_id'], 'users_id_tech' => $newTech )) ; 
            
            // this is now done into GLPI core
            //if( !$user_can_view ) { 
            //    // to cheat the entity rigths, passes default user_entity to raiseEvent(), to be sure that task_tech will receive a notification.
            //    // drawback: notifications that are entity based could be missing.
            //    // tip: $user_entities[0] is the user's default entity
            //    NotificationEvent::raiseEvent('update_task', $glpi_item, array( 'entities_id' => $user_entities[0], 'task_id' => $glpi_task->getId(), 'is_private' => 0 ) ) ; 
            //}
            
            // then update the delIndex
            $query = "UPDATE glpi_plugin_processmaker_tasks SET del_index = $newDelIndex WHERE case_id='$caseId' and del_index=$delIndex; ";
            $res = $DB->query($query) ;             
        }        
    }
    
    /**
     * Summary of solveTask
     * @param mixed $caseId 
     * @param mixed $delIndex 
     */
    public function solveTask( $caseId, $delIndex, $txtToAppend = '' ) {
        global $DB ;
        
        $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
        $res = $DB->query($query) ; 
        if( $DB->numrows($res) > 0) {
            $row = $DB->fetch_array( $res ) ;
                        
            $glpi_task = new $row['itemtype'] ;
            $glpi_task->getFromDB( $row['items_id'] ) ;
            $hostItem = getItemForItemtype( $glpi_task->getItilObjectItemType() ) ;
            $itemFKField = getForeignKeyFieldForItemType( $glpi_task->getItilObjectItemType() ) ;
            $hostItem->getFromDB( $glpi_task->fields[ $itemFKField ] ) ;
            $duration = $this->computeTaskDuration( $glpi_task,  $hostItem->fields['entities_id'] ) ;
            if( $txtToAppend <> "" ) $txtToAppend = "<hr>".$txtToAppend ;
            $glpi_task->update( array( 'id' => $row['items_id'], 'state' => 2, 'end' => $_SESSION["glpi_currenttime"], $itemFKField => $hostItem->getID(), 'actiontime' => $duration, 'users_id_tech' => Session::getLoginUserID(), 'content' => mysql_real_escape_string($glpi_task->fields[ 'content' ].$txtToAppend)  )) ;            
        }        
    }
    
    /**
     * Summary of claimTask
     * @param mixed $caseId 
     * @param mixed $delIndex 
     */
    public function claimTask( $caseId, $delIndex ) {
        global $DB ;
        
        $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE case_id='$caseId' and del_index=$delIndex; ";
        $res = $DB->query($query) ; 
        if( $DB->numrows($res) > 0) {
            $row = $DB->fetch_array( $res ) ;
            $glpi_task = new $row['itemtype'] ;
            $glpi_task->update( array( 'id' => $row['items_id'], 'users_id_tech' => Session::getLoginUserID() )) ;            
        }        
    }
	
    /**
     * Summary of getGLPIUserId
     *      returns GLPI user ID from a Processmaker user ID
     * @param string $pmUserId
     * @return GLPI user id, or 0 if not found
     */
    public static function getGLPIUserId( $pmUserId ){
        global $DB ;        
        $query = "SELECT * FROM glpi_plugin_processmaker_users WHERE pm_users_id='$pmUserId';" ;
	    $res = $DB->query($query) ;
  		if( $DB->numrows($res) > 0) {
            $row = $DB->fetch_array($res);
            return $row['glpi_users_id'];
        } else
            return 0 ;     
    }
    
    /**
     * Summary of getPMUserId
     *      returns processmaker user id for given GLPI user id
     *      user must exists in both systems
     * @param integer $glpi_userId id of user from GLPI database
     * @return a string which is the uid of user in Processmaker database
     */
    public static function getPMUserId( $glpiUserId ) {
        global $DB ;
        $query = "select pm_users_id from glpi_plugin_processmaker_users WHERE glpi_users_id=$glpiUserId;" ;
        $res = $DB->query( $query ) ;
        $row = $DB->fetch_array( $res ) ;
        return $row['pm_users_id'] ;
    }

    
    /**
     * Summary of getCaseIdFromItem
     *      get case id for an id item_id of type item_type (if a case if attached to it)
     * @param string $item_type, the type for the item ("Ticket", "Problem", ...)
     * @param integer $item_id, the id for the item
     * @return getCaseInfoResponse object, false if no case is attached to item, or if an error occurred
     */
    public static function getCaseIdFromItem ($item_type, $item_id ) {
        global $DB ;
        
        $query = "SELECT * FROM glpi_plugin_processmaker_cases WHERE `itemtype` = '$item_type' AND `items_id` = $item_id ;" ;
	    $res = $DB->query($query) ;
  		if( $DB->numrows($res) > 0) {
            // case is existing for this ticket
            // then get info from db
            $row = $DB->fetch_array($res);
            
            return $row['id']  ; 
        }
        
        return false ;
    }
    
    /**
     * Summary of getCaseFromItem
     *      get case infos for an id item_id of type item_type (if a case if attached to it)
     * @param string $item_type, the type for the item ("Ticket", "Problem", ...)
     * @param integer $item_id, the id for the item
     * @return getCaseInfoResponse object, false if no case is attached to item, or if an error occurred
     */
    public function getCaseFromItem( $item_type, $item_id ) {
        global $DB ;
        
        $caseId = self::getCaseIdFromItem( $item_type, $item_id ) ;
        if( $caseId !== false ) {
            $caseInfo = $this->getCaseInfo( $caseId ) ;
            if( $caseInfo !== false && $caseInfo->status_code == 0 )
                return $caseInfo ;     
            else                
                return false ; // means any error
        } else
            return false ; // means no case        
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
    
    public static function pre_show_item_processmakerticket($parm) {
        global $pmHideSolution ;       
        if( ($plug = new Plugin)  && !$plug->isActivated('arbehaviours') ) {
            if ($parm->getID() && in_array($parm->getType(), array('Ticket'))) {
                // then we are in a ticket
                if (isset($_REQUEST['glpi_tab']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk"  ) {
                    $data     = self::multiexplode(array('$','_'), $_REQUEST['glpi_tab']);
                    $itemtype = $data[0];
                    // Default set
                    $tabnum   = 1;
                    if (isset($data[1])) {
                        $tabnum = $data[1];
                    } 
                    elseif( $itemtype == -1 )
                        $tabnum = -1 ;
                
                    if( $data[0] == "processmaker" && $tabnum == 1 ) {
                    
                    }
                    if( ($data[0] == "Ticket" && $tabnum == 2) || $tabnum == -1) {
                        // then we are showing the Solution tab
                        // then we must prevent solving of ticket if a case is running
                        if( !PluginProcessmakerCases::canSolve( $parm ) ) {
                            // then output a new div to hide solution
                            $pmHideSolution = true ;
                            echo "<div id='toHideSolution' style='display: none;'>" ;                        
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Summary of post_show_item_processmakerticket
     * @param $parm
     */
    public static function post_show_item_processmakerticket($parm) {
        global $LANG, $pmHideSolution;
        if( ($plug = new Plugin)  && !$plug->isActivated('arbehaviours') ) { 
            if ($parm->getID() && in_array($parm->getType(), array('Ticket'))) {
                // then we are in a ticket
                if (isset($_REQUEST['glpi_tab']) && $_SESSION['glpiactiveprofile']['interface'] != "helpdesk" ) {
                    $data     = explode('$', $_REQUEST['glpi_tab']);
                    $itemtype = $data[0];
                    // Default set
                    $tabnum   = 1;
                    if (isset($data[1])) {
                        $tabnum = $data[1];
                    } 
                    elseif ($itemtype == -1 )
                        $tabnum = -1 ;
                    
                    if( $tabnum == 2 || $tabnum == -1 ) {
                        // then we are showing the Solution tab
                        // if a case is running
                        // then we must prevent solution to be input
                        if( $pmHideSolution ) { //isset($pmVar['GLPI_ITEM_CAN_BE_SOLVED']) && $pmVar['GLPI_ITEM_CAN_BE_SOLVED'] != 1 ) { //if( $pmInfo !== null && ( $pmInfo->caseStatus != 'COMPLETED' && $pmInfo->caseStatus != 'CANCELLED' )) {

                            echo "</div>";
                            if( $tabnum == -1 ) 
                                echo "</div>";
                            echo "<div style='margin-bottom: 20px;' class='box'>
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
                                                    <span class='red'>".$LANG['processmaker']['item']['preventsolution'][1]."
                                                        <br>
                                                    </span>
                                                </h3>
                                                <h3>
                                                <span >".$LANG['processmaker']['item']['preventsolution'][2]."                                                  
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
                    }
                }
            }
        }
    }   

    /**
     * Summary of canedit_item_processmakertickettask
     * @param $parm
     */
    public static function canedit_item_processmakertickettask($parm) {
        global $DB, $LANG, $_SESSION ;
        // if $parm is a TicketTask, then must check if Task is bound to a PM task
        $query = "SELECT * FROM glpi_plugin_processmaker_tasks WHERE itemtype='".$parm->getType()."' and items_id=".$parm->getId().";" ;
        $res = $DB->query($query) ;
        if( $DB->numrows($res) > 0) {
            $parm->fields['plugin_canedit'] = false ; // to prevent task edition    
            // replace ##ticket.url##_PluginProcessmakerCases$processmakercases by a setActiveTab to the Case panel
            $taskCat = new TaskCategory ;
            $taskCat->getFromDB( $parm->fields['taskcategories_id'] ) ;
            if( Session::haveTranslations('TaskCategory', 'comment') ) { 
                $parm->fields['content'] = str_replace( '##processmaker.taskcomment##', DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $_SESSION['glpilanguage'], $taskCat->fields['comment'] ), $parm->fields['content'] ) ;
            } else {
                $parm->fields['content'] = str_replace( '##processmaker.taskcomment##', $taskCat->fields['comment'], $parm->fields['content'] ) ;
            }
            $parm->fields['content'] = str_replace( '##ticket.url##_PluginProcessmakerCases$processmakercases', "<a href=\"javascript:tabpanel.setActiveTab('PluginProcessmakerCases\$processmakercases');\">".$LANG['processmaker']['item']['task']['manage_text']."</a>", $parm->fields['content'] ) ;            
            if( isset( $parm->fields['tr_id'] ) ) {
                $trID = $parm->fields['tr_id'] ;
                $parm->fields['content'] .= "<script>var loc$trID = document.getElementById('$trID'); loc$trID.style.cursor = 'pointer'; if (loc$trID.addEventListener) { loc$trID.addEventListener('click', function(){tabpanel.setActiveTab('PluginProcessmakerCases\$processmakercases');}, false);} else {loc$trID.attachEvent('onclick', function(){tabpanel.setActiveTab('PluginProcessmakerCases\$processmakercases');});  } </script>";
            }
        }
    }
      
    /**
     * Summary of getItemUsers
     * returns an array of glpi ids and pm ids for each user type assigned to given ticket
     * @param string $itemtype 
     * @param integer $itemId is the ID of the titem
     * @param integer $userType is 1 for ticket requesters, 2 for ticket technicians, and if needed, 3 for watchers
     * @return array of users in the returned array
     */
    public static function getItemUsers( $itemtype, $itemId, $userType ) {
		global $DB ;

        $users = array( ) ;
        
        //$itemtable = getTableForItemType( $itemtype ) ;
        $item = new $itemtype();
        $item_users = $item->userlinkclass ;
        $item_userstable = getTableForItemType( $item_users ) ;
        $itemlink = getForeignKeyFieldForItemType( $itemtype ) ;
		
		$query = "select glpi_plugin_processmaker_users.pm_users_id as pm_users_id, glpi_plugin_processmaker_users.glpi_users_id as glpi_users_id from $item_userstable 
				left join glpi_plugin_processmaker_users on glpi_plugin_processmaker_users.glpi_users_id = $item_userstable.users_id 
				where $item_userstable.$itemlink = $itemId and $item_userstable.type = $userType 
                order by $item_userstable.id" ;			            
		foreach( $DB->request( $query ) as $dbuser ) {
			$users[] = array( 'glpi_id' => $dbuser['glpi_users_id'], 'pm_id' => $dbuser['pm_users_id'] ) ;
		}
        
		return $users ;
	}
    
    /**
     * Summary of saveForm
     * This function posts dynaform variables to PM, using the CURL module.
     * @param mixed $request: is the $_REQUEST server array
     * @param string $cookie: is the $_SERVER['HTTP_COOKIE'] string
     * @return mixed: returns false if request failed, otherwise, returns true
     */
    public function saveForm( $request, $cookie ) {
        
		function HandleHeaderLine( $curl, $header_line ) {
			global $cookies;
			$temp = explode( ": ", $header_line ) ;
			if( is_array( $temp ) && $temp[0] == 'Set-Cookie' ) {
				$temp2 = explode( "; ", $temp[1]) ;
				//$cookies .= $temp2[0].'; ' ;			
				curl_setopt($curl, CURLOPT_COOKIE, $temp2[0]."; " ) ;
			}
			return strlen($header_line);
		}

        $data = http_formdata_flat_hierarchy( $request ) ;        

        $ch = curl_init();

		// curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1 ) ;
        // curl_setopt($ch, CURLOPT_PROXY, "localhost:10000");
		curl_setopt($ch, CURLOPT_HEADER, 1);      
        //		curl_setopt($ch, CURLOPT_VERBOSE, 1);
        //		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 

		curl_setopt($ch, CURLOPT_HEADERFUNCTION, "HandleHeaderLine");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        
        curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."&APP_UID=".$request['APP_UID']."&DEL_INDEX=".$request['DEL_INDEX']."&action=TO_DO" );
        $response = curl_exec ($ch);
		//Toolbox::logInFile( "pmtrace", "URL:\n".$this->serverURL."/cases/cases_open?sid=".$_SESSION["pluginprocessmaker"]["session"]["id"]."\nResponse:\n".$response."\n\n\n" ) ;

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        curl_setopt($ch, CURLOPT_URL, $this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID'] );

        $response = curl_exec ($ch);

        curl_close ($ch);
		//Toolbox::logInFile( "pmtrace", "URL:\n".$this->serverURL."/cases/cases_SaveData?UID=".$request['UID']."&APP_UID=".$request['APP_UID']."\nData:\n".print_r($data, true )."\nResponse:\n".$response."\n\n\n" ) ;
        
        return ($response ? true : false) ;

        //$n = preg_match("/HTTP\/1.1 302 /", $response, $matches);
        
        //return ($n < 1 ? false : true) ;
    }
  
    /**
     * Summary of plugin_item_get_datas_processmaker
     * @param mixed $item 
     */
    public static function plugin_item_get_datas_processmaker($item){
        global $_SESSION;
        if( isset( $item->datas ) && isset( $item->datas['tasks'] ) ){
            $config = PluginProcessmakerConfig::getInstance() ;
            $taskCat = new TaskCategory ;
            $target = reset( $item->target ) ; // to get first target in target array without knowing the key for this value
            // save current translations
            if( isset( $_SESSION['glpi_dropdowntranslations'] ) ) 
                $trans = $_SESSION['glpi_dropdowntranslations'] ; 
            // load available translations for this user
            $_SESSION['glpi_dropdowntranslations'] = DropdownTranslation::getAvailableTranslations($target['language']);
            foreach( $item->datas['tasks'] as &$task ) { // we must check if task category is PM task category or not, if yes then we add task category comment to datas
                $taskCat->getFromDB( $task['##task.category_id##'] ) ;    
                $ancestors = getAncestorsOf( 'glpi_taskcategories', $task['##task.category_id##'] ) ; //$ancestors = json_decode($taskCat->fields['ancestors_cache'], true) ;
                if( in_array( $config->fields['taskcategories_id'], $ancestors) ) {
                    $loc = DropdownTranslation::getTranslatedValue( $taskCat->getID(), 'TaskCategory', 'comment', $target['language'], $taskCat->fields['comment'] ) ;
                    $task['##processmaker.taskcomment##'] = $loc; //['comment'] ;
                }
            }
            // restore default translations
            if( isset( $trans ) )
                $_SESSION['glpi_dropdowntranslations'] = $trans ;
            else
                unset( $_SESSION['glpi_dropdowntranslations']  ) ;
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
        
        $processList = array( ) ;
        $entityAncestors = implode( ", ", getAncestorsOf( getTableForItemType( 'Entity' ), $entity ) ) ;

        $query ="SELECT DISTINCT glpi_plugin_processmaker_processes.id, glpi_plugin_processmaker_processes.name FROM glpi_plugin_processmaker_processes 
            INNER JOIN glpi_plugin_processmaker_processes_profiles ON glpi_plugin_processmaker_processes_profiles.processes_id=glpi_plugin_processmaker_processes.id 
            WHERE is_active = 1 AND itilcategories_id = $category AND `type` = $type AND profiles_id = $profile  AND (entities_id = $entity OR (entities_id IN ($entityAncestors) AND is_recursive = 1))" ;

        foreach( $DB->request( $query ) as $row ) {
            $processList[] = $row ;
        }

        return $processList ;

    }

}

?>