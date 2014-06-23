CREATE TABLE `resource_role_permission` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `resource_id` INT(11) NOT NULL,
  `role_id` INT(4) NOT NULL,
  `allowed` BOOL NOT NULL DEFAULT TRUE COMMENT 'Are users in this role allowed to book this resource?',
  PRIMARY KEY (`id`),
  -- the groups table is still MyISAM
  INDEX `resource_role_role` (`role_id`),
  CONSTRAINT `resource_role_resource`
    FOREIGN KEY (`resource_id`)
    REFERENCES `resource` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
);
