ALTER TABLE `pastoral_report`
    ADD COLUMN
        `actions`                        text COLLATE utf8mb4_unicode_ci DEFAULT NULL
        AFTER `campuses`,
    ADD COLUMN
        `include_records_without_action` tinyint(1) NOT NULL DEFAULT 0
        AFTER `actions`,
    ADD COLUMN
        `include_uncompleted_actions_only` tinyint(1) NOT NULL DEFAULT 0
        AFTER `include_records_without_action`
;
