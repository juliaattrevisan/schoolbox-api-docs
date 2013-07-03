-- Add the unique index on the configName table.
-- If this fails then the index either already exists (great) or there are
-- config entries with the same name (manual update)
ALTER TABLE `config` ADD UNIQUE INDEX `configName` (`name` ASC);
