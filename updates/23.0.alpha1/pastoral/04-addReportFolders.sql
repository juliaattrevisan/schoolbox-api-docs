ALTER TABLE `pastoral_report`
    ADD COLUMN
        `folders` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
        AFTER `campuses`;
