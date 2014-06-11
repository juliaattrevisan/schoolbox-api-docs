/**
 * Adds link back to parent category for this unit (as opposed to the component
 * it's in, which can be removed)
 *
 * Ideally this would be NOT NULL but it's possible for the unit and category
 * to become dissociated.
 */
ALTER TABLE `course_unit_instance`
    ADD COLUMN `category_id` INT(11) NULL AFTER `component_id`,
    ADD INDEX `course_unit_instance_category_id` (`category_id`);
