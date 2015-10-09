<?php

$LANG['processmaker']['title'][1]="Process Maker";
$LANG['processmaker']['title'][2]="Process";
$LANG['processmaker']['title'][3]="Task List";
$LANG['processmaker']['title'][4]="Authorizations";

$LANG['processmaker']['profile']['rightmgt']="Rights Management";
$LANG['processmaker']['profile']['process_config']="Process configuration";

$LANG['processmaker']['process']['process_guid']="Process GUID";
$LANG['processmaker']['process']['hide_case_num_title']="Hide case number and title in task descriptions";
$LANG['processmaker']['process']['insert_task_comment']="Insert Task Category comments in Task Description";
$LANG['processmaker']['process']['type']="Type (helpdesk)";
$LANG['processmaker']['process']['itilcategory']="ITIL Category (helpdesk)";
$LANG['processmaker']['process']['taskcategories']['guid']="Task GUID";
$LANG['processmaker']['process']['taskcategories']['name']="Task name";
$LANG['processmaker']['process']['taskcategories']['completename']="Complete name";
$LANG['processmaker']['process']['taskcategories']['start']="Start";
$LANG['processmaker']['process']['taskcategories']['comment']="Comment";

$LANG['processmaker']['config']['name']="Name";
$LANG['processmaker']['config']['URL']="Process Maker Server URL";
$LANG['processmaker']['config']['workspace']="Workspace Name";
$LANG['processmaker']['config']['theme']="Theme Name";
$LANG['processmaker']['config']['comments']="Comments";
$LANG['processmaker']['config']['refreshprocesslist']="Synchronize Process List";
$LANG['processmaker']['config']['refreshtasklist']="Synchronize Task List";
$LANG['processmaker']['config']['main_task_category']="Main Task Category (edit to change name)";
$LANG['processmaker']['config']['taskwriter']="Task Writer (edit to change name)";
$LANG['processmaker']['config']['pm_group_name']="Group in Process Maker which contains all GLPI users (lang : name)";
    
$LANG['processmaker']['item']['tab']="Process - Case";
$LANG['processmaker']['item']['cancelledcase']="Status: Cancelled";
$LANG['processmaker']['item']['pausedtask']="Status: Task is paused - unpause it?";
$LANG['processmaker']['item']['completedcase']="Status: Completed";
$LANG['processmaker']['item']['nocase']="No case for this item!";
$LANG['processmaker']['item']['startone']="Start one?";
$LANG['processmaker']['item']['selectprocess']="Select the process you want to start:";
$LANG['processmaker']['item']['start']="Start";
$LANG['processmaker']['item']['unpause']="Unpause";
$LANG['processmaker']['item']['deletecase']="Delete case?" ;
$LANG['processmaker']['item']['buttondeletecase']="Delete" ;
$LANG['processmaker']['item']['reassigncase']="Re-assign task to:";
$LANG['processmaker']['item']['buttonreassigncase']="Re-assign";
$LANG['processmaker']['item']['cancelcase']="Cancel case?" ;
$LANG['processmaker']['item']['buttoncancelcase']="Cancel" ;
$LANG['processmaker']['item']['buttondeletecaseconfirmation']="Delete this case?" ;
$LANG['processmaker']['item']['buttoncancelcaseconfirmation']="Cancel this case?" ;

$LANG['processmaker']['item']['case']['deleted']="Case has been deleted!";
$LANG['processmaker']['item']['case']['errordeleted']="Unable to delete case!";
$LANG['processmaker']['item']['case']['cancelled']="Case has been cancelled!";
$LANG['processmaker']['item']['case']['errorcancelled']="Unable to cancel case!";
$LANG['processmaker']['item']['case']['notreassigned']="Error re-assigning task: ";
$LANG['processmaker']['item']['case']['assignedtoyou']="Task already assigned to this person!";
$LANG['processmaker']['item']['case']['reassigned']="Task re-assigned!";
$LANG['processmaker']['item']['case']['casemap']="Case map";
$LANG['processmaker']['item']['case']['casehistory']='Case History' ;
$LANG['processmaker']['item']['case']['dynaforms']='Dynaforms';
$LANG['processmaker']['item']['case']['changelog']='Change Log';
$LANG['processmaker']['item']['case']['caseinfo']='Case info';
$LANG['processmaker']['item']['case']['viewcasemap']='View Case Map';
$LANG['processmaker']['item']['case']['viewcasehistory']='View Case History';
$LANG['processmaker']['item']['case']['viewdynaforms']='View Dynaforms';

$LANG['processmaker']['item']['error'][11]="Error creating case!";
$LANG['processmaker']['item']['error'][14]="Can't create case: no rights for it!";

$LANG['processmaker']['item']['preventsolution'][1]="A 'Case' is running!";
$LANG['processmaker']['item']['preventsolution'][2]="You must manage it first (see 'Process - Case' tab)!";

$LANG['processmaker']['item']['task']['process']="Bound to process: ";
$LANG['processmaker']['item']['task']['case']="Case title: ";
$LANG['processmaker']['item']['task']['task']="Task: ";
$LANG['processmaker']['item']['task']['comment']="##processmaker.taskcomment##" ;
$LANG['processmaker']['item']['task']['manage']="##ticket.url##_PluginProcessmakerCases\$processmakercases" ; //"Go to: ##ticket.url##_PluginProcessmakerCases\$processmakercases" ;
$LANG['processmaker']['item']['task']['manage_text']= "" ; //"Process - Case tab";

$LANG['processmaker']['case']['statuses']['TO_DO'] = "Processing";
$LANG['processmaker']['case']['statuses']['CANCELLED'] = "Cancelled";
$LANG['processmaker']['case']['statuses']['DRAFT'] = "New";
$LANG['processmaker']['case']['statuses']['COMPLETED'] = "Closed";

$LANG['processmaker']['search']['case']="Case";
$LANG['processmaker']['search']['status']="Status";
$LANG['processmaker']['search']['processtitle']="Process Title";
$LANG['processmaker']['search']['casetitle']="Case Title";
$LANG['processmaker']['search']['hascase']="Running Case?";

$LANG['processmaker']['cron']['pmusers']="Syncs GLPI users and pseudo-groups into ProcessMaker."  ;
$LANG['processmaker']['cron']['pmnotifications']="Notifications for GLPI Tasks bound to ProcessMaker Tasks." ;
