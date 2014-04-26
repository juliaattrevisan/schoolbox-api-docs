/* Allow visibility settings on Quiz review screens etc
 */
ALTER TABLE `assess` ADD COLUMN `mark_visibility` INT(1) DEFAULT 1 NULL AFTER `notify_author`;

/* default everyone to the current visibilty */
UPDATE `assess` SET mark_visibility = '2';