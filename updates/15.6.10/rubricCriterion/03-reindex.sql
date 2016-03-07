ALTER TABLE `rubric_criterion`
    DROP INDEX `parent_id`,
    ADD  INDEX `parent_id` (`parent_id`, `parent_type`)
;
