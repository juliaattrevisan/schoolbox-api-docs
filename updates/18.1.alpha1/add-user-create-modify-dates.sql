ALTER TABLE `user`
    ADD COLUMN `created_at`
    DATETIME NOT NULL
    DEFAULT "0000-00-00 00:00:00"
    AFTER `display_grade_graph`,

    ADD COLUMN `updated_at`
    DATETIME NOT NULL
    DEFAULT "0000-00-00 00:00:00"
    AFTER `created_at`,

    CHANGE COLUMN `deleted_at` `deleted_at`
    DATETIME NULL
    DEFAULT NULL
    AFTER `updated_at`;
