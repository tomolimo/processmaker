
USE wf_workflow;

DROP TRIGGER IF EXISTS `GLPI_APPLICATION_DELETE` ;
DROP TRIGGER IF EXISTS `GLPI_APP_DELAY_INSERT` ;
DROP TRIGGER IF EXISTS `GLPI_APP_DELAY_UPDATE` ;
DROP TRIGGER IF EXISTS `GLPI_APP_DELEGATION_INSERT` ;
DROP TRIGGER IF EXISTS `GLPI_APP_DELEGATION_UPDATE` ;
DROP TRIGGER IF EXISTS `GLPI_APP_DELEGATION_DELETE` ;


DELIMITER //

CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APPLICATION_DELETE` AFTER DELETE ON `application` FOR EACH ROW BEGIN
	DECLARE loc_Ticket_id INT(11) ; 
	SELECT ticket_id INTO loc_Ticket_id FROM glpi.glpi_plugin_processmaker_ticketcase WHERE case_id=OLD.APP_UID;
	IF loc_Ticket_id IS NOT NULL THEN
		DELETE FROM glpi.glpi_plugin_processmaker_ticketcase WHERE case_id=OLD.APP_UID ;
	END IF;
END //

CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APP_DELAY_INSERT` AFTER INSERT ON `app_delay` FOR EACH ROW BEGIN
	DECLARE loc_task_cat_id, loc_Ticket_id, loc_Found_Pos, loc_glpi_users_id, loc_Count_Ticket INT(11) ;
  	DECLARE APP_TITLE, APP_PRO_TITLE, APP_TAS_TITLE VARCHAR(255);
  	DECLARE loc_pm_user_id VARCHAR(32) ;

	IF NEW.APP_TYPE = 'PAUSE' THEN
		SELECT ticket_id INTO loc_Ticket_id FROM glpi.glpi_plugin_processmaker_ticketcase WHERE case_id=NEW.APP_UID;
		IF loc_Ticket_id IS NOT NULL THEN
	
	  		SELECT CONTENT.CON_VALUE into APP_TITLE FROM CONTENT WHERE NEW.APP_UID=CON_ID AND CON_CATEGORY='APP_TITLE' and CON_LANG = 'en' LIMIT 1;
	  		IF APP_TITLE IS NULL THEN
	   	 		SET APP_TITLE = '';
	  		END IF;
	  		SELECT CONTENT.CON_VALUE into APP_PRO_TITLE FROM CONTENT WHERE NEW.PRO_UID=CON_ID AND CON_CATEGORY='PRO_TITLE' and CON_LANG = 'en' LIMIT 1;
	  		SET APP_TAS_TITLE = 'Case is paused';

			SELECT glpi_users_id INTO loc_glpi_users_id FROM glpi.glpi_plugin_processmaker_users WHERE pm_users_id=NEW.APP_DELEGATION_USER LIMIT 1;	
			
			SELECT id INTO loc_task_cat_id FROM glpi.glpi_taskcategories WHERE name=APP_PRO_TITLE;
			IF loc_task_cat_id IS NULL THEN
				SET loc_task_cat_id=0 ;
			END IF ;
			
			INSERT INTO glpi.glpi_tickettasks 
					( `tickets_id`, `taskcategories_id`, `date`, `users_id`, `content`, `is_private`, `actiontime`, `begin`, `end`, `state`, `users_id_tech`) 
					VALUES ( loc_Ticket_id, 
							loc_task_cat_id, 
							Now(), 
							1, 
							CONCAT( 'Bound to process: ', APP_PRO_TITLE, ',<br/>case title: ', APP_TITLE, ',<br/>task: ', APP_TAS_TITLE,'.<br/><a href="?id=', loc_Ticket_id, '&forcetab=processmaker_1">Go to Case tab to manage!</a>' ),
							0, 
							0, 
							NEW.APP_ENABLE_ACTION_DATE, 
							NEW.APP_DISABLE_ACTION_DATE, 
							1, 
							loc_glpi_users_id);
			
			INSERT INTO glpi.glpi_plugin_processmaker_tasks (`tickettasks_id`, `case_id`, `del_index`) VALUES (LAST_INSERT_ID(), NEW.APP_DELAY_UID, 0 );
		
		END IF;
	END IF;
END //


CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APP_DELAY_UPDATE` AFTER UPDATE ON `app_delay` FOR EACH ROW BEGIN

	DECLARE loc_tickettasks_id, loc_Count_Task INT(11) ;
	DECLARE loc_glpi_users_id INT(11) ;

	SELECT glpi_pm_tasks.tickettasks_id INTO loc_tickettasks_id FROM glpi.glpi_plugin_processmaker_tasks as glpi_pm_tasks WHERE glpi_pm_tasks.case_id=NEW.APP_DELAY_UID  ;
	IF loc_tickettasks_id  IS NOT NULL THEN
		SELECT glpi_users_id INTO loc_glpi_users_id FROM glpi.glpi_plugin_processmaker_users WHERE pm_users_id=NEW.APP_DISABLE_ACTION_USER LIMIT 1;	
		
		IF NEW.APP_DISABLE_ACTION_DATE IS NOT NULL THEN
			UPDATE glpi.glpi_tickettasks 
				SET state=2, 
					`end`=NEW.APP_DISABLE_ACTION_DATE
				WHERE id=loc_tickettasks_id ;							
		END IF ;
		
	END IF;
END //


CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APP_DELEGATION_DELETE` AFTER DELETE ON `app_delegation` FOR EACH ROW BEGIN

	DECLARE loc_Ticket_id, loc_tickettask_id INT(11) ;

	SELECT glpi_pm_tcase.ticket_id INTO loc_Ticket_id FROM glpi.glpi_plugin_processmaker_ticketcase as glpi_pm_tcase WHERE glpi_pm_tcase.case_id=OLD.APP_UID;
	IF loc_Ticket_id IS NOT NULL THEN
		SELECT glpi_pm_tasks.tickettasks_id INTO loc_tickettask_id FROM glpi.glpi_plugin_processmaker_tasks as glpi_pm_tasks WHERE glpi_pm_tasks.case_id=OLD.APP_UID AND glpi_pm_tasks.del_index=OLD.DEL_INDEX LIMIT 1;
		DELETE FROM glpi.glpi_plugin_processmaker_tasks WHERE tickettasks_id = loc_tickettask_id ;
		DELETE FROM glpi.glpi_tickettasks WHERE id = loc_tickettask_id ;			
	END IF;

END //


CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APP_DELEGATION_INSERT` AFTER INSERT ON `app_delegation` FOR EACH ROW BEGIN
	DECLARE loc_task_cat_id, loc_Ticket_id, loc_Found_Pos, loc_glpi_users_id, loc_Count_Ticket INT(11) ;
 	DECLARE APP_TITLE, APP_PRO_TITLE, APP_TAS_TITLE VARCHAR(255);

	SELECT ticket_id INTO loc_Ticket_id FROM glpi.glpi_plugin_processmaker_ticketcase WHERE case_id=NEW.APP_UID;

	IF loc_Ticket_id IS NOT NULL THEN
	
	  	SELECT CONTENT.CON_VALUE into APP_TITLE FROM CONTENT WHERE NEW.APP_UID=CON_ID AND CON_CATEGORY='APP_TITLE' and CON_LANG = 'en' LIMIT 1;
	  	IF APP_TITLE IS NULL THEN
   	 	SET APP_TITLE = '';
	  	END IF;
	  	SELECT CONTENT.CON_VALUE into APP_PRO_TITLE FROM CONTENT WHERE NEW.PRO_UID=CON_ID AND CON_CATEGORY='PRO_TITLE' and CON_LANG = 'en' LIMIT 1;
	  	SELECT CONTENT.CON_VALUE into APP_TAS_TITLE FROM CONTENT WHERE NEW.TAS_UID=CON_ID AND CON_CATEGORY='TAS_TITLE' and CON_LANG = 'en' LIMIT 1;


			SELECT glpi_users_id INTO loc_glpi_users_id FROM glpi.glpi_plugin_processmaker_users WHERE pm_users_id=NEW.USR_UID LIMIT 1;	
			IF loc_glpi_users_id IS NULL THEN
				/* we must find a user linked to a group */ /* task is NEW.TAS_UID */
				select glpi.glpi_users.id INTO loc_glpi_users_id from task_user 
					join content on content.CON_ID=task_user.USR_UID and content.CON_CATEGORY='GRP_TITLE'
					join glpi.glpi_users on glpi.glpi_users.name=content.CON_VALUE COLLATE utf8_unicode_ci
					where tas_uid=NEW.TAS_UID and tu_relation=2 ;	
			END IF;

			SELECT id INTO loc_task_cat_id FROM glpi.glpi_taskcategories WHERE name=APP_PRO_TITLE;
			IF loc_task_cat_id IS NULL THEN
				SET loc_task_cat_id=0 ;
			END IF ;
		
			INSERT INTO glpi.glpi_tickettasks 
					( `tickets_id`, `taskcategories_id`, `date`, `users_id`, `content`, `is_private`, `actiontime`, `begin`, `end`, `state`, `users_id_tech`) 
					VALUES ( loc_Ticket_id, 
							loc_task_cat_id, 
							Now(), 
							1, 
							CONCAT( 'Bound to process: ', APP_PRO_TITLE, ',<br/>case title: ', APP_TITLE, ',<br/>task: ', APP_TAS_TITLE,'.<br/><a href="?id=', loc_Ticket_id, '&forcetab=processmaker_1">Go to Case tab to manage!</a>' ),
							0, 
							0, 
							NEW.DEL_DELEGATE_DATE, 
							NEW.DEL_TASK_DUE_DATE, 
							1, 
							loc_glpi_users_id);
			
			INSERT INTO glpi.glpi_plugin_processmaker_tasks (`tickettasks_id`, `case_id`, `del_index`) VALUES (LAST_INSERT_ID(), NEW.APP_UID, NEW.DEL_INDEX );
		
	END IF;
END //


CREATE DEFINER=CURRENT_USER TRIGGER `GLPI_APP_DELEGATION_UPDATE` AFTER UPDATE ON `app_delegation` FOR EACH ROW BEGIN

	DECLARE loc_tickettasks_id, loc_Count_Task INT(11) ;
	DECLARE loc_glpi_users_id INT(11) ;

	SELECT glpi_pm_tasks.tickettasks_id INTO loc_tickettasks_id FROM glpi.glpi_plugin_processmaker_tasks as glpi_pm_tasks WHERE glpi_pm_tasks.case_id=NEW.APP_UID AND glpi_pm_tasks.del_index=NEW.DEL_INDEX ;
	IF loc_tickettasks_id  IS NOT NULL THEN
		SELECT glpi_users_id INTO loc_glpi_users_id FROM glpi.glpi_plugin_processmaker_users WHERE pm_users_id=NEW.USR_UID LIMIT 1;	
		
		IF NEW.DEL_THREAD_STATUS = 'CLOSED' THEN
			UPDATE glpi.glpi_tickettasks 
				SET state=2, 
					`begin`=NEW.DEL_DELEGATE_DATE, 
					`end`=NEW.DEL_FINISH_DATE
				WHERE id=loc_tickettasks_id ;							
		ELSE
			UPDATE glpi.glpi_tickettasks 
				SET users_id_tech=loc_glpi_users_id					
				WHERE id=loc_tickettasks_id ;							
		END IF ;
		
	END IF;
END //

DELIMITER ;

