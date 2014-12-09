-- Add a publish date to submission boxes that don't have one for some reason
-- SB-6388: boxes that are part of a course builder don't get publish date auto-set
UPDATE `submission_box` SET `publish_date` = `open_date` WHERE `publish_date` IS NULL;
