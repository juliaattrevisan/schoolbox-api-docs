/* Give resource bookings a name, so you can beavis them
 */
ALTER TABLE `resource_booking` ADD COLUMN `name` VARCHAR(255) NULL AFTER `resource_id`;

/* Create relation between metadata and bookings themselves
 */
CREATE TABLE `eotc_metadata_resourcebooking`( `metadata_id` INT(11), `booking_id` INT(11) );