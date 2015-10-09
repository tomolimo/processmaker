<?php


if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


class PluginProcessmakerProfile extends CommonDBTM {


    //if profile deleted
    static function cleanProfiles(Profile $prof) {

        $plugprof = new self();
        $plugprof->delete(array('id' => $prof->getID()));
    }


    static function select() {

        $prof = new self();
        if ($prof->getFromDBByProfile($_SESSION['glpiactiveprofile']['id'])) {
            $_SESSION["glpi_plugin_processmaker_profile"] = $prof->fields;
        } else {
            unset($_SESSION["glpi_plugin_processmaker_profile"]);
        }
    }


    //profiles modification
    function showForm($ID, $options=array()) {
        global $LANG;

        $target = $this->getFormURL();
        if (isset($options['target'])) {
            $target = $options['target'];
        }

        if (!Session::haveRight("profile","r")) {
            return false;
        }

        $canedit = Session::haveRight("profile", "w");
        $prof = new Profile();
        if ($ID) {
            $this->getFromDBByProfile($ID);
            $prof->getFromDB($ID);
        }
        echo "<form action='".$target."' method='post'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='2'>".$LANG['processmaker']['profile']['rightmgt']." : ".$prof->fields["name"].
        "</th></tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<td>".$LANG['processmaker']['profile']['process_config']." :</td><td>";

        if ($prof->fields['interface']!='helpdesk') {
            Profile::dropdownNoneReadWrite("process_config", $this->fields["process_config"], 1, 1, 1);
        } else {
            echo $LANG['profiles'][12]; // No access;
        }
        echo "</td></tr>";


        if ($canedit) {
            echo "<tr class='tab_bg_1'>";
            echo "<td class='center' colspan='2'>";
            echo "<input type='hidden' name='id' value=".$this->getID().">";
            echo "<input type='submit' name='update_user_profile' value=\"".$LANG['buttons'][7]."\"
               class='submit'>";
            echo "</td></tr>";
        }
        echo "</table>";
        Html::closeForm();
    }

    function getFromDBByProfile($profiles_id) {
		global $DB;
		
		$query = "SELECT * FROM `".$this->getTable()."`
					WHERE `profiles_id` = '" . $profiles_id . "' ";
		if ($result = $DB->query($query)) {
			if ($DB->numrows($result) != 1) {
				return false;
			}
			$this->fields = $DB->fetch_assoc($result);
			if (is_array($this->fields) && count($this->fields)) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
    
    static function createAdminAccess($ID) {
        
        $myProf = new self();
        if (!$myProf->getFromDBByProfile($ID)) {

            $myProf->add(array(
               'profiles_id' => $ID,
               'process_config' => 'w'
               ));
            
        }
    }


    function createUserAccess($Profile) {

        return $this->add(array('profiles_id'   => $Profile->getID()
                                ));
    }


    function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
        global $LANG;

        if ($item->getType()=='Profile') {
            return $LANG['processmaker']['title'][1];
        }
        return '';
    }


    static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
        global $CFG_GLPI;

        if ($item->getType()=='Profile') {
            $ID = $item->getID();
            $prof = new self();
            if ($prof->getFromDBByProfile($ID) || $prof->createUserAccess($item)) {
                $prof->showForm($ID);
            }
        }
        return true;
    }
}

?>