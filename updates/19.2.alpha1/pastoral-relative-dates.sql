ALTER TABLE pastoral_report
    ADD COLUMN `uses_relative_date` BOOL NOT NULL
        DEFAULT FALSE AFTER `description`;

ALTER TABLE pastoral_report
    ADD COLUMN `relative_date_string` VARCHAR(255) NULL AFTER `uses_relative_date`;
