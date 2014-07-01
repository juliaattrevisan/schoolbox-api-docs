/* Copy existing event/sid data into new relation table
 *
 * Used when event_type_id is 1
 */
CREATE TABLE `event_user`( `event_id` INT(11), `user_id` INT(11) );
INSERT INTO `event_user` (event_id, user_id) SELECT id, sid FROM `event` WHERE type = 1;

/* Used when event_type_id is 2
 */
CREATE TABLE `event_category`( `event_id` INT(11), `category_id` INT(11) );
INSERT INTO `event_category` (event_id, category_id) SELECT id, sid FROM `event` WHERE type = 2;