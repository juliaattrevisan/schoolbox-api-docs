-- The unique index on parent+name just gets in the way.
ALTER TABLE `curriculum_node` DROP INDEX `curriculum_node_parent_id`;
