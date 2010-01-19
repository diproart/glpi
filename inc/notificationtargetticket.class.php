<?php
/*
 * @version $Id: notification.class.php 10030 2010-01-05 11:11:22Z moyo $
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class NotificationTargetTicket extends NotificationTarget {

   function getEmails($notifications_id,$itemtype,$data,$options=array()) {

   if (isset($options['sendprivate']) && $options['sendprivate'] == true) {
      $sendprivate = true;
   }
   else {
      $sendprivate = false;
   }

   //Look for all targets whose type is USER_MAILING
   switch ($data['type']) {

      case USER_MAILING_TYPE:
         switch ($data['items_id']) {
            //Send to glpi's global admin (as defined in the mailing configuration)
            case ADMIN_MAILING :
               $notificationtarget->getAdminEmail($notifications_id);
            break;
            //Send to the entity's admninistrator
            case ADMIN_ENTITY_MAILING :
               $target->getEntityAdminEmail();
            break;
            //Send to the technician whose in charge of the ticket
            case ASSIGN_MAILING:
               $this->getAssignEmail($notifications_id);
            break;
            //Send to the author of the ticket
            case AUTHOR_MAILING:
               $target->getAuthorEmail($notifications_id);
            break;
            //Technician in charge of the ticket
            case TECH_SENDING :
               $this->getTechnicianInChargeEmail($notifications_id);
            break;
            //User who's owner of the material
            case USER_MAILING :
               $this->getUserOwnerEmail($notifications_id);
            break;
            //Assign to a supplier
            case ASSIGN_ENT_MAILING :
               $this->getSupplierEmails($notifications_id);
            break;
            //Send to the group in charge of the ticket supervisor
            case SUPERVISOR_ASSIGN_GROUP_MAILING :
               $this->getGroupSupervisorEmail($notifications_id,true);
            break;
            //Send to the user who's got the issue
            case RECIPIENT_MAILING :
               $this->getReceipientEmail($notifications_id);
            break;
            //Send to the supervisor of the requester's group
            case SUPERVISOR_AUTHOR_GROUP_MAILING :
               $this->getGroupSupervisorEmail($notifications_id,false);
            break;
            //Send to the technician previously in charge of the ticket (before reassignation)
            case OLD_ASSIGN_MAILING :
               $this->getOldAssignEmail($notifications_id);
            break;
      }
      //Send to all the users of a group
      case GROUP_MAILING_TYPE:
         $this->getUsersEmailsByGroup($notifications_id,$data['items_id']);
      break;

      //Send to all the users of a profile
      case ASSIGN_MAILING_TYPE:
         $target->getUsersEmailsByProfile($notifications_id,$data['items_id']);
      break;
      }
   }

   function getAssignEmail ($notifications_id) {
      $this->getTechnicianInChargeEmail($notifications_id);
   }

   function getUserByField ($notifications_id, $field) {
      global $DB;
      if (isset($this->obj->fields[$field])
          && $this->obj->fields[$field]>0) {

         $query = NotificationTarget::getDistinctUserSql()."
                  FROM `glpi_users`".
                  NotificationTargetTicket::getJoinProfileSql().
                 "WHERE `glpi_users`.`id` = '".
                  $this->obj->fields[$field]."'";
         foreach ($DB->request($query) as $data) {
            $this->addToEmailList($notifications_id,$data['email'],$data['lang']);
         }
      }
   }

   function getOldAssignEmail ($notifications_id) {
      return $this->getUserByField ($notifications_id, "_old_assign");
   }

   //Get receipient
   function getReceipientEmail($notifications_id) {
      return $this->getUserByField ($notifications_id, "users_id_recipient");
  }

   //Get thecnician in charge of the ticket
   function getTechnicianInChargeEmail($notifications_id,$ticket) {
      NotificationTargetTicket::getByItemUserEmail($notifications_id,'user_id_tech');
   }

   //Get user owner of the material
   function getUserOwnerEmail($notifications_id) {
      NotificationTargetTicket::getByItemUserEmail($notifications_id,'user_id');
   }

   function getByItemUserEmail($notifications_id,$field) {
      global $DB;

      if (isset($this->obj->fields["items_id"])
                && $this->obj->fields["items_id"]>0
                && isset($this->obj->fields["itemtype"])
                && class_exists($this->obj->fields["itemtype"])) {

         $item= new $this->obj->fields["itemtype"]();
         if ($item->getFromDB($this->obj->fields["items_id"])) {
            if ($item->isField($field)) {

               $query = NotificationTarget::getDistinctUserSql()."
                        FROM `glpi_users`".
                        NotificationTargetTicket::getJoinProfileSql().
                       "WHERE `glpi_users`.`id` = '".
                        $item->getField($field)."'";
               foreach ($DB->request($query) as $data) {
                  $this->addToEmailList($notifications_id,$data['email'],$data['lang']);
               }
            }
         }
      }
   }


   function getSupplierEmails($notifications_id, $sendprivate=true) {
      global $DB;

      if (!$sendprivate && isset($ths->obj->fields["suppliers_id_assign"])
          && $this->obj->fields["suppliers_id_assign"]>0) {

         $query = "SELECT DISTINCT `glpi_suppliers`.`email` AS email
                   FROM `glpi_suppliers`
                   WHERE `glpi_suppliers`.`id` = '".
                          $ticket->fields["suppliers_id_assign"]."'";
         foreach ($DB->request($query) as $data) {
            $this->addToEmailList($notifications_id,$data['email']);
         }
      }
   }

   //Get supervisor of a group (works for request group or assigned group)
   function getGroupSupervisorEmail ($notifications_id, $assign=true) {
      global $DB;

      $group_field = ($assign?"groups_id_assign":"groups_id");

      if (isset($this->obj->fields[$group_field])
                && $this->obj->fields[$group_field]>0) {

         $query = NotificationTarget::getDistinctUserSql().
                   "FROM `glpi_groups`
                    LEFT JOIN `glpi_users`
                    ON (`glpi_users`.`id` = `glpi_groups`.`users_id`)".
                   NotificationTargetTicket::getJoinProfileSql()."
                    WHERE `glpi_groups`.`id` = '".$this->obj->fields[$group_field]."'";
         foreach ($DB->request($query) as $data) {
            $this->addToEmailList($notifications_id,$data['email'], $data['lang']);
         }
      }
   }

   function getUsersEmailsByProfile($notifications_id,$profile_id) {
      global $DB;

      $query=NotificationTarget::getDistinctUserSql()."
              FROM `glpi_profiles_users`
              INNER JOIN `glpi_users` ON (`glpi_profiles_users`.`users_id`=`glpi_users`.`id`)
              INNER JOIN `glpi_profiles`
                             ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`
                                 AND `glpi_profiles`.`interface` = 'central'
                                 AND `glpi_profiles`.`show_full_ticket` = '1')
              WHERE `glpi_users`.`is_deleted`='0'
                 AND `glpi_profiles_users`.`profiles_id`='".$profile_id."' ".
                 getEntitiesRestrictRequest("AND","glpi_profiles_users","entities_id",
                                                        $this->obj->fields['entities_id'],true);
      foreach ($DB->request($query) as $data) {
         $this->addToEmailList($notifications_id,$data['email'], $data['lang']);
      }
   }

   static function getJoinProfileSql() {
      return " INNER JOIN `glpi_profiles_users`
                        ON (`glpi_profiles_users`.`users_id` = `glpi_users`.`id`".
                            getEntitiesRestrictRequest("AND","glpi_profiles_users","entities_id",
                                                       $this->job->fields['entities_id'],true).")
                    INNER JOIN `glpi_profiles`
                        ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`
                            AND `glpi_profiles`.`interface` = 'central'
                            AND `glpi_profiles`.`show_full_ticket` = '1') ";
   }
}
?>