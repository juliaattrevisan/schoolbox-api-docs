/* Add Author field to events, to see who set them up
 */

ALTER TABLE `event` ADD COLUMN `author_id` INT(6) NULL AFTER `owner`;

/* Make author_id the same as owner by default
 */
UPDATE `event` SET author_id = `owner`;