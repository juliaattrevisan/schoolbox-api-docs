/**
 * Updates category ID with whatever the component's category is (if possible)
 */
UPDATE `course_unit_instance` `cu`
    LEFT JOIN `component_instance` `ci` ON `cu`.`component_id` = `ci`.`id` AND `ci`.`module` = 'homepage'
    SET `cu`.`category_id` = IFNULL(`ci`.`identifier`, 0);
