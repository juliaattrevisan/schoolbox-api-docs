ALTER TABLE `user`
    ADD COLUMN `created_at`
    DATETIME NOT NULL
    AFTER `display_grade_graph`,

    ADD COLUMN `updated_at`
    DATETIME NOT NULL
    AFTER `created_at`,

    CHANGE COLUMN `deleted_at` `deleted_at`
    DATETIME NULL
    DEFAULT NULL
    AFTER `updated_at`;

UPDATE `user`
SET `created_at` = UNIX_TIMESTAMP(), `updated_at` = UNIX_TIMESTAMP();