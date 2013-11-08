/* SB-2359 - Merge Leave Schema into Core Schema */
CREATE TABLE IF NOT EXISTS `leave_request`(
	`id` int(11) NOT NULL  auto_increment ,
	`user_id` int(11) NOT NULL  ,
	`user_phone` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	`start` datetime NOT NULL  ,
	`finish` datetime NOT NULL  ,
	`host_type` int(11) NOT NULL  ,
	`host_name` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	`host_phone` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	`description` varchar(255) COLLATE utf8_general_ci NOT NULL  ,
	`created` datetime NULL  ,
	`status` int(11) NOT NULL  ,
	`authorisation_datetime` datetime NULL  ,
	`authorisation_user_id` int(11) NULL  ,
	`notes` text COLLATE utf8_general_ci NULL  ,
	`confirmed_by` int(11) NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='utf8';

INSERT IGNORE INTO `luServices`(`id`,`name`) VALUES ( '6','Housemaster');

INSERT IGNORE INTO `config` ( `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`) VALUES('debug_email_address','','Debug Email Address','Parent Notifications go here','1','','12','true','false','false');
INSERT IGNORE INTO `config` ( `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`) VALUES('parent_notification','0','Disable Parent Notification','Disable parent notification email when a leave request is created','3','true:false','12','true','false','false');
INSERT IGNORE INTO `config` ( `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`) VALUES('terms_and_conditions','<ol>\r\n<li>An email will automatically be sent to the email address your parents/guardian have supplied the school. Your parents/guardian then need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.</li>\r\n<li>If you are staying at a host other than family, then the host parent/guardian will need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.  You can forward your email copy of your leave request to them.</li>\r\n<li>You and your parents/guardian will receive notification by email of the status of your leave - approved, modified or rejected.</li>\r\n<li>Confirmation from your parents/guardian and/or hosts must be received by your Housemaster by 8:00pm on the Thursday prior to your leave request.</li>\r\
n<li>You should advise your Housemaster as soon as possible if your leave arrangements change.</li>\r\n<li>This application is a request for leave and the School reserves the right to approve/deny leave.</li>\r\n</ol>','Terms and Conditions','Terms and Conditions','2','','12','true','false','false');

INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_breakfast_start', '07:00', 'Breakfast Start Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');
INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_breakfast_end', '08:00', 'Breakfast End Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');
INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_lunch_start', '11:00', 'Lunch Start Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');
INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_lunch_end', '13:00', 'Lunch End Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');
INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_dinner_start', '17:00', 'Dinner Start Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');
INSERT IGNORE INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('boarder_dinner_end', '18:00', 'Dinner End Time', 'These times are used in the catering report and should be formatted in 24 hour clock (hh:mm)', '1', '12', 'true','false','true');

/* This will error if already completed */
ALTER TABLE `user` ADD COLUMN `boarding_status` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `residential_house_id`;
