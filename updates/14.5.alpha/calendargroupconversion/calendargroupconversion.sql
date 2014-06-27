/* Expand sid column into relations
 */
CREATE TABLE `event_sid`( `event_id` INT(11), `sid` INT(11) );

/* Copy existing event/sid data into new relation table
 */
INSERT INTO event_sid (event_id, sid) SELECT id, sid FROM `event`