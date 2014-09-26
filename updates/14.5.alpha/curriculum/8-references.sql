-- Reference - may be a URI that points to a description of this area or level?
-- It may be used in a query for fetching outcomes.
ALTER TABLE `curriculum_learning_area`
  ADD COLUMN `reference` VARCHAR(500) NOT NULL AFTER `description`;
ALTER TABLE `curriculum_learning_level`
  ADD COLUMN `reference` VARCHAR(500) NOT NULL AFTER `description`;
