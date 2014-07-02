/* Copy existing event/sid data into new relation table
 *
 * Used when event_type_id is 1
 */
CREATE TABLE `event_user`( `event_id` INT(11), `user_id` INT(11) );
INSERT INTO `event_user` (event_id, user_id) SELECT id, sid FROM `event` WHERE access = 1;

/* Used when event_type_id is 2
 */
CREATE TABLE `event_category`( `event_id` INT(11), `category_id` INT(11) );
INSERT INTO `event_category` (event_id, category_id) SELECT id, sid FROM `event` WHERE access = 2;

/* Rename tables and columns for consistency
 */
RENAME TABLE `eventCampus` TO `event_campus`;
ALTER TABLE `event_campus` CHANGE `eventID` `event_id` INT(11) NOT NULL AUTO_INCREMENT, CHANGE `campusID` `campus_id` INT(11) DEFAULT 0 NOT NULL;

RENAME TABLE `eventGroup` TO `event_group`;
ALTER TABLE `event_group` CHANGE `eventID` `event_id` INT(11) NOT NULL AUTO_INCREMENT, CHANGE `groupID` `group_id` INT(11) DEFAULT 0 NOT NULL;
