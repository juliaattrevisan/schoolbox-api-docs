/* Add Border Leave fields to config
 */
ALTER TABLE `config`     CHANGE `type` `type` ENUM('Site','Security','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave') CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;

/* Add config variables for Boarder Leave
 */
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'parent_notification','false','Disable Parent Notification','Disable Parent Notification','input','true:false','Board Leave','true','false','false');
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