/* Add boarding status to user object
 */
ALTER TABLE `user` ADD COLUMN `boarding_status` VARCHAR(255) NULL AFTER `residential_house_id`;