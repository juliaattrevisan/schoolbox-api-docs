-- increase length to match assessment.title
ALTER TABLE submission_box CHANGE COLUMN `name` `name` VARCHAR(255);
