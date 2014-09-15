/* Allow a Return to know about the crocodoc so it can be loaded independently
 */
ALTER TABLE `submission_return` ADD COLUMN `uuid` VARCHAR(255) NULL AFTER `mime_type`;