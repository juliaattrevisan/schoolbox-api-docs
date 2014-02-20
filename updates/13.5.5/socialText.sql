ALTER TABLE `socialstream_post` CHANGE `url` `url` VARCHAR(1024) CHARSET utf8 COLLATE utf8_general_ci NULL, CHANGE `external_body` `external_body` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL COMMENT 'Description for the external link', CHANGE `body` `body` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE `reportcard_comment` CHANGE `body` `body` TEXT NOT NULL;
ALTER TABLE `blogComment` CHANGE `body` `body` TEXT CHARSET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `eportfolio_component_blogcomment` CHANGE `content` `content` TEXT CHARSET utf8 COLLATE utf8_general_ci NULL;
