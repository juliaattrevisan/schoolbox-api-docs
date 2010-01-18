ALTER TABLE `wiki` 
	ADD COLUMN `latestRevision` int(11)   NOT NULL after `categoryID`, 
	CHANGE `created` `created` datetime   NULL after `latestRevision`, 
	CHANGE `deleted` `deleted` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `created`, 
	CHANGE `locked` `locked` enum('true','false')  COLLATE utf8_general_ci NOT NULL DEFAULT 'false' after `deleted`, COMMENT='';

UPDATE wiki w
SET w.latestRevision = (SELECT r.revisionID FROM wikiRevision r WHERE r.wikiID = w.wikiID ORDER BY created DESC LIMIT 1);

