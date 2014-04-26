/* Allow visibility settings on Quiz review screens etc
 */
ALTER TABLE `assess` ADD COLUMN `mark_visibility` INT(1) DEFAULT 1 NULL AFTER `notify_author`;

UPDATE TABLE `assess` SET mark_visibility = '2';