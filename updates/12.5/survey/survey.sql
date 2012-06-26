/* Optional anonymous survey results
 */
ALTER TABLE `assess` ADD COLUMN `anonymous_result` INT(1) DEFAULT 0 NOT NULL AFTER `availability`;