/**
 * Index every soft delete column.
 *
 * I don't think that NULL values are indexed on MyISAM tables.
 * I know that they are on InnoDB tables.
 */
ALTER TABLE `curriculum_node` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `resource_booking` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `resource` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `tile` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `category` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `etherpad_pad` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `socialstream_post` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `curriculum_learning_level` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `curriculum_source` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `curriculum_learning_area` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `storage` ADD INDEX `deleted` (`expired_at`);
ALTER TABLE `news_items` ADD INDEX `deleted` (`item_deleted`);
ALTER TABLE `blog` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `blogComment` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `myFile` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `file` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `tile_layout` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `resource_category` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `forumThread` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `forumPost` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `reportcard_comment` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `task` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `task_instance` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `course_unit_instance` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `assess` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `submission_box` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `assess_attempt` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `rubric_criterion` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `submission_return` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `link` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `textBox` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `categoryCode` ADD INDEX `deleted` (`deleted_at`);
ALTER TABLE `subject_news` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `plagscan_document` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `eportfolio_page` ADD INDEX `deleted` (`deleted_at`);
