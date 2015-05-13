ALTER TABLE `component_config_instance`
    ADD INDEX `component_instance_id` (`component_instance_id`);
ALTER TABLE `component_definition`
    ADD INDEX `module` (`module`);
ALTER TABLE `component_instance`
    ADD INDEX `module` (`module`),
    ADD INDEX `position` (`position`),
    ADD INDEX `sequence` (`sequence`);
ALTER TABLE `dropbox`
    ADD INDEX `deleted` (`deleted`),
    ADD INDEX `deleted_at` (`deleted_at`);
ALTER TABLE `file`
    ADD INDEX `owner` (`owner`),
    ADD INDEX `hash` (`hash`);
ALTER TABLE `forumPost`
    ADD INDEX `parentID` (`parentID`);
ALTER TABLE `link`
    ADD INDEX `owner` (`owner`);
ALTER TABLE `newsTopic`
    ADD INDEX `sequence` (`sequence`);
ALTER TABLE `poll`
    ADD INDEX `created_by` (`created_by`),
    ADD INDEX `close` (`close`);
ALTER TABLE `wiki`
    ADD INDEX `categoryID` (`categoryID`),
    ADD INDEX `deleted` (`deleted`);
ALTER TABLE `wikiOpens`
    ADD INDEX `wiki` (`wikiID`),
    ADD INDEX `user` (`userID`);
ALTER TABLE `wikiRevision`
    ADD INDEX `userID` (`userID`);
