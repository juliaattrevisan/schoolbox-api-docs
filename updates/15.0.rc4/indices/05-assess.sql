ALTER TABLE `assess` ADD INDEX `availability` (`availability`);
ALTER TABLE `assess_answer` ADD INDEX `sequence` (`sequence`);
ALTER TABLE `assess_luQuestionType` ADD INDEX `availability` (`availability`);
ALTER TABLE `assess_question`
    ADD INDEX `sequence` (`sequence`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `assess_question_bank`
    ADD INDEX `question_category_id` (`question_category_id`),
    ADD INDEX `type_id` (`type_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `assess_question_category`
    ADD INDEX `parent_id` (`parent_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `assess_response` ADD INDEX `deleted_at` (`deleted_at`);
ALTER TABLE `assess_section`
    ADD INDEX `assess_id` (`assess_id`),
    ADD INDEX `sequence` (`sequence`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
