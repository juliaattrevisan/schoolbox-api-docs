/******
 *
 *  EOTC INITIAL SETUP
 *
 *  DUE WARNING: THIS DROPS AND REPLACES ALL ASSOCIATED TABLES AND RESETS ALL DATA.
 *  DON'T RUN THIS ON A SERVER THAT HAS DATA THAT YOU CARE TO LOSE.
 *
 ******/


INSERT INTO `error` (`id`, `message`) VALUES ('160', 'This event could not be found.');

/* Event metadata, for eotc events
 */
DROP TABLE IF EXISTS `eotc_metadata`;
CREATE TABLE `eotc_metadata`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_type_id` INT(11) COMMENT 'FK for luEventType',
  `status_id` INT(11) COMMENT 'FK for eotc_status',
  `rams_required` TINYINT(1),
  `comment` TEXT COMMENT 'iAdditional notes',
  `created_at` DATETIME,
  `updated_at` DATETIME,
  `deleted_at` DATETIME,
  PRIMARY KEY (`id`)
);
DROP TABLE IF EXISTS `eotc_event_metadata`;
CREATE TABLE `eotc_event_metadata`(
  `event_id` INT(11),
  `metadata_id` INT(11)
);

/* Unique index, so I can use insert ignore to prevent dupes
 */
ALTER TABLE `eotc_event_metadata` ADD UNIQUE INDEX `event_metadata` (`event_id`, `metadata_id`);

/* Event statuses
 */
DROP TABLE IF EXISTS `eotc_status`;
CREATE TABLE `eotc_status`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255),
  PRIMARY KEY (`id`)
);

INSERT INTO `eotc_status` (`name`) VALUES ('Initial Application');
INSERT INTO `eotc_status` (`name`) VALUES ('Approved In Principle');
INSERT INTO `eotc_status` (`name`) VALUES ('RAMS Approved');
INSERT INTO `eotc_status` (`name`) VALUES ('Confirm RAMs');
INSERT INTO `eotc_status` (`name`) VALUES ('Approved');
INSERT INTO `eotc_status` (`name`) VALUES ('Rejected');

/* Clone out the event table
 */
DROP TABLE IF EXISTS `eotc_event`;
CREATE TABLE `eotc_event` (
  PRIMARY KEY(`id`),
  FULLTEXT KEY `fulltext`( `title` , `detail` , `location` )
  ) ENGINE=MYISAM COLLATE = utf8_general_ci COMMENT = ''
  SELECT `id`, `series_id`, `owner`, `author_id`, `access`, `sid`, `priority`, `type`, `title`, `startDate`, `startTime`, `endDate`, `endTime`, `detail`, `location`, `attendance`, `publish_date`, `completed`, `allday`
  FROM `event`;

ALTER TABLE `eotc_event` DROP `owner`;
ALTER TABLE `eotc_event` DROP `access`; 
ALTER TABLE `eotc_event` DROP `sid`; 
ALTER TABLE `eotc_event` DROP `priority`; 
ALTER TABLE `eotc_event` DROP `attendance`; 
ALTER TABLE `eotc_event` DROP `publish_date`; 
ALTER TABLE `eotc_event` DROP `completed`; 
ALTER TABLE `eotc_event` MODIFY COLUMN id INT(11) AUTO_INCREMENT;
ALTER TABLE `eotc_event` ADD COLUMN `teacher_in_charge_id` INT(11) NULL AFTER `author_id`;

TRUNCATE eotc_event;

/* Event campus relations
 */
DROP TABLE IF EXISTS `eotc_event_campus`;
CREATE TABLE `eotc_event_campus` ( `event_id` INT(11), `campus_id` INT(11) );

/* Event staff relations
 */
DROP TABLE IF EXISTS `eotc_metadata_staff`;
CREATE TABLE `eotc_metadata_staff` ( `metadata_id` INT(11), `user_id` INT(11) );
ALTER TABLE `eotc_metadata_staff` ADD UNIQUE INDEX `metadata_staff` (`metadata_id`, `user_id`);

/* Event student relations
 */
DROP TABLE IF EXISTS `eotc_metadata_student`;
CREATE TABLE `eotc_metadata_student` ( `metadata_id` INT(11), `user_id` INT(11) );
ALTER TABLE `eotc_metadata_student` ADD UNIQUE INDEX `metadata_student` (`metadata_id`, `user_id`);

/* Event file relations
 */
DROP TABLE IF EXISTS `eotc_metadata_file`;
CREATE TABLE `eotc_metadata_file` ( `metadata_id` INT(11), `storage_id` INT(11) );
ALTER TABLE `eotc_metadata_file` ADD UNIQUE INDEX `metadata_storage` (`metadata_id`, `storage_id`);
INSERT INTO `error` (`id`, `message`) VALUES ('161', 'Please upload a RAMS file.');

/* Event activity log relations
 */
DROP TABLE IF EXISTS `eotc_activitylog`;
CREATE TABLE `eotc_activitylog` ( `id` INT(11) AUTO_INCREMENT, `actioner_id` INT(11), `metadata_id` INT(11), `message` TEXT, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, KEY(`id`) );

/* Permissions
 */
DROP TABLE IF EXISTS `eotc_permission`;
CREATE TABLE `eotc_permission` ( `id` INT(11) NOT NULL AUTO_INCREMENT, `user_id` INT(11), `campus_id` INT(11), `status_id` INT(11), `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

/* EOTC Config
 */
DROP TABLE IF EXISTS `eotc_config`;
CREATE TABLE `eotc_config` ( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `value` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('first_reminder', '10');
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('daily_reminder', '3');
INSERT INTO `eotc_config` (`name`, `value`) VALUES ('urgent_threshold', '3');

ALTER TABLE `eotc_config` CHANGE `value` `value` TEXT CHARSET latin1 COLLATE latin1_swedish_ci NULL;
INSERT INTO `eotc_config` (`name`) VALUES ('policy');

/* Unknown event status
 */
INSERT INTO `error` (`id`, `message`) VALUES ('163', 'Unknown event status.');
UPDATE `eotc_status` SET `name`='RAMS Confirmed' WHERE `name`='Confirm RAMs';
INSERT INTO `msg_namespace`
  (`id`,`slug`,`label`)
VALUES
  (NULL, 'eotc', 'EOTC')
ON DUPLICATE KEY update `id` = `id`;

INSERT INTO `msg_source`
  (`namespace`, `key`, `role_type`, `description`)
VALUES
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'submitted',     'staff', 'someone submits an EOTC'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'statuschanged', 'staff', 'a moderator changes the status of your EOTC'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'updated',       'staff', 'someone updates or coments on your EOTC'),
  ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc'), 'upcoming',      'staff', 'an EOTC is upcoming and requires your approval')
ON DUPLICATE KEY
  UPDATE `id` = `id`;

/* Staff */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`,                  `mask`,         `role_default`)
SELECT src.id `msg_source_id`, g.id `role_id`,            'MU,DI,IN,MO,EM' `mask`, 'IN,EM' `role_default`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'eotc')
AND role_type = 'staff'
ON DUPLICATE KEY UPDATE `msg_source_id` = `msg_source_id`;

/* Create relation between metadata and bookings themselves
 */
DROP TABLE IF EXISTS `eotc_metadata_resourcebooking`;
CREATE TABLE `eotc_metadata_resourcebooking` ( `metadata_id` INT(11), `booking_id` INT(11) );UPDATE `eotc_status` SET `name` = 'Details Completed' WHERE `id` = '3';
UPDATE `eotc_status` SET `name` = 'Ready For Final Approval' WHERE `id` = '4';
UPDATE `eotc_status` SET `name` = 'Cancelled' WHERE `id` = '6';
-- Another dumb error table entry
insert into
  error (id, message)
  values (165, 'An EOTC event may not be approved if there are no student attendees.');-- EOTC permissions:
--  is_allowed now controls whether they are actually allowed
--  is_subscribed controls whether this user gets notified when an event enters this status
ALTER TABLE `eotc_permission`
  ADD COLUMN `is_allowed` BOOL DEFAULT TRUE NOT NULL AFTER `status_id`,
  ADD COLUMN `is_subscribed` BOOL DEFAULT FALSE NOT NULL AFTER `is_allowed`;
ALTER TABLE `eotc_event`
    ADD COLUMN `start_datetime` DATETIME NULL AFTER `startTime`,
    ADD COLUMN `end_datetime` DATETIME NULL AFTER `endTime`
;

ALTER TABLE `eotc_event`
    CHANGE `startDate` `startDate` DATE NULL COMMENT 'deprecated',
    CHANGE `startTime` `startTime` TIME NULL COMMENT 'deprecated',
    CHANGE `endDate` `endDate` DATE NULL COMMENT 'deprecated',
    CHANGE `endTime` `endTime` TIME NULL COMMENT 'deprecated'
;

UPDATE `eotc_event` SET
    start_datetime = CONCAT(startDate,' ',startTime),
    end_datetime = CONCAT(endDate,' ',endTime)
;

alter table `eotc_event`
    add index (`start_datetime`),
    add index (`end_datetime`)
;
-- Various EOTC indices
ALTER TABLE `eotc_activitylog`
    ADD INDEX `actioner_id` (`actioner_id`),
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eotc_event`
    ADD INDEX `series_id` (`series_id`),
    ADD INDEX `author_id` (`author_id`),
    ADD INDEX `teacher_in_charge_id` (`teacher_in_charge_id`)
;
ALTER TABLE `eotc_event_campus`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `campus_id` (`campus_id`)
;
ALTER TABLE `eotc_event_metadata`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `metadata_id` (`metadata_id`)
;
ALTER TABLE `eotc_metadata`
    ADD INDEX `event_type_id` (`event_type_id`),
    ADD INDEX `status_id` (`status_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eotc_metadata_file`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `storage_id` (`storage_id`)
;
ALTER TABLE `eotc_metadata_resourcebooking`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `booking_id` (`booking_id`)
;
ALTER TABLE `eotc_metadata_staff`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `user_id` (`user_id`)
;
ALTER TABLE `eotc_metadata_student`
    ADD INDEX `metadata_id` (`metadata_id`),
    ADD INDEX `user_id` (`user_id`)
;
ALTER TABLE `eotc_permission`
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `campus_id` (`campus_id`),
    ADD INDEX `status_id` (`status_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
