ALTER TABLE `submission_return` ADD COLUMN `marker` INT UNSIGNED NOT NULL AFTER `box_id`;

UPDATE submission_return, submission_box SET marker = submission_box.owner WHERE submission_return.`box_id`=submission_box.`id`