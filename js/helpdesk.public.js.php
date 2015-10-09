<?php
// Direct access to file
if (strpos($_SERVER['PHP_SELF'],"processmaker/js/helpdesk.public.js.php")) {
    $AJAX_INCLUDE = 1;
    define('GLPI_ROOT','../../..');
    include (GLPI_ROOT."/inc/includes.php");
    header("Content-type: application/javascript");
    Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
    die("Can not access directly to this file");
}

echo "Ext.onReady(function () {

        function showMask(){Ext.getBody().moveTo( 0, 0); var myMask = new Ext.LoadMask(Ext.getBody(), {removeMask:false}); myMask.show();};

       // look if name='helpdeskform' is present. If yes replace the form.location
        var ahrefTI = '".$CFG_GLPI["root_doc"]."/plugins/processmaker/front/tracking.injector.php';
        var formLink = Ext.select(\"form[name='helpdeskform']\").elements[0];
        if (formLink != undefined) {
            formLink.action = ahrefTI;
        }
        var ticketType = Ext.select(\"select[name='type']\").elements[0];
        if (ticketType != undefined) {            
            ticketType.addEventListener( 'change', showMask );
        }
        var ticketCat = Ext.select(\"select[name='itilcategories_id']\").elements[0];
        if (ticketCat!= undefined) {            
            ticketCat.addEventListener( 'change', showMask );
        }

    });

"; // end of echo


//Ext.onReady(function () {
//    var ahref = "../plugins/processmaker/front/processmaker.helpdesk.form.php";
//    var markup = '';
//    // look for menu id = menu2 to insert a new menu
//    var createTicketMenu = Ext.get('menu2');
//    if (createTicketMenu != undefined) {
//        markup = '<li id="menu2"><a href="' + ahref + '" class="itemP" title="Create User ticket">Create User ticket</a></li>';
//        Ext.DomHelper.insertAfter(createTicketMenu, markup)
//    }
    
//    // look for 'Create a ticket' in order to insert a link <a>
//    var ticketLink = Ext.select('table.tab_cadrehov tbody tr th a').elements[0];
//    if (ticketLink != undefined) {
//        var img = Ext.select('table.tab_cadrehov tbody tr th a img').elements[0];
//        // add a link to the user management screen
//        markup = '&nbsp;<a href="' + ahref + '">User Management Ticket<img src="' + img.attributes["src"].value + '"></a>';
//        Ext.DomHelper.insertAfter(ticketLink, markup)
//        //window.clearInterval(ticketTimer)
//    }

    

//}); 

?>