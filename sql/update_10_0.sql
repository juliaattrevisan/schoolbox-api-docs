ALTER TABLE `wiki` 
	ADD COLUMN `latestRevision` int(11)   NOT NULL after `categoryID`, 
	CHANGE `created` `created` datetime   NULL after `latestRevision`, 
	CHANGE `deleted` `deleted` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `created`, 
	CHANGE `locked` `locked` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `deleted`, COMMENT='';

UPDATE wiki w
SET w.latestRevision = (SELECT r.revisionID FROM wikiRevision r WHERE r.wikiID = w.wikiID ORDER BY created DESC LIMIT 1);

INSERT INTO luServices SET id = 5, name = 'CMS';

UPDATE homepage_comp SET config = 'a:2:{s:4:"sort";s:4:"Date";s:4:"size";i:0;}' WHERE id = 5;