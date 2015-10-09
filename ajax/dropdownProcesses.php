<?php

// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// Purpose of file:
// ----------------------------------------------------------------------

// Direct access to file
if (strpos($_SERVER['PHP_SELF'],"dropdownProcesses.php")) {
    $AJAX_INCLUDE = 1;
    define('GLPI_ROOT','../../..');
    include (GLPI_ROOT."/inc/includes.php");
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
    die("Can not acces directly to this file");
}

include_once dirname(__FILE__)."/../inc/process.class.php" ;

Session::checkLoginUser();

if (!isset($_POST['right'])) {
    $_POST['right'] = "all";
}

// Default view : Nobody
if (!isset($_POST['all'])) {
    $_POST['all'] = 0;
}

$used = array();

if (isset($_POST['used'])) {
    if (is_array($_POST['used'])) {
        $used = $_POST['used'];
    } else {
        $used = unserialize(stripslashes($_POST['used']));
    }
}

if (isset($_POST["entity_restrict"])
    && !is_numeric($_POST["entity_restrict"])
    && !is_array($_POST["entity_restrict"])) {

    $_POST["entity_restrict"] = unserialize(stripslashes($_POST["entity_restrict"]));
}

$result = PluginProcessmakerProcess::getSqlSearchResult(false, $_POST['right'], $_POST["entity_restrict"],
                                   $_POST['value'], $used, $_POST['searchText']);

$processes = array();


if ($DB->numrows($result)) {
    while ($data=$DB->fetch_array($result)) {
        if( in_array( $_POST["entity_restrict"], PluginProcessmakerProcess::getEntitiesForProfileByProcess( $data["id"], $_SESSION['glpiactiveprofile']['id'], true) ) ) {
            $processes[$data["id"]] = $data["name"];
        }
    }
}


echo "<select id='dropdown_".$_POST["myname"].$_POST["rand"]."' name='".$_POST['myname']."'";

if (isset($_POST["on_change"]) && !empty($_POST["on_change"])) {
    echo " onChange='".$_POST["on_change"]."'";
}

echo ">";

if ($_POST['searchText']!=$CFG_GLPI["ajax_wildcard"]
    && $DB->numrows($result)==$CFG_GLPI["dropdown_max"]) {

    echo "<option value='0'>--".$LANG['common'][11]."--</option>";
}

if ($_POST['all']==0) {
    echo "<option value='0'>".Dropdown::EMPTY_VALUE."</option>";
} else if ($_POST['all']==1) {
    echo "<option value='0'>[".$LANG['common'][66]."]</option>";
}

if (isset($_POST['value'])) {
    $output = PluginProcessmakerProcess::getProcessName($_POST['value']);

    if (!empty($output) && $output!="&nbsp;") {
        echo "<option selected value='".$_POST['value']."'>".$output."</option>";
    }
}

if (count($processes)) {
    foreach ($processes as $ID => $output) {
        echo "<option value='$ID' title=\"".Html::cleanInputText($output)."\">".
        Toolbox::substr($output, 0, $_SESSION["glpidropdown_chars_limit"])."</option>";
    }
}
echo "</select>";

if (isset($_POST["comment"]) && $_POST["comment"]) {
    $paramscomment = array('value' => '__VALUE__',
                           'table' => "glpi_plugin_processmaker_processes");

    if (isset($_POST['update_link'])) {
        $paramscomment['withlink'] = "comment_link_".$_POST["myname"].$_POST["rand"];
    }
    Ajax::updateItemOnSelectEvent("dropdown_".$_POST["myname"].$_POST["rand"],
                                  "comment_".$_POST["myname"].$_POST["rand"],
                                  $CFG_GLPI["root_doc"]."/ajax/comments.php", $paramscomment);
}

Ajax::commonDropdownUpdateItem($_POST);
?>