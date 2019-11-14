ALTER TABLE pastoral_report
    ADD COLUMN `usesRelativeDate` BOOL NOT NULL
        DEFAULT TRUE AFTER `description`;

ALTER TABLE pastoral_report
    ADD COLUMN `relativeDate` ENUM ('first day of year', '6 months ago', '3 months ago', '1 month ago', '14 days ago', '7 days ago', 'today')
        DEFAULT 'first day of year' AFTER `usesRelativeDate`;
