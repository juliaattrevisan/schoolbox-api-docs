ALTER TABLE `allowedFileTypes`
    ADD INDEX `deleted` (`deleted`);
ALTER TABLE `class`
    ADD INDEX `code` (`code`);
ALTER TABLE `cmsLog`
    ADD INDEX `object` (`object`),
    ADD INDEX `action` (`action`);
ALTER TABLE `dailyquote_data`
    ADD INDEX `deleted_at` (`deleted_at`);
ALTER TABLE `error`
    ADD INDEX `id` (`id`);
ALTER TABLE `event`
    ADD INDEX `series_id` (`series_id`),
    ADD INDEX `author_id` (`author_id`);
ALTER TABLE `event_attendance`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `status` (`status`);
ALTER TABLE `event_campus`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `campus_id` (`campus_id`);
ALTER TABLE `event_category`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `category_id` (`category_id`),
    ADD UNIQUE INDEX `event_category_id` (`event_id`, `category_id`);
ALTER TABLE `event_group`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `group_id` (`group_id`);
ALTER TABLE `event_user`
    ADD INDEX `event_id` (`event_id`),
    ADD INDEX `user_id` (`user_id`),
    ADD UNIQUE INDEX `event_user_id` (`event_id`, `user_id`);
ALTER TABLE `leave_request`
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `author_id` (`author_id`),
    ADD INDEX `start` (`start`),
    ADD INDEX `finish` (`finish`),
    ADD INDEX `confirmed_by` (`confirmed_by`);
ALTER TABLE `msg_reroute`
    ADD INDEX `delivery` (`delivery`);
ALTER TABLE `msg_reroute_override`
    ADD INDEX `delivery` (`delivery`);
ALTER TABLE `msg_route_perm`
    ADD INDEX `mask` (`mask`),
    ADD INDEX `role_default` (`role_default`);
ALTER TABLE `myFile`
    ADD INDEX `parent` (`parent`),
    ADD INDEX `owner` (`owner`),
    ADD INDEX `hash` (`hash`);
ALTER TABLE `myFolder`
    ADD INDEX `parent` (`parent`),
    ADD INDEX `owner` (`owner`);
ALTER TABLE `perms`
    ADD INDEX `type` (`type`);
ALTER TABLE `reportcard_comment`
    ADD INDEX `student_id` (`student_id`),
    ADD INDEX `student_visibility` (`student_visibility`),
    ADD INDEX `parent_visibility` (`parent_visibility`);
ALTER TABLE `sideMenu`
    ADD INDEX `parent` (`parent`);
ALTER TABLE `sideMenuCampus`
    ADD INDEX `id` (`id`),
    ADD INDEX `campus` (`campus`);
ALTER TABLE `sideMenuGroup`
    ADD INDEX `id` (`id`),
    ADD INDEX `gid` (`gid`);
ALTER TABLE `timetable_campus`
    ADD INDEX `start` (`start`),
    ADD INDEX `reset` (`reset`),
    ADD INDEX `finish` (`finish`);
ALTER TABLE `user_link`
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `linked_user_id` (`linked_user_id`);
ALTER TABLE `user_relation`
    ADD INDEX `user_id` (`user_id`),
    ADD INDEX `relation_id` (`relation_id`);
ALTER TABLE `userAccess`
    ADD INDEX `service` (`service`);
ALTER TABLE `userCampus`
    ADD INDEX `uid` (`uid`),
    ADD INDEX `campus` (`campus`);
