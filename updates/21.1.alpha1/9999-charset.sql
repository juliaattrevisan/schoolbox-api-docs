-- These should disappear now, I guess
DROP TABLE `_deleted_component_config_definition`;
DROP TABLE `_deleted_component_config_instance`;
DROP TABLE `_pastoral_report_groups`;

-- Unlock Tour tables' columns so we can update their stuff
SET foreign_key_checks = 0;

ALTER TABLE `comms_news_audience_complex`
CHANGE COLUMN `entity_type_1` `entity_type_1` ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `entity_type_2` `entity_type_2` ENUM('user', 'super', 'campus', 'role', 'role_type', 'school_house', 'res_house', 'year', 'service', 'pastoral_group', 'folder') CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;

ALTER TABLE `eportfolio_page`
CHANGE COLUMN `left_head_font` `left_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_head_bg` `left_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_font` `left_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_bg_primary` `left_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_bg_secondary` `left_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_link` `left_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_hover` `left_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_head_font` `right_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_head_bg` `right_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_font` `right_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_bg_primary` `right_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_bg_secondary` `right_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_link` `right_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_hover` `right_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_head_font` `top_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_head_bg` `top_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_font` `top_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_bg_primary` `top_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_bg_secondary` `top_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_link` `top_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_hover` `top_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `event_source`
CHANGE COLUMN `guid` `guid` VARCHAR(255) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `filter`
CHANGE COLUMN `entity` `entity` VARCHAR(127) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;

ALTER TABLE `homepage_config`
CHANGE COLUMN `left_head_font` `left_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_head_bg` `left_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_font` `left_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_bg_primary` `left_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_bg_secondary` `left_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_link` `left_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `left_hover` `left_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_head_font` `right_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_head_bg` `right_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_font` `right_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_bg_primary` `right_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_bg_secondary` `right_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_link` `right_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `right_hover` `right_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_head_font` `top_head_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_head_bg` `top_head_bg` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_font` `top_font` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_bg_primary` `top_bg_primary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_bg_secondary` `top_bg_secondary` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_link` `top_link` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `top_hover` `top_hover` VARCHAR(40) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `luEventType`
CHANGE COLUMN `eventColour` `eventColour` VARCHAR(10) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `storage_transform`
CHANGE COLUMN `transform` `transform` VARCHAR(100) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;

ALTER TABLE `student_submission_file`
CHANGE COLUMN `uuid` `uuid` VARCHAR(255) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `student_submission_file_kami`
CHANGE COLUMN `kami_document_id` `kami_document_id` CHAR(32) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `submission_return_file`
CHANGE COLUMN `uuid` `uuid` VARCHAR(255) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `tile`
CHANGE COLUMN `title_color` `title_color` VARCHAR(30) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `title_background_color` `title_background_color` VARCHAR(30) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `background_color` `background_color` VARCHAR(30) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `hover_color` `hover_color` VARCHAR(30) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `tour`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `first_step_uuid` `first_step_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `tour_status`
DROP FOREIGN KEY `tour_status_tour_uuid_fk`;
ALTER TABLE `tour_status`
CHANGE COLUMN `tour_uuid` `tour_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_status`
ADD CONSTRAINT `tour_status_tour_uuid_fk`
  FOREIGN KEY (`tour_uuid`)
  REFERENCES `tour` (`uuid`);

ALTER TABLE `tour_step`
DROP FOREIGN KEY `tour_step_tour_id_fk`,
DROP FOREIGN KEY `tour_step_tour_next_step_uuid_fk`;
ALTER TABLE `tour_step`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `tour_uuid` `tour_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `next_step_uuid` `next_step_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;
ALTER TABLE `tour_step`
ADD CONSTRAINT `tour_step_tour_id_fk`
  FOREIGN KEY (`tour_uuid`)
  REFERENCES `tour` (`uuid`),
ADD CONSTRAINT `tour_step_tour_next_step_uuid_fk`
  FOREIGN KEY (`next_step_uuid`)
  REFERENCES `tour_step` (`uuid`);

ALTER TABLE `tour_step_event`
DROP FOREIGN KEY `tour_step_event_tour_step_uuid_fk`;
ALTER TABLE `tour_step_event`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `tour_step_uuid` `tour_step_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_step_event`
ADD CONSTRAINT `tour_step_event_tour_step_uuid_fk`
  FOREIGN KEY (`tour_step_uuid`)
  REFERENCES `tour_step` (`uuid`);

ALTER TABLE `tour_step_modal`
DROP FOREIGN KEY `tour_step_modal_tour_step_uuid_fk`;
ALTER TABLE `tour_step_modal`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `tour_step_uuid` `tour_step_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_step_modal`
ADD CONSTRAINT `tour_step_modal_tour_step_uuid_fk`
  FOREIGN KEY (`tour_step_uuid`)
  REFERENCES `tour_step` (`uuid`);

ALTER TABLE `tour_step_tooltip`
DROP FOREIGN KEY `tour_step_tooltip_tour_step_uuid_fk`;
ALTER TABLE `tour_step_tooltip`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `tour_step_uuid` `tour_step_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_step_tooltip`
ADD CONSTRAINT `tour_step_tooltip_tour_step_uuid_fk`
  FOREIGN KEY (`tour_step_uuid`)
  REFERENCES `tour_step` (`uuid`);

ALTER TABLE `tour_trigger`
DROP FOREIGN KEY `tour_trigger_tour_uuid_fk`;
ALTER TABLE `tour_trigger`
CHANGE COLUMN `uuid` `uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ,
CHANGE COLUMN `tour_uuid` `tour_uuid` VARCHAR(36) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NOT NULL ;
ALTER TABLE `tour_trigger`
ADD CONSTRAINT `tour_trigger_tour_uuid_fk`
  FOREIGN KEY (`tour_uuid`)
  REFERENCES `tour` (`uuid`);

ALTER TABLE `login_log`
    CHANGE COLUMN  `ip` `ip` VARCHAR(39) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL COMMENT 'coverage for both ipv4 and ipv6 addresses';

ALTER TABLE `user_assume_log`
 CHANGE COLUMN `ip` `ip` VARCHAR(39) CHARACTER SET  'ascii' COLLATE 'ascii_general_ci' NULL;

ALTER TABLE `lu_timetable_colour`
CHANGE COLUMN `hex_colour` `hex_colour` VARCHAR(8) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

ALTER TABLE `user_activity_log`
CHANGE COLUMN `ip` `ip` VARCHAR(15) CHARACTER SET 'ascii' COLLATE 'ascii_general_ci' NULL DEFAULT NULL ;

SET foreign_key_checks = 1;
