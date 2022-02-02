-- Add a column to turnitin_report that denotes whether a file
-- was historical (by default, it's not)

-- This flag is used to determine a different code path when
-- processing the file

ALTER TABLE `turnitin_report`
ADD COLUMN `historical` TINYINT(1) NOT NULL DEFAULT 0 AFTER `submitted_works_match_percentage`;
