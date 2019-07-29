<?php
/*
 * @version $Id: notificationtargettaskcategory.class.php tomolimo $
-------------------------------------------------------------------------

 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class PluginProcessmakerNotificationTargetCase extends PluginProcessmakerNotificationTargetProcessmaker {

   // type
   const EMAIL_RECIPIENTS = 200;

   // user type
   const RECIPIENTS  = 1;

   /**
    * Summary of getEvents
    * @return string[]
    */
   public function getEvents() {
      return ['send_email' => __('Send email', 'processmaker')];
   }


   /**
    * Summary of addAdditionalTargets
    * @param mixed $event
    */
   function addAdditionalTargets($event = '') {
      $this->notification_targets = [];
      $this->notification_targets_labels = [];
      $this->addTarget(self::RECIPIENTS, __('eMail recipients', 'processmaker'), self::EMAIL_RECIPIENTS);
   }


   /**
    * Summary of addSpecificTargets
    * @param mixed $data
    * @param mixed $options
    */
   function addSpecificTargets($data, $options) {

      // test if we are in the good notification
      // then in this case add the targets from the ['recipients']
      if (isset($options['glpi_send_email'])) {
         // normalize $options['glpi_send_email'] to an array of email parameters
         $options['glpi_send_email'] = isset($options['glpi_send_email']['notifications_id']) ? [$options['glpi_send_email']] : $options['glpi_send_email'];

         foreach($options['glpi_send_email'] as $params) {
            if (isset($params['notifications_id'])
                && $params['notifications_id'] == $data['notifications_id']) {
               //Look for all targets whose type is Notification::ITEM_USER
               switch ($data['type']) {
                  case self::EMAIL_RECIPIENTS:

                     switch ($data['items_id']) {

                        case self::RECIPIENTS :
                           $this->addUsers($params);
                           break;
                     }
               }
            }
         }
      }

      // if no target is added to $this, then the notification will not be sent.

   }


   /**
    * Add users from $options['glpi_send_email']['to']
    *
    * @param array $email_param should contain 'recipients'
    *
    * @return void
    */
   function addUsers($email_param = []) {
      global $DB, $CFG_GLPI;

      if (isset($email_param['recipients'])) {
         $id_list = []; // for users with ids
         $email_list = []; // for standalone emails

         // normalize into array the recipient list
         $email_param['recipients'] = is_array($email_param['recipients']) ? $email_param['recipients'] : [$email_param['recipients']];
         foreach ($email_param['recipients'] as $user) {
            if (is_numeric($user)) {
               $id_list[] = intval($user);
            } else {
               $email_list[] = $user;
            }
         }

         $query = $this->getDistinctUserSql()."
                  FROM `glpi_users` ".
                  $this->getProfileJoinSql()."
                  WHERE `glpi_users`.`id` IN (".implode(',', $id_list).")";

         foreach ($DB->request($query) as $data) {
            $this->addToRecipientsList($data);
         }

         foreach($email_list as $email){
            $this->addToRecipientsList([
               'email'    => $email,
               'language' => $CFG_GLPI["language"],
               'users_id' => -1
            ]);
         }
      }
   }

}
