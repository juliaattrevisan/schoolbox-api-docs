-- Fix for stupid config issues where somebody set a boolean config field as
-- "true" by default (and that was picked up as false in the config browser)
-- The "restrict_student_submissions" option suffers from this malady.
UPDATE `config` SET `value` = 0 where `value`='true' and `field` = 'boolean';