<?php

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

include( "../../../inc/includes.php");

if (empty($_POST["_type"])
    || ($_POST["_type"] != "Helpdesk")
    || !$CFG_GLPI["use_anonymous_helpdesk"]) {
    Session::checkRight("ticket", CREATE);
}

// Security check
if (empty($_POST) || count($_POST) == 0) {
    Html::redirect($CFG_GLPI["root_doc"]."/front/helpdesk.public.php");
}

// here we are going to test if we must start a process
if( isset($_POST["_from_helpdesk"]) && $_POST["_from_helpdesk"] == 1
    && isset($_POST["type"]) //&& $_POST["type"] == Ticket::DEMAND_TYPE
    && isset($_POST["itilcategories_id"])
    && isset($_POST["entities_id"])) {
    // here we have to check if there is an existing process in the entity and with the category
    // if yes we will start it
    // if not we will continue
    // special case if RUMT plugin is enabled and no process is available and category is 'User Management' then must start RUMT.

    $processList = PluginProcessmakerProcessmaker::getProcessesWithCategoryAndProfile( $_POST["itilcategories_id"], $_POST["type"], $_SESSION['glpiactiveprofile']['id'], $_SESSION['glpiactive_entity'] ) ;

    // currently only one process should be assigned to this itilcategory so this array should contain only one row
    $processQt = count( $processList ) ;
    if( $processQt == 1 ) {
        $_POST['action']='newcase';
        $_POST['plugin_processmaker_process_id'] = $processList[0]['id'];
        include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.form.php");
        die() ;
    } elseif( $processQt > 1 ) {
        // in this case we should show the process dropdown selection
        include (GLPI_ROOT . "/plugins/processmaker/front/processmaker.helpdesk.form.php");
        die() ;
    } else{
        // in this case should start RUMT
        // if and only if itilcategories_id matches one of the 'User Management' categories
        // could be done via ARBehviours or RUMT itself
        $userManagementCat = array( 100556, 100557, 100558 ) ;
        $plug = new Plugin ;
        if( $processQt == 0 && in_array( $_POST["itilcategories_id"], $userManagementCat) && $plug->isActivated('rayusermanagementticket' )) {
            Html::redirect($CFG_GLPI['root_doc']."/plugins/rayusermanagementticket/front/rayusermanagementticket.helpdesk.public.php");

        }
    }


}

if( !function_exists('http_formdata_flat_hierarchy') ) {
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
}

if( !function_exists('tmpdir') ) {
    /**
     * Summary of tmpdir
     * Will attempts $attempts to create a random temp dir in $path
     * see: http://php.net/manual/en/function.mkdir.php
     * @param string $path: dir into the temp subdir will be created
     * @param string $prefix: used to prefix the random number for dir name
     * @param int $attempts: is the quantity of attempts trying to create tempdir
     * @return bool|string: false if $attempts has been reached, otherwise the path to the newly created dir
     */
    function tmpdir($path, $prefix='', $attempts=3){
        $count = 1 ;
        do {
            $rand=$prefix.rand() ;
        } while( !mkdir($path.'/'.$rand) && $count++ < $attempts ) ;

        return ($count < $attempts ? $path.'/'.$rand : false ) ;
    }
}

// by default loads standard page from GLPI
//include (GLPI_ROOT . "/front/tracking.injector.php");

$ch = curl_init();
curl_setopt($ch, CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);

// why not 		[HTTP_REFERER]	"http://fry07689-glpi090.fr.ray.group/front/helpdesk.public.php?create_ticket=1"	string
curl_setopt($ch, CURLOPT_REFERER, "http://".$_SERVER['SERVER_NAME' ].$CFG_GLPI["root_doc"]."/front/tracking.injector.php" ) ;

curl_setopt($ch, CURLOPT_POST, 1);
$data = http_formdata_flat_hierarchy( $_REQUEST ) ;

// CSRF management
if( GLPI_USE_CSRF_CHECK ) {
   // must set a csrf token
   $data['_glpi_csrf_token'] = Session::getNewCSRFToken() ;
}

// need to add files if some are uploaded
$files = array() ;
$paths = array() ;
if( isset( $_FILES['filename']['name'] ) && is_array($_FILES['filename']['name']) && count($_FILES['filename']['name']) > 0) {
    foreach( $_FILES['filename']['name'] as $num => $file ){
        if( $file <> '' ){
            $path = str_replace( '\\', '/', $_FILES['filename']['tmp_name'][$num] ) ;
            $path = explode('/', $path);
            array_pop( $path ) ;
            $path = tmpdir(implode( '/', $path ), 'php_tmp') ;
            if( $path !== false ) {
                $paths[$num] = $path;
                $files[$num] = $paths[$num].'/'.$file;
                copy( $_FILES['filename']['tmp_name'][$num], $files[$num] ) ;
                $data['filename['.$num.']']='@'.$files[$num] ;
            }
        }
    }
}

curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1 ) ;
//curl_setopt($ch, CURLOPT_PROXY, "localhost:8888");

curl_setopt($ch, CURLOPT_URL, "http://".$_SERVER['SERVER_NAME' ].$CFG_GLPI["root_doc"]."/front/tracking.injector.php");

// as sessions in PHP are not re-entrant, we MUST close current one before curl_exec
@session_write_close() ;

curl_exec ($ch);

curl_close ($ch);

// need to delete temp files
foreach( $files as $file ) {
    unlink( $file ) ;
}
foreach( $paths as $path ) {
    rmdir( $path ) ;
}

