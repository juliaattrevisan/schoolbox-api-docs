-- Need to disambiguate the field from `assess`.`duration`.
-- These are different concepts:
-- * `assess`.`duration`              => period in seconds that a quiz must be completed in
-- * `assessment`.`default_open_span` => default period in days to schedule an assessment during course import
ALTER TABLE `assessment`
  CHANGE `duration` `default_open_span` INT(11) NULL COMMENT 'Default period in days to schedule during Course Import' AFTER `sequence`;

