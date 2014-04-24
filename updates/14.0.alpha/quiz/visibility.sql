/* Allow visibility settings on Quiz review screens etc
 */
ALTER TABLE `assess` ADD COLUMN `visibility_id` INT(1) DEFAULT 1 NULL AFTER `notify_author`;