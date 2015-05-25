-- Assorted eportfolio indices
ALTER TABLE `eportfolio_blog` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `eportfolio_blogComment` ADD INDEX `deleted` (`deleted`);
ALTER TABLE `eportfolio_component_advancedlist_item`
    ADD INDEX `component_instance_id` (`component_instance_id`),
    ADD INDEX `imagehash` (`imagehash`)
;
ALTER TABLE `eportfolio_component_basiclist_item`
    ADD INDEX `component_instance_id` (`component_instance_id`)
;
ALTER TABLE `eportfolio_component_blog`
    ADD INDEX `page_id` (`page_id`),
    ADD INDEX `uid` (`uid`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eportfolio_component_blogcomment`
    ADD INDEX `blog_id` (`blog_id`),
    ADD INDEX `uid` (`uid`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eportfolio_component_filelist_item`
    ADD INDEX `page_id` (`page_id`),
    ADD INDEX `file_id` (`file_id`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eportfolio_component_linklist_item`
    ADD INDEX `component_instance_id` (`component_instance_id`)
;
ALTER TABLE `eportfolio_component_textbox_item`
    ADD INDEX `component_instance_id` (`component_instance_id`)
;
ALTER TABLE `eportfolio_coverphoto`
    ADD INDEX `uid` (`uid`),
    ADD INDEX `imagehash` (`imagehash`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
ALTER TABLE `eportfolio_link` ADD INDEX `owner` (`owner`);
ALTER TABLE `eportfolio_page` ADD INDEX `uid` (`uid`);
ALTER TABLE `eportfolio_permission` ADD INDEX `page_id` (`page_id`);
ALTER TABLE `eportfolio_showcase_item`
    ADD INDEX `submission_box_id` (`submission_box_id`),
    ADD INDEX `uid` (`uid`),
    ADD INDEX `deleted_at` (`deleted_at`)
;
