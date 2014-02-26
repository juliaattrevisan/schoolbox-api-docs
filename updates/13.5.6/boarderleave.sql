/* Remember who created the leave request
 */
ALTER TABLE `leave_request` ADD COLUMN `author_id` INT(11) NOT NULL AFTER `user_id`;

/* Set author defaults
 */
UPDATE leave_request SET author_id = user_id;