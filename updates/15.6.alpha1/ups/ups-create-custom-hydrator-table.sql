CREATE TABLE `ups_custom_hydrator` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class` VARCHAR(256) NOT NULL,
    `role_type` ENUM('student','parent','staff') NOT NULL, -- omitting 'guest', this enum mirrors groups.group_type
    `enabled` BOOL NOT NULL DEFAULT 1,
    `sequence` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX (`class`),
    INDEX (`role_type`),
    INDEX (`role_type`, `enabled`),
    UNIQUE INDEX (`role_type`, `sequence`),
    UNIQUE INDEX (`role_type`, `class`)
);

-- Hydrator for giving parents their student's classes
INSERT INTO `ups_custom_hydrator` (
    `class`,
    `role_type`,
    `enabled`,
    `sequence`
) VALUES (
    'Schoolbox\\Sync\\Hydrator\\Custom\\AddParentToAllChildCampuses',
    'parent',
    TRUE,
    (SELECT COALESCE(MAX(`ch`.`sequence`), -1)+1 FROM `ups_custom_hydrator` `ch` WHERE `role_type` = 'parent')
);

INSERT INTO `ups_custom_hydrator` (
    `class`,
    `role_type`,
    `enabled`,
    `sequence`
) VALUES (
    'Schoolbox\\Sync\\Hydrator\\Custom\\SkipParentIfNoChildren',
    'parent',
    FALSE,
    (SELECT COALESCE(MAX(`ch`.`sequence`), -1)+1 FROM `ups_custom_hydrator` `ch` WHERE `role_type` = 'parent')
);
