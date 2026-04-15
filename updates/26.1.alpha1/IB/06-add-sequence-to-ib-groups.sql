-- Add sequence column to lu_ib_groups for flexible ordering

ALTER TABLE `lu_ib_groups`
    ADD COLUMN `sequence` int(11) NOT NULL DEFAULT 0 AFTER `name`;

-- Set sequence values matching the IB group order (Groups 1–6, Core last)
UPDATE `lu_ib_groups` SET `sequence` = 1 WHERE `name` = 'Studies in Language and Literature';
UPDATE `lu_ib_groups` SET `sequence` = 2 WHERE `name` = 'Language Acquisition';
UPDATE `lu_ib_groups` SET `sequence` = 3 WHERE `name` = 'Individuals and Societies';
UPDATE `lu_ib_groups` SET `sequence` = 4 WHERE `name` = 'Sciences';
UPDATE `lu_ib_groups` SET `sequence` = 5 WHERE `name` = 'Mathematics';
UPDATE `lu_ib_groups` SET `sequence` = 6 WHERE `name` = 'The Arts';
UPDATE `lu_ib_groups` SET `sequence` = 7 WHERE `name` = 'Core';
