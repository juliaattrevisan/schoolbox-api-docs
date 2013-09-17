-- Fix config uniqueness once and for all
DROP INDEX `configName` ON `config`;
ALTER TABLE `config` ADD UNIQUE INDEX `configName` (`name` ASC);
