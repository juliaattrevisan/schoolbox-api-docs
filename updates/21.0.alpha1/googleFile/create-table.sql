CREATE TABLE `google_file`
(
    `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `google_id`  VARCHAR(100) CHARACTER SET 'ascii' NULL DEFAULT NULL,
    `name`       TEXT NULL DEFAULT NULL,
    `icon`       VARCHAR(100) NULL DEFAULT NULL,
    `folder_id`  INT(5) UNSIGNED NOT NULL,
    `owner_id`   INT(4) UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY          `google_id` (`google_id`),
    KEY          `folder_id` (`folder_id`),
    KEY          `owner_id` (`owner_id`),
    CONSTRAINT `fk_google_file_folder` FOREIGN KEY (`folder_id`) REFERENCES `folder` (`id`),
    CONSTRAINT `fk_google_file_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`)
)
