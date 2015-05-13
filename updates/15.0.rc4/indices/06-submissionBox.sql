ALTER TABLE `student_submission`
    ADD INDEX `hash` (`hash`);
ALTER TABLE `submission_box`
    ADD INDEX `publish_date` (`publish_date`),
    ADD INDEX `open_date` (`open_date`),
    ADD INDEX `date` (`date`),
    ADD INDEX `late_date` (`late_date`);
ALTER TABLE `submission_log`
    ADD INDEX `user` (`user`),
    ADD INDEX `page` (`page`),
    ADD INDEX `sid` (`sid`);
ALTER TABLE `submission_return`
    ADD INDEX `marker` (`marker`),
    ADD INDEX `hash` (`hash`),
    ADD INDEX `submitted` (`submitted`),
    ADD INDEX `returned` (`returned`);
ALTER TABLE `rubric_criterion`
    ADD INDEX `parent_id` (`parent_id`);
ALTER TABLE `rubric_descriptor`
    ADD INDEX `criterion_id` (`criterion_id`),
    ADD INDEX `submission_box_id` (`submission_box_id`),
    ADD INDEX `valid` (`valid`);
ALTER TABLE `rubric_mark`
    ADD INDEX `student_id` (`student_id`),
    ADD INDEX `submission_box_id` (`submission_box_id`),
    ADD INDEX `criterion_id` (`criterion_id`),
    ADD INDEX `valid` (`valid`);
ALTER TABLE `rubric_result`
    ADD INDEX `resultset_id` (`resultset_id`);
ALTER TABLE `rubric_usage`
    ADD INDEX `criterion_id` (`criterion_id`),
    ADD INDEX `submission_box_id` (`submission_box_id`);