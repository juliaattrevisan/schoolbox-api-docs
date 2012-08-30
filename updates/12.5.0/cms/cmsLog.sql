/* Add 'access' to the `action` enum */
ALTER TABLE `cmsLog` CHANGE `action` `action` ENUM('create','modify','delete','access') CHARSET utf8 COLLATE utf8_general_ci DEFAULT 'create' NOT NULL;