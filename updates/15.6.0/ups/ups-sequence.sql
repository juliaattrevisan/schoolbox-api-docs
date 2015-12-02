ALTER TABLE `ups_rule` DROP INDEX `sequence`;
ALTER TABLE `ups_rule` ADD UNIQUE INDEX `sequence_roletype` (`role_type`, `sequence`);