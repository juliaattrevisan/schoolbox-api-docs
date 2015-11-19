/* UPS tables */

CREATE TABLE `ups_rule`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_type` ENUM('student','parent','staff') NOT NULL, -- omitting 'guest', this enum mirrors groups.group_type
  `enabled` BOOL NOT NULL DEFAULT 1,
  `sequence` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`role_type`),
  INDEX (`role_type`, `enabled`),
  UNIQUE INDEX (`sequence`)
);

CREATE TABLE `ups_condition`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` INT UNSIGNED NOT NULL,
  `condition_type_id` INT UNSIGNED NOT NULL,
  `arguments` VARCHAR(512) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`rule_id`)
);

CREATE TABLE `ups_effect`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_id` INT UNSIGNED NOT NULL,
  `user_property` VARCHAR(64) NOT NULL,
  `effect_type_id` INT UNSIGNED NOT NULL,
  `arguments` VARCHAR(512) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX (`rule_id`)
);

/* Lookup tables */

CREATE TABLE `ups_condition_type`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `ups_effect_type`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
);


/* Apply foreign key constraints */

ALTER TABLE `ups_condition`
  ADD FOREIGN KEY (`rule_id`) REFERENCES `ups_rule`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  ADD FOREIGN KEY (`condition_type_id`) REFERENCES `ups_condition_type`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE `ups_effect`
  ADD FOREIGN KEY (`rule_id`) REFERENCES `ups_rule`(`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  ADD FOREIGN KEY (`effect_type_id`) REFERENCES `ups_effect_type`(`id`) ON UPDATE CASCADE ON DELETE CASCADE;
