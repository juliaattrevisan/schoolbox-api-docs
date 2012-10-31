/* add the html field to the plagscan table */
ALTER TABLE `plagscan_document`
	ADD COLUMN `html` mediumtext  COLLATE utf8_general_ci NULL after `score`;
