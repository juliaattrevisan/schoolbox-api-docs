/* Optional anonymous survey results
 */
ALTER TABLE `assess` ADD COLUMN `anonymous_result` INT(1) DEFAULT 0 NOT NULL AFTER `availability`;

/* Optional email notification whenever someone completes an attempt
 */
ALTER TABLE `assess` ADD COLUMN `notify_author` INT(1) DEFAULT 0 NOT NULL AFTER `anonymous_result`;