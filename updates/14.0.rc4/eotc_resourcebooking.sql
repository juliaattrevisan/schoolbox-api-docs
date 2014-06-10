/* Give resource bookings a name, so you can beavis them
 */
ALTER TABLE `resource_booking` ADD COLUMN `name` VARCHAR(255) NULL AFTER `resource_id`;