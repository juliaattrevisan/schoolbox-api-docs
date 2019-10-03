-- Add a column to determine when plagscan is enabled
-- Default is OFF for obvious reasons
ALTER TABLE `submission_box`
    ADD COLUMN `similarity_check_enabled` TINYINT NOT NULL DEFAULT 0 AFTER `self_reviewability`;

-- Everyone was previously forced to plagscan their uploads
-- whether they liked it or not
UPDATE `submission_box` SET similarity_check_enabled = 1;
