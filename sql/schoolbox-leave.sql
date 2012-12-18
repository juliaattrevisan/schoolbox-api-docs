/* Create leave table */
CREATE TABLE `leave_request`(
	`id` int(11) NOT NULL  auto_increment ,
	`user_id` int(11) NOT NULL  ,
	`user_phone` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`start` datetime NOT NULL  ,
	`finish` datetime NOT NULL  ,
	`host_type` int(11) NOT NULL  ,
	`host_name` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`host_phone` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`description` varchar(255) COLLATE latin1_swedish_ci NOT NULL  ,
	`created` datetime NULL  ,
	`status` int(11) NOT NULL  ,
	`authorisation_datetime` datetime NULL  ,
	`authorisation_user_id` int(11) NULL  ,
	`notes` text COLLATE latin1_swedish_ci NULL  ,
	`confirmed_by` int(11) NULL  ,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET='latin1';

ALTER TABLE `user` ADD COLUMN `boarding_status` VARCHAR(255)  COLLATE utf8_general_ci NULL AFTER `residential_house_id`;

INSERT INTO `luServices`(`id`,`name`) VALUES ( '6','Housemaster');

INSERT INTO `config` ( `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES('debug_email_address','','Debug Email Address','Parent Notifications go here','input','','Board Leave','true','false','false');
INSERT INTO `config` ( `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES('parent_notification','0','Disable Parent Notification','Disable parent notification email when a leave request is created','boolean','true:false','Board Leave','true','false','false');
INSERT INTO `config` ( `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES('terms_and_conditions','<ol>\r\n<li>An email will automatically be sent to the email address your parents/guardian have supplied the school. Your parents/guardian then need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.</li>\r\n<li>If you are staying at a host other than family, then the host parent/guardian will need to contact your Housemaster (e.g. phone/fax/email) to confirm they are aware of your leave request and approve of it.  You can forward your email copy of your leave request to them.</li>\r\n<li>You and your parents/guardian will receive notification by email of the status of your leave - approved, modified or rejected.</li>\r\n<li>Confirmation from your parents/guardian and/or hosts must be received by your Housemaster by 8:00pm on the Thursday prior to your leave request.</li>\r\n<li>You should advise your Housemaster as soon as possible if your leave arrangements change.</li>\r\n<li>This application is a request for leave and the School reserves the right to approve/deny leave.</li>\r\n</ol>','Terms and Conditions','Terms and Conditions','textarea','','Board Leave','true','false','false');