/* Add Border Leave fields to config
 */
ALTER TABLE `config`     CHANGE `type` `type` ENUM('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave') CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;

/* Add config variables for Boarder Leave
 */
INSERT INTO `portal-dev`.`config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'parent_notification','0','Disable Parent Notification','Disable parent notification email when a leave request is created','boolean','true:false','Board Leave','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'debug_email_address','steve.chan@alaress.com.au','Debug Email Address','Parent Notifications go here','input',NULL,'Board Leave','true','false','false');
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'terms_and_conditions','blah','Terms and Conditions','Terms and Conditions','textarea',NULL,'Board Leave','true','false','false');

/* Add different residential house lookup table
 */
CREATE TABLE `luResidentialHouse`(     `id` INT(20) NOT NULL AUTO_INCREMENT ,     `name` VARCHAR(255) ,     PRIMARY KEY (`id`)  );

/* Alter column lengths
 */
ALTER TABLE `leave_request`     CHANGE `user_phone` `user_phone` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,     CHANGE `host_name` `host_name` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,     CHANGE `host_phone` `host_phone` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,     CHANGE `description` `description` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;

/* Add Notes column
 */
ALTER TABLE `leave_request`     ADD COLUMN `notes` TEXT NULL AFTER `authorisation_user_id`;

UPDATE `portal-dev`.`config` SET `value`='TERMS AND CONDITIONS OF LEAVE <br /><br />\r\n\r\n1) An email will automatically be sent to the email address your parents/guardian have supplied the school. Your parents/guardian then need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.<br /><br />\r\n\r\n2) If you are staying at a host other than family, then the host parent/guardian will need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.  You can forward your email copy of your leave request to them.<br /><br />\r\n\r\n3) You and your parents/guardian will receive notification by email of the status of your leave - approved, modified or rejected.<br /><br />\r\n\r\n4) Confirmation from your parents/guardian and/or hosts must be received by your Housemaster by 8:00pm on the Thursday prior to your leave request.<br /><br />\r\n\r\n5) You should advise your Housemaster as soon as possible if your leave arrangements change.<br /><br />\r\n\r\n6) This application is a request for leave and the School reserves the right to approve/deny leave.<br /><br />' WHERE `name`='terms_and_conditions';
ALTER TABLE `leave_request` ADD COLUMN `confirmed_by` INT(11) NULL AFTER `notes`;