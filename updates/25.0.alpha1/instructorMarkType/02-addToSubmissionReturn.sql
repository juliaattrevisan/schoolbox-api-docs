ALTER TABLE `submission_return`
    ADD `instructor_mark` VARCHAR(64) DEFAULT NULL AFTER `mark`;

-- All the mark schemes are the same right now, so we can just copy the mark
UPDATE `submission_return` SET `instructor_mark` = `mark`;
