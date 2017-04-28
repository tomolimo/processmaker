<?php

$LANG['processmaker']['title'][1]="ProcessMaker";
$LANG['processmaker']['title'][2]="Processus";
$LANG['processmaker']['title'][3]="Liste des tâches";
$LANG['processmaker']['title'][4]="Autorisations";

$LANG['processmaker']['profile']['rightmgt']="Gestion des droits";
$LANG['processmaker']['profile']['process_config']="Configuration des Processus";
$LANG['processmaker']['profile']['case_delete']="Supprimer cas";

$LANG['processmaker']['process']['process_guid']="GUID du Processus";
$LANG['processmaker']['process']['project_type_short']="Type de projet" ;
$LANG['processmaker']['process']['project_type']="Type de projet (à modifier si pas à jour)" ;
$LANG['processmaker']['process']['project_type_bpmn']='BPMN' ;
$LANG['processmaker']['process']['project_type_classic']='Classique' ;
$LANG['processmaker']['process']['taskcategories']['guid']="GUID de la Tâche";
$LANG['processmaker']['process']['hide_case_num_title_short']="Masquer numéro et titre des Cas";
$LANG['processmaker']['process']['hide_case_num_title']="Masquer numéro et titre des Cas dans les descriptions des tâches";
$LANG['processmaker']['process']['insert_task_comment_short']="Insérer Catégories de Tâches";
$LANG['processmaker']['process']['insert_task_comment']="Insérer les commentaires des Catégories de Tâches dans les descriptions des Tâches";
$LANG['processmaker']['process']['type']="Type de ticket (self-service)";
$LANG['processmaker']['process']['itilcategory']="Catégorie ITIL (self-service)";
$LANG['processmaker']['process']['taskcategories']['name']="Nom de la Tâche";
$LANG['processmaker']['process']['taskcategories']['completename']="Nom complet";
$LANG['processmaker']['process']['taskcategories']['start']="Début";
$LANG['processmaker']['process']['taskcategories']['comment']="Commentaire";
$LANG['processmaker']['process']['profile']="Effacer définitivement";

$LANG['processmaker']['config']['name']="Nom";
$LANG['processmaker']['config']['setup']="Configuration du serveur ProcessMaker";
$LANG['processmaker']['config']['mysql']="Configuration du serveur SQL";
$LANG['processmaker']['config']['URL']="URL du serveur (doit être dans le même domaine que GLPI)";
$LANG['processmaker']['config']['workspace']="Nom du Workspace";
$LANG['processmaker']['config']['admin']['user']="Non de l'administreur du server ProcessMaker";
$LANG['processmaker']['config']['admin']['password']="Mot de passe de l'administrateur du serveur ProcessMaker";
$LANG['processmaker']['config']['theme']="Nom du Theme";
$LANG['processmaker']['config']['comments']="Commentaires";
$LANG['processmaker']['config']['refreshprocesslist']="Synchroniser la liste des processus";
$LANG['processmaker']['config']['refreshtasklist']="Synchroniser la liste des Tâches";
$LANG['processmaker']['config']['main_task_category']="Categorie principale des tâches (editer pour changer le nom)";
$LANG['processmaker']['config']['taskwriter']="Auteur des tâches (editer pour changer le nom)";
$LANG['processmaker']['config']['pm_group_name']="Groupe dans ProcessMaker qui contiendra les utilisateurs de GLPi";
$LANG['processmaker']['config']['maintenance']="Mode maintenance" ;
$LANG['processmaker']['config']['undermaintenance']="En maintenance, veuillez réessayer plus tard." ;
$LANG['processmaker']['config']['empty_pm_group_name']="Pour sélectionner un groupe, renseigner d'abord la config de ProcessMaker !";
$LANG['processmaker']['config']['test']="Test";
$LANG['processmaker']['config']['connectionstatus']="Status de la connexion";
$LANG['processmaker']['config']['domain']="Domaine utilisé";
$LANG['processmaker']['config']['domain-error']="Aucun !";

$LANG['processmaker']['item']['tab']="Processus - Cas";
$LANG['processmaker']['item']['cancelledcase']="Statut : Annulé";
$LANG['processmaker']['item']['pausedtask']="Statut : Tâche en pause - la relancer ?";
$LANG['processmaker']['item']['completedcase']="Statut : Terminé";
$LANG['processmaker']['item']['nocase']="Pas de cas en cours pour cet item!";
$LANG['processmaker']['item']['startone']="Démarrer un nouveau cas ?";
$LANG['processmaker']['item']['selectprocess']="Choisir le processus à démarrer :";
$LANG['processmaker']['item']['start']="Démarrer";
$LANG['processmaker']['item']['unpause']="Relancer";
$LANG['processmaker']['item']['deletecase']="Effacer cas ?" ;
$LANG['processmaker']['item']['buttondeletecase']="Effacer" ;
$LANG['processmaker']['item']['reassigncase']="Ré-affecter tâche à :";
$LANG['processmaker']['item']['buttonreassigncase']="Ré-affecter";
$LANG['processmaker']['item']['cancelcase']="Annuler cas ?" ;
$LANG['processmaker']['item']['buttoncancelcase']="Annuler" ;
$LANG['processmaker']['item']['buttondeletecaseconfirmation']="Effacer ce cas ?" ;
$LANG['processmaker']['item']['buttoncancelcaseconfirmation']="Annuler ce cas ?" ;

$LANG['processmaker']['item']['case']['deleted']="Le cas a été effacé !";
$LANG['processmaker']['item']['case']['errordeleted']="Impossible d'effacer le cas !";
$LANG['processmaker']['item']['case']['cancelled']="Le cas a été annulé !";
$LANG['processmaker']['item']['case']['errorcancelled']="Impossible d'annuler le cas !";
$LANG['processmaker']['item']['case']['notreassigned']="Impossible de re-assigner cette tâche : ";
$LANG['processmaker']['item']['case']['assignedtoyou']="Tâche déjà assignée à cette personne !";
$LANG['processmaker']['item']['case']['reassigned']="Tâche ré-assignée !";
$LANG['processmaker']['item']['case']['casemap']="Carte du cas";
$LANG['processmaker']['item']['case']['casehistory']='Historique du cas' ;
$LANG['processmaker']['item']['case']['dynaforms']='Dynaforms';
$LANG['processmaker']['item']['case']['changelog']='Historique des modifications';
$LANG['processmaker']['item']['case']['caseinfo']='Infos du cas';
$LANG['processmaker']['item']['case']['viewcasemap']='Voir carte du cas';
$LANG['processmaker']['item']['case']['viewcasehistory']='Voir historique du cas';
$LANG['processmaker']['item']['case']['viewdynaforms']='Voir dynaforms';
$LANG['processmaker']['item']['case']['viewcasechangeloghistory']='Voir historique évolutions' ;
$LANG['processmaker']['item']['case']['casechangeloghistory']='Historique évolutions' ;

$LANG['processmaker']['item']['error'][11]="Erreur à la création du cas !";
$LANG['processmaker']['item']['error'][14]="Impossible de créer le cas : pas de droits pour cela !";
$LANG['processmaker']['item']['error'][100]=$LANG['processmaker']['item']['error'][11];

$LANG['processmaker']['item']['preventsolution'][1]="Un 'Cas' est en cours !";
$LANG['processmaker']['item']['preventsolution'][2]="Vous devez d'abord le terminer (voir onglet 'Processus - Cas') !";

$LANG['processmaker']['item']['task']['process']="Lié au processus : ";
$LANG['processmaker']['item']['task']['case']="Titre du cas : ";
$LANG['processmaker']['item']['task']['task']="Tâche : ";
$LANG['processmaker']['item']['task']['comment']="##processmaker.taskcomment##" ;
$LANG['processmaker']['item']['task']['manage']="##ticket.url##_PluginProcessmakerCase\$processmakercases" ;
$LANG['processmaker']['item']['task']['manage_text']=""; //"l'onglet Processus - Cas";

$LANG['processmaker']['case']['statuses']['TO_DO'] = "En cours";
$LANG['processmaker']['case']['statuses']['CANCELLED'] = "Annulé";
$LANG['processmaker']['case']['statuses']['DRAFT'] = "Nouveau";
$LANG['processmaker']['case']['statuses']['COMPLETED'] = "Achevé";

$LANG['processmaker']['search']['case']="Cas";
$LANG['processmaker']['search']['status']="Statut";
$LANG['processmaker']['search']['processtitle']="Nom du processus";
$LANG['processmaker']['search']['casetitle']="Titre du cas";
$LANG['processmaker']['search']['hascase']="Cas en cours ?";

$LANG['processmaker']['cron']['pmusers']="Synchro des utilisateurs GLPI et des groups avec ProcessMaker."  ;
//$LANG['processmaker']['cron']['pmnotifications']="Notifications des tâches GLPI liées à des tâches de ProcessMaker." ;
$LANG['processmaker']['cron']['pmtaskactions']="Pour appliquer les actions de tâches entre cas."  ;
$LANG['processmaker']['cron']['pmorphancases']['description']="Nettoyage des cas orphelins." ;
$LANG['processmaker']['cron']['pmorphancases']['parameter']="Nombre de jours avant nettoyage des cas orphelins.";
