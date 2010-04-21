/* Alter table in target */
ALTER TABLE `blog`
	ADD FULLTEXT KEY `fulltext`(`heading`,`body`), COMMENT='';

/* Alter table in target */
ALTER TABLE `category`
	ADD FULLTEXT KEY `fulltext`(`name`,`code`), COMMENT='';

/* Alter table in target */
ALTER TABLE `file`
	ADD FULLTEXT KEY `fulltext`(`realname`,`description`,`comment`), COMMENT='';

/* Alter table in target */
ALTER TABLE `forum`
	ADD COLUMN `last_post_id` int(11)   NULL after `thread`,
	ADD COLUMN `last_post_title` varchar(200)  COLLATE utf8_general_ci NULL after `last_post_id`,
	ADD COLUMN `last_post_time` datetime   NULL after `last_post_title`,
	ADD COLUMN `views` int(11)   NOT NULL after `last_post_time`, COMMENT='';

/* Alter table in target */
ALTER TABLE `forumPost`
	ADD FULLTEXT KEY `fulltext`(`body`), COMMENT='';

/* Alter table in target */
ALTER TABLE `groups`
	ADD FULLTEXT KEY `fulltext`(`name`), COMMENT='';

/* Alter table in target */
ALTER TABLE `link`
	ADD FULLTEXT KEY `fulltext`(`url`,`description`,`comment`), COMMENT='';

/* Alter table in target */
ALTER TABLE `luCampus`
	ADD FULLTEXT KEY `fulltext`(`name`), COMMENT='';

/* Alter table in target */
ALTER TABLE `news_items` 
	DROP COLUMN `item_link1`,
	DROP COLUMN `item_link2`,
	DROP COLUMN `item_link3`,
	DROP COLUMN `item_link4`,
	DROP COLUMN `item_link5`,
	ADD FULLTEXT KEY `fulltext`(`item_heading`,`item_body`), COMMENT='';

/* Alter table in target */
ALTER TABLE `subject_news`
	ADD FULLTEXT KEY `fulltext`(`heading`,`body`), COMMENT='';

/* Alter table in target */
ALTER TABLE `submission_return`
	ADD COLUMN `submitted` datetime   NULL after `returned`, COMMENT='';

/* Alter table in target */
ALTER TABLE `user`
	ADD COLUMN `key` varchar(40)  COLLATE utf8_general_ci NULL after `hash`,
	ADD FULLTEXT KEY `fulltext`(`username`,`title`,`firstname`,`lastname`,`alt_email`), COMMENT='';

/* Alter table in target */
ALTER TABLE `wiki`
	ADD COLUMN `latestRevision` int(11)   NOT NULL after `categoryID`,
	CHANGE `created` `created` datetime   NULL after `latestRevision`,
	CHANGE `deleted` `deleted` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `created`,
	CHANGE `locked` `locked` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `deleted`, COMMENT='';

/* Alter table in target */
ALTER TABLE `wikiRevision`
	ADD FULLTEXT KEY `fulltext`(`heading`,`body`),
	ADD KEY `wikiID`(`wikiID`), COMMENT='';


UPDATE wiki w
SET w.latestRevision = (SELECT r.revisionID FROM wikiRevision r WHERE r.wikiID = w.wikiID ORDER BY created DESC LIMIT 1);

INSERT INTO luServices SET id = 5, name = 'CMS';

UPDATE homepage_comp SET config = 'a:2:{s:4:"sort";s:4:"Date";s:4:"size";i:0;}' WHERE id = 5;