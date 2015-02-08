-- A lot of searching is done on the category code.
-- Shouldn't we have an index on that?
ALTER TABLE `categoryCode` ADD INDEX (`code`);
