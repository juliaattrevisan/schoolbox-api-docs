ALTER TABLE `pastoral_report`
    ADD COLUMN
        `campuses` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
        AFTER `houses`;
