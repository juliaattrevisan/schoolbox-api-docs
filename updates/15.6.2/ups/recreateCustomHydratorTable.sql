-- Recreate the custom hydrator table, with ASCII charset for class field
-- (PHP class names will never not be ASCII)
CREATE TABLE IF NOT EXISTS `ups_custom_hydrator` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class` VARCHAR(256) CHARACTER SET latin1 NOT NULL,
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

-- If the table existed already, set the class column to ASCII
ALTER TABLE `ups_custom_hydrator` MODIFY `class` VARCHAR(256) CHARACTER SET latin1 NOT NULL;

-- Hydrator for giving parents their student's classes
INSERT IGNORE INTO `ups_custom_hydrator` (
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

INSERT IGNORE INTO `ups_custom_hydrator` (
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
