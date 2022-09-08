-- add new message to msg_source
INSERT INTO `msg_source` (`namespace`,
                          `key`,
                          `role_type`,
                          `description`)
VALUES ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral'),
        'addedToReport',
        'staff',
        'a new {:pastoral} record is added to an existing report.');
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `mask`, `role_default`)
SELECT (SELECT id
        FROM `msg_source`
        WHERE `namespace` = (SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral')
          AND `key` = 'addedToReport'
          AND `role_type` = 'staff'),
       `groups`.id,
       'MU,DI,IN,MO,EM',
       'IN'
FROM `groups`
WHERE `groups`.`group_type` = 'staff';

-- create a new column to pastoral_report table
ALTER TABLE `pastoral_report`
    ADD COLUMN `notification_enabled` TINYINT DEFAULT 0
        AFTER `include_students_without_house`;

-- reset empty string to null on pastoral_report table
UPDATE `pastoral_report` SET `description` = NULL WHERE `description` = '';
UPDATE `pastoral_report` SET `relative_date_string` = NULL WHERE `relative_date_string` = '';
UPDATE `pastoral_report` SET `types` = NULL WHERE `types` = '';
UPDATE `pastoral_report` SET `sub_types` = NULL WHERE `sub_types` = '';
UPDATE `pastoral_report` SET `authors` = NULL WHERE `authors` = '';
UPDATE `pastoral_report` SET `students` = NULL WHERE `students` = '';
UPDATE `pastoral_report` SET `tags` = NULL WHERE `tags` = '';
UPDATE `pastoral_report` SET `years` = NULL WHERE `years` = '';
UPDATE `pastoral_report` SET `houses` = NULL WHERE `houses` = '';
UPDATE `pastoral_report` SET `campuses` = NULL WHERE `campuses` = '';
UPDATE `pastoral_report` SET `actions` = NULL WHERE `actions` = '';
