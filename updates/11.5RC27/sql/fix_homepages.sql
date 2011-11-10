/* Alter table in target: Allows Default Homepage Column widths to be used */
ALTER TABLE `homepageTemplate`
	ADD COLUMN `left_column_width` float   NULL DEFAULT '69.9' after `layout`,
	ADD COLUMN `right_column_width` float   NULL DEFAULT '29.9' after `left_column_width`;