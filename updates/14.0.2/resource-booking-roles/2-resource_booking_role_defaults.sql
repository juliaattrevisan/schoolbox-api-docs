-- Staff roles are allowed to book all items by default, all others are not.
INSERT INTO `resource_role_permission` (`resource_id`, `role_id`, `allowed`)
    SELECT `r`.`id`, `g`.`id`, (`g`.`group_type` = 'staff')
    FROM `resource` `r`
    CROSS JOIN `groups` `g`;
