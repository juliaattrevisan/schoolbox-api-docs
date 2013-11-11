/* Add weights to assess table */
ALTER TABLE `assess`
ADD COLUMN `weight` INT(11) UNSIGNED DEFAULT 100  NOT NULL AFTER `max_attempts`;

/* Add weights to submissionbox table */
ALTER TABLE `submission_box`
ADD COLUMN `weight` INT(11) UNSIGNED DEFAULT 100  NOT NULL AFTER `created`;
