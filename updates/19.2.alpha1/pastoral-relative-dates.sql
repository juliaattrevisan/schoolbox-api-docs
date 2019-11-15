ALTER TABLE pastoral_report
    ADD COLUMN `uses_relative_date` BOOL NOT NULL
        DEFAULT TRUE AFTER `description`;

ALTER TABLE pastoral_report
    ADD COLUMN `relative_date` ENUM ('first day of january', '6 months ago', '3 months ago', '1 month ago', '14 days ago', '7 days ago', 'today')
        DEFAULT 'first day of january' AFTER `uses_relative_date`;
