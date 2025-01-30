-- create a new column to pastoral_report table
ALTER TABLE `pastoral_report`
ADD COLUMN `include_archived_records` TINYINT DEFAULT 0 AFTER `after_date`;
