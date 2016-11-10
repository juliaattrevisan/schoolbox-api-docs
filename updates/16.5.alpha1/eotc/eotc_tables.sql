/******
 *
 *  EOTC INITIAL SETUP
 *
 ******/

/* Create tables for all clients but 'stk'
*/
delimiter //
create procedure check_website()
  begin

    set @identifier := (SELECT configId FROM `config` WHERE  name = 'site_location' AND value = 'stk');

    if @identifier is null then

      /* Event activity log relations
      */
      DROP TABLE IF EXISTS `eotc_activitylog`;
      CREATE TABLE `eotc_activitylog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `actioner_id` int(11) DEFAULT NULL,
        `metadata_id` int(11) DEFAULT NULL,
        `message` text,
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `deleted_at` datetime DEFAULT NULL,
        KEY `id` (`id`),
        KEY `actioner_id` (`actioner_id`),
        KEY `metadata_id` (`metadata_id`),
        KEY `deleted_at` (`deleted_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* EOTC Config
       */
      DROP TABLE IF EXISTS `eotc_config`;
      CREATE TABLE `eotc_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) DEFAULT NULL,
        `value` text CHARACTER SET utf8,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;


      DROP TABLE IF EXISTS `eotc_event`;
      CREATE TABLE `eotc_event` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `series_id` int(6) DEFAULT NULL,
        `author_id` int(6) DEFAULT NULL,
        `teacher_in_charge_id` int(11) DEFAULT NULL,
        `type` int(10) unsigned DEFAULT NULL,
        `title` varchar(250) NOT NULL DEFAULT '',
        `startDate` date DEFAULT NULL COMMENT 'deprecated',
        `startTime` time DEFAULT NULL COMMENT 'deprecated',
        `start_datetime` datetime DEFAULT NULL,
        `endDate` date DEFAULT NULL COMMENT 'deprecated',
        `endTime` time DEFAULT NULL COMMENT 'deprecated',
        `end_datetime` datetime DEFAULT NULL,
        `detail` text,
        `location` varchar(250) DEFAULT NULL,
        `allday` tinyint(1) DEFAULT '0',
        PRIMARY KEY (`id`),
        KEY `start_datetime` (`start_datetime`),
        KEY `end_datetime` (`end_datetime`),
        KEY `series_id` (`series_id`),
        KEY `author_id` (`author_id`),
        KEY `teacher_in_charge_id` (`teacher_in_charge_id`),
        FULLTEXT KEY `fulltext` (`title`,`detail`,`location`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Event campus relations
       */
      DROP TABLE IF EXISTS `eotc_event_campus`;
      CREATE TABLE `eotc_event_campus` (
        `event_id` int(11) DEFAULT NULL,
        `campus_id` int(11) DEFAULT NULL,
        KEY `event_id` (`event_id`),
        KEY `campus_id` (`campus_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      DROP TABLE IF EXISTS `eotc_event_metadata`;
      CREATE TABLE `eotc_event_metadata` (
        `event_id` int(11) DEFAULT NULL,
        `metadata_id` int(11) DEFAULT NULL,
        UNIQUE KEY `event_metadata` (`event_id`,`metadata_id`),
        KEY `event_id` (`event_id`),
        KEY `metadata_id` (`metadata_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;


      /* Event metadata, for eotc events
       */
      DROP TABLE IF EXISTS `eotc_metadata`;
      CREATE TABLE `eotc_metadata` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `event_type_id` int(11) DEFAULT NULL COMMENT 'FK for luEventType',
        `status_id` int(11) DEFAULT NULL COMMENT 'FK for eotc_status',
        `rams_required` tinyint(1) DEFAULT NULL,
        `comment` text COMMENT 'iAdditional notes',
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `deleted_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `event_type_id` (`event_type_id`),
        KEY `status_id` (`status_id`),
        KEY `deleted_at` (`deleted_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Event file relations
       */
      DROP TABLE IF EXISTS `eotc_metadata_file`;
      CREATE TABLE `eotc_metadata_file` (
        `metadata_id` int(11) DEFAULT NULL,
        `storage_id` int(11) DEFAULT NULL,
        UNIQUE KEY `metadata_storage` (`metadata_id`,`storage_id`),
        KEY `metadata_id` (`metadata_id`),
        KEY `storage_id` (`storage_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      DROP TABLE IF EXISTS `eotc_metadata_resourcebooking`;
      CREATE TABLE `eotc_metadata_resourcebooking` (
        `metadata_id` int(11) DEFAULT NULL,
        `booking_id` int(11) DEFAULT NULL,
        KEY `metadata_id` (`metadata_id`),
        KEY `booking_id` (`booking_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Event staff relations
       */
      DROP TABLE IF EXISTS `eotc_metadata_staff`;
      CREATE TABLE `eotc_metadata_staff` (
        `metadata_id` int(11) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        UNIQUE KEY `metadata_staff` (`metadata_id`,`user_id`),
        KEY `metadata_id` (`metadata_id`),
        KEY `user_id` (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Event student relations
       */
      DROP TABLE IF EXISTS `eotc_metadata_student`;
      CREATE TABLE `eotc_metadata_student` (
        `metadata_id` int(11) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        UNIQUE KEY `metadata_student` (`metadata_id`,`user_id`),
        KEY `metadata_id` (`metadata_id`),
        KEY `user_id` (`user_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Permissions
       */
      DROP TABLE IF EXISTS `eotc_permission`;
      CREATE TABLE `eotc_permission` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `campus_id` int(11) DEFAULT NULL,
        `status_id` int(11) DEFAULT NULL,
        `is_allowed` tinyint(1) NOT NULL DEFAULT '1',
        `is_subscribed` tinyint(1) NOT NULL DEFAULT '0',
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `deleted_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `campus_id` (`campus_id`),
        KEY `status_id` (`status_id`),
        KEY `deleted_at` (`deleted_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      /* Event statuses
       */
      DROP TABLE IF EXISTS `eotc_status`;
      CREATE TABLE `eotc_status` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

    end if;

  end;
//
delimiter ;
call check_website();
drop procedure check_website;