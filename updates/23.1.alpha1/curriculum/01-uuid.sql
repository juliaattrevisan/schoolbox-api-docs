ALTER TABLE `curriculum_node`
    ADD COLUMN `uuid` VARCHAR(36) NULL,
    ADD UNIQUE INDEX `uuid_UNIQUE` (`uuid` ASC);
