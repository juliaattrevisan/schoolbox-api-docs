INSERT INTO `msg_source` (`namespace`,
                          `key`,
                          `role_type`,
                          `description`)
VALUES ((SELECT `id` FROM `msg_namespace` WHERE `slug` = 'pastoral'),
        'addedToReport',
        'staff',
        'a new {:pastoral} record is added to an existing report.');

ALTER TABLE `pastoral_report`
    ADD COLUMN `notification_enabled` TINYINT DEFAULT 0
        AFTER `include_students_without_house`;
