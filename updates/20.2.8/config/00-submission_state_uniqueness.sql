-- This uniqueness is determined in a presave mapper method
ALTER TABLE `submission_errors` DROP INDEX `code` ;
ALTER TABLE `submission_errors` ADD INDEX `code` (`code` ASC);